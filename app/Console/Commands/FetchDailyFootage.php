<?php

namespace App\Console\Commands;

use App\Models\SsJob;
use App\Services\WorkStudio\Assessments\Queries\DailyFootageQuery;
use App\Services\WorkStudio\Client\ApiCredentialManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FetchDailyFootage extends Command
{
    protected $signature = 'ws:fetch-daily-footage
        {date? : Target date (MM-DD-YYYY, MM-DD, or YYYY). Default: previous complete week (Sun-Sat)}
        {--jobguid= : Query a single JOBGUID directly (skips ss_jobs lookup)}
        {--status=ACTIV : Job status filter (single value)}
        {--all-statuses : Use all planner_concern statuses (ACTIV, QC, REWRK, CLOSE)}
        {--chunk-size=200 : Number of JOBGUIDs per API batch}
        {--dry-run : Show what would happen without making API calls}';

    protected $description = 'Fetch daily footage by station completion from WorkStudio API and save as domain-grouped JSON';

    private int $chunkSize;

    private Carbon $targetDate;

    private Carbon $editDateStart;

    private Carbon $editDateEnd;

    private string $filenamePrefix;

    public function handle(): int
    {
        $this->chunkSize = (int) $this->option('chunk-size');

        $this->resolveDate();

        $mode = match ($this->filenamePrefix) {
            'we' => 'Week-Ending',
            'year' => 'Year',
            default => 'Daily',
        };
        $this->info("{$mode} mode — target: {$this->targetDate->format('m-d-Y')}, range: {$this->editDateStart->format('Y-m-d')} to {$this->editDateEnd->format('Y-m-d')}");

        // Stage 1: Get JOBGUIDs
        $jobGuids = $this->getJobGuids();

        if ($jobGuids->isEmpty()) {
            $this->warn('No jobs found matching the criteria.');

            return self::SUCCESS;
        }

        $this->info("Found {$jobGuids->count()} jobs to query.");

        if ($this->option('dry-run')) {
            $this->displayDryRun($jobGuids);

            return self::SUCCESS;
        }

        // Stage 2: Query WorkStudio API in chunks
        $allResults = $this->fetchFromApiInChunks($jobGuids->all());

        if ($allResults === null) {
            return self::FAILURE;
        }

        $this->info("Received {$allResults->count()} daily footage records from API.");

        if ($allResults->isEmpty()) {
            $this->warn('No footage data returned from WorkStudio API.');

            return self::SUCCESS;
        }

        // Stage 3: Enrich records
        $enriched = $this->enrichRecords($allResults);

        // Stage 4: Write per-domain JSON files
        $this->writePerDomainJson($enriched);

        return self::SUCCESS;
    }

    /**
     * Parse the date argument and determine WE vs Daily vs Year mode.
     *
     * - No argument → previous complete week (Sun-Sat), or current week if today is Saturday
     * - YYYY → Year mode (Jan 1 – Dec 31)
     * - Saturday date → WE mode (Sun-Sat week range)
     * - Non-Saturday date → Daily mode (single day)
     */
    private function resolveDate(): void
    {
        $dateArg = $this->argument('date');

        if ($dateArg === null) {
            $this->resolveDefaultWeek();

            return;
        }

        // Year mode: 4-digit year → full year range
        if (preg_match('/^\d{4}$/', $dateArg)) {
            $this->resolveYearMode((int) $dateArg);

            return;
        }

        $this->targetDate = $this->parseDate($dateArg);

        if ($this->targetDate->isSaturday()) {
            // WE mode: Sunday 00:00 through Saturday 23:59:59
            $this->filenamePrefix = 'we';
            $this->editDateStart = $this->targetDate->copy()->subDays(6)->startOfDay();
            $this->editDateEnd = $this->targetDate->copy()->endOfDay();
        } else {
            // Daily mode: single day
            $this->filenamePrefix = 'day';
            $this->editDateStart = $this->targetDate->copy()->startOfDay();
            $this->editDateEnd = $this->targetDate->copy()->endOfDay();
        }
    }

    /**
     * Default: previous complete Sun-Sat week, or current week if today is Saturday.
     */
    private function resolveDefaultWeek(): void
    {
        $today = Carbon::now();

        if ($today->isSaturday()) {
            // Saturday → use this week (Sun through today)
            $this->targetDate = $today->copy()->startOfDay();
        } else {
            // Any other day → previous complete week's Saturday
            $this->targetDate = $today->copy()->previous(Carbon::SATURDAY);
        }

        $this->filenamePrefix = 'we';
        $this->editDateStart = $this->targetDate->copy()->subDays(6)->startOfDay();
        $this->editDateEnd = $this->targetDate->copy()->endOfDay();
    }

    /**
     * Year mode: query the entire year (Jan 1 – Dec 31).
     */
    private function resolveYearMode(int $year): void
    {
        $this->filenamePrefix = 'year';
        $this->targetDate = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $this->editDateStart = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $this->editDateEnd = Carbon::createFromDate($year, 12, 31)->endOfDay();
    }

    /**
     * Parse a date string in MM-DD-YYYY or MM-DD format.
     */
    private function parseDate(string $dateArg): Carbon
    {
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateArg)) {
            return Carbon::createFromFormat('m-d-Y', $dateArg)->startOfDay();
        }

        if (preg_match('/^\d{2}-\d{2}$/', $dateArg)) {
            return Carbon::createFromFormat('m-d-Y', $dateArg.'-'.now()->year)->startOfDay();
        }

        $this->error("Invalid date format: {$dateArg}. Use MM-DD-YYYY, MM-DD, or YYYY.");

        exit(self::FAILURE);
    }

    /**
     * Parse a /Date(2020-04-14T17:53:34.413Z)/ wrapper from DDOProtocol API.
     */
    private function parseDateWrapper(string $raw): ?Carbon
    {
        // Strip /Date(...)/  wrapper → extract ISO datetime string
        $cleaned = str_replace(['/Date(', ')/'], '', $raw);

        if ($cleaned === '' || $cleaned === $raw) {
            // Not in wrapper format — try direct parse as MM-DD-YYYY fallback
            return rescue(fn () => Carbon::createFromFormat('m-d-Y', $raw), null, false);
        }

        return Carbon::parse($cleaned)->startOfDay();
    }

    /**
     * Get JOBGUIDs from ss_jobs or a single --jobguid option.
     *
     * @return Collection<int, string>
     */
    private function getJobGuids(): Collection
    {
        $singleGuid = $this->option('jobguid');

        if ($singleGuid !== null) {
            return collect([$singleGuid]);
        }

        $scopeYear = config('ws_assessment_query.scope_year');
        $jobTypes = config('ws_assessment_query.job_types.assessments');

        $query = SsJob::query()
            ->where('scope_year', $scopeYear)
            ->whereIn('job_type', $jobTypes);

        if ($this->option('all-statuses')) {
            $statuses = config('ws_assessment_query.statuses.planner_concern');
            $query->whereIn('status', $statuses);
        } else {
            $query->where('status', $this->option('status'));
        }

        return $query->pluck('job_guid');
    }

    /**
     * Display dry-run output.
     *
     * @param  Collection<int, string>  $jobGuids
     */
    private function displayDryRun(Collection $jobGuids): void
    {
        $jobs = SsJob::whereIn('job_guid', $jobGuids->take(20))->get();

        $this->table(
            ['job_guid', 'status', 'job_type', 'edit_date'],
            $jobs->map(fn (SsJob $job) => [
                $job->job_guid,
                $job->status,
                $job->job_type,
                $job->edit_date?->format('Y-m-d'),
            ])->toArray(),
        );

        $this->warn("Dry run — showing first 20 of {$jobGuids->count()} jobs. No API calls made.");
    }

    /**
     * Fetch daily footage data from WorkStudio API, chunking the JOBGUID list.
     *
     * @param  array<int, string>  $jobGuids
     * @return Collection<int, array<string, mixed>>|null
     */
    private function fetchFromApiInChunks(array $jobGuids): ?Collection
    {
        $chunks = array_chunk($jobGuids, $this->chunkSize);
        $allResults = collect();

        $bar = $this->output->createProgressBar(count($chunks));
        $bar->setFormat(' %current%/%max% chunks [%bar%] %percent:3s%% %elapsed:6s%');
        $bar->start();

        foreach ($chunks as $chunk) {
            $results = $this->fetchChunk($chunk);

            if ($results === null) {
                $bar->finish();
                $this->newLine();

                return null;
            }

            $allResults = $allResults->merge($results);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $allResults;
    }

    /**
     * Execute a single chunk against the WorkStudio API.
     *
     * @param  array<int, string>  $jobGuids
     * @return Collection<int, array<string, mixed>>|null
     */
    private function fetchChunk(array $jobGuids): ?Collection
    {
        $credentials = app(ApiCredentialManager::class)->getServiceAccountCredentials();
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');

        $sql = DailyFootageQuery::build(
            $jobGuids,
            $this->editDateStart->format('Y-m-d'),
            $this->editDateEnd->format('Y-m-d'),
        );

        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => ApiCredentialManager::formatDbParameters($credentials['username'], $credentials['password']),
            'SQL' => $sql,
        ];

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($credentials['username'], $credentials['password'])
                ->timeout(180)
                ->connectTimeout(30)
                ->post("{$baseUrl}/GETQUERY", $payload);

            $data = $response->json();

            if (! $data) {
                $this->error("Empty response (HTTP {$response->status()}).");

                return null;
            }

            if (isset($data['protocol']) && $data['protocol'] === 'ERROR' || isset($data['errorMessage'])) {
                $this->error($data['errorMessage'] ?? 'Unknown API error.');

                return null;
            }

            // API returns {"Protocol":"QUERYRESULT"} with no Heading/Data when zero rows match
            if (! isset($data['Heading'], $data['Data'])) {
                return collect();
            }

            $headings = $data['Heading'];

            return collect($data['Data'])->map(fn (array $row) => array_combine($headings, $row));
        } catch (\Throwable $e) {
            $this->error("API request failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Enrich raw API records: parse dates, split stations, extract domain.
     *
     * @param  Collection<int, array<string, mixed>>  $results
     * @return Collection<int, array<string, mixed>>
     */
    private function enrichRecords(Collection $results): Collection
    {
        return $results->map(function (array $row) {
            $username = $row['FRSTR_USER'] ?? null;

            // Extract domain from DOMAIN\username format (for folder grouping only)
            $domain = $username ? explode('\\', $username, 2)[0] : 'UNKNOWN';

            // Parse completion_date — API returns /Date(2020-04-14T17:53:34.413Z)/ wrapper
            $rawDate = $row['completion_date'] ?? null;
            $completionDate = $rawDate
                ? $this->parseDateWrapper($rawDate)
                : null;

            // Split station_list string into array
            $stationList = $row['station_list'] ?? '';
            $stations = $stationList !== '' ? explode(',', $stationList) : [];

            return [
                '_domain' => $domain,
                'job_guid' => $row['JOBGUID'],
                'frstr_user' => $username,
                'datepop' => $completionDate?->format('Y-m-d'),
                'distance_planned' => (float) ($row['daily_footage_meters'] ?? 0),
                'unit_count' => (int) ($row['unit_count'] ?? 0),
                'stations' => $stations,
            ];
        });
    }

    /**
     * Write enriched records grouped by domain to per-domain JSON files.
     *
     * @param  Collection<int, array<string, mixed>>  $enriched
     */
    private function writePerDomainJson(Collection $enriched): void
    {
        $dateForFilename = $this->targetDate->format('m_d_Y');
        $grouped = $enriched->groupBy('_domain');

        foreach ($grouped as $domain => $records) {
            // Strip the internal _domain key before writing
            $cleaned = $records->map(fn (array $r) => collect($r)->except('_domain')->all())->values();

            $filename = "daily-footage/{$domain}/{$this->filenamePrefix}{$dateForFilename}_planning_activities.json";
            Storage::put($filename, $cleaned->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("  {$domain}: {$cleaned->count()} records -> {$filename}");
        }

        $this->info("Wrote {$enriched->count()} records across {$grouped->count()} domains.");
    }
}

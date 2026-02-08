<?php

namespace App\Console\Commands;

use App\Models\Circuit;
use App\Models\SsJob;
use App\Models\WsUser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class FetchSsJobs extends Command
{
    protected $signature = 'ws:fetch-jobs
        {--dry-run : Show what would happen without changes}
        {--year= : Override scope year (default from config)}';

    protected $description = 'Fetch SS jobs from WorkStudio API and upsert into ss_jobs table';

    public function handle(): int
    {
        $year = $this->option('year') ?? config('ws_assessment_query.scope_year');

        $this->info("Fetching SS jobs for year: {$year}");

        $rows = $this->fetchFromApi($year);

        if ($rows === null) {
            return self::FAILURE;
        }

        $this->info("Found {$rows->count()} raw rows from API.");

        if ($rows->isEmpty()) {
            $this->warn('No jobs returned from API.');

            return self::SUCCESS;
        }

        $grouped = $this->groupByJobGuid($rows);
        $this->info("Grouped into {$grouped->count()} unique jobs.");

        if ($this->option('dry-run')) {
            $preview = $grouped->take(20)->map(fn (array $job) => [
                $job['job_guid'],
                $job['work_order'],
                $job['job_type'],
                $job['status'],
                $job['raw_title'],
                count($job['extensions']),
            ]);
            $this->table(['job_guid', 'work_order', 'job_type', 'status', 'title', 'ext_count'], $preview->toArray());
            $this->warn('Dry run — no changes made. Showing first 20 of '.$grouped->count());

            return self::SUCCESS;
        }

        $this->upsertJobs($grouped, $year);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array<string, mixed>>|null
     */
    private function fetchFromApi(string $year): ?Collection
    {
        $username = config('workstudio.service_account.username');
        $password = config('workstudio.service_account.password');
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');

        $editDateSql = "FORMAT(CAST(DATEADD(DAY, -2, CAST(SS.EDITDATE AS DATETIME)) AT TIME ZONE 'UTC' AT TIME ZONE 'Eastern Standard Time' AS DATETIME), 'yyyy-MM-dd HH:mm:ss')";

        $sql = 'SELECT SS.JOBGUID, SS.WO, SS.EXT, SS.JOBTYPE, SS.STATUS, '
            .'SS.TAKEN, SS.TAKENBY, SS.MODIFIEDBY, SS.VERSION, SS.SYNCHVERSN, '
            .'SS.ASSIGNEDTO, SS.TITLE, SS.PJOBGUID, '
            ."{$editDateSql} AS EDITDATE "
            .'FROM SS '
            .'INNER JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID '
            ."WHERE WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$year}%' "
            ."AND SS.JOBTYPE LIKE 'Assessment%' "
            .'ORDER BY SS.JOBGUID, SS.EXT';

        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => "USER NAME={$username}\r\nPASSWORD={$password}\r\n",
            'SQL' => $sql,
        ];

        try {
            /** @var \Iluminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($username, $password)
                ->timeout(180)
                ->connectTimeout(30)
                ->post("{$baseUrl}/GETQUERY", $payload);

            $data = $response->json();

            if (! $data) {
                $this->error("Empty response (HTTP {$response->status()}).");

                return null;
            }

            if (isset($data['protocol']) && $data['protocol'] === 'ERROR') {
                $this->error($data['errorMessage'] ?? 'Unknown API error.');

                return null;
            }

            if (! isset($data['Heading'], $data['Data'])) {
                $this->error('Unexpected response format — missing Heading/Data.');

                return null;
            }

            $headings = $data['Heading'];

            return collect($data['Data'])->map(fn (array $row) => array_combine($headings, $row));
        } catch (\Throwable $e) {
            $this->error("API request failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Group raw rows by JOBGUID, collecting EXT values into an extensions array.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<string, array<string, mixed>>
     */
    private function groupByJobGuid(Collection $rows): Collection
    {
        return $rows->groupBy('JOBGUID')->map(function (Collection $group, string $jobGuid) {
            $first = $group->first();

            $extensions = $group
                ->pluck('EXT')
                ->filter(fn ($ext) => $ext !== null && $ext !== '')
                ->unique()
                ->values()
                ->all();

            return [
                'job_guid' => $jobGuid,
                'parent_job_guid' => $first['PJOBGUID'] ?: null,
                'work_order' => $first['WO'] ?? '',
                'extensions' => $extensions,
                'job_type' => $first['JOBTYPE'] ?? '',
                'status' => $first['STATUS'] ?? '',
                'edit_date' => $first['EDITDATE'] ?? null,
                'taken' => $this->parseTaken($first['TAKEN'] ?? null),
                'taken_by_username' => $first['TAKENBY'] ?: null,
                'modified_by_username' => $first['MODIFIEDBY'] ?: null,
                'version' => $first['VERSION'] ?? null,
                'sync_version' => $first['SYNCHVERSN'] ?? null,
                'assigned_to' => $first['ASSIGNEDTO'] ?: null,
                'raw_title' => $first['TITLE'] ?? '',
            ];
        });
    }

    /**
     * Upsert grouped jobs into the ss_jobs table.
     *
     * @param  Collection<string, array<string, mixed>>  $jobs
     */
    private function upsertJobs(Collection $jobs, string $year): void
    {
        $circuitMap = $this->buildCircuitMap();
        $userMap = WsUser::pluck('id', 'username')->all();

        $bar = $this->output->createProgressBar($jobs->count());
        $bar->start();

        $created = 0;
        $updated = 0;

        foreach ($jobs as $jobData) {
            $circuitId = $this->resolveCircuitId($jobData['raw_title'], $circuitMap);
            $takenById = $userMap[$jobData['taken_by_username']] ?? null;
            $modifiedById = $userMap[$jobData['modified_by_username']] ?? null;

            $editDate = null;
            if ($jobData['edit_date']) {
                try {
                    $editDate = Carbon::parse($jobData['edit_date']);
                } catch (\Throwable) {
                    // Skip unparseable dates
                }
            }

            $attributes = [
                'circuit_id' => $circuitId,
                'parent_job_guid' => $jobData['parent_job_guid'],
                'taken_by_id' => $takenById,
                'modified_by_id' => $modifiedById,
                'work_order' => $jobData['work_order'],
                'extensions' => $jobData['extensions'],
                'job_type' => $jobData['job_type'],
                'status' => $jobData['status'],
                'scope_year' => $year,
                'edit_date' => $editDate,
                'taken' => $jobData['taken'],
                'version' => $jobData['version'],
                'sync_version' => $jobData['sync_version'],
                'assigned_to' => $jobData['assigned_to'],
                'raw_title' => $jobData['raw_title'],
                'last_synced_at' => now(),
            ];

            $job = SsJob::updateOrCreate(
                ['job_guid' => $jobData['job_guid']],
                $attributes,
            );

            if ($job->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Jobs synced: {$created} created, {$updated} updated.");

        $this->updateCircuitJobGuids($year);
    }

    /**
     * Build a lookup map from raw_line_name to circuit ID.
     *
     * @return array<string, int>
     */
    private function buildCircuitMap(): array
    {
        $map = [];

        Circuit::whereNotNull('properties')->each(function (Circuit $circuit) use (&$map) {
            $rawLineName = $circuit->properties['raw_line_name'] ?? null;

            if ($rawLineName) {
                $map[$rawLineName] = $circuit->id;
            }
        });

        return $map;
    }

    /**
     * Resolve a circuit ID from a raw title by matching against circuit properties.
     *
     * @param  array<string, int>  $circuitMap
     */
    private function resolveCircuitId(string $rawTitle, array $circuitMap): ?int
    {
        return $circuitMap[$rawTitle] ?? null;
    }

    /**
     * Update circuit properties with jobguids for the given scope year.
     */
    private function updateCircuitJobGuids(string $year): void
    {
        $jobsByCircuit = SsJob::whereNotNull('circuit_id')
            ->where('scope_year', $year)
            ->get()
            ->groupBy('circuit_id');

        $updatedCount = 0;

        foreach ($jobsByCircuit as $circuitId => $jobs) {
            /** @var \app\Models\Circuit $circuit */
            $circuit = Circuit::find($circuitId);

            if (! $circuit) {
                continue;
            }

            $properties = $circuit->properties ?? [];
            $yearData = $properties[$year] ?? [];
            $yearData['jobguids'] = $jobs->pluck('job_guid')->values()->all();
            $properties[$year] = $yearData;
            $circuit->properties = $properties;
            $circuit->save();
            $updatedCount++;
        }

        $this->info("Updated jobguids on {$updatedCount} circuits for year {$year}.");
    }

    private function parseTaken(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return strtolower($value) === 'true' || $value === '1';
        }

        return (bool) $value;
    }
}

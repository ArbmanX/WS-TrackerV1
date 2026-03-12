<?php

namespace App\Console\Commands\Fetch;

use App\Console\Commands\Traits\GetsAdditionalAssessmentMetrics;
use App\Console\Commands\Traits\GetsDailyFootage;
use App\Models\Assessment;
use App\Models\Circuit;
use App\Models\PlannerDailyRecord;
use App\Services\WorkStudio\Assessments\Queries\FetchAssessmentQueries;
use App\Services\WorkStudio\Client\GetQueryService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FetchAssessments extends Command
{
    use GetsDailyFootage, GetsAdditionalAssessmentMetrics;

    protected $signature = 'ws:fetch-assessments
        {--year= : Scope to a specific year (omit for all years)}
        {--status= : Filter by status (e.g. ACTIV, CLOSE). Omit for planner_concern defaults}
        {--full : Force full re-sync, bypass incremental EDITDATE delta}
        {--dry-run : Preview without upserting}
        {--users=* : Filter by username(s). Accepts multiple values}';

    protected $description = 'Fetch assessments from WorkStudio API and upsert into assessments table';
    public int $chunkSize = 200;

    public function handle(GetQueryService $queryService): int
    {

        $failLogger = $this->buildFailLogger();
        $year = $this->option('year');
        $status = $this->option('status');
        $full = $this->option('full');
        $dryRun = $this->option('dry-run');
        $users = $this->option('users');

        $this->info($year ? "Fetching assessments for year: {$year}" : 'Fetching all assessments (no year filter)');

        if (! empty($users)) {

            FetchAssessmentQueries::forUsers($users, $year);
        }

        try {
            $assessments = $this->fetchFromApi($queryService, $year, $status, $full);
        } catch (\Exception $e) {
            $decoded = json_decode($e->getMessage(), true);
            $this->error('API request failed: '.($decoded['Message'] ?? $e->getMessage()));
            $failLogger->error('FetchAssessments API failure', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->info("Found {$assessments->count()} assessments from API.");

        if ($assessments->isEmpty()) {

            $failLogger->info('No assessments were returned so all existing assessments are up-to-date.');

            $this->info('No assessments to sync. Exiting.');

            return self::SUCCESS;
        }

        $jobGuids = $assessments->filter(function ($row) {

            return  in_array($row['CONTRACTOR'], ['Asplundh', 'ASPLUNDH'])
                &&
                $row['SCOPE_YEAR'] == '2026';
        })->pluck('JOBGUID')->unique()->values()
            ->all();

        $jobGuidCount = count($jobGuids);

        $this->info("Getting Daily Planner Records, {$jobGuidCount} individual jobs will be recorded.....");
        $dailyFootage = $this->getDailyFootageInChunks(
            $queryService,
            $jobGuids,
            $this->chunkSize
        );


        if ($dryRun) {
            $this->displayPreview($assessments);

            return self::SUCCESS;
        }

        $this->upsertAssessments($assessments);

        $syncResults = PlannerDailyRecord::syncFromApi($dailyFootage);

        $this->info(implode(', ', $syncResults));

        $this->newLine();

        $this->info('Time to grab some more details about the Assessments... ');

        $additionalAssessmentMetrics = $this->getAdditionalMetrics($queryService, $jobGuids);

        $this->persistMetrics($additionalAssessmentMetrics);
        $this->persistContributors($additionalAssessmentMetrics);

        return self::SUCCESS;
    }

    /**
     * Fetch assessment rows from the WorkStudio GETQUERY API.
     *
     * Uses incremental sync by default: queries only records edited after
     * the latest EDITDATE OLE float already stored locally. When --full
     * is passed (or no local data exists yet), fetches everything.
     *
     * @param  GetQueryService  $queryService  HTTP client wrapper for the DDOProtocol API
     * @param  ?string  $year     Scope year filter (null = all years)
     * @param  ?string  $status   Status filter, e.g. 'ACTIV' (null = planner_concern defaults)
     * @param  bool     $full     When true, skip incremental delta and re-fetch all records
     * @return Collection<int, array<string, mixed>>  Raw API rows keyed by column name
     */
    private function fetchFromApi(GetQueryService $queryService, ?string $year, ?string $status, bool $full): Collection
    {
        $maxEditDateOle = null;

        if (! $full) {
            $maxEditDateOle = Assessment::max('last_edited_ole');

            if ($maxEditDateOle !== null) {
                $maxEditDate = Assessment::max('last_edited');
                $this->info("Incremental sync: fetching records edited since {$maxEditDate}");
                $this->info('Existing assessments in database: ' . Assessment::count());
            }
        }

        $sql = FetchAssessmentQueries::buildFetchQuery($year, $status, $maxEditDateOle);


        return $queryService->executeAndHandle($sql);
    }

    private function displayPreview(Collection $rows): void
    {
        $preview = $rows->take(20)->map(fn(array $row) => [
            $row['JOBGUID'],
            $row['WO'],
            $row['EXT'],
            $row['JOBTYPE'],
            $row['STATUS'],
            $row['TITLE'],
        ]);

        $this->table(['job_guid', 'work_order', 'ext', 'job_type', 'status', 'title'], $preview->toArray());

        if ($rows->count() > 20) {
            $this->info('Showing first 20 of ' . $rows->count() . ' assessments.');
        }
    }

    private function upsertAssessments(Collection $rows): void
    {
        $circuitMap = $this->buildCircuitMap();

        $failLogger = $this->buildFailLogger();

        // Sort by extension depth — parents (@, len 1) before children (C_a, C_ba, etc.)
        $sorted = $rows->sortBy(fn(array $row) => strlen($row['EXT'] ?? ''));

        $bar = $this->output->createProgressBar($sorted->count());
        $bar->start();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($sorted as $row) {
            $circuitId = $this->resolveCircuitId($row['TITLE'] ?? '', $circuitMap);

            if ($circuitId === null) {
                $failLogger->warning('Skipped assessment — circuit not resolved', [
                    'JOBGUID' => $row['JOBGUID'],
                    'WO' => $row['WO'],
                    'TITLE' => $row['TITLE'] ?? '',
                    'reason' => 'No matching circuit for raw_title',
                ]);
                $skipped++;
                $bar->advance();

                continue;
            }

            $attributes = $this->mapRowToAttributes($row, $circuitId);

            $assessment = Assessment::firstOrNew(['job_guid' => $row['JOBGUID']]);

            $isNew = ! $assessment->exists;

            $assessment->fill($attributes);

            $assessment->last_synced_at = now();

            if ($isNew) {

                $assessment->discovered_at = now();

                $created++;
            } else {

                $updated++;
            }

            $assessment->save();

            $bar->advance();
        }

        $bar->finish();

        $this->newLine(2);

        $this->info("Assessments synced: {$created} created, {$updated} updated, {$skipped} skipped.");

        $this->flagSplitParents();

        $this->updateCircuitJobGuids();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRowToAttributes(array $row, int $circuitId): array
    {
        // Parent assessments (EXT = @) are roots — always null parent_job_guid
        $ext = $row['EXT'] ?? '@';
        $parentGuid = $ext !== '@' && ! empty($row['PJOBGUID']) ? $row['PJOBGUID'] : null;
        $oleFloat = is_numeric($row['EDITDATE_OLE'] ?? null) ? (float) $row['EDITDATE_OLE'] : null;

        $lastEdited = null;
        if (! empty($row['EDITDATE'])) {
            try {
                $lastEdited = Carbon::parse($row['EDITDATE']);
            } catch (\Throwable) {
                // Skip unparseable dates — OLE float is the reliable fallback
            }
        }

        $scopeYear = ! empty($row['SCOPE_YEAR']) ? (string) $row['SCOPE_YEAR'] : null;

        return [
            'parent_job_guid' => $parentGuid,
            'circuit_id' => $circuitId,
            'work_order' => $row['WO'] ?? '',
            'extension' => $row['EXT'] ?? '@',
            'job_type' => $row['JOBTYPE'] ?? '',
            'status' => $row['STATUS'] ?? '',
            'scope_year' => $scopeYear,
            'taken' => $this->parseTaken($row['TAKEN'] ?? null),
            'taken_by_username' => $row['TAKENBY'] ?: null,
            'modified_by_username' => $row['MODIFIEDBY'] ?: null,
            'assigned_to' => $row['ASSIGNEDTO'] ?: null,
            'raw_title' => $row['TITLE'] ?? '',
            'version' => $row['VERSION'] ?? null,
            'sync_version' => $row['SYNCHVERSN'] ?? null,
            'cycle_type' => $row['CYCLETYPE'] ?: null,
            'region' => $row['REGION'] ?: null,
            'contractor' => $row['CONTRACTOR'] ?: null,
            'planned_emergent' => $row['PLANNEDEMERGENT'] ?: null,
            'voltage' => $row['VOLTAGE'] ?? null,
            'cost_method' => $row['COSTMETHOD'] ?: null,
            'program_name' => $row['PROGRAMNAME'] ?: null,
            'permissioning_required' => $this->parseTaken($row['PERMISSIONING_REQUIRED'] ?? null),
            'percent_complete' => $row['PRCENT'] ?? null,
            'length' => $row['LENGTH'] ?? null,
            'length_completed' => $row['LENGTHCOMP'] ?? null,
            'last_edited' => $lastEdited,
            'last_edited_ole' => $oleFloat,
        ];
    }

    /**
     * Flag is_split = true on Assessment Dx parents that have split children.
     */
    private function flagSplitParents(): void
    {
        $parentGuids = Assessment::whereNotNull('parent_job_guid')
            ->distinct()
            ->pluck('parent_job_guid');

        if ($parentGuids->isEmpty()) {
            return;
        }

        $flagged = Assessment::where('job_type', 'Assessment Dx')
            ->whereIn('job_guid', $parentGuids)
            ->update(['is_split' => true]);

        $this->info("Flagged {$flagged} parent assessments as split.");
    }

    /**
     * @return array<string, int>
     */
    private function buildCircuitMap(): array
    {
        $map = [];

        Circuit::all()->each(function (Circuit $circuit) use (&$map) {
            $rawLineName = $circuit->properties['raw_line_name'] ?? null;

            if ($rawLineName) {
                $map[strtoupper($rawLineName)] = $circuit->id;
            }

            if ($circuit->line_name) {
                $map[strtoupper($circuit->line_name)] = $circuit->id;
            }
        });

        return $map;
    }

    /**
     * @param  array<string, int>  $circuitMap
     */
    private function resolveCircuitId(string $rawTitle, array $circuitMap): ?int
    {
        $upper = strtoupper($rawTitle);

        if (isset($circuitMap[$upper])) {
            return $circuitMap[$upper];
        }

        // Strip voltage prefixes and " LINE" suffix — same logic as FetchCircuits
        $cleaned = trim(str_replace(
            ['69/12 KV ', ' LINE', '69/12KV ', '69/12 ', '138/12 KV ', '138/12KV '],
            '',
            $upper
        ));

        return $circuitMap[$cleaned] ?? null;
    }

    private function updateCircuitJobGuids(): void
    {
        $this->info("Updating Job / Assessment Properties on Circuits.");

        $assessments = Assessment::whereNotNull('circuit_id')
            ->whereNotNull('scope_year')
            ->get();

        $updatedCount = 0;

        $assessments->groupBy('circuit_id')->each(function (Collection $circuitAssessments, int $circuitId) use (&$updatedCount) {
            /** @var \App\Models\Circuit $circuit */
            $circuit = Circuit::find($circuitId);

            if (! $circuit) {
                return;
            }

            $properties = $circuit->properties ?? [];

            $properties = $properties + $circuitAssessments->groupBy('scope_year')
                ->map(
                    fn($items) => $items
                        ->groupBy('cycle_type')
                        ->map(
                            fn($group) => $group
                                ->pluck('job_guid')
                                ->values()
                                ->all()
                        )->toArray()
                )->toArray();

            $lastTrim = Carbon::create(collect($properties)
                ->filter(fn($value, $key) => is_numeric($key) && is_array($value) && isset($value['Cycle Maintenance - Trim']))
                ->keys()
                ->max(), 1, 1);
            $nextTrim = $lastTrim->copy()->addYears(5);

            $circuit->properties = $properties;
            $circuit->last_trim = $lastTrim;
            $circuit->next_trim = $nextTrim;
            $circuit->save();
            $updatedCount++;
        });
        $this->info("Updated jobguids on {$updatedCount} circuits.");
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

    private function buildFailLogger(): \Psr\Log\LoggerInterface
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/failed-assessment-fetch.log'),
            'level' => 'debug',
        ]);
    }
}

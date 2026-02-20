<?php

namespace App\Console\Commands\Fetch;

use App\Models\Assessment;
use App\Models\Circuit;
use App\Services\WorkStudio\Client\ApiCredentialManager;
use App\Services\WorkStudio\Shared\Helpers\WSHelpers;
use App\Services\WorkStudio\Shared\Helpers\WSSQLCaster;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchAssessments extends Command
{
    protected $signature = 'ws:fetch-assessments
        {--year= : Scope to a specific year (omit for all years)}
        {--status= : Filter by status (e.g. ACTIV, CLOSE). Omit for planner_concern defaults}
        {--full : Force full re-sync, bypass incremental EDITDATE delta}
        {--dry-run : Preview without upserting}';

    protected $description = 'Fetch assessments from WorkStudio API and upsert into assessments table';

    public function handle(): int
    {
        $year = $this->option('year');
        $status = $this->option('status');
        $full = $this->option('full');
        $dryRun = $this->option('dry-run');

        $this->info($year ? "Fetching assessments for year: {$year}" : 'Fetching all assessments (no year filter)');

        $rows = $this->fetchFromApi($year, $status, $full);

        if ($rows === null) {
            return self::FAILURE;
        }

        $this->info("Found {$rows->count()} assessments from API.");

        if ($rows->isEmpty()) {
            
            $this->warn('No assessments were returned so all existing assessments are up-to-date.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->displayPreview($rows);

            return self::SUCCESS;
        }

        $this->upsertAssessments($rows);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array<string, mixed>>|null
     */
    private function fetchFromApi(?string $year, ?string $status, bool $full): ?Collection
    {
        $credentials = app(ApiCredentialManager::class)->getServiceAccountCredentials();
        $baseUrl = rtrim((string) config('workstudio.base_url'), '/');
        $jobTypes = WSHelpers::toSqlInClause(config('ws_assessment_query.job_types.assessments'));
        $editDateCast = WSSQLCaster::cast('VEGJOB.EDITDATE');

        $scopeYearExpr = "CASE WHEN xref.WP_STARTDATE IS NULL OR xref.WP_STARTDATE = '' THEN NULL "
            . "ELSE YEAR(CAST(REPLACE(REPLACE(xref.WP_STARTDATE, '/Date(', ''), ')/', '') AS DATE)) END";

        $sql = 'SELECT SS.JOBGUID, SS.PJOBGUID, SS.WO, SS.EXT, SS.JOBTYPE, SS.STATUS, '
            . 'SS.TAKEN, SS.TAKENBY, SS.MODIFIEDBY, SS.VERSION, SS.SYNCHVERSN, '
            . 'SS.ASSIGNEDTO, SS.TITLE, '
            . 'VEGJOB.CYCLETYPE, VEGJOB.REGION, '
            . 'VEGJOB.PLANNEDEMERGENT, VEGJOB.VOLTAGE, VEGJOB.COSTMETHOD, '
            . 'VEGJOB.PROGRAMNAME, VEGJOB.PERMISSIONING_REQUIRED, '
            . 'VEGJOB.PRCENT, VEGJOB.LENGTH, VEGJOB.LENGTHCOMP, '
            . 'VEGJOB.EDITDATE AS EDITDATE_OLE, '
            . "{$editDateCast} AS EDITDATE, "
            . "{$scopeYearExpr} AS SCOPE_YEAR "
            . 'FROM SS '
            . 'INNER JOIN VEGJOB ON VEGJOB.JOBGUID = SS.JOBGUID '
            . 'LEFT JOIN WPStartDate_Assessment_Xrefs xref '
            . 'ON xref.Assess_JOBGUID = CASE '
            . 'WHEN SS.EXT = \'@\' THEN SS.JOBGUID '
            . "ELSE COALESCE(NULLIF(SS.PJOBGUID, ''), SS.JOBGUID) END "
            . "WHERE SS.JOBTYPE IN ({$jobTypes}) ";

        if ($year) {
            $sql .= "AND xref.WP_STARTDATE LIKE '%{$year}%' ";
        }

        if ($status) {
            $sql .= "AND SS.STATUS = '{$status}' ";
        } else {
            $statuses = WSHelpers::toSqlInClause(config('ws_assessment_query.statuses.planner_concern'));
            $sql .= "AND SS.STATUS IN ({$statuses}) ";
        }

        $assessments = Assessment::query();

        if ($assessments->count() > 0 || ! $full) {

            $this->info("Existing assessments in database: {$assessments->count()}");

            $maxOle = $assessments->max('last_edited_ole');
            $maxEditDate = $assessments->max('last_edited');
            if ($maxOle !== null) {
                $sql .= "AND VEGJOB.EDITDATE > {$maxOle} ";
                $this->info("Incremental sync: fetching records with EDITDATE > {$maxEditDate} (OLE float > {$maxOle})");
            }
        }

        $sql .= 'ORDER BY SS.JOBGUID';

        $payload = [
            'Protocol' => 'GETQUERY',
            'DBParameters' => ApiCredentialManager::formatDbParameters($credentials['username'], $credentials['password']),
            'SQL' => $sql,
        ];

        $this->info("Sending API request to {$baseUrl}/GETQUERY with SQL:\n{$sql}");

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($credentials['username'], $credentials['password'])
                ->timeout(180)
                ->connectTimeout(30)
                ->post("{$baseUrl}/GETQUERY", $payload);

            $data = $response->json();

            $this->info("API responded, checking status and data");

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

            return collect($data['Data'])->map(fn(array $row) => array_combine($headings, $row));
        } catch (\Throwable $e) {
            $this->error("API request failed: {$e->getMessage()}");

            return null;
        }
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

        Circuit::whereNotNull('properties')->each(function (Circuit $circuit) use (&$map) {
            $rawLineName = $circuit->properties['raw_line_name'] ?? null;

            if ($rawLineName) {
                $map[$rawLineName] = $circuit->id;
            }
        });

        return $map;
    }

    /**
     * @param  array<string, int>  $circuitMap
     */
    private function resolveCircuitId(string $rawTitle, array $circuitMap): ?int
    {
        return $circuitMap[$rawTitle] ?? null;
    }

    private function updateCircuitJobGuids(): void
    {
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

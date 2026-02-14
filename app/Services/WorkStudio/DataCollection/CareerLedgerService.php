<?php

namespace App\Services\WorkStudio\DataCollection;

use App\Models\AssessmentMonitor;
use App\Models\PlannerCareerEntry;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\DataCollection\Queries\CareerLedgerQueries;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CareerLedgerService
{
    private CareerLedgerQueries $queries;

    public function __construct(
        private GetQueryService $queryService,
    ) {
        $this->queries = new CareerLedgerQueries($this->buildServiceContext());
    }

    /**
     * Bootstrap import: read a JSON file and bulk-insert career entries.
     *
     * Idempotent — skips rows where planner_username + job_guid already exist.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function importFromJson(string $path): array
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("Bootstrap file not found: {$path}");
        }

        $json = json_decode(file_get_contents($path), true);

        if (! is_array($json)) {
            throw new \InvalidArgumentException('Invalid JSON structure: expected array of entries');
        }

        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($json as $entry) {
            try {
                $exists = PlannerCareerEntry::where('planner_username', $entry['planner_username'])
                    ->where('job_guid', $entry['job_guid'])
                    ->exists();

                if ($exists) {
                    $stats['skipped']++;

                    continue;
                }


             // TODO - consider upsert if we want to update existing entries with new data (e.g. from live monitor)
                PlannerCareerEntry::create([
                    'planner_username' => $entry['planner_username'],
                    'planner_display_name' => $entry['planner_display_name'] ?? null,
                    'job_guid' => $entry['job_guid'],
                    'line_name' => $entry['line_name'] ?? '',
                    'region' => $entry['region'] ?? '',
                    'scope_year' => $entry['scope_year'] ?? '',
                    'cycle_type' => $entry['cycle_type'] ?? null,
                    'assessment_total_miles' => $entry['assessment_total_miles'] ?? null,
                    'assessment_completed_miles' => $entry['assessment_completed_miles'] ?? null,
                    'assessment_pickup_date' => $entry['assessment_pickup_date'] ?? null,
                    'assessment_qc_date' => $entry['assessment_qc_date'] ?? null,
                    'assessment_close_date' => $entry['assessment_close_date'] ?? null,
                    'went_to_rework' => $entry['went_to_rework'] ?? false,
                    'rework_details' => $entry['rework_details'] ?? null,
                    'daily_metrics' => $entry['daily_metrics'] ?? [],
                    'summary_totals' => $entry['summary_totals'] ?? [],
                    'source' => 'bootstrap',
                ]);

                $stats['imported']++;
            } catch (\Throwable $e) {
                Log::warning('Career ledger import: failed entry', [
                    'job_guid' => $entry['job_guid'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Export career ledger data from the API to a JSON file for bootstrapping.
     *
     * Queries all CLOSE assessments, runs daily footage attribution for each,
     * and writes the compiled data as a JSON bootstrap file.
     */
    public function exportToJson(string $path): int
    {
        $context = $this->buildServiceContext();

        $assessments = $this->queryService->getDailyActivitiesForAllAssessments($context);
        $allAssessments = $assessments->first();

        if (! is_array($allAssessments)) {
            return 0;
        }

        $closeAssessments = collect($allAssessments)
            ->filter(fn (array $a) => ($a['Status'] ?? '') === 'CLOSE');

        if ($closeAssessments->isEmpty()) {
            file_put_contents($path, json_encode([], JSON_PRETTY_PRINT));

            return 0;
        }

        $entries = [];

        foreach ($closeAssessments->chunk(10) as $chunk) {
            $guids = $chunk->pluck('Job_GUID')->toArray();
            $sql = $this->queries->getDailyFootageAttributionBatch($guids);
            $footageData = $this->queryService->executeAndHandle($sql);

            foreach ($chunk as $assessment) {
                $jobGuid = $assessment['Job_GUID'];
                $dailyMetrics = $footageData
                    ->filter(fn (array $row) => $row['JOBGUID'] === $jobGuid)
                    ->values()
                    ->toArray();

                $timelineSql = $this->queries->getAssessmentTimeline($jobGuid);
                $timeline = $this->queryService->executeAndHandle($timelineSql);
                $dates = $this->extractTimelineDates($timeline);

                $summaryTotalsSql = $this->queries->getWorkTypeBreakdown($jobGuid);
                $summaryTotals = $this->queryService->executeAndHandle($summaryTotalsSql)->toArray();

                   
                    // TODO planner username should be from dates between pickup and qc, but
                    // this should just be a FK that points to the ws_user table 
                    // we will have to rewrite the migration for this table 
                $entries[] = [
                    'planner_username' => $this->extractUsername($assessment['Current_Owner'] ?? ''),
                    'planner_display_name' => $assessment['Current_Owner'] ?? null,
                    'job_guid' => $jobGuid,
                    'line_name' => $assessment['Line_Name'] ?? null,
                    'region' => $assessment['Region'] ?? null,
                    'scope_year' => $assessment['Scope_Year'] ?? null,
                    'cycle_type' => $assessment['Cycle_Type'] ?? null,
                    'assessment_total_miles' => $assessment['Total_Miles'] ?? null,
                    'assessment_completed_miles' => $assessment['Completed_Miles'] ?? null,
                    'assessment_pickup_date' => $dates['pickup'] ?? null,
                    'assessment_qc_date' => $dates['qc'] ?? null,
                    'assessment_close_date' => $dates['close'] ?? null,
                    'went_to_rework' => $this->hadRework($timeline),
                    'rework_details' => null,
                    'daily_metrics' => $dailyMetrics,
                    'summary_totals' => $summaryTotals,
                ];
            }
        }

        file_put_contents($path, json_encode($entries, JSON_PRETTY_PRINT));

        return count($entries);
    }

    /**
     * Create a career entry from a closing assessment monitor.
     *
     * Called by the ProcessAssessmentClose listener when an assessment
     * transitions from active monitoring to CLOSE status.
     */
    public function appendFromMonitor(AssessmentMonitor $monitor): PlannerCareerEntry
    {
        $jobGuid = $monitor->job_guid;

        $footageSql = $this->queries->getDailyFootageAttribution($jobGuid);
        $footageData = $this->queryService->executeAndHandle($footageSql);
        $dailyMetrics = $footageData->toArray();

        $summaryTotalsSql = $this->queries->getWorkTypeBreakdown($jobGuid);
        $summaryTotals = $this->queryService->executeAndHandle($summaryTotalsSql)->toArray();

        $timelineSql = $this->queries->getAssessmentTimeline($jobGuid);
        $timeline = $this->queryService->executeAndHandle($timelineSql);
        $dates = $this->extractTimelineDates($timeline);

        $reworkDetails = null;
        $wentToRework = $this->hadRework($timeline);

        if ($wentToRework) {
            $reworkSql = $this->queries->getReworkDetails($jobGuid);
            $reworkDetails = $this->queryService->executeAndHandle($reworkSql)->toArray();
        }

        return PlannerCareerEntry::create([
            'planner_username' => $this->extractUsername($monitor->current_planner ?? ''),
            'planner_display_name' => $monitor->current_planner,
            'job_guid' => $jobGuid,
            'line_name' => $monitor->line_name,
            'region' => $monitor->region,
            'scope_year' => $monitor->scope_year,
            'cycle_type' => $monitor->cycle_type,
            'assessment_total_miles' => $monitor->total_miles,
            'assessment_completed_miles' => $monitor->latest_snapshot['footage']['completed_miles'] ?? null,
            'assessment_pickup_date' => $dates['pickup'] ?? null,
            'assessment_qc_date' => $dates['qc'] ?? null,
            'assessment_close_date' => $dates['close'] ?? null,
            'went_to_rework' => $wentToRework,
            'rework_details' => $reworkDetails,
            'daily_metrics' => $dailyMetrics,
            'summary_totals' => $summaryTotals,
            'source' => 'live_monitor',
        ]);
    }

    /**
     * Extract lifecycle dates from JOBHISTORY timeline.
     *
     * @return array{pickup: ?string, qc: ?string, close: ?string}
     */
    private function extractTimelineDates($timeline): array
    {
        $dates = ['pickup' => null, 'qc' => null, 'close' => null];

        foreach ($timeline as $entry) {
            $status = $entry['JOBSTATUS'] ?? null;
            $logDate = $entry['LOGDATE'] ?? null;

            if (! $logDate) {
                continue;
            }

            $parsed = $this->parseDdoDate($logDate);

            if ($status === 'ACTIV' && $dates['pickup'] === null) {
                $dates['pickup'] = $parsed;
            } elseif ($status === 'QC' && $dates['qc'] === null) {
                $dates['qc'] = $parsed;
            } elseif ($status === 'CLOSE') {
                $dates['close'] = $parsed;
            }
        }

        return $dates;
    }

    /**
     * Check if the assessment went through rework based on timeline.
     */
    private function hadRework($timeline): bool
    {
        foreach ($timeline as $entry) {
            if (($entry['JOBSTATUS'] ?? null) === 'REWRK') {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse a DDOProtocol date value to Y-m-d string.
     */
    private function parseDdoDate(string $value): ?string
    {
        try {
            if (str_contains($value, '/Date(')) {
                $raw = str_replace(['/Date(', ')/'], '', $value);

                return Carbon::parse($raw)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract the username portion from a DOMAIN\username string.
     */
    private function extractUsername(string $domainUsername): string
    {
        if (str_contains($domainUsername, '\\')) {
            return substr($domainUsername, strpos($domainUsername, '\\') + 1);
        }

        return $domainUsername;
    }

    /**
     * Build a UserQueryContext for service-account (cron) operations.
     */
    private function buildServiceContext(): UserQueryContext
    {
        return new UserQueryContext(
            resourceGroups: config('workstudio_resource_groups.all', []),
            contractors: config('ws_assessment_query.contractors', ['Asplundh']),
            domain: strtoupper(config('ws_assessment_query.contractors.0', 'ASPLUNDH')),
            username: 'service',
            userId: 0,
        );
    }
}

<?php

namespace App\Services\WorkStudio\Planners;

use App\Models\PlannerJobAssignment;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\Planners\Queries\PlannerCareerLedger;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PlannerCareerLedgerService
{
    private PlannerCareerLedger $queries;

    public function __construct(
        private GetQueryService $queryService,
    ) {
        $this->queries = new PlannerCareerLedger($this->buildServiceContext());
    }

    /**
     * Discover JOBGUIDs for given FRSTR_USERs and upsert to planner_job_assignments.
     *
     * @param  string|array<int, string>  $frstrUsers
     * @param  bool  $current  When true, discover active/QC/rework assessments instead of closed
     * @return Collection<int, PlannerJobAssignment>
     */
    public function discoverJobGuids(string|array $frstrUsers, bool $current = false, bool $allYears = false): Collection
    {
        $users = is_array($frstrUsers) ? $frstrUsers : [$frstrUsers];

        $sql = $this->queries->getDistinctJobGuids($users, $current, $allYears);
        $results = $this->queryService->executeAndHandle($sql);

        $assignments = collect();

        foreach ($results as $row) {
            $jobGuid = $row['JOBGUID'] ?? null;

            if (! $jobGuid) {
                continue;
            }

            foreach ($users as $user) {
                $assignment = PlannerJobAssignment::firstOrCreate(
                    ['frstr_user' => $user, 'job_guid' => $jobGuid],
                    ['status' => 'discovered', 'discovered_at' => now()],
                );

                $assignments->push($assignment);
            }
        }

        return $assignments;
    }

    /**
     * Export career data for a single user to a JSON file.
     *
     * Incremental by default: previously-exported assignments are checked for
     * staleness (EDITDATE > updated_at) and only new daily metrics are fetched.
     * Metadata fields are always refreshed for stale assessments.
     *
     * @param  bool  $current  When true, export active/QC/rework assessments instead of closed
     * @return string File path of the exported JSON
     */
    public function exportForUser(string $frstrUser, string $outputDir, bool $current = false): string
    {
        $allAssignments = PlannerJobAssignment::forUser($frstrUser)->get();

        if ($allAssignments->isEmpty()) {
            $filePath = rtrim($outputDir, '/')."/{$frstrUser}_".now()->format('Y-m-d').'.json';
            file_put_contents($filePath, json_encode([], JSON_PRETTY_PRINT));

            return $filePath;
        }

        // Split into new vs previously exported
        $newAssignments = $allAssignments->filter(fn ($a) => ! $a->export_path);
        $existingAssignments = $allAssignments->filter(fn ($a) => $a->export_path !== null);

        // Determine stale GUIDs among existing exports
        $staleGuids = [];
        $existingEntries = [];
        $existingFilePath = null;

        if ($existingAssignments->isNotEmpty()) {
            $existingFilePath = $existingAssignments->first()->export_path;
            $existingGuids = $existingAssignments->pluck('job_guid')->toArray();

            // Fetch EDITDATE from remote API
            $sql = $this->queries->getEditDates($existingGuids);
            $editDates = $this->queryService->executeAndHandle($sql);

            foreach ($editDates as $row) {
                $guid = $row['JOBGUID'] ?? null;
                $editDate = $row['edit_date'] ?? null;

                if (! $guid || ! $editDate) {
                    continue;
                }

                $assignment = $existingAssignments->firstWhere('job_guid', $guid);

                if (! $assignment) {
                    continue;
                }

                try {
                    $remoteEditDate = Carbon::parse($editDate);
                } catch (\Throwable) {
                    continue;
                }

                if ($remoteEditDate->gt($assignment->updated_at)) {
                    $staleGuids[] = $guid;
                }
            }

            // Load existing JSON if file exists
            if ($existingFilePath && file_exists($existingFilePath)) {
                $existingEntries = json_decode(file_get_contents($existingFilePath), true) ?? [];
            } else {
                // File missing — treat all existing as new
                $newAssignments = $allAssignments;
                $staleGuids = [];
                $existingEntries = [];
                $existingFilePath = null;
            }
        }

        $entries = $existingEntries;

        // Fetch full data for new assignments
        $newGuids = $newAssignments->pluck('job_guid')->toArray();

        if (! empty($newGuids)) {
            $sql = $this->queries->getFullCareerData($newGuids);
            $results = $this->queryService->executeAndHandle($sql);

            foreach ($results as $row) {
                $entries[] = $this->buildEntry($frstrUser, $row);
            }
        }

        // Fetch data for stale assignments (date-filtered daily metrics)
        if (! empty($staleGuids)) {
            $staleAssignments = $existingAssignments->whereIn('job_guid', $staleGuids);
            $earliestUpdatedAt = $staleAssignments->min('updated_at');
            $dateStart = Carbon::parse($earliestUpdatedAt)->toDateString();
            $dateEnd = now()->toDateString();

            $sql = $this->queries->getFullCareerData($staleGuids, $dateStart, $dateEnd);
            $results = $this->queryService->executeAndHandle($sql);

            foreach ($results as $row) {
                $entries = $this->updateExistingEntry($entries, $frstrUser, $row);
            }
        }

        // Determine output path
        $filePath = $existingFilePath ?? rtrim($outputDir, '/')."/{$frstrUser}_".now()->format('Y-m-d').'.json';
        file_put_contents($filePath, json_encode($entries, JSON_PRETTY_PRINT));

        // Update all affected assignments
        $affectedGuids = array_merge($newGuids, $staleGuids);

        if (! empty($affectedGuids)) {
            PlannerJobAssignment::forUser($frstrUser)
                ->whereIn('job_guid', $affectedGuids)
                ->update([
                    'status' => 'exported',
                    'export_path' => $filePath,
                ]);
        }

        return $filePath;
    }

    /**
     * Batch export career data for multiple users.
     *
     * @param  array<int, string>  $frstrUsers
     * @param  bool  $current  When true, export active/QC/rework assessments instead of closed
     * @return array<string, string> Map of username => file path
     */
    public function exportForUsers(array $frstrUsers, string $outputDir, bool $current = false): array
    {
        $results = [];

        foreach ($frstrUsers as $user) {
            try {
                $results[$user] = $this->exportForUser($user, $outputDir, $current);
            } catch (\Throwable $e) {
                Log::warning('Planner career export failed', [
                    'user' => $user,
                    'error' => $e->getMessage(),
                ]);
                $results[$user] = 'ERROR: '.$e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Build a single export entry from an API result row.
     */
    private function buildEntry(string $frstrUser, array $row): array
    {
        $timeline = json_decode($row['timeline'] ?? '[]', true) ?? [];
        $dates = $this->extractTimelineDates(collect($timeline));
        $wentToRework = $this->hadRework(collect($timeline));
        $reworkPeriods = $this->extractReworkPeriods($timeline, $dates['close']);
        $reworkDetails = json_decode($row['rework_details'] ?? 'null', true);
        $dailyMetrics = json_decode($row['daily_metrics'] ?? '[]', true) ?? [];
        $dailyMetrics = $this->enrichDailyMetricsWithStatus($dailyMetrics, $dates, $reworkPeriods);
        $summaryTotals = json_decode($row['work_type_breakdown'] ?? '[]', true) ?? [];

        return [
            'planner_username' => $frstrUser,
            'job_guid' => $row['JOBGUID'],
            'line_name' => $row['line_name'] ?? null,
            'region' => $row['region'] ?? null,
            'scope_year' => $row['scope_year'] ?? null,
            'cycle_type' => $row['cycle_type'] ?? null,
            'assessment_total_miles' => $row['total_miles'] ?? null,
            'assessment_total_miles_planned' => $row['total_miles_planned'] ?? null,
            'assessment_pickup_date' => $dates['pickup'] ?? null,
            'assessment_qc_date' => $dates['qc'] ?? null,
            'assessment_close_date' => $dates['close'] ?? null,
            'went_to_rework' => $wentToRework,
            'rework_details' => $reworkDetails,
            'daily_metrics' => $dailyMetrics,
            'summary_totals' => $summaryTotals,
        ];
    }

    /**
     * Update an existing entry in the entries array with fresh API data.
     *
     * Refreshes metadata fields and merges new daily_metrics.
     *
     * @return array<int, array<string, mixed>>
     */
    private function updateExistingEntry(array $entries, string $frstrUser, array $row): array
    {
        $jobGuid = $row['JOBGUID'];
        $freshEntry = $this->buildEntry($frstrUser, $row);

        $existingIndex = null;
        foreach ($entries as $i => $entry) {
            if (($entry['job_guid'] ?? null) === $jobGuid) {
                $existingIndex = $i;
                break;
            }
        }

        if ($existingIndex === null) {
            // Not found in existing entries — add as new
            $entries[] = $freshEntry;

            return $entries;
        }

        $existing = $entries[$existingIndex];

        // Refresh metadata fields
        $existing['line_name'] = $freshEntry['line_name'];
        $existing['region'] = $freshEntry['region'];
        $existing['cycle_type'] = $freshEntry['cycle_type'];
        $existing['assessment_total_miles'] = $freshEntry['assessment_total_miles'];
        $existing['assessment_total_miles_planned'] = $freshEntry['assessment_total_miles_planned'];
        $existing['assessment_pickup_date'] = $freshEntry['assessment_pickup_date'];
        $existing['assessment_qc_date'] = $freshEntry['assessment_qc_date'];
        $existing['assessment_close_date'] = $freshEntry['assessment_close_date'];
        $existing['went_to_rework'] = $freshEntry['went_to_rework'];
        $existing['rework_details'] = $freshEntry['rework_details'];
        $existing['summary_totals'] = $freshEntry['summary_totals'];

        // Merge and deduplicate daily metrics
        $existingMetrics = $existing['daily_metrics'] ?? [];
        $newMetrics = $freshEntry['daily_metrics'] ?? [];
        $existing['daily_metrics'] = $this->mergeAndDeduplicateDailyMetrics($existingMetrics, $newMetrics);

        // Re-enrich assumed_status on merged metrics
        $timeline = json_decode($row['timeline'] ?? '[]', true) ?? [];
        $dates = $this->extractTimelineDates(collect($timeline));
        $reworkPeriods = $this->extractReworkPeriods($timeline, $dates['close']);
        $existing['daily_metrics'] = $this->enrichDailyMetricsWithStatus(
            $existing['daily_metrics'], $dates, $reworkPeriods
        );

        $entries[$existingIndex] = $existing;

        return $entries;
    }

    /**
     * Merge new daily metrics into existing, deduplicate by completion_date, and sort.
     */
    private function mergeAndDeduplicateDailyMetrics(array $existing, array $new): array
    {
        // Index by completion_date — new values overwrite old for same date
        $merged = [];

        foreach ($existing as $metric) {
            $date = $metric['completion_date'] ?? null;
            if ($date !== null) {
                $merged[$date] = $metric;
            }
        }

        foreach ($new as $metric) {
            $date = $metric['completion_date'] ?? null;
            if ($date !== null) {
                $merged[$date] = $metric;
            }
        }

        // Sort by completion_date and re-index
        ksort($merged);

        return array_values($merged);
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
     * Extract rework periods from timeline as date pairs.
     *
     * Each rework period starts when JOBSTATUS = 'REWRK' and ends
     * at the next timeline entry's date (or the close date as fallback).
     *
     * @return array<int, array{start: string, end: ?string}>
     */
    private function extractReworkPeriods($timeline, ?string $closeDate): array
    {
        $periods = [];

        foreach ($timeline as $i => $entry) {
            if (($entry['JOBSTATUS'] ?? null) !== 'REWRK') {
                continue;
            }

            $start = $this->parseDdoDate($entry['LOGDATE'] ?? '');

            if (! $start) {
                continue;
            }

            // End is the next timeline entry's date, or close date as fallback
            $end = null;
            for ($j = $i + 1; $j < count($timeline); $j++) {
                $nextDate = $this->parseDdoDate($timeline[$j]['LOGDATE'] ?? '');
                if ($nextDate) {
                    $end = $nextDate;
                    break;
                }
            }

            $end = $end ?? $closeDate;

            $periods[] = ['start' => $start, 'end' => $end];
        }

        return $periods;
    }

    /**
     * Determine the assumed status for a given date based on assessment lifecycle.
     *
     * @param  array{pickup: ?string, qc: ?string, close: ?string}  $lifecycle
     * @param  array<int, array{start: string, end: ?string}>  $reworkPeriods
     */
    private function determineAssumedStatus(string $date, array $lifecycle, array $reworkPeriods): string
    {
        if ($lifecycle['close'] && $date >= $lifecycle['close']) {
            return 'Closed';
        }

        if ($lifecycle['qc'] && $date >= $lifecycle['qc']) {
            foreach ($reworkPeriods as $period) {
                $afterStart = $date >= $period['start'];
                $beforeEnd = $period['end'] === null || $date < $period['end'];

                if ($afterStart && $beforeEnd) {
                    return 'Rework';
                }
            }

            return 'QC';
        }

        return 'Active';
    }

    /**
     * Add assumed_status to each daily metric entry.
     *
     * @param  array{pickup: ?string, qc: ?string, close: ?string}  $lifecycle
     * @param  array<int, array{start: string, end: ?string}>  $reworkPeriods
     */
    private function enrichDailyMetricsWithStatus(array $dailyMetrics, array $lifecycle, array $reworkPeriods): array
    {
        return array_map(function ($entry) use ($lifecycle, $reworkPeriods) {
            $date = $entry['completion_date'] ?? null;

            if ($date) {
                $entry['assumed_status'] = $this->determineAssumedStatus($date, $lifecycle, $reworkPeriods);
            }

            return $entry;
        }, $dailyMetrics);
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
     * Build a UserQueryContext for service-account operations.
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

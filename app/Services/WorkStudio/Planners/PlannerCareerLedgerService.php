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
    public function discoverJobGuids(string|array $frstrUsers, bool $current = false): Collection
    {
        $users = is_array($frstrUsers) ? $frstrUsers : [$frstrUsers];

        $sql = $this->queries->getDistinctJobGuids($users, $current);
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
     * @param  bool  $current  When true, export active/QC/rework assessments instead of closed
     * @return string File path of the exported JSON
     */
    public function exportForUser(string $frstrUser, string $outputDir, bool $current = false): string
    {
        $assignments = PlannerJobAssignment::forUser($frstrUser)
            ->whereIn('status', ['discovered', 'processed'])
            ->get();

        if ($assignments->isEmpty()) {
            $filePath = rtrim($outputDir, '/')."/{$frstrUser}_".now()->format('Y-m-d').'.json';
            file_put_contents($filePath, json_encode([], JSON_PRETTY_PRINT));

            return $filePath;
        }

        $guids = $assignments->pluck('job_guid')->toArray();

        // Single API call — returns one row per assessment with JSON columns
        $sql = $this->queries->getFullCareerData($guids);
        $results = $this->queryService->executeAndHandle($sql);

        $entries = [];

        foreach ($results as $row) {
            $timeline = json_decode($row['timeline'] ?? '[]', true) ?? [];
            $dates = $this->extractTimelineDates(collect($timeline));
            $wentToRework = $this->hadRework(collect($timeline));
            $reworkPeriods = $this->extractReworkPeriods($timeline, $dates['close']);
            $reworkDetails = json_decode($row['rework_details'] ?? 'null', true);
            $dailyMetrics = json_decode($row['daily_metrics'] ?? '[]', true) ?? [];
            $dailyMetrics = $this->enrichDailyMetricsWithStatus($dailyMetrics, $dates, $reworkPeriods);
            $summaryTotals = json_decode($row['work_type_breakdown'] ?? '[]', true) ?? [];

            $entries[] = [
                'planner_username' => $frstrUser,
                'job_guid' => $row['JOBGUID'],
                'line_name' => $row['line_name'] ?? null,
                'region' => $row['region'] ?? null,
                'scope_year' => $this->scopeYear(),
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

        $filePath = rtrim($outputDir, '/')."/{$frstrUser}_".now()->format('Y-m-d').'.json';
        file_put_contents($filePath, json_encode($entries, JSON_PRETTY_PRINT));

        PlannerJobAssignment::forUser($frstrUser)
            ->whereIn('job_guid', $guids)
            ->update(['status' => 'exported']);

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
     * Get scope year from config.
     */
    private function scopeYear(): string
    {
        return config('ws_assessment_query.scope_year', (string) now()->year);
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

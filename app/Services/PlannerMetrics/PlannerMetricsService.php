<?php

namespace App\Services\PlannerMetrics;

use App\Models\Assessment;
use App\Models\PlannerDailyRecord;
use App\Models\PlannerJobAssignment;
use App\Services\PlannerMetrics\Contracts\PlannerMetricsServiceInterface;
use Carbon\CarbonImmutable;

class PlannerMetricsService implements PlannerMetricsServiceInterface
{
    private const METERS_PER_MILE = 1609.344;

    public function getQuotaMetrics(string $period = 'week', int $offset = 0): array
    {
        $planners = $this->discoverPlanners();
        $quota = (float) config('planner_metrics.quota_miles_per_week');
        $gapThreshold = (float) config('planner_metrics.gap_warning_threshold', 3.0);
        $results = [];

        foreach ($planners as $frstrUser) {
            $periodMiles = $this->calculatePeriodMiles($frstrUser, $period, $offset);
            $quotaTarget = $this->calculateQuotaTarget($period, $quota, $offset);
            $percentComplete = $quotaTarget > 0 ? round(($periodMiles / $quotaTarget) * 100, 1) : 0;
            $gapMiles = max(0, round($quotaTarget - $periodMiles, 1));
            $streakWeeks = $this->calculateStreak($frstrUser, $quota);
            $lastWeekMiles = $this->calculateLastWeekMiles($frstrUser);
            $healthSignal = $this->resolveHealthSignal($frstrUser);

            $status = match (true) {
                $periodMiles >= $quotaTarget => 'success',
                $gapMiles < $gapThreshold => 'warning',
                default => 'error',
            };

            $results[] = [
                'username' => $frstrUser,
                'display_name' => $this->stripDomain($frstrUser),
                'period_miles' => round($periodMiles, 1),
                'quota_target' => round($quotaTarget, 1),
                'percent_complete' => $percentComplete,
                'streak_weeks' => $streakWeeks,
                'last_week_miles' => round($lastWeekMiles, 1),
                'days_since_last_edit' => $healthSignal['days_since_last_edit'],
                'active_assessment_count' => $healthSignal['active_assessment_count'],
                'status' => $status,
                'gap_miles' => $gapMiles,
                'circuits' => $healthSignal['circuits'],
            ];
        }

        return $results;
    }

    public function getUnifiedMetrics(int $offset = 0): array
    {
        $planners = $this->discoverPlanners();
        $quota = (float) config('planner_metrics.quota_miles_per_week');
        $gapThreshold = (float) config('planner_metrics.gap_warning_threshold', 3.0);
        $results = [];

        foreach ($planners as $frstrUser) {
            $periodMiles = $this->calculatePeriodMiles($frstrUser, 'week', $offset);
            $quotaTarget = $this->calculateQuotaTarget('week', $quota, $offset);
            $quotaPercent = $quotaTarget > 0 ? round(($periodMiles / $quotaTarget) * 100, 1) : 0;
            $gapMiles = max(0, round($quotaTarget - $periodMiles, 1));
            $streakWeeks = $this->calculateStreak($frstrUser, $quota);
            $dailyMiles = $this->calculateDailyMiles($frstrUser, $offset);
            $healthSignal = $this->resolveHealthSignal($frstrUser);

            $status = match (true) {
                $periodMiles >= $quotaTarget => 'success',
                $gapMiles < $gapThreshold => 'warning',
                default => 'error',
            };

            $results[] = [
                'username' => $frstrUser,
                'display_name' => $this->stripDomain($frstrUser),
                'period_miles' => round($periodMiles, 1),
                'quota_target' => round($quotaTarget, 1),
                'quota_percent' => $quotaPercent,
                'streak_weeks' => $streakWeeks,
                'gap_miles' => $gapMiles,
                'days_since_last_edit' => $healthSignal['days_since_last_edit'],
                'pending_over_threshold' => $healthSignal['pending_over_threshold'],
                'permission_breakdown' => $healthSignal['permission_breakdown'],
                'total_miles' => $healthSignal['total_miles'],
                'overall_percent' => $healthSignal['percent_complete'],
                'active_assessment_count' => $healthSignal['active_assessment_count'],
                'status' => $status,
                'daily_miles' => $dailyMiles,
                'circuits' => $healthSignal['circuits'],
            ];
        }

        return $results;
    }

    public function getHealthMetrics(): array
    {
        $planners = $this->discoverPlanners();
        $warningDays = (int) config('planner_metrics.staleness_warning_days');
        $criticalDays = (int) config('planner_metrics.staleness_critical_days');
        $results = [];

        foreach ($planners as $frstrUser) {
            $healthSignal = $this->resolveHealthSignal($frstrUser);

            $status = match (true) {
                ($healthSignal['days_since_last_edit'] !== null && $healthSignal['days_since_last_edit'] >= $criticalDays)
                    || $healthSignal['pending_over_threshold'] >= 5 => 'error',
                ($healthSignal['days_since_last_edit'] !== null && $healthSignal['days_since_last_edit'] >= $warningDays)
                    || $healthSignal['pending_over_threshold'] > 0 => 'warning',
                default => 'success',
            };

            $results[] = [
                'username' => $frstrUser,
                'display_name' => $this->stripDomain($frstrUser),
                'days_since_last_edit' => $healthSignal['days_since_last_edit'],
                'pending_over_threshold' => $healthSignal['pending_over_threshold'],
                'permission_breakdown' => $healthSignal['permission_breakdown'],
                'total_miles' => $healthSignal['total_miles'],
                'percent_complete' => $healthSignal['percent_complete'],
                'active_assessment_count' => $healthSignal['active_assessment_count'],
                'status' => $status,
                'circuits' => $healthSignal['circuits'],
            ];
        }

        return $results;
    }

    public function getDistinctPlanners(): array
    {
        return collect($this->discoverPlanners())
            ->map(fn (string $frstrUser) => [
                'username' => $frstrUser,
                'display_name' => $this->stripDomain($frstrUser),
            ])
            ->values()
            ->all();
    }

    public function getPeriodLabel(string $period, int $offset = 0): string
    {
        $now = CarbonImmutable::now();
        [$start, $end] = $this->getDateWindow($period, $now, $offset);
        $startDate = CarbonImmutable::parse($start);
        $endDate = CarbonImmutable::parse($end);

        if ($startDate->year === $endDate->year) {
            return $startDate->format('M j').' – '.$endDate->format('M j, Y');
        }

        return $startDate->format('M j, Y').' – '.$endDate->format('M j, Y');
    }

    public function getDefaultOffset(string $period): int
    {
        if ($period !== 'week') {
            return 0;
        }

        $tz = config('planner_metrics.default_offset_timezone', 'America/New_York');
        $now = CarbonImmutable::now($tz);
        $flipDay = config('planner_metrics.default_offset_flip_day', 'Tuesday');
        $flipHour = (int) config('planner_metrics.default_offset_flip_hour', 17);

        if ($now->englishDayOfWeek === $flipDay) {
            return $now->hour < $flipHour ? -1 : 0;
        }

        $dayMap = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
        $flipDayOfWeek = $dayMap[$flipDay] ?? 2;

        return $now->dayOfWeek < $flipDayOfWeek ? -1 : 0;
    }

    public function getMonthlyMilesBreakdown(int $months = 4): array
    {
        $planners = $this->discoverPlanners();
        $now = CarbonImmutable::now();
        $results = [];

        $monthWindows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $now->subMonthsNoOverflow($i)->startOfMonth();
            $monthEnd = $i === 0 ? $now : $monthStart->endOfMonth();
            $monthWindows[] = [
                'label' => $monthStart->format('M'),
                'year' => $monthStart->year,
                'start' => $monthStart->format('Y-m-d'),
                'end' => $monthEnd->format('Y-m-d'),
            ];
        }

        foreach ($planners as $frstrUser) {
            $monthly = [];
            foreach ($monthWindows as $window) {
                $miles = (float) PlannerDailyRecord::query()
                    ->where('frstr_user', $frstrUser)
                    ->where('assess_date', '>=', $window['start'])
                    ->where('assess_date', '<', CarbonImmutable::parse($window['end'])->addDay()->format('Y-m-d'))
                    ->sum('span_miles');

                $monthly[] = [
                    'month' => $window['label'],
                    'year' => $window['year'],
                    'miles' => round($miles, 1),
                ];
            }

            $results[$frstrUser] = $monthly;
        }

        return $results;
    }

    /**
     * Get distinct planners from job assignments.
     *
     * @return list<string>
     */
    private function discoverPlanners(): array
    {
        return PlannerJobAssignment::query()
            ->distinct()
            ->pluck('frstr_user')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Strip domain prefix for display (e.g. "ASPLUNDH\jsmith" → "jsmith").
     */
    private function stripDomain(string $frstrUser): string
    {
        return str_contains($frstrUser, '\\')
            ? substr($frstrUser, strrpos($frstrUser, '\\') + 1)
            : $frstrUser;
    }

    /**
     * Resolve health signals from assessments + assessment_metrics tables.
     *
     * @return array{
     *     days_since_last_edit: int|null,
     *     pending_over_threshold: int,
     *     permission_breakdown: array<string, int>,
     *     total_miles: float,
     *     percent_complete: float,
     *     active_assessment_count: int,
     *     circuits: list<array{job_guid: string, line_name: string, region: string, total_miles: float, completed_miles: float, percent_complete: float, permission_breakdown: array<string, int>}>,
     * }
     */
    private function resolveHealthSignal(string $frstrUser): array
    {
        $guids = PlannerJobAssignment::forUser($frstrUser)->pluck('job_guid');

        $assessments = Assessment::query()
            ->with('metrics')
            ->whereIn('job_guid', $guids)
            ->where('status', 'ACTIV')
            ->get();

        if ($assessments->isEmpty()) {
            return [
                'days_since_last_edit' => null,
                'pending_over_threshold' => 0,
                'permission_breakdown' => [],
                'total_miles' => 0,
                'percent_complete' => 0,
                'active_assessment_count' => 0,
                'circuits' => [],
            ];
        }

        $circuits = $assessments->map(function (Assessment $assessment) {
            $metrics = $assessment->metrics;

            return [
                'job_guid' => $assessment->job_guid,
                'line_name' => $assessment->raw_title,
                'region' => $assessment->region,
                'total_miles' => round(($assessment->length ?? 0) / self::METERS_PER_MILE, 1),
                'completed_miles' => round(($assessment->length_completed ?? 0) / self::METERS_PER_MILE, 1),
                'percent_complete' => (float) ($assessment->percent_complete ?? 0),
                'permission_breakdown' => $metrics ? [
                    'approved' => $metrics->approved ?? 0,
                    'pending' => $metrics->pending ?? 0,
                    'refused' => $metrics->refused ?? 0,
                    'no_contact' => $metrics->no_contact ?? 0,
                    'deferred' => $metrics->deferred ?? 0,
                    'ppl_approved' => $metrics->ppl_approved ?? 0,
                ] : [],
            ];
        })->values()->all();

        $now = CarbonImmutable::now();
        $worstDays = 0;
        foreach ($assessments as $assessment) {
            if ($assessment->last_edited) {
                $days = (int) $now->diffInDays($assessment->last_edited);
                $worstDays = max($worstDays, $days);
            }
        }

        $totalPending = $assessments->sum(fn ($a) => $a->metrics?->pending_over_threshold ?? 0);

        $permissionCounts = [];
        $permissionKeys = ['approved', 'pending', 'refused', 'no_contact', 'deferred', 'ppl_approved'];
        foreach ($permissionKeys as $key) {
            $sum = $assessments->sum(fn ($a) => $a->metrics?->$key ?? 0);
            if ($sum > 0) {
                $permissionCounts[$key] = $sum;
            }
        }

        $totalMiles = $assessments->sum(fn ($a) => ($a->length_completed ?? 0) / self::METERS_PER_MILE);
        $avgPercent = round($assessments->avg(fn ($a) => (float) ($a->percent_complete ?? 0)), 1);

        return [
            'days_since_last_edit' => $worstDays,
            'pending_over_threshold' => $totalPending,
            'permission_breakdown' => $permissionCounts,
            'total_miles' => round($totalMiles, 1),
            'percent_complete' => $avgPercent,
            'active_assessment_count' => $assessments->count(),
            'circuits' => $circuits,
        ];
    }

    /**
     * Sum span_miles from planner_daily_records within the date window.
     */
    private function calculatePeriodMiles(string $frstrUser, string $period, int $offset = 0): float
    {
        $now = CarbonImmutable::now();
        [$start, $end] = $this->getDateWindow($period, $now, $offset);

        return (float) PlannerDailyRecord::query()
            ->where('frstr_user', $frstrUser)
            ->where('assess_date', '>=', $start)
            ->where('assess_date', '<', CarbonImmutable::parse($end)->addDay()->format('Y-m-d'))
            ->sum('span_miles');
    }

    /**
     * Bucket span_miles by day-of-week within the week window.
     *
     * @return list<array{day: string, miles: float}>
     */
    private function calculateDailyMiles(string $frstrUser, int $offset = 0): array
    {
        $now = CarbonImmutable::now();
        [$start] = $this->getDateWindow('week', $now, $offset);
        $startDate = CarbonImmutable::parse($start);
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $buckets = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->addDays($i);
            $buckets[$date->format('Y-m-d')] = [
                'day' => $dayNames[$date->dayOfWeek],
                'miles' => 0.0,
            ];
        }

        $records = PlannerDailyRecord::query()
            ->where('frstr_user', $frstrUser)
            ->where('assess_date', '>=', $start)
            ->where('assess_date', '<', $startDate->addDays(7)->format('Y-m-d'))
            ->selectRaw('assess_date, SUM(span_miles) as total_miles')
            ->groupBy('assess_date')
            ->get();

        foreach ($records as $record) {
            $dateKey = $record->assess_date->format('Y-m-d');
            if (isset($buckets[$dateKey])) {
                $buckets[$dateKey]['miles'] = (float) $record->total_miles;
            }
        }

        return array_values(array_map(fn (array $day) => [
            'day' => $day['day'],
            'miles' => round($day['miles'], 2),
        ], $buckets));
    }

    private function calculateQuotaTarget(string $period, float $weeklyQuota, int $offset = 0): float
    {
        if ($period === 'week') {
            return $weeklyQuota;
        }

        $now = CarbonImmutable::now();
        [$start, $end] = $this->getDateWindow($period, $now, $offset);
        $days = CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;

        return round($weeklyQuota * ($days / 7), 1);
    }

    /**
     * Count consecutive prior completed weeks where the planner met quota.
     */
    private function calculateStreak(string $frstrUser, float $weeklyQuota): int
    {
        $streak = 0;
        $now = CarbonImmutable::now();
        $weekStartDay = (int) config('planner_metrics.week_starts_on', CarbonImmutable::SUNDAY);
        $checkStart = $now->startOfWeek($weekStartDay)->subWeek();

        while (true) {
            $weekStart = $checkStart->format('Y-m-d');
            $weekEnd = $checkStart->addDays(6)->format('Y-m-d');

            $weekMiles = (float) PlannerDailyRecord::query()
                ->where('frstr_user', $frstrUser)
                ->where('assess_date', '>=', $weekStart)
                ->where('assess_date', '<', $checkStart->addDays(7)->format('Y-m-d'))
                ->sum('span_miles');

            if ($weekMiles >= $weeklyQuota) {
                $streak++;
                $checkStart = $checkStart->subWeek();
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get total miles for the prior completed week.
     */
    private function calculateLastWeekMiles(string $frstrUser): float
    {
        $now = CarbonImmutable::now();
        $weekStartDay = (int) config('planner_metrics.week_starts_on', CarbonImmutable::SUNDAY);
        $lastWeekStart = $now->startOfWeek($weekStartDay)->subWeek();
        $lastWeekEnd = $lastWeekStart->addDays(6);

        return (float) PlannerDailyRecord::query()
            ->where('frstr_user', $frstrUser)
            ->where('assess_date', '>=', $lastWeekStart->format('Y-m-d'))
            ->where('assess_date', '<', $lastWeekEnd->addDay()->format('Y-m-d'))
            ->sum('span_miles');
    }

    /**
     * Compute the start/end date window for a given period and offset.
     *
     * @return array{string, string} [start Y-m-d, end Y-m-d]
     */
    private function getDateWindow(string $period, CarbonImmutable $now, int $offset = 0): array
    {
        $weekStartDay = (int) config('planner_metrics.week_starts_on', CarbonImmutable::SUNDAY);

        switch ($period) {
            case 'week':
                $start = $now->startOfWeek($weekStartDay)->addWeeks($offset);
                $end = $offset < 0 ? $start->addDays(6) : $now;
                break;

            case 'month':
                $start = $now->startOfMonth()->addMonths($offset);
                $end = $offset < 0 ? $start->endOfMonth() : $now;
                break;

            case 'year':
                $start = $now->startOfYear()->addYears($offset);
                $end = $offset < 0 ? $start->endOfYear() : $now;
                break;

            case 'scope-year':
                $fiscalStart = $now->month >= 7
                    ? CarbonImmutable::create($now->year, 7, 1)
                    : CarbonImmutable::create($now->year - 1, 7, 1);
                $start = $fiscalStart->addYears($offset);
                $end = $offset < 0 ? $start->addYear()->subDay() : $now;
                break;

            default:
                $start = $now->startOfWeek($weekStartDay)->addWeeks($offset);
                $end = $offset < 0 ? $start->addDays(6) : $now;
        }

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
}

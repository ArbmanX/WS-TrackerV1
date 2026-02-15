<?php

namespace App\Services\PlannerMetrics;

use App\Models\AssessmentMonitor;
use App\Models\PlannerCareerEntry;
use App\Models\PlannerJobAssignment;
use App\Services\PlannerMetrics\Contracts\PlannerMetricsServiceInterface;
use Carbon\CarbonImmutable;

class PlannerMetricsService implements PlannerMetricsServiceInterface
{
    public function getQuotaMetrics(string $period = 'week'): array
    {
        $planners = $this->getDistinctPlanners();
        $quota = (float) config('planner_metrics.quota_miles_per_week');
        $gapThreshold = (float) config('planner_metrics.gap_warning_threshold', 3.0);
        $results = [];

        foreach ($planners as $planner) {
            $entries = PlannerCareerEntry::forPlanner($planner['username'])->get();
            $periodMiles = $this->calculatePeriodMiles($entries, $period);
            $quotaTarget = $this->calculateQuotaTarget($period, $quota);
            $percentComplete = $quotaTarget > 0 ? round(($periodMiles / $quotaTarget) * 100, 1) : 0;
            $gapMiles = max(0, round($quotaTarget - $periodMiles, 1));
            $streakWeeks = $this->calculateStreak($entries, $quota);
            $lastWeekMiles = $this->calculateLastWeekMiles($entries);
            $healthSignal = $this->resolveHealthSignal($planner['username']);

            $status = match (true) {
                $periodMiles >= $quotaTarget => 'success',
                $gapMiles < $gapThreshold => 'warning',
                default => 'error',
            };

            $results[] = [
                'username' => $planner['username'],
                'display_name' => $planner['display_name'],
                'period_miles' => round($periodMiles, 1),
                'quota_target' => round($quotaTarget, 1),
                'percent_complete' => $percentComplete,
                'streak_weeks' => $streakWeeks,
                'last_week_miles' => round($lastWeekMiles, 1),
                'days_since_last_edit' => $healthSignal['days_since_last_edit'],
                'active_assessment_count' => $healthSignal['active_assessment_count'],
                'status' => $status,
                'gap_miles' => $gapMiles,
            ];
        }

        return $results;
    }

    public function getHealthMetrics(): array
    {
        $planners = $this->getDistinctPlanners();
        $warningDays = (int) config('planner_metrics.staleness_warning_days');
        $criticalDays = (int) config('planner_metrics.staleness_critical_days');
        $results = [];

        foreach ($planners as $planner) {
            $healthSignal = $this->resolveHealthSignal($planner['username']);

            $status = match (true) {
                ($healthSignal['days_since_last_edit'] !== null && $healthSignal['days_since_last_edit'] >= $criticalDays)
                    || $healthSignal['pending_over_threshold'] >= 5 => 'error',
                ($healthSignal['days_since_last_edit'] !== null && $healthSignal['days_since_last_edit'] >= $warningDays)
                    || $healthSignal['pending_over_threshold'] > 0 => 'warning',
                default => 'success',
            };

            $results[] = [
                'username' => $planner['username'],
                'display_name' => $planner['display_name'],
                'days_since_last_edit' => $healthSignal['days_since_last_edit'],
                'pending_over_threshold' => $healthSignal['pending_over_threshold'],
                'permission_breakdown' => $healthSignal['permission_breakdown'],
                'total_miles' => $healthSignal['total_miles'],
                'percent_complete' => $healthSignal['percent_complete'],
                'active_assessment_count' => $healthSignal['active_assessment_count'],
                'status' => $status,
            ];
        }

        return $results;
    }

    public function getDistinctPlanners(): array
    {
        return PlannerCareerEntry::query()
            ->select('planner_username', 'planner_display_name')
            ->groupBy('planner_username', 'planner_display_name')
            ->get()
            ->map(fn ($entry) => [
                'username' => $entry->planner_username,
                'display_name' => $entry->planner_display_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     days_since_last_edit: int|null,
     *     pending_over_threshold: int,
     *     permission_breakdown: array<string, int>,
     *     total_miles: float,
     *     percent_complete: float,
     *     active_assessment_count: int,
     * }
     */
    private function resolveHealthSignal(string $username): array
    {
        $guids = PlannerJobAssignment::forNormalizedUser($username)->pluck('job_guid');
        $monitors = AssessmentMonitor::whereIn('job_guid', $guids)->active()->get();

        if ($monitors->isEmpty()) {
            return [
                'days_since_last_edit' => null,
                'pending_over_threshold' => 0,
                'permission_breakdown' => [],
                'total_miles' => 0,
                'percent_complete' => 0,
                'active_assessment_count' => 0,
            ];
        }

        $worstDays = 0;
        $totalPending = 0;
        $permissionCounts = [];
        $totalMiles = 0;
        $totalPercent = 0;

        foreach ($monitors as $monitor) {
            $snapshot = $monitor->latest_snapshot;
            if (! $snapshot) {
                continue;
            }

            $daysSince = $snapshot['planner_activity']['days_since_last_edit'] ?? 0;
            $worstDays = max($worstDays, $daysSince);

            $totalPending += $snapshot['aging_units']['pending_over_threshold'] ?? 0;

            foreach ($snapshot['permission_breakdown'] ?? [] as $status => $count) {
                $permissionCounts[$status] = ($permissionCounts[$status] ?? 0) + $count;
            }

            $totalMiles += $snapshot['footage']['completed_miles'] ?? 0;
            $totalPercent += $snapshot['footage']['percent_complete'] ?? 0;
        }

        $avgPercent = $monitors->count() > 0 ? round($totalPercent / $monitors->count(), 1) : 0;

        return [
            'days_since_last_edit' => $worstDays,
            'pending_over_threshold' => $totalPending,
            'permission_breakdown' => $permissionCounts,
            'total_miles' => round($totalMiles, 1),
            'percent_complete' => $avgPercent,
            'active_assessment_count' => $monitors->count(),
        ];
    }

    /**
     * Sum daily_footage_miles across all entries for a planner within the date window.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, PlannerCareerEntry>  $entries
     */
    private function calculatePeriodMiles($entries, string $period): float
    {
        $now = CarbonImmutable::now();

        if ($period === 'scope-year') {
            $currentScopeYear = (string) $now->year;

            return $entries
                ->where('scope_year', $currentScopeYear)
                ->sum(fn (PlannerCareerEntry $entry) => collect($entry->daily_metrics)
                    ->sum(fn (array $metric) => (float) ($metric['daily_footage_miles'] ?? 0))
                );
        }

        [$start, $end] = $this->getDateWindow($period, $now);

        $total = 0;
        foreach ($entries as $entry) {
            foreach ($entry->daily_metrics as $metric) {
                $date = $metric['completion_date'] ?? null;
                if ($date && $date >= $start && $date <= $end) {
                    $total += (float) ($metric['daily_footage_miles'] ?? 0);
                }
            }
        }

        return $total;
    }

    private function calculateQuotaTarget(string $period, float $weeklyQuota): float
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'week' => $weeklyQuota,
            'month' => $weeklyQuota * $this->weeksInMonth($now),
            'year' => $weeklyQuota * $this->weeksElapsedInYear($now),
            'scope-year' => $weeklyQuota * $this->weeksElapsedInYear($now),
            default => $weeklyQuota,
        };
    }

    /**
     * Count consecutive prior weeks where the planner met quota.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, PlannerCareerEntry>  $entries
     */
    private function calculateStreak($entries, float $weeklyQuota): int
    {
        $streak = 0;
        $now = CarbonImmutable::now();
        $checkMonday = $now->startOfWeek()->subWeek();

        while (true) {
            $weekStart = $checkMonday->format('Y-m-d');
            $weekEnd = $checkMonday->addDays(6)->format('Y-m-d');

            $weekMiles = 0;
            foreach ($entries as $entry) {
                foreach ($entry->daily_metrics as $metric) {
                    $date = $metric['completion_date'] ?? null;
                    if ($date && $date >= $weekStart && $date <= $weekEnd) {
                        $weekMiles += (float) ($metric['daily_footage_miles'] ?? 0);
                    }
                }
            }

            if ($weekMiles >= $weeklyQuota) {
                $streak++;
                $checkMonday = $checkMonday->subWeek();
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get the total miles for the prior completed week (Monday-Sunday before current week).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, PlannerCareerEntry>  $entries
     */
    private function calculateLastWeekMiles($entries): float
    {
        $now = CarbonImmutable::now();
        $lastMonday = $now->startOfWeek()->subWeek();
        $lastSunday = $lastMonday->addDays(6);

        $start = $lastMonday->format('Y-m-d');
        $end = $lastSunday->format('Y-m-d');

        $total = 0;
        foreach ($entries as $entry) {
            foreach ($entry->daily_metrics as $metric) {
                $date = $metric['completion_date'] ?? null;
                if ($date && $date >= $start && $date <= $end) {
                    $total += (float) ($metric['daily_footage_miles'] ?? 0);
                }
            }
        }

        return $total;
    }

    /**
     * @return array{string, string}
     */
    private function getDateWindow(string $period, CarbonImmutable $now): array
    {
        return match ($period) {
            'week' => [
                $now->startOfWeek()->format('Y-m-d'),
                $now->format('Y-m-d'),
            ],
            'month' => [
                $now->startOfMonth()->format('Y-m-d'),
                $now->format('Y-m-d'),
            ],
            'year' => [
                $now->startOfYear()->format('Y-m-d'),
                $now->format('Y-m-d'),
            ],
            default => [
                $now->startOfWeek()->format('Y-m-d'),
                $now->format('Y-m-d'),
            ],
        };
    }

    private function weeksInMonth(CarbonImmutable $now): float
    {
        return round($now->daysInMonth / 7, 1);
    }

    private function weeksElapsedInYear(CarbonImmutable $now): float
    {
        return round($now->dayOfYear / 7, 1);
    }
}

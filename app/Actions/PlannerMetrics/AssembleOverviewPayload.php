<?php

namespace App\Actions\PlannerMetrics;

use App\Services\PlannerMetrics\Contracts\CoachingMessageGeneratorInterface;
use App\Services\PlannerMetrics\Contracts\PlannerMetricsServiceInterface;
use Carbon\CarbonImmutable;

class AssembleOverviewPayload
{
    public function __construct(
        private PlannerMetricsServiceInterface $metricsService,
        private CoachingMessageGeneratorInterface $coachingGenerator,
    ) {}

    public function __invoke(int $offset): array
    {
        $planners = $this->metricsService->getUnifiedMetrics($offset);
        $periodLabel = $this->metricsService->getPeriodLabel('week', $offset);

        $weekStartDay = (int) config('planner_metrics.week_starts_on', CarbonImmutable::SUNDAY);
        $now = CarbonImmutable::now();
        $weekStart = $now->startOfWeek($weekStartDay)->addWeeks($offset);
        $weekEnd = $offset < 0 ? $weekStart->addDays(6) : $now;

        $enrichedPlanners = array_map(fn (array $planner) => $this->enrichPlanner($planner), $planners);

        $total = count($enrichedPlanners);
        $onTrack = collect($enrichedPlanners)->where('status', 'success')->count();

        return [
            'period' => [
                'label' => $periodLabel,
                'offset' => $offset,
                'is_current' => $offset >= 0,
                'week_start_date' => $weekStart->format('Y-m-d'),
                'week_end_date' => $weekEnd->format('Y-m-d'),
            ],
            'summary' => [
                'on_track' => $onTrack,
                'total_planners' => $total,
                'needs_attention' => $total - $onTrack,
                'team_avg_percent' => $total ? round(collect($enrichedPlanners)->avg('quota_percent'), 1) : 0,
                'total_aging' => collect($enrichedPlanners)->sum('pending_over_threshold'),
                'total_miles' => round(collect($enrichedPlanners)->sum('period_miles'), 1),
            ],
            'config' => [
                'quota_target' => (float) config('planner_metrics.quota_miles_per_week'),
                'staleness_warning_days' => (int) config('planner_metrics.staleness_warning_days'),
                'staleness_critical_days' => (int) config('planner_metrics.staleness_critical_days'),
                'gap_warning_threshold' => (float) config('planner_metrics.gap_warning_threshold'),
            ],
            'planners' => $enrichedPlanners,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    // TODO(human): Implement enrichPlanner — see Learn by Doing below
    private function enrichPlanner(array $planner): array
    {
        return $planner;
    }
}

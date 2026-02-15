<?php

namespace App\Services\PlannerMetrics\Contracts;

interface PlannerMetricsServiceInterface
{
    /**
     * Get quota metrics for all planners within the given time period.
     *
     * @return list<array{
     *     username: string,
     *     display_name: string,
     *     period_miles: float,
     *     quota_target: float,
     *     percent_complete: float,
     *     streak_weeks: int,
     *     last_week_miles: float,
     *     days_since_last_edit: int|null,
     *     active_assessment_count: int,
     *     status: string,
     *     gap_miles: float,
     * }>
     */
    public function getQuotaMetrics(string $period = 'week'): array;

    /**
     * Get health metrics for all planners.
     *
     * @return list<array{
     *     username: string,
     *     display_name: string,
     *     days_since_last_edit: int|null,
     *     pending_over_threshold: int,
     *     permission_breakdown: array<string, int>,
     *     total_miles: float,
     *     percent_complete: float,
     *     active_assessment_count: int,
     *     status: string,
     * }>
     */
    public function getHealthMetrics(): array;

    /**
     * Get distinct planners from career entries.
     *
     * @return list<array{username: string, display_name: string}>
     */
    public function getDistinctPlanners(): array;
}

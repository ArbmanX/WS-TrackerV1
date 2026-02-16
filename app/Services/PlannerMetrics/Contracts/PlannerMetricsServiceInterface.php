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
     *     circuits: list<array{job_guid: string, line_name: string, region: string, total_miles: float, completed_miles: float, percent_complete: float, permission_breakdown: array<string, int>}>,
     * }>
     */
    public function getQuotaMetrics(string $period = 'week', int $offset = 0): array;

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
     *     circuits: list<array{job_guid: string, line_name: string, region: string, total_miles: float, completed_miles: float, percent_complete: float, permission_breakdown: array<string, int>}>,
     * }>
     */
    public function getHealthMetrics(): array;

    /**
     * Get distinct planners from career entries.
     *
     * @return list<array{username: string, display_name: string}>
     */
    public function getDistinctPlanners(): array;

    /**
     * Get unified metrics (quota + health) for all planners, week-only.
     *
     * @return list<array{
     *     username: string,
     *     display_name: string,
     *     period_miles: float,
     *     quota_target: float,
     *     quota_percent: float,
     *     streak_weeks: int,
     *     gap_miles: float,
     *     days_since_last_edit: int|null,
     *     pending_over_threshold: int,
     *     permission_breakdown: array<string, int>,
     *     total_miles: float,
     *     overall_percent: float,
     *     active_assessment_count: int,
     *     status: string,
     *     circuits: list<array{job_guid: string, line_name: string, region: string, total_miles: float, completed_miles: float, percent_complete: float, permission_breakdown: array<string, int>}>,
     * }>
     */
    public function getUnifiedMetrics(int $offset = 0): array;

    /**
     * Get the human-readable label for a period + offset combination.
     *
     * @return string e.g. "Feb 9 – Feb 15, 2026"
     */
    public function getPeriodLabel(string $period, int $offset = 0): string;

    /**
     * Get the auto-default offset for a period.
     *
     * For "week", returns -1 before the flip day/hour (previous completed week)
     * and 0 after (current week). Other periods always return 0.
     */
    public function getDefaultOffset(string $period): int;
}

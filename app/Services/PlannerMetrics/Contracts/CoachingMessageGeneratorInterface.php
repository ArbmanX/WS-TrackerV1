<?php

namespace App\Services\PlannerMetrics\Contracts;

interface CoachingMessageGeneratorInterface
{
    /**
     * Generate a contextual coaching message for a planner.
     *
     * @param  array<string, mixed>  $plannerMetrics  Single planner entry from getQuotaMetrics()
     */
    public function generate(array $plannerMetrics): ?string;
}

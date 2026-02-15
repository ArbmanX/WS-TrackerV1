<?php

namespace App\Services\PlannerMetrics;

use App\Services\PlannerMetrics\Contracts\CoachingMessageGeneratorInterface;

class CoachingMessageGenerator implements CoachingMessageGeneratorInterface
{
    public function generate(array $plannerMetrics): ?string
    {
        $gapMiles = (float) ($plannerMetrics['gap_miles'] ?? 0);
        $lastWeekMiles = (float) ($plannerMetrics['last_week_miles'] ?? 0);
        $streakWeeks = (int) ($plannerMetrics['streak_weeks'] ?? 0);
        $status = $plannerMetrics['status'] ?? 'success';
        $daysSinceLastEdit = $plannerMetrics['days_since_last_edit'] ?? null;

        // Nudge — highest priority: stale edits while behind quota
        if ($gapMiles > 0 && $daysSinceLastEdit !== null && $daysSinceLastEdit >= 4) {
            return "Your last edit was {$daysSinceLastEdit} days ago. Picking up where you left off?";
        }

        // Recovery — large gap needs multi-day effort
        if ($gapMiles >= 3) {
            return "Last week you hit {$lastWeekMiles} mi. Two strong days would close the gap.";
        }

        // Encouraging — small gap, one push away
        if ($gapMiles > 0 && $gapMiles < 3) {
            return "You're {$gapMiles} mi away — a strong day gets you there.";
        }

        // Celebration — sustained streak
        if ($streakWeeks >= 3 && $status === 'success') {
            return "{$streakWeeks} weeks on target! Keep the momentum going.";
        }

        return null;
    }
}

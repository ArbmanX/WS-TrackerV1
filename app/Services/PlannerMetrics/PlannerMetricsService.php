<?php

namespace App\Services\PlannerMetrics;

use App\Models\AssessmentMonitor;
use App\Models\PlannerJobAssignment;
use App\Services\PlannerMetrics\Contracts\PlannerMetricsServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class PlannerMetricsService implements PlannerMetricsServiceInterface
{
    public function getQuotaMetrics(string $period = 'week', int $offset = 0): array
    {
        $files = $this->discoverCareerFiles();
        $quota = (float) config('planner_metrics.quota_miles_per_week');
        $gapThreshold = (float) config('planner_metrics.gap_warning_threshold', 3.0);
        $results = [];

        foreach ($files as $username => $filepath) {
            $data = $this->loadCareerFile($filepath);

            if ($data === null) {
                continue;
            }

            $assessments = $data['assessments'] ?? [];
            $displayName = $this->extractDisplayName($data, $username);
            unset($data);

            $periodMiles = $this->calculatePeriodMiles($assessments, $period, $offset);
            $quotaTarget = $this->calculateQuotaTarget($period, $quota, $offset);
            $percentComplete = $quotaTarget > 0 ? round(($periodMiles / $quotaTarget) * 100, 1) : 0;
            $gapMiles = max(0, round($quotaTarget - $periodMiles, 1));
            $streakWeeks = $this->calculateStreak($assessments, $quota);
            $lastWeekMiles = $this->calculateLastWeekMiles($assessments);
            $healthSignal = $this->resolveHealthSignal($username);

            unset($assessments);

            $status = match (true) {
                $periodMiles >= $quotaTarget => 'success',
                $gapMiles < $gapThreshold => 'warning',
                default => 'error',
            };

            $results[] = [
                'username' => $username,
                'display_name' => $displayName,
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

    public function getHealthMetrics(): array
    {
        $files = $this->discoverCareerFiles();
        $warningDays = (int) config('planner_metrics.staleness_warning_days');
        $criticalDays = (int) config('planner_metrics.staleness_critical_days');
        $results = [];

        foreach ($files as $username => $filepath) {
            $data = $this->loadCareerFile($filepath);

            if ($data === null) {
                continue;
            }

            $displayName = $this->extractDisplayName($data, $username);
            unset($data);

            $healthSignal = $this->resolveHealthSignal($username);

            $status = match (true) {
                ($healthSignal['days_since_last_edit'] !== null && $healthSignal['days_since_last_edit'] >= $criticalDays)
                    || $healthSignal['pending_over_threshold'] >= 5 => 'error',
                ($healthSignal['days_since_last_edit'] !== null && $healthSignal['days_since_last_edit'] >= $warningDays)
                    || $healthSignal['pending_over_threshold'] > 0 => 'warning',
                default => 'success',
            };

            $results[] = [
                'username' => $username,
                'display_name' => $displayName,
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
        $files = $this->discoverCareerFiles();
        $planners = [];

        foreach ($files as $username => $filepath) {
            $data = $this->loadCareerFile($filepath);

            if ($data === null) {
                continue;
            }

            $planners[] = [
                'username' => $username,
                'display_name' => $this->extractDisplayName($data, $username),
            ];

            unset($data);
        }

        return $planners;
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
        $flipDayOfWeek = $dayMap[$flipDay] ?? 2; // default Tuesday if misconfigured

        return $now->dayOfWeek < $flipDayOfWeek ? -1 : 0;
    }

    /**
     * Discover career JSON files without loading content.
     *
     * Returns [username => filepath] for the most recent file per planner.
     *
     * @return array<string, string>
     */
    private function discoverCareerFiles(): array
    {
        $path = config('planner_metrics.career_json_path');

        if (! $path || ! is_dir($path)) {
            return [];
        }

        $files = glob($path.'/*.json');

        if (! is_array($files) || empty($files)) {
            return [];
        }

        sort($files);

        // Group by username, keeping the most recent file per planner.
        // Filename format: {username}_{YYYY-MM-DD}.json — regex anchors on fixed date suffix.
        $grouped = [];
        foreach ($files as $file) {
            $basename = pathinfo($file, PATHINFO_FILENAME);

            if (! preg_match('/^(.+)_(\d{4}-\d{2}-\d{2})$/', $basename, $matches)) {
                continue;
            }

            $grouped[$matches[1]] = $file;
        }

        return $grouped;
    }

    /**
     * Load and decode a single career JSON file.
     *
     * @return array<string, mixed>|null
     */
    private function loadCareerFile(string $filepath): ?array
    {
        $content = file_get_contents($filepath);

        if ($content === false) {
            Log::warning('Failed to read career JSON file', ['path' => $filepath]);

            return null;
        }

        $decoded = json_decode($content, true);
        unset($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Malformed career JSON', ['path' => $filepath, 'error' => json_last_error_msg()]);

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Extract display name from career data.
     */
    private function extractDisplayName(array $careerData, string $fallback): string
    {
        $firstAssessment = $careerData['assessments'][0] ?? null;

        if ($firstAssessment && isset($firstAssessment['planner_username'])) {
            return str_contains($firstAssessment['planner_username'], '\\')
                ? substr($firstAssessment['planner_username'], strrpos($firstAssessment['planner_username'], '\\') + 1)
                : $firstAssessment['planner_username'];
        }

        return $fallback;
    }

    /**
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
                'circuits' => [],
            ];
        }

        $circuits = $monitors->map(function ($monitor) {
            $footage = $monitor->latest_snapshot['footage'] ?? [];

            return [
                'job_guid' => $monitor->job_guid,
                'line_name' => $monitor->line_name,
                'region' => $monitor->region,
                'total_miles' => (float) $monitor->total_miles,
                'completed_miles' => (float) ($footage['completed_miles'] ?? 0),
                'percent_complete' => (float) ($footage['percent_complete'] ?? 0),
                'permission_breakdown' => $monitor->latest_snapshot['permission_breakdown'] ?? [],
            ];
        })->values()->all();

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
            'circuits' => $circuits,
        ];
    }

    /**
     * Sum daily_footage_miles across all assessments within the date window.
     * Unified for all periods including scope-year (date-range based, not scope_year field).
     */
    private function calculatePeriodMiles(array $assessments, string $period, int $offset = 0): float
    {
        $now = CarbonImmutable::now();
        [$start, $end] = $this->getDateWindow($period, $now, $offset);

        $total = 0;
        foreach ($assessments as $assessment) {
            foreach ($assessment['daily_metrics'] ?? [] as $metric) {
                $date = $metric['completion_date'] ?? null;
                if ($date && $date >= $start && $date <= $end) {
                    $total += (float) ($metric['daily_footage_miles'] ?? 0);
                }
            }
        }

        return $total;
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
     * Count consecutive prior completed weeks (Sun–Sat) where the planner met quota.
     * Always counts backward from today, regardless of viewing offset.
     */
    private function calculateStreak(array $assessments, float $weeklyQuota): int
    {
        $streak = 0;
        $now = CarbonImmutable::now();
        $weekStartDay = (int) config('planner_metrics.week_starts_on', CarbonImmutable::SUNDAY);
        $checkStart = $now->startOfWeek($weekStartDay)->subWeek();

        while (true) {
            $weekStart = $checkStart->format('Y-m-d');
            $weekEnd = $checkStart->addDays(6)->format('Y-m-d');

            $weekMiles = 0;
            foreach ($assessments as $assessment) {
                foreach ($assessment['daily_metrics'] ?? [] as $metric) {
                    $date = $metric['completion_date'] ?? null;
                    if ($date && $date >= $weekStart && $date <= $weekEnd) {
                        $weekMiles += (float) ($metric['daily_footage_miles'] ?? 0);
                    }
                }
            }

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
     * Get the total miles for the prior completed week (Sun–Sat before current week).
     * Always relative to today, regardless of viewing offset.
     */
    private function calculateLastWeekMiles(array $assessments): float
    {
        $now = CarbonImmutable::now();
        $weekStartDay = (int) config('planner_metrics.week_starts_on', CarbonImmutable::SUNDAY);
        $lastWeekStart = $now->startOfWeek($weekStartDay)->subWeek();
        $lastWeekEnd = $lastWeekStart->addDays(6);

        $start = $lastWeekStart->format('Y-m-d');
        $end = $lastWeekEnd->format('Y-m-d');

        $total = 0;
        foreach ($assessments as $assessment) {
            foreach ($assessment['daily_metrics'] ?? [] as $metric) {
                $date = $metric['completion_date'] ?? null;
                if ($date && $date >= $start && $date <= $end) {
                    $total += (float) ($metric['daily_footage_miles'] ?? 0);
                }
            }
        }

        return $total;
    }

    /**
     * Compute the start/end date window for a given period and offset.
     *
     * offset = 0 means current period (end = today for partial data).
     * offset < 0 means past period (end = last day of that period).
     * offset > 0 is technically supported but shows future windows with no data;
     * callers should clamp to <= 0 before invoking.
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

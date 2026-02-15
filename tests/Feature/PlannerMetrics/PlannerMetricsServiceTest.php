<?php

use App\Models\AssessmentMonitor;
use App\Models\PlannerCareerEntry;
use App\Models\PlannerJobAssignment;
use App\Services\PlannerMetrics\PlannerMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PlannerMetricsService;
});

test('it returns empty array when no career entries exist', function () {
    expect($this->service->getQuotaMetrics())->toBe([])
        ->and($this->service->getHealthMetrics())->toBe([]);
});

test('it calculates weekly miles from daily_metrics JSONB', function () {
    $monday = CarbonImmutable::now()->startOfWeek();

    PlannerCareerEntry::factory()->create([
        'planner_username' => 'jsmith',
        'planner_display_name' => 'J Smith',
        'daily_metrics' => [
            ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 2.5, 'unit_count' => 10, 'stations' => []],
            ['completion_date' => $monday->addDay()->format('Y-m-d'), 'daily_footage_miles' => 3.0, 'unit_count' => 12, 'stations' => []],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result)->toHaveCount(1)
        ->and($result[0]['period_miles'])->toBe(5.5)
        ->and($result[0]['username'])->toBe('jsmith');
});

test('it calculates monthly miles aggregation', function () {
    $startOfMonth = CarbonImmutable::now()->startOfMonth();

    PlannerCareerEntry::factory()->create([
        'planner_username' => 'alice',
        'planner_display_name' => 'Alice A',
        'daily_metrics' => [
            ['completion_date' => $startOfMonth->format('Y-m-d'), 'daily_footage_miles' => 4.0, 'unit_count' => 10, 'stations' => []],
            ['completion_date' => $startOfMonth->addDays(5)->format('Y-m-d'), 'daily_footage_miles' => 3.0, 'unit_count' => 8, 'stations' => []],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('month');

    expect($result)->toHaveCount(1)
        ->and($result[0]['period_miles'])->toBe(7.0);
});

test('it computes streak count for consecutive on-target weeks', function () {
    $now = CarbonImmutable::now();
    $lastMonday = $now->startOfWeek()->subWeek();
    $twoWeeksAgo = $lastMonday->subWeek();

    PlannerCareerEntry::factory()->create([
        'planner_username' => 'streaker',
        'planner_display_name' => 'Streaker S',
        'daily_metrics' => [
            // Two weeks ago: 7.0 mi (above 6.5 quota)
            ['completion_date' => $twoWeeksAgo->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
            // Last week: 6.5 mi (exactly quota)
            ['completion_date' => $lastMonday->format('Y-m-d'), 'daily_footage_miles' => 6.5, 'unit_count' => 18, 'stations' => []],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0]['streak_weeks'])->toBe(2);
});

test('it resets streak when a week is below quota', function () {
    $now = CarbonImmutable::now();
    $lastMonday = $now->startOfWeek()->subWeek();
    $twoWeeksAgo = $lastMonday->subWeek();
    $threeWeeksAgo = $twoWeeksAgo->subWeek();

    PlannerCareerEntry::factory()->create([
        'planner_username' => 'broken',
        'planner_display_name' => 'Broken B',
        'daily_metrics' => [
            // Three weeks ago: 7.0 (above)
            ['completion_date' => $threeWeeksAgo->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
            // Two weeks ago: 2.0 (below — streak broken)
            ['completion_date' => $twoWeeksAgo->format('Y-m-d'), 'daily_footage_miles' => 2.0, 'unit_count' => 5, 'stations' => []],
            // Last week: 8.0 (above)
            ['completion_date' => $lastMonday->format('Y-m-d'), 'daily_footage_miles' => 8.0, 'unit_count' => 25, 'stations' => []],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0]['streak_weeks'])->toBe(1);
});

test('it returns planners unsorted from service', function () {
    PlannerCareerEntry::factory()->create(['planner_username' => 'charlie', 'planner_display_name' => 'Charlie C']);
    PlannerCareerEntry::factory()->create(['planner_username' => 'alice', 'planner_display_name' => 'Alice A']);
    PlannerCareerEntry::factory()->create(['planner_username' => 'bob', 'planner_display_name' => 'Bob B']);

    $result = $this->service->getQuotaMetrics();

    // Service returns based on DB ordering (planner_username), not alphabetical by display_name
    // Component handles sorting
    expect($result)->toHaveCount(3);
});

test('it includes days_since_last_edit in quota metrics return', function () {
    $monday = CarbonImmutable::now()->startOfWeek();

    PlannerCareerEntry::factory()->create([
        'planner_username' => 'editor',
        'planner_display_name' => 'Editor E',
        'daily_metrics' => [
            ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 3.0, 'unit_count' => 10, 'stations' => []],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0])->toHaveKeys([
        'username', 'display_name', 'period_miles', 'quota_target',
        'percent_complete', 'streak_weeks', 'last_week_miles',
        'days_since_last_edit', 'active_assessment_count', 'status', 'gap_miles',
    ]);
});

test('it returns health metrics from assessment_monitors via job_guid bridge', function () {
    $jobGuid = '{'.Str::uuid()->toString().'}';

    PlannerCareerEntry::factory()->create([
        'planner_username' => 'healthcheck',
        'planner_display_name' => 'Health Check',
    ]);

    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'healthcheck',
        'frstr_user' => 'ASPLUNDH\\healthcheck',
        'job_guid' => $jobGuid,
    ]);

    AssessmentMonitor::factory()->withSnapshots(3)->create([
        'job_guid' => $jobGuid,
        'current_status' => 'ACTIV',
    ]);

    $result = $this->service->getHealthMetrics();

    expect($result)->toHaveCount(1)
        ->and($result[0]['username'])->toBe('healthcheck')
        ->and($result[0]['active_assessment_count'])->toBe(1)
        ->and($result[0])->toHaveKeys([
            'days_since_last_edit', 'pending_over_threshold',
            'permission_breakdown', 'total_miles', 'percent_complete',
        ]);
});

test('it aggregates health metrics across multiple active assessments per planner', function () {
    $guid1 = '{'.Str::uuid()->toString().'}';
    $guid2 = '{'.Str::uuid()->toString().'}';

    PlannerCareerEntry::factory()->create([
        'planner_username' => 'multi',
        'planner_display_name' => 'Multi M',
    ]);

    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'multi',
        'frstr_user' => 'ASPLUNDH\\multi',
        'job_guid' => $guid1,
    ]);
    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'multi',
        'frstr_user' => 'ASPLUNDH\\multi',
        'job_guid' => $guid2,
    ]);

    AssessmentMonitor::factory()->withSnapshots(1)->create([
        'job_guid' => $guid1,
        'current_status' => 'ACTIV',
    ]);
    AssessmentMonitor::factory()->withSnapshots(1)->create([
        'job_guid' => $guid2,
        'current_status' => 'ACTIV',
    ]);

    $result = $this->service->getHealthMetrics();

    expect($result[0]['active_assessment_count'])->toBe(2);
});

test('it uses forNormalizedUser scope for username bridge', function () {
    $guid = '{'.Str::uuid()->toString().'}';

    PlannerCareerEntry::factory()->create([
        'planner_username' => 'scopetest',
        'planner_display_name' => 'Scope Test',
    ]);

    // Only the normalized_username match should work
    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'scopetest',
        'frstr_user' => 'ASPLUNDH\\scopetest',
        'job_guid' => $guid,
    ]);

    // This one has different normalized_username — should NOT match
    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'otheruser',
        'frstr_user' => 'ASPLUNDH\\otheruser',
        'job_guid' => '{'.Str::uuid()->toString().'}',
    ]);

    AssessmentMonitor::factory()->withSnapshots(1)->create([
        'job_guid' => $guid,
        'current_status' => 'ACTIV',
    ]);

    $result = $this->service->getHealthMetrics();

    expect($result[0]['active_assessment_count'])->toBe(1);
});

test('it returns success/warning/error status based on thresholds', function () {
    $monday = CarbonImmutable::now()->startOfWeek();

    // Success — meets quota
    PlannerCareerEntry::factory()->create([
        'planner_username' => 'onpace',
        'planner_display_name' => 'On Pace',
        'daily_metrics' => [
            ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
        ],
    ]);

    // Warning — small gap
    PlannerCareerEntry::factory()->create([
        'planner_username' => 'behind',
        'planner_display_name' => 'Behind B',
        'daily_metrics' => [
            ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 5.0, 'unit_count' => 14, 'stations' => []],
        ],
    ]);

    // Error — large gap (>= 3 mi)
    PlannerCareerEntry::factory()->create([
        'planner_username' => 'wayout',
        'planner_display_name' => 'Way Out',
        'daily_metrics' => [
            ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 1.0, 'unit_count' => 3, 'stations' => []],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');
    $indexed = collect($result)->keyBy('username');

    expect($indexed['onpace']['status'])->toBe('success')
        ->and($indexed['behind']['status'])->toBe('warning')
        ->and($indexed['wayout']['status'])->toBe('error');
});

test('it handles planner with zero active assessments in health view', function () {
    PlannerCareerEntry::factory()->create([
        'planner_username' => 'noassess',
        'planner_display_name' => 'No Assess',
    ]);

    $result = $this->service->getHealthMetrics();

    expect($result[0]['days_since_last_edit'])->toBeNull()
        ->and($result[0]['active_assessment_count'])->toBe(0)
        ->and($result[0]['status'])->toBe('success');
});

test('it respects config values for quota target and thresholds', function () {
    config(['planner_metrics.quota_miles_per_week' => 10.0]);

    $monday = CarbonImmutable::now()->startOfWeek();

    PlannerCareerEntry::factory()->create([
        'planner_username' => 'configtest',
        'planner_display_name' => 'Config Test',
        'daily_metrics' => [
            ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    // With 10.0 quota, 7.0 mi means gap = 3.0 → error status
    expect($result[0]['quota_target'])->toBe(10.0)
        ->and($result[0]['gap_miles'])->toBe(3.0)
        ->and($result[0]['status'])->toBe('error');
});

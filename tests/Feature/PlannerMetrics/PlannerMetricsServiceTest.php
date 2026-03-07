<?php

use App\Models\Assessment;
use App\Models\AssessmentMetric;
use App\Models\PlannerDailyRecord;
use App\Models\PlannerJobAssignment;
use App\Services\PlannerMetrics\PlannerMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Freeze to Wednesday, February 11, 2026 at noon UTC.
    // With Sunday-start weeks: current week = Sun Feb 8 – (today) Wed Feb 11.
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 2, 11, 12, 0, 0, 'UTC'));

    $this->service = new PlannerMetricsService;
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * Create a planner with job assignments and daily records.
 */
function createPlanner(string $username, array $dailyRecords = [], array $jobGuids = []): void
{
    $frstrUser = 'ASPLUNDH\\'.$username;

    if (empty($jobGuids)) {
        $jobGuids = ['{'.Str::uuid()->toString().'}'];
    }

    foreach ($jobGuids as $guid) {
        PlannerJobAssignment::factory()->create([
            'frstr_user' => $frstrUser,
            'normalized_username' => $username,
            'job_guid' => $guid,
        ]);
    }

    foreach ($dailyRecords as $record) {
        PlannerDailyRecord::create([
            'job_guid' => $record['job_guid'] ?? $jobGuids[0],
            'frstr_user' => $frstrUser,
            'work_order' => $record['work_order'] ?? fake()->numerify('WO-######'),
            'extension' => '@',
            'assess_date' => $record['date'],
            'stat_name' => $record['stat_name'] ?? fake()->numerify('STA-###'),
            'sequence' => $record['sequence'] ?? fake()->numberBetween(1, 999),
            'unit_guid' => '{'.Str::uuid()->toString().'}',
            'unit' => 'SPM',
            'span_miles' => $record['miles'],
            'last_synced_at' => now(),
        ]);
    }
}

/**
 * Create an active assessment with metrics for a given job_guid.
 */
function createActiveAssessment(string $jobGuid, array $overrides = [], array $metricOverrides = []): void
{
    Assessment::factory()->create(array_merge([
        'job_guid' => $jobGuid,
        'status' => 'ACTIV',
    ], $overrides));

    AssessmentMetric::factory()->create(array_merge([
        'job_guid' => $jobGuid,
    ], $metricOverrides));
}

// ─── Basic tests ─────────────────────────────────────────────────────────────

test('it returns empty array when no planners exist', function () {
    expect($this->service->getQuotaMetrics())->toBe([])
        ->and($this->service->getHealthMetrics())->toBe([]);
});

test('it calculates weekly miles from planner_daily_records', function () {
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY); // Sun Feb 8

    createPlanner('jsmith', [
        ['date' => $weekStart->format('Y-m-d'), 'miles' => 2.5],
        ['date' => $weekStart->addDay()->format('Y-m-d'), 'miles' => 3.0],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result)->toHaveCount(1)
        ->and($result[0]['period_miles'])->toBe(5.5)
        ->and($result[0]['username'])->toBe('ASPLUNDH\\jsmith');
});

test('it calculates monthly miles aggregation', function () {
    $startOfMonth = CarbonImmutable::now()->startOfMonth(); // Feb 1

    createPlanner('alice', [
        ['date' => $startOfMonth->format('Y-m-d'), 'miles' => 4.0],
        ['date' => $startOfMonth->addDays(5)->format('Y-m-d'), 'miles' => 3.0],
    ]);

    $result = $this->service->getQuotaMetrics('month');

    expect($result)->toHaveCount(1)
        ->and($result[0]['period_miles'])->toBe(7.0);
});

test('it computes streak count for consecutive on-target weeks', function () {
    $now = CarbonImmutable::now();
    $lastWeekStart = $now->startOfWeek(CarbonImmutable::SUNDAY)->subWeek(); // Sun Feb 1
    $twoWeeksAgo = $lastWeekStart->subWeek(); // Sun Jan 25

    createPlanner('streaker', [
        ['date' => $twoWeeksAgo->format('Y-m-d'), 'miles' => 7.0],
        ['date' => $lastWeekStart->format('Y-m-d'), 'miles' => 6.5],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0]['streak_weeks'])->toBe(2);
});

test('it resets streak when a week is below quota', function () {
    $now = CarbonImmutable::now();
    $lastWeekStart = $now->startOfWeek(CarbonImmutable::SUNDAY)->subWeek();
    $twoWeeksAgo = $lastWeekStart->subWeek();
    $threeWeeksAgo = $twoWeeksAgo->subWeek();

    createPlanner('broken', [
        ['date' => $threeWeeksAgo->format('Y-m-d'), 'miles' => 7.0],
        ['date' => $twoWeeksAgo->format('Y-m-d'), 'miles' => 2.0],
        ['date' => $lastWeekStart->format('Y-m-d'), 'miles' => 8.0],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0]['streak_weeks'])->toBe(1);
});

test('it returns planners from job assignments', function () {
    createPlanner('charlie');
    createPlanner('alice');
    createPlanner('bob');

    $result = $this->service->getQuotaMetrics();

    expect($result)->toHaveCount(3);
});

test('it includes all expected keys in quota metrics return', function () {
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    createPlanner('editor', [
        ['date' => $weekStart->format('Y-m-d'), 'miles' => 3.0],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0])->toHaveKeys([
        'username', 'display_name', 'period_miles', 'quota_target',
        'percent_complete', 'streak_weeks', 'last_week_miles',
        'days_since_last_edit', 'active_assessment_count', 'status', 'gap_miles',
    ]);
});

test('it returns health metrics from assessments via job_guid bridge', function () {
    $jobGuid = '{'.Str::uuid()->toString().'}';

    createPlanner('healthcheck', [], [$jobGuid]);
    createActiveAssessment($jobGuid, [
        'raw_title' => 'Circuit-1234',
        'region' => 'NORTH',
        'length' => 16093.44,
        'length_completed' => 8046.72,
        'percent_complete' => 50,
        'last_edited' => now()->subDays(3),
    ]);

    $result = $this->service->getHealthMetrics();

    expect($result)->toHaveCount(1)
        ->and($result[0]['username'])->toBe('ASPLUNDH\\healthcheck')
        ->and($result[0]['active_assessment_count'])->toBe(1)
        ->and($result[0])->toHaveKeys([
            'days_since_last_edit', 'pending_over_threshold',
            'permission_breakdown', 'total_miles', 'percent_complete',
        ]);
});

test('it aggregates health metrics across multiple active assessments per planner', function () {
    $guid1 = '{'.Str::uuid()->toString().'}';
    $guid2 = '{'.Str::uuid()->toString().'}';

    createPlanner('multi', [], [$guid1, $guid2]);
    createActiveAssessment($guid1);
    createActiveAssessment($guid2);

    $result = $this->service->getHealthMetrics();

    expect($result[0]['active_assessment_count'])->toBe(2);
});

test('it uses forUser scope for username bridge', function () {
    $guid = '{'.Str::uuid()->toString().'}';

    createPlanner('scopetest', [], [$guid]);
    createPlanner('otheruser');
    createActiveAssessment($guid);

    $result = $this->service->getHealthMetrics();

    $indexed = collect($result)->keyBy('username');

    expect($indexed['ASPLUNDH\\scopetest']['active_assessment_count'])->toBe(1)
        ->and($indexed['ASPLUNDH\\otheruser']['active_assessment_count'])->toBe(0);
});

test('it returns success/warning/error status based on thresholds', function () {
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    createPlanner('onpace', [
        ['date' => $weekStart->format('Y-m-d'), 'miles' => 7.0],
    ]);
    createPlanner('behind', [
        ['date' => $weekStart->format('Y-m-d'), 'miles' => 5.0],
    ]);
    createPlanner('wayout', [
        ['date' => $weekStart->format('Y-m-d'), 'miles' => 1.0],
    ]);

    $result = $this->service->getQuotaMetrics('week');
    $indexed = collect($result)->keyBy('username');

    expect($indexed['ASPLUNDH\\onpace']['status'])->toBe('success')
        ->and($indexed['ASPLUNDH\\behind']['status'])->toBe('warning')
        ->and($indexed['ASPLUNDH\\wayout']['status'])->toBe('error');
});

test('it handles planner with zero active assessments in health view', function () {
    createPlanner('noassess');

    $result = $this->service->getHealthMetrics();

    expect($result[0]['days_since_last_edit'])->toBeNull()
        ->and($result[0]['active_assessment_count'])->toBe(0)
        ->and($result[0]['status'])->toBe('success');
});

test('it respects config values for quota target and thresholds', function () {
    config(['planner_metrics.quota_miles_per_week' => 10.0]);

    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    createPlanner('configtest', [
        ['date' => $weekStart->format('Y-m-d'), 'miles' => 7.0],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0]['quota_target'])->toBe(10.0)
        ->and($result[0]['gap_miles'])->toBe(3.0)
        ->and($result[0]['status'])->toBe('error');
});

test('it strips domain prefix for display_name', function () {
    createPlanner('tgibson');

    $planners = $this->service->getDistinctPlanners();

    expect($planners[0]['display_name'])->toBe('tgibson');
});

test('it handles planner with no daily records', function () {
    createPlanner('nodata');

    $planners = $this->service->getDistinctPlanners();

    expect($planners)->toHaveCount(1)
        ->and($planners[0]['username'])->toBe('ASPLUNDH\\nodata')
        ->and($planners[0]['display_name'])->toBe('nodata');
});

test('it returns empty when no job assignments exist', function () {
    expect($this->service->getQuotaMetrics())->toBe([])
        ->and($this->service->getDistinctPlanners())->toBe([]);
});

// ─── Offset navigation tests ────────────────────────────────────────────────

test('it returns previous week data when offset is -1', function () {
    createPlanner('nav', [
        // Previous week data (Feb 1 and Feb 5)
        ['date' => '2026-02-01', 'miles' => 3.0],
        ['date' => '2026-02-05', 'miles' => 4.0],
        // Current week data (Feb 8) — should NOT be included
        ['date' => '2026-02-08', 'miles' => 99.0],
    ]);

    $result = $this->service->getQuotaMetrics('week', -1);

    expect($result[0]['period_miles'])->toBe(7.0);
});

test('it returns two weeks ago data when offset is -2', function () {
    createPlanner('nav2', [
        ['date' => '2026-01-27', 'miles' => 5.5],
        // Previous week data — should NOT be included
        ['date' => '2026-02-03', 'miles' => 99.0],
    ]);

    $result = $this->service->getQuotaMetrics('week', -2);

    expect($result[0]['period_miles'])->toBe(5.5);
});

test('it includes Saturday data for past weeks (full Sun-Sat range)', function () {
    createPlanner('sattest', [
        ['date' => '2026-02-07', 'miles' => 2.0],
        // Sunday (start of NEXT week) — excluded from offset -1
        ['date' => '2026-02-08', 'miles' => 99.0],
    ]);

    $result = $this->service->getQuotaMetrics('week', -1);

    expect($result[0]['period_miles'])->toBe(2.0);
});

test('it shows partial week for current week offset 0', function () {
    createPlanner('partial', [
        ['date' => '2026-02-08', 'miles' => 1.0],
        ['date' => '2026-02-10', 'miles' => 2.0],
        ['date' => '2026-02-11', 'miles' => 1.5],
        // Future data (Feb 12) — should NOT be included
        ['date' => '2026-02-12', 'miles' => 99.0],
    ]);

    $result = $this->service->getQuotaMetrics('week', 0);

    expect($result[0]['period_miles'])->toBe(4.5);
});

// ─── Default offset (auto-flip) tests ────────────────────────────────────────

test('it defaults to previous week on Sunday', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 2, 8, 10, 0, 0, 'America/New_York'));

    expect($this->service->getDefaultOffset('week'))->toBe(-1);
});

test('it defaults to previous week on Monday', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 2, 9, 10, 0, 0, 'America/New_York'));

    expect($this->service->getDefaultOffset('week'))->toBe(-1);
});

test('it defaults to previous week on Tuesday before 5 PM ET', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 2, 10, 16, 59, 0, 'America/New_York'));

    expect($this->service->getDefaultOffset('week'))->toBe(-1);
});

test('it defaults to current week on Tuesday at 5 PM ET', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 2, 10, 17, 0, 0, 'America/New_York'));

    expect($this->service->getDefaultOffset('week'))->toBe(0);
});

test('it defaults to current week on Wednesday', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 2, 11, 10, 0, 0, 'America/New_York'));

    expect($this->service->getDefaultOffset('week'))->toBe(0);
});

test('it always returns 0 offset for non-week periods', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 2, 9, 10, 0, 0, 'America/New_York'));

    expect($this->service->getDefaultOffset('month'))->toBe(0)
        ->and($this->service->getDefaultOffset('year'))->toBe(0)
        ->and($this->service->getDefaultOffset('scope-year'))->toBe(0);
});

// ─── Scope-year (fiscal year Jul 1 – Jun 30) tests ──────────────────────────

test('it calculates scope-year from July 1 to June 30', function () {
    createPlanner('fiscal', [
        // Aug 2025 — within fiscal year
        ['date' => '2025-08-15', 'miles' => 3.0],
        // Dec 2025 — within fiscal year
        ['date' => '2025-12-10', 'miles' => 2.0],
        // Feb 2026 — within fiscal year
        ['date' => '2026-02-05', 'miles' => 4.0],
        // Jun 2025 — BEFORE fiscal year, should NOT be included
        ['date' => '2025-06-15', 'miles' => 99.0],
    ]);

    $result = $this->service->getQuotaMetrics('scope-year', 0);

    expect($result[0]['period_miles'])->toBe(9.0);
});

test('it shifts scope-year with offset', function () {
    createPlanner('pastfiscal', [
        // Oct 2024 — within previous fiscal year
        ['date' => '2024-10-20', 'miles' => 5.0],
        // Mar 2025 — within previous fiscal year
        ['date' => '2025-03-12', 'miles' => 3.0],
        // Aug 2025 — CURRENT fiscal year, NOT included in offset -1
        ['date' => '2025-08-15', 'miles' => 99.0],
    ]);

    $result = $this->service->getQuotaMetrics('scope-year', -1);

    expect($result[0]['period_miles'])->toBe(8.0);
});

// ─── Period label tests ──────────────────────────────────────────────────────

test('it generates correct period label for same-year range', function () {
    expect($this->service->getPeriodLabel('week', 0))->toBe('Feb 8 – Feb 11, 2026');
});

test('it generates full week label for past weeks', function () {
    expect($this->service->getPeriodLabel('week', -1))->toBe('Feb 1 – Feb 7, 2026');
});

test('it generates cross-year period label when range spans years', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 1, 2, 12, 0, 0, 'UTC'));

    expect($this->service->getPeriodLabel('week', 0))->toBe('Dec 28, 2025 – Jan 2, 2026');
});

test('it generates month period label', function () {
    expect($this->service->getPeriodLabel('month', 0))->toBe('Feb 1 – Feb 11, 2026');
});

test('it generates full month label for past months', function () {
    expect($this->service->getPeriodLabel('month', -1))->toBe('Jan 1 – Jan 31, 2026');
});

test('it generates scope-year period label', function () {
    expect($this->service->getPeriodLabel('scope-year', 0))->toBe('Jul 1, 2025 – Feb 11, 2026');
});

// ─── Circuit drawer data tests ───────────────────────────────────────────────

test('resolveHealthSignal returns circuits key with correct count', function () {
    $guid1 = '{'.Str::uuid()->toString().'}';
    $guid2 = '{'.Str::uuid()->toString().'}';

    createPlanner('drawer', [], [$guid1, $guid2]);

    createActiveAssessment($guid1, [
        'raw_title' => 'Circuit-1234',
        'region' => 'NORTH',
    ]);
    createActiveAssessment($guid2, [
        'raw_title' => 'Circuit-5678',
        'region' => 'SOUTH',
    ]);

    $result = $this->service->getHealthMetrics();

    expect($result[0])->toHaveKey('circuits')
        ->and($result[0]['circuits'])->toHaveCount(2);
});

test('each circuit has expected keys', function () {
    $guid = '{'.Str::uuid()->toString().'}';

    createPlanner('keys', [], [$guid]);
    createActiveAssessment($guid, [
        'raw_title' => 'Circuit-9999',
        'region' => 'EAST',
        'length' => 24944.832, // ~15.5 miles
    ]);

    $result = $this->service->getHealthMetrics();
    $circuit = $result[0]['circuits'][0];

    expect($circuit)->toHaveKeys([
        'job_guid', 'line_name', 'region', 'total_miles',
        'completed_miles', 'percent_complete', 'permission_breakdown',
    ])
        ->and($circuit['line_name'])->toBe('Circuit-9999')
        ->and($circuit['region'])->toBe('EAST')
        ->and($circuit['total_miles'])->toBe(15.5);
});

test('circuits array is empty when no active assessments exist', function () {
    createPlanner('nocircuit');

    $result = $this->service->getHealthMetrics();

    expect($result[0]['circuits'])->toBe([]);
});

test('circuits handles null metrics gracefully', function () {
    $guid = '{'.Str::uuid()->toString().'}';

    createPlanner('nullmetrics', [], [$guid]);

    // Create assessment WITHOUT metrics
    Assessment::factory()->create([
        'job_guid' => $guid,
        'status' => 'ACTIV',
        'length' => 16093.44,
        'length_completed' => 0,
        'percent_complete' => 0,
    ]);

    $result = $this->service->getHealthMetrics();
    $circuit = $result[0]['circuits'][0];

    expect($circuit['completed_miles'])->toBe(0.0)
        ->and($circuit['percent_complete'])->toBe(0.0)
        ->and($circuit['permission_breakdown'])->toBe([]);
});

// ─── Unified metrics tests ──────────────────────────────────────────────────

test('getUnifiedMetrics returns all expected keys', function () {
    $guid = '{'.Str::uuid()->toString().'}';
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    createPlanner('unified', [
        ['date' => $weekStart->format('Y-m-d'), 'miles' => 4.0],
    ], [$guid]);

    createActiveAssessment($guid);

    $result = $this->service->getUnifiedMetrics();

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveKeys([
            'username', 'display_name',
            'period_miles', 'quota_target', 'quota_percent', 'streak_weeks', 'gap_miles',
            'days_since_last_edit', 'pending_over_threshold', 'permission_breakdown',
            'total_miles', 'overall_percent', 'active_assessment_count',
            'status', 'circuits',
        ]);
});

test('getUnifiedMetrics uses quota_percent not percent_complete', function () {
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    createPlanner('namechk', [
        ['date' => $weekStart->format('Y-m-d'), 'miles' => 3.25],
    ]);

    $result = $this->service->getUnifiedMetrics();

    expect($result[0])->toHaveKey('quota_percent')
        ->and($result[0])->toHaveKey('overall_percent')
        ->and($result[0])->not->toHaveKey('percent_complete')
        ->and($result[0]['quota_percent'])->toBe(50.0); // 3.25 / 6.5 = 50%
});

test('getUnifiedMetrics respects offset for week navigation', function () {
    createPlanner('offnav', [
        ['date' => '2026-02-03', 'miles' => 5.0],
        // Current week — excluded at offset -1
        ['date' => '2026-02-09', 'miles' => 99.0],
    ]);

    $result = $this->service->getUnifiedMetrics(-1);

    expect($result[0]['period_miles'])->toBe(5.0);
});

test('getUnifiedMetrics returns empty when no planners', function () {
    expect($this->service->getUnifiedMetrics())->toBe([]);
});

test('circuits are included in quota metrics return', function () {
    $guid = '{'.Str::uuid()->toString().'}';
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    createPlanner('quotacircuit', [
        ['date' => $weekStart->format('Y-m-d'), 'miles' => 3.0],
    ], [$guid]);

    createActiveAssessment($guid);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0])->toHaveKey('circuits')
        ->and($result[0]['circuits'])->toHaveCount(1);
});

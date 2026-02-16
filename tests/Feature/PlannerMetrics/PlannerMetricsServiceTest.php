<?php

use App\Models\AssessmentMonitor;
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
    $this->fixtureDir = sys_get_temp_dir().'/career_test_'.Str::random(8);
    mkdir($this->fixtureDir, 0755, true);
    config()->set('planner_metrics.career_json_path', $this->fixtureDir);
});

afterEach(function () {
    CarbonImmutable::setTestNow();

    if (is_dir($this->fixtureDir)) {
        array_map('unlink', glob($this->fixtureDir.'/*.json'));
        rmdir($this->fixtureDir);
    }
});

function writeCareerFixture(string $dir, string $username, array $assessments, string $date = '2026-02-15'): void
{
    $data = [
        'career_timeframe' => '1yrs 0months 0days',
        'total_career_miles' => 100.0,
        'assessment_count' => count($assessments),
        'total_career_unit_count' => 50,
        'assessments' => $assessments,
    ];

    file_put_contents(
        $dir.'/'.$username.'_'.$date.'.json',
        json_encode($data, JSON_PRETTY_PRINT)
    );
}

// ─── Existing tests (updated for Sunday–Saturday boundaries) ─────────────────

test('it returns empty array when no career JSON files exist', function () {
    expect($this->service->getQuotaMetrics())->toBe([])
        ->and($this->service->getHealthMetrics())->toBe([]);
});

test('it calculates weekly miles from daily_metrics in JSON', function () {
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY); // Sun Feb 8

    writeCareerFixture($this->fixtureDir, 'jsmith', [
        [
            'planner_username' => 'ASPLUNDH\\jsmith',
            'job_guid' => '{11111111-1111-1111-1111-111111111111}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 2.5, 'unit_count' => 10, 'stations' => []],
                ['completion_date' => $weekStart->addDay()->format('Y-m-d'), 'daily_footage_miles' => 3.0, 'unit_count' => 12, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result)->toHaveCount(1)
        ->and($result[0]['period_miles'])->toBe(5.5)
        ->and($result[0]['username'])->toBe('jsmith');
});

test('it calculates monthly miles aggregation', function () {
    $startOfMonth = CarbonImmutable::now()->startOfMonth(); // Feb 1

    writeCareerFixture($this->fixtureDir, 'alice', [
        [
            'planner_username' => 'ASPLUNDH\\alice',
            'job_guid' => '{22222222-2222-2222-2222-222222222222}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $startOfMonth->format('Y-m-d'), 'daily_footage_miles' => 4.0, 'unit_count' => 10, 'stations' => []],
                ['completion_date' => $startOfMonth->addDays(5)->format('Y-m-d'), 'daily_footage_miles' => 3.0, 'unit_count' => 8, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('month');

    expect($result)->toHaveCount(1)
        ->and($result[0]['period_miles'])->toBe(7.0);
});

test('it computes streak count for consecutive on-target weeks', function () {
    $now = CarbonImmutable::now();
    $lastWeekStart = $now->startOfWeek(CarbonImmutable::SUNDAY)->subWeek(); // Sun Feb 1
    $twoWeeksAgo = $lastWeekStart->subWeek(); // Sun Jan 25

    writeCareerFixture($this->fixtureDir, 'streaker', [
        [
            'planner_username' => 'ASPLUNDH\\streaker',
            'job_guid' => '{33333333-3333-3333-3333-333333333333}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $twoWeeksAgo->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
                ['completion_date' => $lastWeekStart->format('Y-m-d'), 'daily_footage_miles' => 6.5, 'unit_count' => 18, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0]['streak_weeks'])->toBe(2);
});

test('it resets streak when a week is below quota', function () {
    $now = CarbonImmutable::now();
    $lastWeekStart = $now->startOfWeek(CarbonImmutable::SUNDAY)->subWeek(); // Sun Feb 1
    $twoWeeksAgo = $lastWeekStart->subWeek(); // Sun Jan 25
    $threeWeeksAgo = $twoWeeksAgo->subWeek(); // Sun Jan 18

    writeCareerFixture($this->fixtureDir, 'broken', [
        [
            'planner_username' => 'ASPLUNDH\\broken',
            'job_guid' => '{44444444-4444-4444-4444-444444444444}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $threeWeeksAgo->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
                ['completion_date' => $twoWeeksAgo->format('Y-m-d'), 'daily_footage_miles' => 2.0, 'unit_count' => 5, 'stations' => []],
                ['completion_date' => $lastWeekStart->format('Y-m-d'), 'daily_footage_miles' => 8.0, 'unit_count' => 25, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0]['streak_weeks'])->toBe(1);
});

test('it returns planners from JSON files', function () {
    writeCareerFixture($this->fixtureDir, 'charlie', [
        ['planner_username' => 'ASPLUNDH\\charlie', 'job_guid' => '{55555555-5555-5555-5555-555555555555}', 'scope_year' => 2026, 'daily_metrics' => []],
    ]);
    writeCareerFixture($this->fixtureDir, 'alice', [
        ['planner_username' => 'ASPLUNDH\\alice', 'job_guid' => '{66666666-6666-6666-6666-666666666666}', 'scope_year' => 2026, 'daily_metrics' => []],
    ]);
    writeCareerFixture($this->fixtureDir, 'bob', [
        ['planner_username' => 'ASPLUNDH\\bob', 'job_guid' => '{77777777-7777-7777-7777-777777777777}', 'scope_year' => 2026, 'daily_metrics' => []],
    ]);

    $result = $this->service->getQuotaMetrics();

    expect($result)->toHaveCount(3);
});

test('it includes all expected keys in quota metrics return', function () {
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY); // Sun Feb 8

    writeCareerFixture($this->fixtureDir, 'editor', [
        [
            'planner_username' => 'ASPLUNDH\\editor',
            'job_guid' => '{88888888-8888-8888-8888-888888888888}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 3.0, 'unit_count' => 10, 'stations' => []],
            ],
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

    writeCareerFixture($this->fixtureDir, 'healthcheck', [
        ['planner_username' => 'ASPLUNDH\\healthcheck', 'job_guid' => $jobGuid, 'scope_year' => 2026, 'daily_metrics' => []],
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

    writeCareerFixture($this->fixtureDir, 'multi', [
        ['planner_username' => 'ASPLUNDH\\multi', 'job_guid' => $guid1, 'scope_year' => 2026, 'daily_metrics' => []],
        ['planner_username' => 'ASPLUNDH\\multi', 'job_guid' => $guid2, 'scope_year' => 2026, 'daily_metrics' => []],
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

    writeCareerFixture($this->fixtureDir, 'scopetest', [
        ['planner_username' => 'ASPLUNDH\\scopetest', 'job_guid' => $guid, 'scope_year' => 2026, 'daily_metrics' => []],
    ]);

    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'scopetest',
        'frstr_user' => 'ASPLUNDH\\scopetest',
        'job_guid' => $guid,
    ]);

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
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY); // Sun Feb 8

    writeCareerFixture($this->fixtureDir, 'onpace', [
        [
            'planner_username' => 'ASPLUNDH\\onpace',
            'job_guid' => '{AAAA1111-1111-1111-1111-111111111111}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
            ],
        ],
    ]);

    writeCareerFixture($this->fixtureDir, 'behind', [
        [
            'planner_username' => 'ASPLUNDH\\behind',
            'job_guid' => '{BBBB2222-2222-2222-2222-222222222222}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 5.0, 'unit_count' => 14, 'stations' => []],
            ],
        ],
    ]);

    writeCareerFixture($this->fixtureDir, 'wayout', [
        [
            'planner_username' => 'ASPLUNDH\\wayout',
            'job_guid' => '{CCCC3333-3333-3333-3333-333333333333}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 1.0, 'unit_count' => 3, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');
    $indexed = collect($result)->keyBy('username');

    expect($indexed['onpace']['status'])->toBe('success')
        ->and($indexed['behind']['status'])->toBe('warning')
        ->and($indexed['wayout']['status'])->toBe('error');
});

test('it handles planner with zero active assessments in health view', function () {
    writeCareerFixture($this->fixtureDir, 'noassess', [
        ['planner_username' => 'ASPLUNDH\\noassess', 'job_guid' => '{DDDD4444-4444-4444-4444-444444444444}', 'scope_year' => 2026, 'daily_metrics' => []],
    ]);

    $result = $this->service->getHealthMetrics();

    expect($result[0]['days_since_last_edit'])->toBeNull()
        ->and($result[0]['active_assessment_count'])->toBe(0)
        ->and($result[0]['status'])->toBe('success');
});

test('it respects config values for quota target and thresholds', function () {
    config(['planner_metrics.quota_miles_per_week' => 10.0]);

    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    writeCareerFixture($this->fixtureDir, 'configtest', [
        [
            'planner_username' => 'ASPLUNDH\\configtest',
            'job_guid' => '{EEEE5555-5555-5555-5555-555555555555}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0]['quota_target'])->toBe(10.0)
        ->and($result[0]['gap_miles'])->toBe(3.0)
        ->and($result[0]['status'])->toBe('error');
});

test('it picks the most recent JSON file per planner', function () {
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    writeCareerFixture($this->fixtureDir, 'jsmith', [
        [
            'planner_username' => 'ASPLUNDH\\jsmith',
            'job_guid' => '{FFFF6666-6666-6666-6666-666666666666}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 1.0, 'unit_count' => 5, 'stations' => []],
            ],
        ],
    ], '2026-02-10');

    writeCareerFixture($this->fixtureDir, 'jsmith', [
        [
            'planner_username' => 'ASPLUNDH\\jsmith',
            'job_guid' => '{FFFF7777-7777-7777-7777-777777777777}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 5.0, 'unit_count' => 15, 'stations' => []],
            ],
        ],
    ], '2026-02-15');

    $result = $this->service->getQuotaMetrics('week');

    expect($result)->toHaveCount(1)
        ->and($result[0]['period_miles'])->toBe(5.0);
});

test('it strips domain prefix for display_name', function () {
    writeCareerFixture($this->fixtureDir, 'tgibson', [
        ['planner_username' => 'ASPLUNDH\\tgibson', 'job_guid' => '{AAAA8888-8888-8888-8888-888888888888}', 'scope_year' => 2026, 'daily_metrics' => []],
    ]);

    $planners = $this->service->getDistinctPlanners();

    expect($planners[0]['display_name'])->toBe('tgibson');
});

test('it handles planner with empty assessments array', function () {
    $data = [
        'career_timeframe' => null,
        'total_career_miles' => 0,
        'assessment_count' => 0,
        'total_career_unit_count' => 0,
        'assessments' => [],
    ];

    file_put_contents(
        $this->fixtureDir.'/nodata_2026-02-15.json',
        json_encode($data, JSON_PRETTY_PRINT)
    );

    $planners = $this->service->getDistinctPlanners();

    expect($planners)->toHaveCount(1)
        ->and($planners[0]['username'])->toBe('nodata')
        ->and($planners[0]['display_name'])->toBe('nodata');
});

test('it handles underscored usernames in filenames', function () {
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    writeCareerFixture($this->fixtureDir, 'eci_chris', [
        [
            'planner_username' => 'ASPLUNDH\\eci chris',
            'job_guid' => '{AAAA9999-9999-9999-9999-999999999999}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 4.0, 'unit_count' => 10, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result)->toHaveCount(1)
        ->and($result[0]['username'])->toBe('eci_chris')
        ->and($result[0]['display_name'])->toBe('eci chris')
        ->and($result[0]['period_miles'])->toBe(4.0);
});

test('it returns empty when directory does not exist', function () {
    config()->set('planner_metrics.career_json_path', '/tmp/nonexistent_'.Str::random(16));

    expect($this->service->getQuotaMetrics())->toBe([])
        ->and($this->service->getDistinctPlanners())->toBe([]);
});

// ─── Offset navigation tests ────────────────────────────────────────────────

test('it returns previous week data when offset is -1', function () {
    // Frozen: Wed Feb 11. Previous week: Sun Feb 1 – Sat Feb 7.
    writeCareerFixture($this->fixtureDir, 'nav', [
        [
            'planner_username' => 'ASPLUNDH\\nav',
            'job_guid' => '{11111111-1111-1111-1111-111111111111}',
            'scope_year' => 2026,
            'daily_metrics' => [
                // Previous week data (Feb 1 and Feb 5)
                ['completion_date' => '2026-02-01', 'daily_footage_miles' => 3.0, 'unit_count' => 10, 'stations' => []],
                ['completion_date' => '2026-02-05', 'daily_footage_miles' => 4.0, 'unit_count' => 12, 'stations' => []],
                // Current week data (Feb 8) — should NOT be included
                ['completion_date' => '2026-02-08', 'daily_footage_miles' => 99.0, 'unit_count' => 50, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week', -1);

    expect($result[0]['period_miles'])->toBe(7.0);
});

test('it returns two weeks ago data when offset is -2', function () {
    // Frozen: Wed Feb 11. Two weeks ago: Sun Jan 25 – Sat Jan 31.
    writeCareerFixture($this->fixtureDir, 'nav2', [
        [
            'planner_username' => 'ASPLUNDH\\nav2',
            'job_guid' => '{22222222-2222-2222-2222-222222222222}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => '2026-01-27', 'daily_footage_miles' => 5.5, 'unit_count' => 15, 'stations' => []],
                // Previous week data — should NOT be included
                ['completion_date' => '2026-02-03', 'daily_footage_miles' => 99.0, 'unit_count' => 50, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week', -2);

    expect($result[0]['period_miles'])->toBe(5.5);
});

test('it includes Saturday data for past weeks (full Sun-Sat range)', function () {
    // Previous week: Sun Feb 1 – Sat Feb 7. Data on Saturday should be included.
    writeCareerFixture($this->fixtureDir, 'sattest', [
        [
            'planner_username' => 'ASPLUNDH\\sattest',
            'job_guid' => '{33333333-3333-3333-3333-333333333333}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => '2026-02-07', 'daily_footage_miles' => 2.0, 'unit_count' => 5, 'stations' => []],
                // Sunday (start of NEXT week) — excluded from offset -1
                ['completion_date' => '2026-02-08', 'daily_footage_miles' => 99.0, 'unit_count' => 50, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week', -1);

    expect($result[0]['period_miles'])->toBe(2.0);
});

test('it shows partial week for current week offset 0', function () {
    // Frozen: Wed Feb 11. Current week window = Sun Feb 8 – Wed Feb 11.
    writeCareerFixture($this->fixtureDir, 'partial', [
        [
            'planner_username' => 'ASPLUNDH\\partial',
            'job_guid' => '{44444444-4444-4444-4444-444444444444}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => '2026-02-08', 'daily_footage_miles' => 1.0, 'unit_count' => 3, 'stations' => []],
                ['completion_date' => '2026-02-10', 'daily_footage_miles' => 2.0, 'unit_count' => 5, 'stations' => []],
                ['completion_date' => '2026-02-11', 'daily_footage_miles' => 1.5, 'unit_count' => 4, 'stations' => []],
                // Future data (Feb 12) — should NOT be included
                ['completion_date' => '2026-02-12', 'daily_footage_miles' => 99.0, 'unit_count' => 50, 'stations' => []],
            ],
        ],
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
    // Frozen: Feb 11, 2026. Fiscal year = Jul 1, 2025 – Jun 30, 2026.
    writeCareerFixture($this->fixtureDir, 'fiscal', [
        [
            'planner_username' => 'ASPLUNDH\\fiscal',
            'job_guid' => '{55555555-5555-5555-5555-555555555555}',
            'scope_year' => 2026,
            'daily_metrics' => [
                // Aug 2025 — within fiscal year
                ['completion_date' => '2025-08-15', 'daily_footage_miles' => 3.0, 'unit_count' => 10, 'stations' => []],
                // Dec 2025 — within fiscal year
                ['completion_date' => '2025-12-10', 'daily_footage_miles' => 2.0, 'unit_count' => 8, 'stations' => []],
                // Feb 2026 — within fiscal year
                ['completion_date' => '2026-02-05', 'daily_footage_miles' => 4.0, 'unit_count' => 12, 'stations' => []],
                // Jun 2025 — BEFORE fiscal year, should NOT be included
                ['completion_date' => '2025-06-15', 'daily_footage_miles' => 99.0, 'unit_count' => 50, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('scope-year', 0);

    expect($result[0]['period_miles'])->toBe(9.0);
});

test('it shifts scope-year with offset', function () {
    // Frozen: Feb 11, 2026. Offset -1 = Jul 1, 2024 – Jun 30, 2025.
    writeCareerFixture($this->fixtureDir, 'pastfiscal', [
        [
            'planner_username' => 'ASPLUNDH\\pastfiscal',
            'job_guid' => '{66666666-6666-6666-6666-666666666666}',
            'scope_year' => 2025,
            'daily_metrics' => [
                // Oct 2024 — within previous fiscal year
                ['completion_date' => '2024-10-20', 'daily_footage_miles' => 5.0, 'unit_count' => 15, 'stations' => []],
                // Mar 2025 — within previous fiscal year
                ['completion_date' => '2025-03-12', 'daily_footage_miles' => 3.0, 'unit_count' => 10, 'stations' => []],
                // Aug 2025 — CURRENT fiscal year, NOT included in offset -1
                ['completion_date' => '2025-08-15', 'daily_footage_miles' => 99.0, 'unit_count' => 50, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('scope-year', -1);

    expect($result[0]['period_miles'])->toBe(8.0);
});

// ─── Period label tests ──────────────────────────────────────────────────────

test('it generates correct period label for same-year range', function () {
    // Frozen: Wed Feb 11. Week offset 0 = Feb 8 – Feb 11, 2026.
    expect($this->service->getPeriodLabel('week', 0))->toBe('Feb 8 – Feb 11, 2026');
});

test('it generates full week label for past weeks', function () {
    // Week offset -1 = Feb 1 – Feb 7, 2026.
    expect($this->service->getPeriodLabel('week', -1))->toBe('Feb 1 – Feb 7, 2026');
});

test('it generates cross-year period label when range spans years', function () {
    // Freeze to Friday Jan 2, 2026. Current week (Sunday-start) = Dec 28, 2025 – Jan 2, 2026.
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 1, 2, 12, 0, 0, 'UTC'));

    expect($this->service->getPeriodLabel('week', 0))->toBe('Dec 28, 2025 – Jan 2, 2026');
});

test('it generates month period label', function () {
    // Frozen: Feb 11. Month offset 0 = Feb 1 – Feb 11, 2026.
    expect($this->service->getPeriodLabel('month', 0))->toBe('Feb 1 – Feb 11, 2026');
});

test('it generates full month label for past months', function () {
    // Month offset -1 = Jan 1 – Jan 31, 2026.
    expect($this->service->getPeriodLabel('month', -1))->toBe('Jan 1 – Jan 31, 2026');
});

test('it generates scope-year period label', function () {
    // Scope-year offset 0 = Jul 1, 2025 – Feb 11, 2026.
    expect($this->service->getPeriodLabel('scope-year', 0))->toBe('Jul 1, 2025 – Feb 11, 2026');
});

// ─── Circuit drawer data tests ───────────────────────────────────────────────

test('resolveHealthSignal returns circuits key with correct count', function () {
    $guid1 = '{'.Str::uuid()->toString().'}';
    $guid2 = '{'.Str::uuid()->toString().'}';

    writeCareerFixture($this->fixtureDir, 'drawer', [
        ['planner_username' => 'ASPLUNDH\\drawer', 'job_guid' => $guid1, 'scope_year' => 2026, 'daily_metrics' => []],
    ]);

    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'drawer',
        'frstr_user' => 'ASPLUNDH\\drawer',
        'job_guid' => $guid1,
    ]);
    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'drawer',
        'frstr_user' => 'ASPLUNDH\\drawer',
        'job_guid' => $guid2,
    ]);

    AssessmentMonitor::factory()->withSnapshots(1)->create([
        'job_guid' => $guid1,
        'current_status' => 'ACTIV',
        'line_name' => 'Circuit-1234',
        'region' => 'NORTH',
    ]);
    AssessmentMonitor::factory()->withSnapshots(1)->create([
        'job_guid' => $guid2,
        'current_status' => 'ACTIV',
        'line_name' => 'Circuit-5678',
        'region' => 'SOUTH',
    ]);

    $result = $this->service->getHealthMetrics();

    expect($result[0])->toHaveKey('circuits')
        ->and($result[0]['circuits'])->toHaveCount(2);
});

test('each circuit has expected keys', function () {
    $guid = '{'.Str::uuid()->toString().'}';

    writeCareerFixture($this->fixtureDir, 'keys', [
        ['planner_username' => 'ASPLUNDH\\keys', 'job_guid' => $guid, 'scope_year' => 2026, 'daily_metrics' => []],
    ]);

    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'keys',
        'frstr_user' => 'ASPLUNDH\\keys',
        'job_guid' => $guid,
    ]);

    AssessmentMonitor::factory()->withSnapshots(1)->create([
        'job_guid' => $guid,
        'current_status' => 'ACTIV',
        'line_name' => 'Circuit-9999',
        'region' => 'EAST',
        'total_miles' => 15.5,
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

test('circuits array is empty when no active monitors exist', function () {
    writeCareerFixture($this->fixtureDir, 'nocircuit', [
        ['planner_username' => 'ASPLUNDH\\nocircuit', 'job_guid' => '{AAAA0000-0000-0000-0000-000000000000}', 'scope_year' => 2026, 'daily_metrics' => []],
    ]);

    $result = $this->service->getHealthMetrics();

    expect($result[0]['circuits'])->toBe([]);
});

test('circuits handles null latest_snapshot gracefully', function () {
    $guid = '{'.Str::uuid()->toString().'}';

    writeCareerFixture($this->fixtureDir, 'nullsnap', [
        ['planner_username' => 'ASPLUNDH\\nullsnap', 'job_guid' => $guid, 'scope_year' => 2026, 'daily_metrics' => []],
    ]);

    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'nullsnap',
        'frstr_user' => 'ASPLUNDH\\nullsnap',
        'job_guid' => $guid,
    ]);

    // Create monitor WITHOUT snapshots (latest_snapshot = null)
    AssessmentMonitor::factory()->create([
        'job_guid' => $guid,
        'current_status' => 'ACTIV',
        'total_miles' => 10.0,
    ]);

    $result = $this->service->getHealthMetrics();
    $circuit = $result[0]['circuits'][0];

    expect($circuit['completed_miles'])->toBe(0.0)
        ->and($circuit['percent_complete'])->toBe(0.0)
        ->and($circuit['permission_breakdown'])->toBe([]);
});

test('circuits are included in quota metrics return', function () {
    $guid = '{'.Str::uuid()->toString().'}';
    $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

    writeCareerFixture($this->fixtureDir, 'quotacircuit', [
        [
            'planner_username' => 'ASPLUNDH\\quotacircuit',
            'job_guid' => $guid,
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $weekStart->format('Y-m-d'), 'daily_footage_miles' => 3.0, 'unit_count' => 10, 'stations' => []],
            ],
        ],
    ]);

    PlannerJobAssignment::factory()->create([
        'normalized_username' => 'quotacircuit',
        'frstr_user' => 'ASPLUNDH\\quotacircuit',
        'job_guid' => $guid,
    ]);

    AssessmentMonitor::factory()->withSnapshots(1)->create([
        'job_guid' => $guid,
        'current_status' => 'ACTIV',
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0])->toHaveKey('circuits')
        ->and($result[0]['circuits'])->toHaveCount(1);
});

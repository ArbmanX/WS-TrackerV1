<?php

use App\Models\AssessmentMonitor;
use App\Models\PlannerJobAssignment;
use App\Services\PlannerMetrics\PlannerMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PlannerMetricsService;
    $this->fixtureDir = sys_get_temp_dir().'/career_test_'.Str::random(8);
    mkdir($this->fixtureDir, 0755, true);
    config()->set('planner_metrics.career_json_path', $this->fixtureDir);
});

afterEach(function () {
    // Clean up fixture files
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

test('it returns empty array when no career JSON files exist', function () {
    expect($this->service->getQuotaMetrics())->toBe([])
        ->and($this->service->getHealthMetrics())->toBe([]);
});

test('it calculates weekly miles from daily_metrics in JSON', function () {
    $monday = CarbonImmutable::now()->startOfWeek();

    writeCareerFixture($this->fixtureDir, 'jsmith', [
        [
            'planner_username' => 'ASPLUNDH\\jsmith',
            'job_guid' => '{11111111-1111-1111-1111-111111111111}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 2.5, 'unit_count' => 10, 'stations' => []],
                ['completion_date' => $monday->addDay()->format('Y-m-d'), 'daily_footage_miles' => 3.0, 'unit_count' => 12, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result)->toHaveCount(1)
        ->and($result[0]['period_miles'])->toBe(5.5)
        ->and($result[0]['username'])->toBe('jsmith');
});

test('it calculates monthly miles aggregation', function () {
    $startOfMonth = CarbonImmutable::now()->startOfMonth();

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
    $lastMonday = $now->startOfWeek()->subWeek();
    $twoWeeksAgo = $lastMonday->subWeek();

    writeCareerFixture($this->fixtureDir, 'streaker', [
        [
            'planner_username' => 'ASPLUNDH\\streaker',
            'job_guid' => '{33333333-3333-3333-3333-333333333333}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $twoWeeksAgo->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
                ['completion_date' => $lastMonday->format('Y-m-d'), 'daily_footage_miles' => 6.5, 'unit_count' => 18, 'stations' => []],
            ],
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

    writeCareerFixture($this->fixtureDir, 'broken', [
        [
            'planner_username' => 'ASPLUNDH\\broken',
            'job_guid' => '{44444444-4444-4444-4444-444444444444}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $threeWeeksAgo->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
                ['completion_date' => $twoWeeksAgo->format('Y-m-d'), 'daily_footage_miles' => 2.0, 'unit_count' => 5, 'stations' => []],
                ['completion_date' => $lastMonday->format('Y-m-d'), 'daily_footage_miles' => 8.0, 'unit_count' => 25, 'stations' => []],
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
    $monday = CarbonImmutable::now()->startOfWeek();

    writeCareerFixture($this->fixtureDir, 'editor', [
        [
            'planner_username' => 'ASPLUNDH\\editor',
            'job_guid' => '{88888888-8888-8888-8888-888888888888}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 3.0, 'unit_count' => 10, 'stations' => []],
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
    $monday = CarbonImmutable::now()->startOfWeek();

    writeCareerFixture($this->fixtureDir, 'onpace', [
        [
            'planner_username' => 'ASPLUNDH\\onpace',
            'job_guid' => '{AAAA1111-1111-1111-1111-111111111111}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
            ],
        ],
    ]);

    writeCareerFixture($this->fixtureDir, 'behind', [
        [
            'planner_username' => 'ASPLUNDH\\behind',
            'job_guid' => '{BBBB2222-2222-2222-2222-222222222222}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 5.0, 'unit_count' => 14, 'stations' => []],
            ],
        ],
    ]);

    writeCareerFixture($this->fixtureDir, 'wayout', [
        [
            'planner_username' => 'ASPLUNDH\\wayout',
            'job_guid' => '{CCCC3333-3333-3333-3333-333333333333}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 1.0, 'unit_count' => 3, 'stations' => []],
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

    $monday = CarbonImmutable::now()->startOfWeek();

    writeCareerFixture($this->fixtureDir, 'configtest', [
        [
            'planner_username' => 'ASPLUNDH\\configtest',
            'job_guid' => '{EEEE5555-5555-5555-5555-555555555555}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 7.0, 'unit_count' => 20, 'stations' => []],
            ],
        ],
    ]);

    $result = $this->service->getQuotaMetrics('week');

    expect($result[0]['quota_target'])->toBe(10.0)
        ->and($result[0]['gap_miles'])->toBe(3.0)
        ->and($result[0]['status'])->toBe('error');
});

test('it picks the most recent JSON file per planner', function () {
    $monday = CarbonImmutable::now()->startOfWeek();

    // Older file with different data
    writeCareerFixture($this->fixtureDir, 'jsmith', [
        [
            'planner_username' => 'ASPLUNDH\\jsmith',
            'job_guid' => '{FFFF6666-6666-6666-6666-666666666666}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 1.0, 'unit_count' => 5, 'stations' => []],
            ],
        ],
    ], '2026-02-10');

    // Newer file should win
    writeCareerFixture($this->fixtureDir, 'jsmith', [
        [
            'planner_username' => 'ASPLUNDH\\jsmith',
            'job_guid' => '{FFFF7777-7777-7777-7777-777777777777}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 5.0, 'unit_count' => 15, 'stations' => []],
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
    $monday = CarbonImmutable::now()->startOfWeek();

    writeCareerFixture($this->fixtureDir, 'eci_chris', [
        [
            'planner_username' => 'ASPLUNDH\\eci chris',
            'job_guid' => '{AAAA9999-9999-9999-9999-999999999999}',
            'scope_year' => 2026,
            'daily_metrics' => [
                ['completion_date' => $monday->format('Y-m-d'), 'daily_footage_miles' => 4.0, 'unit_count' => 10, 'stations' => []],
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

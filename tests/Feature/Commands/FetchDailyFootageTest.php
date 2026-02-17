<?php

use App\Models\Assessment;
use App\Services\WorkStudio\Assessments\Queries\DailyFootageQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Build a fake DDOProtocol API response with the new schema (includes station_list).
 */
function fakeDailyFootageResponse(?array $overrideData = null): array
{
    return [
        'Heading' => ['JOBGUID', 'completion_date', 'FRSTR_USER', 'daily_footage_meters', 'station_list', 'unit_count'],
        'Data' => $overrideData ?? [
            ['{abc-111}', '01-15-2026', 'ASPLUNDH\\tgibson', '1523.7', '0,1,5', '3'],
            ['{abc-111}', '01-16-2026', 'ASPLUNDH\\tgibson', '987.3', '2,3', '2'],
            ['{abc-111}', '01-16-2026', 'ASPLUNDH\\jdoe', '412.5', '10', '0'],
            ['{abc-222}', '01-20-2026', 'PPL\\msmith', '2100.0', '0,4,7,12', '4'],
        ],
    ];
}

/**
 * Create an Assessment matching the default filters (ACTIV, scope_year, assessment job_type).
 */
function createMatchingJob(array $overrides = []): Assessment
{
    return Assessment::factory()->create(array_merge([
        'status' => 'ACTIV',
        'scope_year' => config('ws_assessment_query.scope_year'),
        'job_type' => 'Assessment Dx',
    ], $overrides));
}

// ──────────────────────────────────────────────────
// Date Resolution
// ──────────────────────────────────────────────────

test('default date resolves to previous complete week when not Saturday', function () {
    // Freeze to Friday Feb 6, 2026 — previous Saturday is Jan 31, 2026
    // Week range: Sun Jan 25 - Sat Jan 31
    Carbon::setTestNow(Carbon::parse('2026-02-06'));
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage')
        ->expectsOutputToContain('Week-Ending mode')
        ->assertSuccessful();

    // Verify filename uses the previous Saturday (Jan 31)
    expect(Storage::allFiles())->toContain('daily-footage/ASPLUNDH/we01_31_2026_planning_activities.json');

    Carbon::setTestNow();
});

test('default date uses current week when today is Saturday', function () {
    // Freeze to Saturday Feb 7, 2026 — should use THIS week (Sun Feb 1 - Sat Feb 7)
    Carbon::setTestNow(Carbon::parse('2026-02-07'));
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage')
        ->expectsOutputToContain('Week-Ending mode')
        ->expectsOutputToContain('Found 1 jobs')
        ->assertSuccessful();

    // Verify filename uses TODAY's Saturday (Feb 7), not last week
    expect(Storage::allFiles())->toContain('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json');

    Carbon::setTestNow();
});

test('year argument triggers Year mode with full year range', function () {
    Storage::fake();

    createMatchingJob(); // scope_year 2026 — matches
    createMatchingJob(['scope_year' => '2025']); // Previous year — excluded

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 2026')
        ->expectsOutputToContain('Year mode')
        ->expectsOutputToContain('Found 1 jobs')
        ->assertSuccessful();

    // Filename uses 'year' prefix
    expect(Storage::allFiles())->toContain('daily-footage/ASPLUNDH/year01_01_2026_planning_activities.json');
});

test('Saturday date triggers WE mode', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')
        ->expectsOutputToContain('Week-Ending mode')
        ->expectsOutputToContain('Found 1 jobs')
        ->assertSuccessful();

    // Filename should use 'we' prefix
    expect(Storage::allFiles())->toContain('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json');
});

test('non-Saturday date triggers Daily mode', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-04-2026')
        ->expectsOutputToContain('Daily mode')
        ->expectsOutputToContain('Found 1 jobs')
        ->assertSuccessful();

    // Filename should use 'day' prefix
    expect(Storage::allFiles())->toContain('daily-footage/ASPLUNDH/day02_04_2026_planning_activities.json');
});

test('MM-DD format assumes current year', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15'));
    Storage::fake();

    // 02-07 → Feb 7 of current year (2026) — Saturday → WE mode
    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-07')
        ->expectsOutputToContain('Week-Ending mode')
        ->assertSuccessful();

    expect(Storage::allFiles())->toContain('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json');

    Carbon::setTestNow();
});

// ──────────────────────────────────────────────────
// Job Filtering
// ──────────────────────────────────────────────────

test('filters jobs by status (default ACTIV)', function () {
    Storage::fake();

    createMatchingJob(['status' => 'ACTIV']);
    createMatchingJob(['status' => 'QC']);

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')
        ->expectsOutputToContain('Found 1 jobs')
        ->assertSuccessful();
});

test('--status overrides default ACTIV filter', function () {
    Storage::fake();

    createMatchingJob(['status' => 'QC']);
    createMatchingJob(['status' => 'ACTIV']);

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026 --status=QC')
        ->expectsOutputToContain('Found 1 jobs')
        ->assertSuccessful();
});

test('--all-statuses uses all planner_concern statuses', function () {
    Storage::fake();

    createMatchingJob(['status' => 'ACTIV']);
    createMatchingJob(['status' => 'QC']);
    createMatchingJob(['status' => 'REWRK']);
    createMatchingJob(['status' => 'CLOSE']);
    createMatchingJob(['status' => 'SA']); // Not in planner_concern

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026 --all-statuses')
        ->expectsOutputToContain('Found 4 jobs')
        ->assertSuccessful();
});

test('filters jobs by assessment job_type from config', function () {
    Storage::fake();

    createMatchingJob(['job_type' => 'Assessment Dx']);
    createMatchingJob(['job_type' => 'Split_Assessment']);
    createMatchingJob(['job_type' => 'Work Dx']); // Not an assessment type

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')
        ->expectsOutputToContain('Found 2 jobs')
        ->assertSuccessful();
});

// ──────────────────────────────────────────────────
// --jobguid Bypass
// ──────────────────────────────────────────────────

test('--jobguid bypasses assessments lookup', function () {
    Storage::fake();

    // No Assessment records at all — should still query the API with the given GUID
    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026 --jobguid={custom-guid-123}')
        ->expectsOutputToContain('Found 1 jobs')
        ->assertSuccessful();

    Http::assertSentCount(1);
});

// ──────────────────────────────────────────────────
// Enrichment & Output Shape
// ──────────────────────────────────────────────────

test('enriched JSON records have correct shape', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse([
        ['{abc-111}', '01-15-2026', 'ASPLUNDH\\tgibson', '1523.7', '0,1,5', '3'],
    ]))]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')->assertSuccessful();

    $json = json_decode(Storage::get('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json'), true);

    expect($json)->toBeArray()
        ->and($json)->toHaveCount(1)
        ->and($json[0])->toHaveKeys(['job_guid', 'frstr_user', 'datepop', 'distance_planned', 'unit_count', 'stations'])
        ->and($json[0])->not->toHaveKey('_domain')
        ->and($json[0])->not->toHaveKey('domain')
        ->and($json[0])->not->toHaveKey('footage_miles')
        ->and($json[0])->not->toHaveKey('week_ending')
        ->and($json[0])->not->toHaveKey('ws_user_id');
});

test('station_list is split into array', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse([
        ['{abc-111}', '01-15-2026', 'ASPLUNDH\\tgibson', '500.0', '0,1,5,10', '4'],
    ]))]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')->assertSuccessful();

    $json = json_decode(Storage::get('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json'), true);

    expect($json[0]['stations'])->toBe(['0', '1', '5', '10']);
});

test('empty station_list produces empty array', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse([
        ['{abc-111}', '01-15-2026', 'ASPLUNDH\\tgibson', '500.0', '', '0'],
    ]))]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')->assertSuccessful();

    $json = json_decode(Storage::get('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json'), true);

    expect($json[0]['stations'])->toBe([]);
});

test('completion_date MM-DD-YYYY is converted to datepop YYYY-MM-DD', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse([
        ['{abc-111}', '01-15-2026', 'ASPLUNDH\\tgibson', '1523.7', '0', '1'],
    ]))]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')->assertSuccessful();

    $json = json_decode(Storage::get('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json'), true);

    expect($json[0]['datepop'])->toBe('2026-01-15')
        ->and($json[0]['distance_planned'])->toBe(1523.7);
});

test('multiple domains produce separate JSON files', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')->assertSuccessful();

    expect(Storage::exists('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json'))->toBeTrue()
        ->and(Storage::exists('daily-footage/PPL/we02_07_2026_planning_activities.json'))->toBeTrue();

    $asplundh = json_decode(Storage::get('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json'), true);
    $ppl = json_decode(Storage::get('daily-footage/PPL/we02_07_2026_planning_activities.json'), true);

    expect($asplundh)->toHaveCount(3)
        ->and($ppl)->toHaveCount(1);
});

test('no .manifest file is written', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')->assertSuccessful();

    expect(Storage::exists('daily-footage/.manifest'))->toBeFalse();
});

// ──────────────────────────────────────────────────
// SQL Query Assertions
// ──────────────────────────────────────────────────

test('DailyFootageQuery prefers DATEPOP with ASSDDATE fallback', function () {
    $sql = DailyFootageQuery::build(['{guid-1}', '{guid-2}']);

    expect($sql)
        ->toContain('VU.DATEPOP')
        ->toContain("REPLACE(VU.DATEPOP, '/Date('")
        ->toContain("REPLACE(VU.ASSDDATE, '/Date('")
        ->toContain('COALESCE(VU.DATEPOP, VU.ASSDDATE) ASC');
});

test('DailyFootageQuery includes STRING_AGG for station_list', function () {
    $sql = DailyFootageQuery::build(['{guid-1}']);

    expect($sql)
        ->toContain("STRING_AGG(CAST(FU.STATNAME AS VARCHAR(MAX)), ',') WITHIN GROUP (ORDER BY FU.STATNAME) AS station_list")
        ->toContain('SUM(ISNULL(ST.SPANLGTH, 0)) AS daily_footage_meters')
        ->toContain('FU.FRSTR_USER')
        ->toContain('FU.completion_date');
});

test('DailyFootageQuery falls back to ASSDDATE when DATEPOP is null', function () {
    $sql = DailyFootageQuery::build(['{guid-1}']);

    expect($sql)
        ->toContain('COALESCE(')
        ->toContain('ASSDDATE')
        ->toContain('VU.DATEPOP IS NOT NULL OR VU.ASSDDATE IS NOT NULL');
});

test('DailyFootageQuery filters completion_date by date range when provided', function () {
    $sql = DailyFootageQuery::build(['{guid-1}'], '2026-02-01', '2026-02-07');

    expect($sql)
        ->toContain("FU.completion_date BETWEEN '2026-02-01' AND '2026-02-07'");
});

test('DailyFootageQuery omits date filter when no range provided', function () {
    $sql = DailyFootageQuery::build(['{guid-1}']);

    expect($sql)->not->toContain('BETWEEN');
});

test('DailyFootageQuery uses derived table not CTE', function () {
    $sql = DailyFootageQuery::build(['{guid-1}']);

    expect($sql)
        ->toContain('ROW_NUMBER() OVER')
        ->toContain('PARTITION BY VU.JOBGUID, VU.STATNAME')
        ->toContain('FU.unit_rank = 1')
        ->toContain('JOIN STATIONS ST')
        ->toContain('GROUP BY FU.JOBGUID')
        ->not->toMatch('/\bWITH\b(?!IN)/'); // "WITH" keyword but not "WITHIN"
});

test('DailyFootageQuery joins UNITS table and counts working units', function () {
    $sql = DailyFootageQuery::build(['{guid-1}']);

    expect($sql)
        ->toContain('JOIN UNITS U')
        ->toContain('ON U.UNIT = FU.UNIT')
        ->toContain("WHEN U.SUMMARYGRP IS NOT NULL AND U.SUMMARYGRP != '' AND U.SUMMARYGRP != 'Summary-NonWork' THEN 1 ELSE 0 END")
        ->toContain('AS unit_count');
});

test('unit_count is cast to integer in enriched output', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse([
        ['{abc-111}', '01-15-2026', 'ASPLUNDH\\tgibson', '1523.7', '0,1,5', '3'],
    ]))]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')->assertSuccessful();

    $json = json_decode(Storage::get('daily-footage/ASPLUNDH/we02_07_2026_planning_activities.json'), true);

    expect($json[0]['unit_count'])->toBe(3)
        ->and($json[0]['unit_count'])->toBeInt();
});

// ──────────────────────────────────────────────────
// Dry-Run, Errors, Edge Cases
// ──────────────────────────────────────────────────

test('dry-run shows jobs without calling API', function () {
    createMatchingJob();

    Http::fake();

    $this->artisan('ws:fetch-daily-footage 02-07-2026 --dry-run')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('handles no matching jobs gracefully', function () {
    // No Assessment records at all
    $this->artisan('ws:fetch-daily-footage 02-07-2026')
        ->expectsOutputToContain('No jobs found')
        ->assertSuccessful();
});

test('handles API error response', function () {
    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response([
        'protocol' => 'ERROR',
        'errorMessage' => 'Access denied',
    ])]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')
        ->assertFailed();
});

test('handles empty API data set', function () {
    Storage::fake();

    createMatchingJob();

    Http::fake(['*/GETQUERY' => Http::response([
        'Protocol' => 'QUERYRESULT',
    ])]);

    $this->artisan('ws:fetch-daily-footage 02-07-2026')
        ->expectsOutputToContain('No footage data')
        ->assertSuccessful();
});

test('chunks large JOBGUID lists into multiple API calls', function () {
    Storage::fake();

    // Create 5 matching jobs
    for ($i = 0; $i < 5; $i++) {
        createMatchingJob();
    }

    Http::fake(['*/GETQUERY' => Http::response(fakeDailyFootageResponse())]);

    // Chunk size of 2 = 3 API calls (2+2+1)
    $this->artisan('ws:fetch-daily-footage 02-07-2026 --chunk-size=2')
        ->assertSuccessful();

    Http::assertSentCount(3);
});

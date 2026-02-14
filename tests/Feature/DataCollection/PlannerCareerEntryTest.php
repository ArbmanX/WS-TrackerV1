<?php

use App\Models\PlannerCareerEntry;

test('can create a career entry with factory', function () {
    $entry = PlannerCareerEntry::factory()->create();

    expect($entry->job_guid)->toStartWith('{')
        ->and($entry->planner_username)->not->toBeEmpty()
        ->and($entry->daily_metrics)->toBeArray()
        ->and($entry->summary_totals)->toBeArray()
        ->and($entry->source)->toBe('bootstrap');
});

test('jsonb columns are cast to arrays', function () {
    $entry = PlannerCareerEntry::factory()->create();
    $fresh = PlannerCareerEntry::find($entry->id);

    expect($fresh->daily_metrics)->toBeArray()
        ->and($fresh->summary_totals)->toBeArray()
        ->and($fresh->rework_details)->toBeNull();
});

test('date columns are cast to carbon instances', function () {
    $entry = PlannerCareerEntry::factory()->create();
    $fresh = PlannerCareerEntry::find($entry->id);

    expect($fresh->assessment_pickup_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($fresh->assessment_qc_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($fresh->assessment_close_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('went_to_rework is cast to boolean', function () {
    $entry = PlannerCareerEntry::factory()->create();

    expect($entry->went_to_rework)->toBeBool()->toBeFalse();
});

test('withRework factory state sets rework fields', function () {
    $entry = PlannerCareerEntry::factory()->withRework()->create();

    expect($entry->went_to_rework)->toBeTrue()
        ->and($entry->rework_details)->toBeArray()
        ->and($entry->rework_details)->toHaveKeys(['rework_count', 'audit_user', 'audit_date', 'audit_notes', 'failed_unit_count']);
});

test('fromLiveMonitor factory state sets source', function () {
    $entry = PlannerCareerEntry::factory()->fromLiveMonitor()->create();

    expect($entry->source)->toBe('live_monitor');
});

test('forPlanner scope filters by username', function () {
    PlannerCareerEntry::factory()->create(['planner_username' => 'alice']);
    PlannerCareerEntry::factory()->create(['planner_username' => 'bob']);

    expect(PlannerCareerEntry::forPlanner('alice')->count())->toBe(1);
});

test('forRegion scope filters by region', function () {
    PlannerCareerEntry::factory()->create(['region' => 'NORTH']);
    PlannerCareerEntry::factory()->create(['region' => 'SOUTH']);

    expect(PlannerCareerEntry::forRegion('NORTH')->count())->toBe(1);
});

test('forScopeYear scope filters by year', function () {
    PlannerCareerEntry::factory()->create(['scope_year' => '2026']);
    PlannerCareerEntry::factory()->create(['scope_year' => '2025']);

    expect(PlannerCareerEntry::forScopeYear('2026')->count())->toBe(1);
});

test('fromBootstrap scope filters by source', function () {
    PlannerCareerEntry::factory()->fromBootstrap()->create();
    PlannerCareerEntry::factory()->fromLiveMonitor()->create();

    expect(PlannerCareerEntry::fromBootstrap()->count())->toBe(1)
        ->and(PlannerCareerEntry::fromLiveMonitor()->count())->toBe(1);
});

test('unique constraint on planner_username and job_guid', function () {
    $entry = PlannerCareerEntry::factory()->create();

    PlannerCareerEntry::factory()->create([
        'planner_username' => $entry->planner_username,
        'job_guid' => $entry->job_guid,
    ]);
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);

test('summary_totals contains expected metric keys', function () {
    $entry = PlannerCareerEntry::factory()->create();

    expect($entry->summary_totals)->toHaveKeys([
        'total_footage_feet',
        'total_footage_miles',
        'total_stations',
        'total_work_units',
        'total_nw_units',
        'working_days',
        'avg_daily_footage_feet',
        'first_activity_date',
        'last_activity_date',
    ]);
});

test('daily_metrics keys are date-formatted strings', function () {
    $entry = PlannerCareerEntry::factory()->create();

    foreach (array_keys($entry->daily_metrics) as $key) {
        expect($key)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }
});

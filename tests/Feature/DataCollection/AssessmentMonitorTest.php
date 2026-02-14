<?php

use App\Models\AssessmentMonitor;

test('can create monitor with factory', function () {
    $monitor = AssessmentMonitor::factory()->create();

    expect($monitor->job_guid)->toStartWith('{')
        ->and($monitor->current_status)->toBe('ACTIV')
        ->and($monitor->daily_snapshots)->toBeArray()
        ->and($monitor->daily_snapshots)->toBeEmpty();
});

test('jsonb columns are cast to arrays', function () {
    $monitor = AssessmentMonitor::factory()->withSnapshots()->create();
    $fresh = AssessmentMonitor::find($monitor->id);

    expect($fresh->daily_snapshots)->toBeArray()
        ->and($fresh->latest_snapshot)->toBeArray();
});

test('date columns are cast to carbon instances', function () {
    $monitor = AssessmentMonitor::factory()->withSnapshots()->create();
    $fresh = AssessmentMonitor::find($monitor->id);

    expect($fresh->first_snapshot_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($fresh->last_snapshot_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('job_guid has unique constraint', function () {
    $monitor = AssessmentMonitor::factory()->create();

    AssessmentMonitor::factory()->create(['job_guid' => $monitor->job_guid]);
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);

test('withSnapshots factory state generates date-keyed snapshots', function () {
    $monitor = AssessmentMonitor::factory()->withSnapshots(3)->create();

    expect($monitor->daily_snapshots)->toHaveCount(3)
        ->and($monitor->latest_snapshot)->toBeArray()
        ->and($monitor->first_snapshot_date)->not->toBeNull()
        ->and($monitor->last_snapshot_date)->not->toBeNull();

    foreach (array_keys($monitor->daily_snapshots) as $key) {
        expect($key)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }
});

test('snapshot contains expected metric sections', function () {
    $monitor = AssessmentMonitor::factory()->withSnapshots(1)->create();
    $snapshot = $monitor->latest_snapshot;

    expect($snapshot)->toHaveKeys([
        'permission_breakdown',
        'unit_counts',
        'work_type_breakdown',
        'footage',
        'notes_compliance',
        'planner_activity',
        'aging_units',
        'suspicious',
    ]);
});

test('inQc factory state sets status', function () {
    $monitor = AssessmentMonitor::factory()->inQc()->create();

    expect($monitor->current_status)->toBe('QC');
});

test('inRework factory state sets status', function () {
    $monitor = AssessmentMonitor::factory()->inRework()->create();

    expect($monitor->current_status)->toBe('REWRK');
});

test('active scope filters by ACTIV status', function () {
    AssessmentMonitor::factory()->create();
    AssessmentMonitor::factory()->inQc()->create();

    expect(AssessmentMonitor::active()->count())->toBe(1);
});

test('inQc scope filters by QC status', function () {
    AssessmentMonitor::factory()->create();
    AssessmentMonitor::factory()->inQc()->create();

    expect(AssessmentMonitor::inQc()->count())->toBe(1);
});

test('inRework scope filters by REWRK status', function () {
    AssessmentMonitor::factory()->create();
    AssessmentMonitor::factory()->inRework()->create();

    expect(AssessmentMonitor::inRework()->count())->toBe(1);
});

test('forRegion scope filters by region', function () {
    AssessmentMonitor::factory()->create(['region' => 'NORTH']);
    AssessmentMonitor::factory()->create(['region' => 'SOUTH']);

    expect(AssessmentMonitor::forRegion('NORTH')->count())->toBe(1);
});

test('addSnapshot appends to daily_snapshots and updates denormalized fields', function () {
    $monitor = AssessmentMonitor::factory()->create();
    $firstSnapshot = ['unit_counts' => ['total_units' => 42]];
    $secondSnapshot = ['unit_counts' => ['total_units' => 55]];

    // First snapshot
    $monitor->addSnapshot('2026-02-10', $firstSnapshot);
    $monitor->save();

    $fresh = AssessmentMonitor::find($monitor->id);
    expect($fresh->daily_snapshots)->toHaveCount(1)
        ->and($fresh->daily_snapshots['2026-02-10'])->toBe($firstSnapshot)
        ->and($fresh->latest_snapshot)->toBe($firstSnapshot)
        ->and($fresh->first_snapshot_date->format('Y-m-d'))->toBe('2026-02-10')
        ->and($fresh->last_snapshot_date->format('Y-m-d'))->toBe('2026-02-10');

    // Second snapshot — accumulates, doesn't replace
    $fresh->addSnapshot('2026-02-11', $secondSnapshot);
    $fresh->save();

    $reloaded = AssessmentMonitor::find($monitor->id);
    expect($reloaded->daily_snapshots)->toHaveCount(2)
        ->and($reloaded->daily_snapshots['2026-02-10'])->toBe($firstSnapshot)
        ->and($reloaded->daily_snapshots['2026-02-11'])->toBe($secondSnapshot)
        ->and($reloaded->latest_snapshot)->toBe($secondSnapshot)
        ->and($reloaded->first_snapshot_date->format('Y-m-d'))->toBe('2026-02-10')
        ->and($reloaded->last_snapshot_date->format('Y-m-d'))->toBe('2026-02-11');
});

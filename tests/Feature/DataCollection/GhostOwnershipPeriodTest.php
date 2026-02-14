<?php

use App\Models\GhostOwnershipPeriod;
use App\Models\GhostUnitEvidence;

test('can create ownership period with factory', function () {
    $period = GhostOwnershipPeriod::factory()->create();

    expect($period->job_guid)->toStartWith('{')
        ->and($period->takeover_username)->toStartWith('ONEPPL\\')
        ->and($period->baseline_snapshot)->toBeArray()
        ->and($period->status)->toBe('active');
});

test('jsonb baseline_snapshot is cast to array', function () {
    $period = GhostOwnershipPeriod::factory()->create();
    $fresh = GhostOwnershipPeriod::find($period->id);

    expect($fresh->baseline_snapshot)->toBeArray()
        ->and($fresh->baseline_snapshot[0])->toHaveKeys(['unitguid', 'unit_type', 'statname', 'permstat', 'forester']);
});

test('date columns are cast to carbon instances', function () {
    $period = GhostOwnershipPeriod::factory()->create();
    $fresh = GhostOwnershipPeriod::find($period->id);

    expect($fresh->takeover_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($fresh->return_date)->toBeNull();
});

test('is_parent_takeover is cast to boolean', function () {
    $period = GhostOwnershipPeriod::factory()->create();

    expect($period->is_parent_takeover)->toBeBool()->toBeFalse();
});

test('has many evidence relationship', function () {
    $period = GhostOwnershipPeriod::factory()->create();
    GhostUnitEvidence::factory()->count(3)->create(['ownership_period_id' => $period->id]);

    expect($period->evidence)->toHaveCount(3);
});

test('active scope filters active periods', function () {
    GhostOwnershipPeriod::factory()->active()->create();
    GhostOwnershipPeriod::factory()->resolved()->create();

    expect(GhostOwnershipPeriod::active()->count())->toBe(1);
});

test('resolved scope filters resolved periods', function () {
    GhostOwnershipPeriod::factory()->active()->create();
    GhostOwnershipPeriod::factory()->resolved()->create();

    expect(GhostOwnershipPeriod::resolved()->count())->toBe(1);
});

test('parentTakeovers scope filters parent takeovers', function () {
    GhostOwnershipPeriod::factory()->parentTakeover()->create();
    GhostOwnershipPeriod::factory()->create();

    expect(GhostOwnershipPeriod::parentTakeovers()->count())->toBe(1);
});

test('resolved factory state sets return_date', function () {
    $period = GhostOwnershipPeriod::factory()->resolved()->create();

    expect($period->status)->toBe('resolved')
        ->and($period->return_date)->not->toBeNull();
});

test('parentTakeover factory state sets flag', function () {
    $period = GhostOwnershipPeriod::factory()->parentTakeover()->create();

    expect($period->is_parent_takeover)->toBeTrue();
});

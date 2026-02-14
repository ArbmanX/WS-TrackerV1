<?php

use App\Models\GhostOwnershipPeriod;
use App\Models\GhostUnitEvidence;

test('can create evidence with factory', function () {
    $evidence = GhostUnitEvidence::factory()->create();

    expect($evidence->unitguid)->toStartWith('{')
        ->and($evidence->takeover_username)->toStartWith('ONEPPL\\')
        ->and($evidence->unit_type)->not->toBeEmpty();
});

test('belongs to ownership period', function () {
    $period = GhostOwnershipPeriod::factory()->create();
    $evidence = GhostUnitEvidence::factory()->create(['ownership_period_id' => $period->id]);

    expect($evidence->ownershipPeriod->id)->toBe($period->id);
});

test('ownership_period_id is nullable', function () {
    $evidence = GhostUnitEvidence::factory()->create(['ownership_period_id' => null]);

    expect($evidence->ownership_period_id)->toBeNull()
        ->and($evidence->ownershipPeriod)->toBeNull();
});

test('has no updated_at timestamp', function () {
    $evidence = GhostUnitEvidence::factory()->create();

    expect($evidence->updated_at)->toBeNull()
        ->and(GhostUnitEvidence::UPDATED_AT)->toBeNull();
});

test('created_at is set automatically', function () {
    $evidence = GhostUnitEvidence::factory()->create();

    expect($evidence->created_at)->not->toBeNull()
        ->and($evidence->created_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('date columns are cast to carbon instances', function () {
    $evidence = GhostUnitEvidence::factory()->create();
    $fresh = GhostUnitEvidence::find($evidence->id);

    expect($fresh->detected_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($fresh->takeover_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('forAssessment scope filters by job_guid', function () {
    $guid = '{test-guid-12345678-1234-1234-1234}';
    GhostUnitEvidence::factory()->create(['job_guid' => $guid]);
    GhostUnitEvidence::factory()->create();

    expect(GhostUnitEvidence::forAssessment($guid)->count())->toBe(1);
});

test('detectedBetween scope filters by date range', function () {
    GhostUnitEvidence::factory()->create(['detected_date' => '2026-01-15']);
    GhostUnitEvidence::factory()->create(['detected_date' => '2026-02-15']);

    expect(GhostUnitEvidence::detectedBetween('2026-01-01', '2026-01-31')->count())->toBe(1)
        ->and(GhostUnitEvidence::detectedBetween('2026-01-01', '2026-12-31')->count())->toBe(2);
});

test('on delete set null preserves evidence when period deleted', function () {
    $period = GhostOwnershipPeriod::factory()->create();
    $evidence = GhostUnitEvidence::factory()->create(['ownership_period_id' => $period->id]);

    $period->delete();

    $fresh = GhostUnitEvidence::find($evidence->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->ownership_period_id)->toBeNull()
        ->and($fresh->job_guid)->not->toBeEmpty();
});

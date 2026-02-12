<?php

use App\Models\RegionalSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('factory creates a valid regional snapshot', function () {
    $snapshot = RegionalSnapshot::factory()->create();

    expect($snapshot)->toBeInstanceOf(RegionalSnapshot::class)
        ->and($snapshot->id)->toBeGreaterThan(0)
        ->and($snapshot->scope_year)->toBeString()
        ->and($snapshot->context_hash)->toHaveLength(8)
        ->and($snapshot->region)->toBeString()
        ->and($snapshot->captured_at)->not->toBeNull();
});

test('capturedAt factory state sets timestamp', function () {
    $timestamp = now()->subDays(3);
    $snapshot = RegionalSnapshot::factory()->capturedAt($timestamp)->create();

    expect($snapshot->captured_at->toDateString())->toBe($timestamp->toDateString());
});

test('forYear scope filters by scope year', function () {
    RegionalSnapshot::factory()->create(['scope_year' => '2025']);
    RegionalSnapshot::factory()->count(2)->create(['scope_year' => '2026']);

    expect(RegionalSnapshot::forYear('2026')->count())->toBe(2)
        ->and(RegionalSnapshot::forYear('2025')->count())->toBe(1);
});

test('forContext scope filters by context hash', function () {
    RegionalSnapshot::factory()->count(3)->create(['context_hash' => 'xxxx1111']);
    RegionalSnapshot::factory()->create(['context_hash' => 'yyyy2222']);

    expect(RegionalSnapshot::forContext('xxxx1111')->count())->toBe(3)
        ->and(RegionalSnapshot::forContext('yyyy2222')->count())->toBe(1);
});

test('forRegion scope filters by region', function () {
    RegionalSnapshot::factory()->count(2)->create(['region' => 'HARRISBURG']);
    RegionalSnapshot::factory()->create(['region' => 'LANCASTER']);

    expect(RegionalSnapshot::forRegion('HARRISBURG')->count())->toBe(2)
        ->and(RegionalSnapshot::forRegion('LANCASTER')->count())->toBe(1)
        ->and(RegionalSnapshot::forRegion('LEHIGH')->count())->toBe(0);
});

test('scopes can be chained', function () {
    RegionalSnapshot::factory()->create(['scope_year' => '2026', 'region' => 'HARRISBURG', 'context_hash' => 'aabb1122']);
    RegionalSnapshot::factory()->create(['scope_year' => '2026', 'region' => 'LANCASTER', 'context_hash' => 'aabb1122']);
    RegionalSnapshot::factory()->create(['scope_year' => '2025', 'region' => 'HARRISBURG', 'context_hash' => 'aabb1122']);

    expect(RegionalSnapshot::forYear('2026')->forRegion('HARRISBURG')->count())->toBe(1)
        ->and(RegionalSnapshot::forYear('2026')->forContext('aabb1122')->count())->toBe(2);
});

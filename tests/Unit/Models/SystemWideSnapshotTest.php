<?php

use App\Models\SystemWideSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('factory creates a valid system-wide snapshot', function () {
    $snapshot = SystemWideSnapshot::factory()->create();

    expect($snapshot)->toBeInstanceOf(SystemWideSnapshot::class)
        ->and($snapshot->id)->toBeGreaterThan(0)
        ->and($snapshot->scope_year)->toBeString()
        ->and($snapshot->context_hash)->toHaveLength(8)
        ->and($snapshot->captured_at)->not->toBeNull();
});

test('capturedAt factory state sets timestamp', function () {
    $timestamp = now()->subDays(7);
    $snapshot = SystemWideSnapshot::factory()->capturedAt($timestamp)->create();

    expect($snapshot->captured_at->toDateString())->toBe($timestamp->toDateString());
});

test('forYear scope filters by scope year', function () {
    SystemWideSnapshot::factory()->create(['scope_year' => '2025']);
    SystemWideSnapshot::factory()->create(['scope_year' => '2026']);
    SystemWideSnapshot::factory()->create(['scope_year' => '2026']);

    expect(SystemWideSnapshot::forYear('2026')->count())->toBe(2)
        ->and(SystemWideSnapshot::forYear('2025')->count())->toBe(1)
        ->and(SystemWideSnapshot::forYear('2024')->count())->toBe(0);
});

test('forContext scope filters by context hash', function () {
    SystemWideSnapshot::factory()->create(['context_hash' => 'aaaa1111']);
    SystemWideSnapshot::factory()->create(['context_hash' => 'aaaa1111']);
    SystemWideSnapshot::factory()->create(['context_hash' => 'bbbb2222']);

    expect(SystemWideSnapshot::forContext('aaaa1111')->count())->toBe(2)
        ->and(SystemWideSnapshot::forContext('bbbb2222')->count())->toBe(1);
});

test('integer columns are cast correctly', function () {
    $snapshot = SystemWideSnapshot::factory()->create([
        'total_assessments' => 100,
        'active_count' => 20,
    ]);

    $fresh = SystemWideSnapshot::find($snapshot->id);
    expect($fresh->total_assessments)->toBeInt()
        ->and($fresh->active_count)->toBeInt();
});

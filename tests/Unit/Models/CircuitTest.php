<?php

use App\Models\Circuit;
use App\Models\Region;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

test('factory creates a valid circuit', function () {
    $circuit = Circuit::factory()->create();

    expect($circuit)->toBeInstanceOf(Circuit::class)
        ->and($circuit->line_name)->not->toBeEmpty()
        ->and($circuit->is_active)->toBeTrue();
});

test('region relationship returns Region or null', function () {
    $orphan = Circuit::factory()->create();
    $linked = Circuit::factory()->withRegion()->create();

    expect($orphan->region)->toBeNull()
        ->and($linked->region)->toBeInstanceOf(Region::class);
});

test('active scope excludes inactive circuits', function () {
    Circuit::factory()->create(['line_name' => 'ACTIVE-001']);
    Circuit::factory()->inactive()->create(['line_name' => 'INACTIVE-001']);

    $active = Circuit::active()->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->line_name)->toBe('ACTIVE-001');
});

test('line_name must be unique', function () {
    Circuit::factory()->create(['line_name' => 'DUPLICATE-LINE']);

    Circuit::factory()->create(['line_name' => 'DUPLICATE-LINE']);
})->throws(\Illuminate\Database\QueryException::class);

test('properties is cast to array', function () {
    $circuit = Circuit::factory()->create([
        'properties' => ['2026' => ['some' => 'data']],
    ]);

    $fresh = $circuit->fresh();

    expect($fresh->properties)->toBeArray()
        ->and($fresh->properties['2026'])->toBe(['some' => 'data']);
});

test('last_trim and next_trim are cast to date', function () {
    $circuit = Circuit::factory()->create([
        'last_trim' => '2025-06-15',
        'next_trim' => '2026-06-15',
    ]);

    $fresh = $circuit->fresh();

    expect($fresh->last_trim)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($fresh->next_trim)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($fresh->last_trim->format('Y-m-d'))->toBe('2025-06-15');
});

<?php

use App\Models\Circuit;
use App\Models\Region;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

test('factory creates a valid region', function () {
    $region = Region::factory()->create();

    expect($region)->toBeInstanceOf(Region::class)
        ->and($region->name)->not->toBeEmpty()
        ->and($region->is_active)->toBeTrue();
});

test('active scope excludes inactive regions', function () {
    Region::factory()->create(['name' => 'ACTIVE_ONE']);
    Region::factory()->inactive()->create(['name' => 'INACTIVE_ONE']);

    $active = Region::active()->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->name)->toBe('ACTIVE_ONE');
});

test('circuits relationship returns circuit collection', function () {
    $region = Region::factory()->create();
    Circuit::factory()->count(3)->create(['region_id' => $region->id]);

    expect($region->circuits)->toHaveCount(3)
        ->each->toBeInstanceOf(Circuit::class);
});

test('name must be unique', function () {
    Region::factory()->create(['name' => 'DUPLICATE']);

    Region::factory()->create(['name' => 'DUPLICATE']);
})->throws(\Illuminate\Database\QueryException::class);

test('is_active is cast to boolean', function () {
    $region = Region::factory()->create(['is_active' => 1]);

    expect($region->fresh()->is_active)->toBeBool()->toBeTrue();
});

<?php

use App\Models\Region;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RegionSeeder;

test('region seeder creates 6 regions', function () {
    $this->seed(RegionSeeder::class);

    expect(Region::count())->toBe(6);
});

test('region seeder is idempotent', function () {
    $this->seed(RegionSeeder::class);
    $this->seed(RegionSeeder::class);

    expect(Region::count())->toBe(6);
});

test('all expected region names exist', function () {
    $this->seed(RegionSeeder::class);

    $expected = ['HARRISBURG', 'LANCASTER', 'LEHIGH', 'CENTRAL', 'SUSQUEHANNA', 'NORTHEAST'];
    $actual = Region::pluck('name')->sort()->values()->all();

    expect($actual)->toBe(collect($expected)->sort()->values()->all());
});

test('all regions are active by default', function () {
    $this->seed(RegionSeeder::class);

    expect(Region::where('is_active', false)->count())->toBe(0);
});

test('regions have correct sort order', function () {
    $this->seed(RegionSeeder::class);

    $ordered = Region::orderBy('sort_order')->pluck('name')->all();

    expect($ordered)->toBe(['HARRISBURG', 'LANCASTER', 'LEHIGH', 'CENTRAL', 'SUSQUEHANNA', 'NORTHEAST']);
});

test('circuit seeder skips gracefully when data file missing', function () {
    $this->seed(ReferenceDataSeeder::class);

    // Regions should be seeded, circuits should be 0 (no data file)
    expect(Region::count())->toBe(6)
        ->and(\App\Models\Circuit::count())->toBe(0);
});

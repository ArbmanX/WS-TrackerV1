<?php

use App\Models\Circuit;
use App\Models\Region;
use Database\Seeders\RegionSeeder;
use Illuminate\Support\Facades\Http;

/**
 * Sample API response in WorkStudio Heading/Data format.
 */
function fakeSampleResponse(): array
{
    return [
        'Heading' => ['line_name', 'region', 'total_miles'],
        'Data' => [
            ['CIRCUIT-001', 'Harrisburg', 5.12],
            ['CIRCUIT-002', 'Lancaster', 8.75],
            ['CIRCUIT-003', 'Unknown Region', null],
        ],
    ];
}

test('command exists with correct signature', function () {
    $this->artisan('ws:fetch-circuits --dry-run')
        ->assertSuccessful();
})->skip('Requires HTTP fake — tested below');

test('dry-run does not modify database', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeSampleResponse())]);

    $this->artisan('ws:fetch-circuits --dry-run')
        ->assertSuccessful();

    expect(Circuit::count())->toBe(0);
});

test('seed creates circuit records with region mapping', function () {
    $this->seed(RegionSeeder::class);

    Http::fake(['*/GETQUERY' => Http::response(fakeSampleResponse())]);

    $this->artisan('ws:fetch-circuits --seed')
        ->assertSuccessful();

    expect(Circuit::count())->toBe(3);

    $circuit1 = Circuit::where('line_name', 'CIRCUIT-001')->first();
    expect($circuit1->region->display_name)->toBe('Harrisburg');

    // Unknown region maps to null
    $circuit3 = Circuit::where('line_name', 'CIRCUIT-003')->first();
    expect($circuit3->region_id)->toBeNull();
});

test('seed initializes properties with scope year key', function () {
    $this->seed(RegionSeeder::class);

    Http::fake(['*/GETQUERY' => Http::response(fakeSampleResponse())]);

    $this->artisan('ws:fetch-circuits --seed --year=2026')
        ->assertSuccessful();

    $circuit = Circuit::first();
    expect($circuit->properties)->toBeArray()
        ->and($circuit->properties)->toHaveKey('2026')
        ->and($circuit->properties['2026'])->toHaveKey('total_miles');
});

test('seed preserves existing year keys on re-run', function () {
    $this->seed(RegionSeeder::class);

    Http::fake(['*/GETQUERY' => Http::response(fakeSampleResponse())]);

    // First fetch for 2025
    $this->artisan('ws:fetch-circuits --seed --year=2025');

    // Second fetch for 2026
    $this->artisan('ws:fetch-circuits --seed --year=2026');

    $circuit = Circuit::first();
    expect($circuit->properties)->toHaveKey('2025')
        ->and($circuit->properties)->toHaveKey('2026');
});

test('save creates data file', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeSampleResponse())]);

    $this->artisan('ws:fetch-circuits --save')
        ->assertSuccessful();

    expect(file_exists(database_path('data/circuits.php')))->toBeTrue();

    $data = require database_path('data/circuits.php');
    expect($data)->toBeArray()->toHaveCount(3);

    // Cleanup
    @unlink(database_path('data/circuits.php'));
});

test('handles API error response gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response([
        'protocol' => 'ERROR',
        'errorMessage' => 'Test error message',
    ])]);

    $this->artisan('ws:fetch-circuits --dry-run')
        ->assertFailed();
});

test('handles empty API response gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response(null, 200)]);

    $this->artisan('ws:fetch-circuits --dry-run')
        ->assertFailed();
});

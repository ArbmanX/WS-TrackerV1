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

test('default display does not modify database', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeSampleResponse())]);

    $this->artisan('ws:fetch-circuits')
        ->assertSuccessful();

    expect(Circuit::count())->toBe(0);
});

test('seed creates circuit records with region mapping', function () {
    $this->seed(RegionSeeder::class);

    Http::fake(['*/GETQUERY' => Http::response(fakeSampleResponse())]);

    $this->artisan('ws:fetch-circuits --seed')
        ->assertSuccessful();

    // Unknown region circuits are skipped (only 2 of 3 have valid regions)
    expect(Circuit::count())->toBe(2);

    $circuit1 = Circuit::where('line_name', 'CIRCUIT-001')->first();
    expect($circuit1->region->display_name)->toBe('Harrisburg');

    // Unknown region circuit is not created
    $circuit3 = Circuit::where('line_name', 'CIRCUIT-003')->first();
    expect($circuit3)->toBeNull();
});

test('seed initializes properties with raw_line_name', function () {
    $this->seed(RegionSeeder::class);

    Http::fake(['*/GETQUERY' => Http::response(fakeSampleResponse())]);

    $this->artisan('ws:fetch-circuits --seed')
        ->assertSuccessful();

    $circuit = Circuit::first();
    expect($circuit->properties)->toBeArray()
        ->and($circuit->properties)->toHaveKey('raw_line_name');
});

test('seed updates existing circuit on re-run', function () {
    $this->seed(RegionSeeder::class);

    Http::fake(['*/GETQUERY' => Http::response(fakeSampleResponse())]);

    $this->artisan('ws:fetch-circuits --seed');

    // Re-run should not duplicate
    $this->artisan('ws:fetch-circuits --seed');

    expect(Circuit::count())->toBe(2);
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

    $this->artisan('ws:fetch-circuits')
        ->assertFailed();
});

test('handles empty API response gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response(null, 200)]);

    $this->artisan('ws:fetch-circuits')
        ->assertFailed();
});

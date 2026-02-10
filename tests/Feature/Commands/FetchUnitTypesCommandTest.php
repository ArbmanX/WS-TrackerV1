<?php

use App\Models\UnitType;
use Illuminate\Support\Facades\Http;

function fakeUnitTypesResponse(?array $overrideData = null): array
{
    return [
        'Heading' => ['UNIT', 'UNITSSNAME', 'UNITSETID', 'SUMMARYGRP', 'ENTITYNAME'],
        'Data' => $overrideData ?? [
            ['1BRKR', 'Breaker', 'VEG', 'Summary-TRIM', 'TreeWork'],
            ['NW', 'Non Work', 'GENERAL', 'Summary-NonWork', 'NonWork'],
            ['SENSI', 'Sensitive Area', 'VEG', '', 'Environment'],
            ['2HNGR', 'Hanger', 'VEG', 'Summary', 'TreeWork'],
        ],
    ];
}

test('creates unit_type records from API response', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeUnitTypesResponse())]);

    $this->artisan('ws:fetch-unit-types')
        ->assertSuccessful();

    expect(UnitType::count())->toBe(4);
});

test('derives work_unit correctly from summarygrp', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeUnitTypesResponse())]);

    $this->artisan('ws:fetch-unit-types')->assertSuccessful();

    // Summary-TRIM → work unit
    expect(UnitType::where('unit', '1BRKR')->first()->work_unit)->toBeTrue();

    // Summary-NonWork → NOT work unit
    expect(UnitType::where('unit', 'NW')->first()->work_unit)->toBeFalse();

    // Empty string summarygrp → NOT work unit
    expect(UnitType::where('unit', 'SENSI')->first()->work_unit)->toBeFalse();

    // Summary → work unit
    expect(UnitType::where('unit', '2HNGR')->first()->work_unit)->toBeTrue();
});

test('null summarygrp is treated as non-work unit', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeUnitTypesResponse([
        ['BRIDGE', 'Bridge Crossing', 'GENERAL', null, 'Infrastructure'],
    ]))]);

    $this->artisan('ws:fetch-unit-types')->assertSuccessful();

    expect(UnitType::where('unit', 'BRIDGE')->first()->work_unit)->toBeFalse();
});

test('upserts existing records on re-run', function () {
    UnitType::factory()->create([
        'unit' => '1BRKR',
        'unitssname' => 'Old Name',
        'summarygrp' => 'Summary-TRIM',
        'work_unit' => true,
    ]);

    expect(UnitType::count())->toBe(1);

    Http::fake(['*/GETQUERY' => Http::response(fakeUnitTypesResponse())]);

    $this->artisan('ws:fetch-unit-types')->assertSuccessful();

    // 1 updated + 3 created = 4 total
    expect(UnitType::count())->toBe(4);

    $updated = UnitType::where('unit', '1BRKR')->first();
    expect($updated->unitssname)->toBe('Breaker');
});

test('dry-run does not modify database', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeUnitTypesResponse())]);

    $this->artisan('ws:fetch-unit-types --dry-run')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(UnitType::count())->toBe(0);
});

test('handles API error response', function () {
    Http::fake(['*/GETQUERY' => Http::response([
        'protocol' => 'ERROR',
        'errorMessage' => 'Access denied',
    ])]);

    $this->artisan('ws:fetch-unit-types')
        ->assertFailed();

    expect(UnitType::count())->toBe(0);
});

test('handles empty API response gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response([
        'Protocol' => 'QUERYRESULT',
    ])]);

    $this->artisan('ws:fetch-unit-types')
        ->expectsOutputToContain('No unit types')
        ->assertSuccessful();

    expect(UnitType::count())->toBe(0);
});

test('sets last_synced_at on created records', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeUnitTypesResponse([
        ['1BRKR', 'Breaker', 'VEG', 'Summary-TRIM', 'TreeWork'],
    ]))]);

    $this->artisan('ws:fetch-unit-types')->assertSuccessful();

    $unit = UnitType::where('unit', '1BRKR')->first();
    expect($unit->last_synced_at)->not->toBeNull();
});

test('stores all fields from API response', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeUnitTypesResponse([
        ['1BRKR', 'Breaker', 'VEG', 'Summary-TRIM', 'TreeWork'],
    ]))]);

    $this->artisan('ws:fetch-unit-types')->assertSuccessful();

    $unit = UnitType::where('unit', '1BRKR')->first();
    expect($unit->unit)->toBe('1BRKR')
        ->and($unit->unitssname)->toBe('Breaker')
        ->and($unit->unitsetid)->toBe('VEG')
        ->and($unit->summarygrp)->toBe('Summary-TRIM')
        ->and($unit->entityname)->toBe('TreeWork');
});

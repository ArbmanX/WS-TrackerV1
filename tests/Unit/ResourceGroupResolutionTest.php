<?php

use App\Services\WorkStudio\Shared\Services\ResourceGroupAccessService;

uses(Tests\TestCase::class);

test('resolves regions from explicit group_to_region_map', function () {
    $service = new ResourceGroupAccessService;

    $regions = $service->resolveRegionsFromGroups([
        'WorkStudio\\Everyone',
        'ASPLUNDH\\VEG_PLANNERS',
    ]);

    expect($regions)->toContain('CENTRAL', 'HARRISBURG', 'LEHIGH', 'LANCASTER');
    expect($regions)->toHaveCount(8);
});

test('strips domain prefix correctly', function () {
    $service = new ResourceGroupAccessService;

    // CENTRAL is in the 'all' list, so it should resolve directly
    $regions = $service->resolveRegionsFromGroups(['ASPLUNDH\\CENTRAL']);

    expect($regions)->toContain('CENTRAL');
});

test('resolves group that directly matches a known region', function () {
    $service = new ResourceGroupAccessService;

    $regions = $service->resolveRegionsFromGroups(['ANYDOM\\HARRISBURG']);

    expect($regions)->toContain('HARRISBURG');
});

test('ignores groups that do not match any mapping or region', function () {
    $service = new ResourceGroupAccessService;

    // 'Everyone' is not in map or in the known regions list
    $regions = $service->resolveRegionsFromGroups(['WorkStudio\\Everyone']);

    // Falls back to planner defaults because nothing resolved
    expect($regions)->toBe(config('workstudio_resource_groups.roles.planner'));
});

test('falls back to planner role when no groups resolve', function () {
    $service = new ResourceGroupAccessService;

    $regions = $service->resolveRegionsFromGroups([]);

    expect($regions)->toBe(config('workstudio_resource_groups.roles.planner'));
});

test('handles group without domain prefix', function () {
    $service = new ResourceGroupAccessService;

    $regions = $service->resolveRegionsFromGroups(['VEG_PLANNERS']);

    // VEG_PLANNERS is in the map, should resolve
    expect($regions)->toHaveCount(8);
});

test('deduplicates regions from multiple matching groups', function () {
    $service = new ResourceGroupAccessService;

    // VEG_PLANNERS maps to 8 regions including CENTRAL
    // CENTRAL also directly matches a known region
    $regions = $service->resolveRegionsFromGroups([
        'ASPLUNDH\\VEG_PLANNERS',
        'ASPLUNDH\\CENTRAL',
    ]);

    // Should still have 8 unique regions (CENTRAL appears once)
    expect($regions)->toHaveCount(8);
    expect(count($regions))->toBe(count(array_unique($regions)));
});

test('merges regions from multiple mapped groups', function () {
    // Temporarily add a second mapping via config
    config(['workstudio_resource_groups.group_to_region_map.VEG_CREW' => ['VEG_CREW', 'VEG_FOREMAN']]);

    $service = new ResourceGroupAccessService;

    $regions = $service->resolveRegionsFromGroups([
        'ASPLUNDH\\VEG_PLANNERS',
        'ASPLUNDH\\VEG_CREW',
    ]);

    // 8 from VEG_PLANNERS + 2 from VEG_CREW = 10 unique
    expect($regions)->toContain('VEG_CREW', 'VEG_FOREMAN', 'CENTRAL');
    expect($regions)->toHaveCount(10);
});

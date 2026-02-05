<?php

use App\Services\WorkStudio\Services\CachedQueryService;
use App\Services\WorkStudio\Services\GetQueryService;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->mockQuery = Mockery::mock(GetQueryService::class);
    $this->service = new CachedQueryService($this->mockQuery);
    Cache::flush();
});

test('caches results and serves from cache on second call', function () {
    $data = collect([['metric' => 'test_value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->once()
        ->andReturn($data);

    $first = $this->service->getSystemWideMetrics();
    $second = $this->service->getSystemWideMetrics();

    expect($first)->toEqual($data);
    expect($second)->toEqual($data);
});

test('forceRefresh busts cache and re-fetches', function () {
    $staleData = collect([['metric' => 'stale']]);
    $freshData = collect([['metric' => 'fresh']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->twice()
        ->andReturn($staleData, $freshData);

    $this->service->getSystemWideMetrics();
    $result = $this->service->getSystemWideMetrics(forceRefresh: true);

    expect($result)->toEqual($freshData);
});

test('invalidateAll clears all datasets', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')->andReturn($data);
    $this->mockQuery->shouldReceive('getRegionalMetrics')->andReturn($data);

    $this->service->getSystemWideMetrics();
    $this->service->getRegionalMetrics();

    $cleared = $this->service->invalidateAll();

    expect($cleared)->toBeGreaterThanOrEqual(2);
});

test('invalidateDataset clears a single dataset', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->twice()
        ->andReturn($data);

    $this->service->getSystemWideMetrics();
    $this->service->invalidateDataset('system_wide_metrics');

    // Should trigger a fresh call since cache was cleared
    $this->service->getSystemWideMetrics();
});

test('getCacheStatus returns correct per-dataset status', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')->andReturn($data);

    $this->service->getSystemWideMetrics();

    $status = $this->service->getCacheStatus();

    expect($status)->toHaveKey('system_wide_metrics');
    expect($status['system_wide_metrics']['cached'])->toBeTrue();
    expect($status['system_wide_metrics']['hit_count'])->toBe(0);
    expect($status['system_wide_metrics']['miss_count'])->toBe(1);
    expect($status['system_wide_metrics']['cached_at'])->not->toBeNull();
    expect($status['system_wide_metrics']['label'])->toBe('System-Wide Metrics');

    // Uncached dataset should show as not cached
    expect($status['regional_metrics']['cached'])->toBeFalse();
    expect($status['regional_metrics']['hit_count'])->toBe(0);
    expect($status['regional_metrics']['miss_count'])->toBe(0);
});

test('warmAll calls all methods and returns success per dataset', function () {
    $data = collect([['metric' => 'value']]);
    $datasets = config('ws_cache.datasets');

    foreach ($datasets as $definition) {
        $this->mockQuery->shouldReceive($definition['method'])->andReturn($data);
    }

    $results = $this->service->warmAll();

    expect($results)->toHaveCount(count($datasets));

    foreach ($results as $result) {
        expect($result['success'])->toBeTrue();
    }
});

test('warmAll handles individual failures gracefully', function () {
    $data = collect([['metric' => 'value']]);
    $datasets = config('ws_cache.datasets');

    $first = true;
    foreach ($datasets as $definition) {
        if ($first) {
            $this->mockQuery->shouldReceive($definition['method'])
                ->andThrow(new Exception('API Error'));
            $first = false;
        } else {
            $this->mockQuery->shouldReceive($definition['method'])->andReturn($data);
        }
    }

    $results = $this->service->warmAll();
    $keys = array_keys($datasets);

    expect($results[$keys[0]]['success'])->toBeFalse();
    expect($results[$keys[0]]['error'])->toBe('API Error');
    expect($results[$keys[1]]['success'])->toBeTrue();
});

test('cache key includes scope_year', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->once()
        ->andReturn($data);

    $this->service->getSystemWideMetrics();

    $scopeYear = config('ws_assessment_query.scope_year', date('Y'));

    expect(Cache::has("ws:{$scopeYear}:system_wide_metrics"))->toBeTrue();
});

test('getDriverName returns current store name', function () {
    $driver = $this->service->getDriverName();

    expect($driver)->toBe(config('cache.default'));
});

test('hit count increments on cache hits', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->once()
        ->andReturn($data);

    // First call is a miss
    $this->service->getSystemWideMetrics();
    // Second call is a hit
    $this->service->getSystemWideMetrics();
    // Third call is also a hit
    $this->service->getSystemWideMetrics();

    $status = $this->service->getCacheStatus();

    expect($status['system_wide_metrics']['miss_count'])->toBe(1);
    expect($status['system_wide_metrics']['hit_count'])->toBe(2);
});

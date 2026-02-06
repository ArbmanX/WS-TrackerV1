<?php

use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\Shared\Cache\CachedQueryService;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

function testContext(array $overrides = []): UserQueryContext
{
    return new UserQueryContext(
        resourceGroups: $overrides['resourceGroups'] ?? ['CENTRAL', 'HARRISBURG'],
        contractors: $overrides['contractors'] ?? ['Asplundh'],
        domain: $overrides['domain'] ?? 'ASPLUNDH',
        username: $overrides['username'] ?? 'jsmith',
        userId: $overrides['userId'] ?? 1,
    );
}

beforeEach(function () {
    $this->mockQuery = Mockery::mock(GetQueryService::class);
    $this->service = new CachedQueryService($this->mockQuery);
    $this->context = testContext();
    Cache::flush();
});

test('caches results and serves from cache on second call', function () {
    $data = collect([['metric' => 'test_value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->once()
        ->andReturn($data);

    $first = $this->service->getSystemWideMetrics($this->context);
    $second = $this->service->getSystemWideMetrics($this->context);

    expect($first)->toEqual($data);
    expect($second)->toEqual($data);
});

test('forceRefresh busts cache and re-fetches', function () {
    $staleData = collect([['metric' => 'stale']]);
    $freshData = collect([['metric' => 'fresh']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->twice()
        ->andReturn($staleData, $freshData);

    $this->service->getSystemWideMetrics($this->context);
    $result = $this->service->getSystemWideMetrics($this->context, forceRefresh: true);

    expect($result)->toEqual($freshData);
});

test('invalidateAll clears all datasets across tracked contexts', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')->andReturn($data);
    $this->mockQuery->shouldReceive('getRegionalMetrics')->andReturn($data);

    $this->service->getSystemWideMetrics($this->context);
    $this->service->getRegionalMetrics($this->context);

    $cleared = $this->service->invalidateAll();

    expect($cleared)->toBeGreaterThanOrEqual(2);
});

test('invalidateDataset clears a single dataset', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->twice()
        ->andReturn($data);

    $this->service->getSystemWideMetrics($this->context);
    $this->service->invalidateDataset('system_wide_metrics', $this->context);

    // Should trigger a fresh call since cache was cleared
    $this->service->getSystemWideMetrics($this->context);
});

test('getCacheStatus returns correct per-dataset status', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')->andReturn($data);

    $this->service->getSystemWideMetrics($this->context);

    $status = $this->service->getCacheStatus($this->context);

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

test('warmAllForContext calls all methods and returns success per dataset', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')->andReturn($data);
    $this->mockQuery->shouldReceive('getRegionalMetrics')->andReturn($data);
    $this->mockQuery->shouldReceive('getActiveAssessmentsOrderedByOldest')->andReturn($data);

    $results = $this->service->warmAllForContext($this->context);

    $datasets = config('ws_cache.datasets');
    expect($results)->toHaveCount(count($datasets));

    foreach ($results as $result) {
        expect($result['success'])->toBeTrue();
    }
});

test('warmAllForContext handles individual failures gracefully', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->andThrow(new Exception('API Error'));
    $this->mockQuery->shouldReceive('getRegionalMetrics')->andReturn($data);
    $this->mockQuery->shouldReceive('getActiveAssessmentsOrderedByOldest')->andReturn($data);

    $results = $this->service->warmAllForContext($this->context);
    $keys = array_keys(config('ws_cache.datasets'));

    expect($results[$keys[0]]['success'])->toBeFalse();
    expect($results[$keys[0]]['error'])->toBe('API Error');
    expect($results[$keys[1]]['success'])->toBeTrue();
});

test('cache key includes context hash', function () {
    $data = collect([['metric' => 'value']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->once()
        ->andReturn($data);

    $this->service->getSystemWideMetrics($this->context);

    $hash = substr($this->context->cacheHash(), 0, 8);
    $scopeYear = config('ws_assessment_query.scope_year', date('Y'));

    expect(Cache::has("ws:{$scopeYear}:ctx:{$hash}:system_wide_metrics"))->toBeTrue();
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
    $this->service->getSystemWideMetrics($this->context);
    // Second call is a hit
    $this->service->getSystemWideMetrics($this->context);
    // Third call is also a hit
    $this->service->getSystemWideMetrics($this->context);

    $status = $this->service->getCacheStatus($this->context);

    expect($status['system_wide_metrics']['miss_count'])->toBe(1);
    expect($status['system_wide_metrics']['hit_count'])->toBe(2);
});

test('different contexts get separate cache entries', function () {
    $data1 = collect([['metric' => 'user1_data']]);
    $data2 = collect([['metric' => 'user2_data']]);

    $context1 = testContext(['resourceGroups' => ['CENTRAL']]);
    $context2 = testContext(['resourceGroups' => ['HARRISBURG']]);

    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->twice()
        ->andReturn($data1, $data2);

    $result1 = $this->service->getSystemWideMetrics($context1);
    $result2 = $this->service->getSystemWideMetrics($context2);

    expect($result1)->toEqual($data1);
    expect($result2)->toEqual($data2);
});

test('identical contexts share cache entries', function () {
    $data = collect([['metric' => 'shared_data']]);

    $context1 = testContext(['username' => 'user1']);
    $context2 = testContext(['username' => 'user2']);

    // Same resource groups + contractors = same hash
    $this->mockQuery->shouldReceive('getSystemWideMetrics')
        ->once()
        ->andReturn($data);

    $result1 = $this->service->getSystemWideMetrics($context1);
    $result2 = $this->service->getSystemWideMetrics($context2);

    expect($result1)->toEqual($data);
    expect($result2)->toEqual($data);
});

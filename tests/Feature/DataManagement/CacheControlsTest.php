<?php

use App\Livewire\DataManagement\CacheControls;
use App\Services\WorkStudio\Shared\Cache\CachedQueryService;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $user = \App\Models\User::factory()->withWorkStudio()->withRole('sudo-admin')->create();
    \App\Models\UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);
    $this->actingAs($user);
});

function mockCacheService(): \Mockery\MockInterface
{
    $mock = Mockery::mock(CachedQueryService::class);

    $mock->shouldReceive('getCacheStatus')
        ->withArgs(fn ($context) => $context instanceof UserQueryContext)
        ->andReturn([
            'system_wide_metrics' => [
                'label' => 'System-Wide Metrics',
                'description' => 'Aggregated metrics across all regions.',
                'key' => 'ws:2026:ctx:a1b2c3d4:system_wide_metrics',
                'cached' => true,
                'cached_at' => now()->subMinutes(5)->toIso8601String(),
                'ttl_seconds' => 900,
                'ttl_remaining' => 600,
                'hit_count' => 12,
                'miss_count' => 3,
            ],
            'regional_metrics' => [
                'label' => 'Regional Metrics',
                'description' => 'Metrics grouped by region.',
                'key' => 'ws:2026:ctx:a1b2c3d4:regional_metrics',
                'cached' => false,
                'cached_at' => null,
                'ttl_seconds' => 900,
                'ttl_remaining' => null,
                'hit_count' => 0,
                'miss_count' => 0,
            ],
        ]);
    $mock->shouldReceive('getDriverName')->andReturn('database');

    app()->instance(CachedQueryService::class, $mock);

    return $mock;
}

test('guests cannot access cache controls', function () {
    auth()->logout();

    $this->get(route('data-management.cache'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit cache controls', function () {
    mockCacheService();

    Livewire::test(CacheControls::class)
        ->assertOk()
        ->assertSee('Cache Controls');
});

test('page displays cache driver', function () {
    mockCacheService();

    Livewire::test(CacheControls::class)
        ->assertSee('Database');
});

test('page displays all dataset rows', function () {
    mockCacheService();

    Livewire::test(CacheControls::class)
        ->assertSee('System-Wide Metrics')
        ->assertSee('Regional Metrics')
        ->assertSee('Cached')
        ->assertSee('Never Cached');
});

test('refreshDataset action works and shows flash', function () {
    $mock = mockCacheService();
    $mock->shouldReceive('invalidateDataset')
        ->withArgs(fn ($dataset, $context) => $dataset === 'system_wide_metrics' && $context instanceof UserQueryContext)
        ->once();
    $mock->shouldReceive('getSystemWideMetrics')
        ->withArgs(fn ($context) => $context instanceof UserQueryContext)
        ->once()
        ->andReturn(collect([]));

    Livewire::test(CacheControls::class)
        ->call('refreshDataset', 'system_wide_metrics')
        ->assertSee('Refreshed System-Wide Metrics successfully');
});

test('clearAll action works', function () {
    $mock = mockCacheService();
    $mock->shouldReceive('invalidateAll')->once()->andReturn(2);

    Livewire::test(CacheControls::class)
        ->call('clearAll')
        ->assertSee('Cleared 2 cached dataset(s)');
});

test('warmAll action works', function () {
    $mock = mockCacheService();
    $mock->shouldReceive('warmAllForContext')
        ->withArgs(fn ($context) => $context instanceof UserQueryContext)
        ->once()
        ->andReturn([
            'system_wide_metrics' => ['success' => true],
            'regional_metrics' => ['success' => true],
        ]);

    Livewire::test(CacheControls::class)
        ->call('warmAll')
        ->assertSee('Warmed all 2 datasets successfully');
});

test('refreshDataset rejects invalid dataset', function () {
    mockCacheService();

    Livewire::test(CacheControls::class)
        ->call('refreshDataset', 'nonexistent_dataset')
        ->assertSee('Invalid dataset');
});

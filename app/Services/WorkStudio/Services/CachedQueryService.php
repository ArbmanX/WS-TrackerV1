<?php

declare(strict_types=1);

namespace App\Services\WorkStudio\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CachedQueryService
{
    public function __construct(
        private GetQueryService $queryService,
    ) {}

    /**
     * Get system-wide aggregated metrics (cached).
     */
    public function getSystemWideMetrics(bool $forceRefresh = false): Collection
    {
        return $this->cached('system_wide_metrics', $forceRefresh, fn () => $this->queryService->getSystemWideMetrics());
    }

    /**
     * Get metrics grouped by region (cached).
     */
    public function getRegionalMetrics(bool $forceRefresh = false): Collection
    {
        return $this->cached('regional_metrics', $forceRefresh, fn () => $this->queryService->getRegionalMetrics());
    }

    /**
     * Get daily activity data for all assessments (cached).
     */
    public function getDailyActivitiesForAllAssessments(bool $forceRefresh = false): Collection
    {
        return $this->cached('daily_activities', $forceRefresh, fn () => $this->queryService->getDailyActivitiesForAllAssessments());
    }

    /**
     * Get active assessments ordered by oldest (cached).
     */
    public function getActiveAssessmentsOrderedByOldest(int $limit = 20, ?string $domain = null, bool $forceRefresh = false): Collection
    {
        return $this->cached('active_assessments', $forceRefresh, fn () => $this->queryService->getActiveAssessmentsOrderedByOldest($limit, $domain));
    }

    /**
     * Get all job GUIDs for the scope year (cached).
     */
    public function getJobGuids(bool $forceRefresh = false): Collection
    {
        return $this->cached('job_guids', $forceRefresh, fn () => $this->queryService->getJobGuids());
    }

    /**
     * Invalidate all WS cache keys and reset registry.
     */
    public function invalidateAll(): int
    {
        $datasets = array_keys(config('ws_cache.datasets'));
        $count = 0;

        foreach ($datasets as $dataset) {
            if (Cache::forget($this->cacheKey($dataset))) {
                $count++;
            }
        }

        Cache::forget($this->registryKey());

        return $count;
    }

    /**
     * Invalidate a single dataset cache.
     */
    public function invalidateDataset(string $dataset): void
    {
        Cache::forget($this->cacheKey($dataset));

        $registry = $this->getRegistry();
        unset($registry[$dataset]);
        $this->saveRegistry($registry);
    }

    /**
     * Pre-populate all dataset caches.
     *
     * @return array<string, array{success: bool, error?: string}>
     */
    public function warmAll(): array
    {
        $datasets = config('ws_cache.datasets');
        $results = [];

        foreach ($datasets as $key => $definition) {
            try {
                $method = $definition['method'];
                $this->queryService->{$method}();

                $registry = $this->getRegistry();
                $existing = $registry[$key] ?? ['hit_count' => 0, 'miss_count' => 0, 'cached_at' => null];
                $registry[$key] = array_merge(
                    ['hit_count' => 0, 'miss_count' => 0, 'cached_at' => null],
                    $existing,
                    [
                        'cached_at' => now()->toIso8601String(),
                        'miss_count' => ($existing['miss_count'] ?? 0) + 1,
                    ],
                );
                $this->saveRegistry($registry);

                Cache::put(
                    $this->cacheKey($key),
                    $this->queryService->{$method}(),
                    $this->ttl($key)
                );

                $results[$key] = ['success' => true];
            } catch (\Throwable $e) {
                Log::warning("CachedQueryService: warmAll failed for {$key}", [
                    'error' => $e->getMessage(),
                ]);
                $results[$key] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Get cache status for all datasets.
     *
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     key: string,
     *     cached: bool,
     *     cached_at: string|null,
     *     ttl_seconds: int,
     *     ttl_remaining: int|null,
     *     hit_count: int,
     *     miss_count: int,
     * }>
     */
    public function getCacheStatus(): array
    {
        $datasets = config('ws_cache.datasets');
        $registry = $this->getRegistry();
        $status = [];

        foreach ($datasets as $key => $definition) {
            $cacheKey = $this->cacheKey($key);
            $isCached = Cache::has($cacheKey);
            $meta = $registry[$key] ?? [];

            $ttlRemaining = null;
            if ($isCached && isset($meta['cached_at'])) {
                $cachedAt = \Carbon\Carbon::parse($meta['cached_at']);
                $expiresAt = $cachedAt->addSeconds($this->ttl($key));
                $ttlRemaining = max(0, (int) now()->diffInSeconds($expiresAt, false));
            }

            $status[$key] = [
                'label' => $definition['label'],
                'description' => $definition['description'],
                'key' => $cacheKey,
                'cached' => $isCached,
                'cached_at' => $meta['cached_at'] ?? null,
                'ttl_seconds' => $this->ttl($key),
                'ttl_remaining' => $ttlRemaining,
                'hit_count' => $meta['hit_count'] ?? 0,
                'miss_count' => $meta['miss_count'] ?? 0,
            ];
        }

        return $status;
    }

    /**
     * Get the current cache driver name.
     */
    public function getDriverName(): string
    {
        return config('cache.default', 'file');
    }

    /**
     * Resolve, cache, and track a dataset.
     */
    private function cached(string $dataset, bool $forceRefresh, \Closure $resolver): Collection
    {
        $key = $this->cacheKey($dataset);

        if ($forceRefresh) {
            Cache::forget($key);
        }

        $wasCached = Cache::has($key);

        $result = Cache::remember($key, $this->ttl($dataset), $resolver);

        $this->trackAccess($dataset, $wasCached);

        return $result;
    }

    /**
     * Track hit/miss for a dataset in the registry.
     */
    private function trackAccess(string $dataset, bool $wasHit): void
    {
        $registry = $this->getRegistry();

        $registry[$dataset] = array_merge(
            ['hit_count' => 0, 'miss_count' => 0, 'cached_at' => null],
            $registry[$dataset] ?? [],
        );

        if ($wasHit) {
            $registry[$dataset]['hit_count']++;
        } else {
            $registry[$dataset]['miss_count']++;
            $registry[$dataset]['cached_at'] = now()->toIso8601String();
        }

        $this->saveRegistry($registry);
    }

    /**
     * Build the full cache key for a dataset.
     */
    private function cacheKey(string $dataset): string
    {
        $prefix = config('ws_cache.prefix', 'ws');
        $scopeYear = config('ws_assessment_query.scope_year', date('Y'));

        return "{$prefix}:{$scopeYear}:{$dataset}";
    }

    /**
     * Build the registry cache key.
     */
    private function registryKey(): string
    {
        $prefix = config('ws_cache.prefix', 'ws');
        $scopeYear = config('ws_assessment_query.scope_year', date('Y'));
        $registryName = config('ws_cache.registry_key', '_cache_registry');

        return "{$prefix}:{$scopeYear}:{$registryName}";
    }

    /**
     * Get the TTL for a dataset in seconds.
     */
    private function ttl(string $dataset): int
    {
        return config("ws_cache.ttl.{$dataset}", 900);
    }

    /**
     * Load the registry from cache.
     *
     * @return array<string, array{cached_at: string|null, hit_count: int, miss_count: int}>
     */
    private function getRegistry(): array
    {
        return Cache::get($this->registryKey(), []);
    }

    /**
     * Save the registry to cache (long TTL — 24 hours).
     */
    private function saveRegistry(array $registry): void
    {
        Cache::put($this->registryKey(), $registry, 86400);
    }
}

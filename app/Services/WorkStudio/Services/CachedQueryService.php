<?php

declare(strict_types=1);

namespace App\Services\WorkStudio\Services;

use App\Services\WorkStudio\ValueObjects\UserQueryContext;
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
    public function getSystemWideMetrics(UserQueryContext $context, bool $forceRefresh = false): Collection
    {
        return $this->cached('system_wide_metrics', $context, $forceRefresh,
            fn () => $this->queryService->getSystemWideMetrics($context));
    }

    /**
     * Get metrics grouped by region (cached).
     */
    public function getRegionalMetrics(UserQueryContext $context, bool $forceRefresh = false): Collection
    {
        return $this->cached('regional_metrics', $context, $forceRefresh,
            fn () => $this->queryService->getRegionalMetrics($context));
    }

    /**
     * Get daily activity data for all assessments (cached).
     */
    public function getDailyActivitiesForAllAssessments(UserQueryContext $context, bool $forceRefresh = false): Collection
    {
        return $this->cached('daily_activities', $context, $forceRefresh,
            fn () => $this->queryService->getDailyActivitiesForAllAssessments($context));
    }

    /**
     * Get active assessments ordered by oldest (cached).
     */
    public function getActiveAssessmentsOrderedByOldest(UserQueryContext $context, int $limit = 20, bool $forceRefresh = false): Collection
    {
        return $this->cached('active_assessments', $context, $forceRefresh,
            fn () => $this->queryService->getActiveAssessmentsOrderedByOldest($context, $limit));
    }

    /**
     * Get all job GUIDs for the scope year (cached).
     */
    public function getJobGuids(UserQueryContext $context, bool $forceRefresh = false): Collection
    {
        return $this->cached('job_guids', $context, $forceRefresh,
            fn () => $this->queryService->getJobGuids($context));
    }

    /**
     * Invalidate all cache keys for a specific user context.
     */
    public function invalidateAllForContext(UserQueryContext $context): int
    {
        $datasets = array_keys(config('ws_cache.datasets'));
        $count = 0;

        foreach ($datasets as $dataset) {
            if (Cache::forget($this->cacheKey($dataset, $context))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Invalidate all WS cache keys across all tracked contexts.
     */
    public function invalidateAll(): int
    {
        $hashes = $this->getTrackedContextHashes();
        $datasets = array_keys(config('ws_cache.datasets'));
        $count = 0;

        foreach ($hashes as $hash) {
            foreach ($datasets as $dataset) {
                if (Cache::forget($this->cacheKeyFromHash($dataset, $hash))) {
                    $count++;
                }
            }
        }

        // Also clear legacy non-context keys (backward compat during transition)
        foreach ($datasets as $dataset) {
            $legacyKey = $this->legacyCacheKey($dataset);
            if (Cache::forget($legacyKey)) {
                $count++;
            }
        }

        Cache::forget($this->contextHashesKey());
        Cache::forget($this->registryKey());

        return $count;
    }

    /**
     * Invalidate a single dataset cache for a given context.
     */
    public function invalidateDataset(string $dataset, UserQueryContext $context): void
    {
        Cache::forget($this->cacheKey($dataset, $context));

        $registry = $this->getRegistry();
        $registryKey = $this->registryDatasetKey($dataset, $context);
        unset($registry[$registryKey]);
        $this->saveRegistry($registry);
    }

    /**
     * Pre-populate all dataset caches for a given context.
     *
     * @return array<string, array{success: bool, error?: string}>
     */
    public function warmAllForContext(UserQueryContext $context): array
    {
        $datasets = config('ws_cache.datasets');
        $results = [];

        foreach ($datasets as $key => $definition) {
            try {
                $method = $definition['method'];
                $data = $this->{$method}($context, forceRefresh: true);

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
     * Get cache status for all datasets for a given context.
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
    public function getCacheStatus(UserQueryContext $context): array
    {
        $datasets = config('ws_cache.datasets');
        $registry = $this->getRegistry();
        $status = [];

        foreach ($datasets as $key => $definition) {
            $cacheKey = $this->cacheKey($key, $context);
            $isCached = Cache::has($cacheKey);
            $registryKey = $this->registryDatasetKey($key, $context);
            $meta = $registry[$registryKey] ?? [];

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
     * Resolve, cache, and track a dataset with context-scoped key.
     */
    private function cached(string $dataset, UserQueryContext $context, bool $forceRefresh, \Closure $resolver): Collection
    {
        $key = $this->cacheKey($dataset, $context);

        if ($forceRefresh) {
            Cache::forget($key);
        }

        $wasCached = Cache::has($key);

        $result = Cache::remember($key, $this->ttl($dataset), $resolver);

        $this->trackAccess($dataset, $context, $wasCached);
        $this->trackContextHash($context);

        return $result;
    }

    /**
     * Track hit/miss for a dataset in the registry.
     */
    private function trackAccess(string $dataset, UserQueryContext $context, bool $wasHit): void
    {
        $registry = $this->getRegistry();
        $registryKey = $this->registryDatasetKey($dataset, $context);

        $registry[$registryKey] = array_merge(
            ['hit_count' => 0, 'miss_count' => 0, 'cached_at' => null],
            $registry[$registryKey] ?? [],
        );

        if ($wasHit) {
            $registry[$registryKey]['hit_count']++;
        } else {
            $registry[$registryKey]['miss_count']++;
            $registry[$registryKey]['cached_at'] = now()->toIso8601String();
        }

        $this->saveRegistry($registry);
    }

    /**
     * Track a context hash so invalidateAll() can find it later.
     */
    private function trackContextHash(UserQueryContext $context): void
    {
        $hash = substr($context->cacheHash(), 0, 8);
        $hashes = $this->getTrackedContextHashes();

        if (! in_array($hash, $hashes, true)) {
            $hashes[] = $hash;
            Cache::put($this->contextHashesKey(), $hashes, 86400);
        }
    }

    /**
     * Get all tracked context hashes.
     *
     * @return array<int, string>
     */
    private function getTrackedContextHashes(): array
    {
        return Cache::get($this->contextHashesKey(), []);
    }

    /**
     * Build the full cache key for a dataset scoped to a user context.
     */
    private function cacheKey(string $dataset, UserQueryContext $context): string
    {
        $hash = substr($context->cacheHash(), 0, 8);

        return $this->cacheKeyFromHash($dataset, $hash);
    }

    /**
     * Build cache key from a pre-computed hash.
     */
    private function cacheKeyFromHash(string $dataset, string $hash): string
    {
        $prefix = config('ws_cache.prefix', 'ws');
        $scopeYear = config('ws_assessment_query.scope_year', date('Y'));

        return "{$prefix}:{$scopeYear}:ctx:{$hash}:{$dataset}";
    }

    /**
     * Build legacy cache key (non-context) for backward compat cleanup.
     */
    private function legacyCacheKey(string $dataset): string
    {
        $prefix = config('ws_cache.prefix', 'ws');
        $scopeYear = config('ws_assessment_query.scope_year', date('Y'));

        return "{$prefix}:{$scopeYear}:{$dataset}";
    }

    /**
     * Build the registry dataset key (scoped to context hash).
     */
    private function registryDatasetKey(string $dataset, UserQueryContext $context): string
    {
        $hash = substr($context->cacheHash(), 0, 8);

        return "{$hash}:{$dataset}";
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
     * Build the key for tracking active context hashes.
     */
    private function contextHashesKey(): string
    {
        $prefix = config('ws_cache.prefix', 'ws');
        $scopeYear = config('ws_assessment_query.scope_year', date('Y'));

        return "{$prefix}:{$scopeYear}:_context_hashes";
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

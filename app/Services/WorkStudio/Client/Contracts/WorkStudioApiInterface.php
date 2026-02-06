<?php

namespace App\Services\WorkStudio\Client\Contracts;

use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Illuminate\Support\Collection;

/**
 * WorkStudio API Service Interface
 *
 * Defines the public contract for interacting with the WorkStudio API.
 * Implemented by WorkStudioApiService which delegates query execution to GetQueryService.
 */
interface WorkStudioApiInterface
{
    /**
     * Check if the WorkStudio API is reachable.
     */
    public function healthCheck(): bool;

    /**
     * Get the current API credentials info (without exposing password).
     *
     * @return array{type: string, username: string, user_id: int|null}
     */
    public function getCurrentCredentialsInfo(): array;

    /**
     * Execute a raw SQL query against the WorkStudio API.
     *
     * @param  string  $sql  The SQL query to execute
     * @param  int|null  $userId  Optional user ID for credentials
     * @return array|null The raw response data
     */
    public function executeQuery(string $sql, ?int $userId = null): ?array;

    /**
     * Get all job GUIDs for the entire scope year.
     *
     * @return Collection Collection of job GUID records
     */
    public function getJobGuids(UserQueryContext $context): Collection;

    /**
     * Get system-wide aggregated metrics.
     *
     * @return Collection Collection of system-wide metric data
     */
    public function getSystemWideMetrics(UserQueryContext $context): Collection;

    /**
     * Get metrics grouped by region.
     *
     * @return Collection Collection of regional metric data
     */
    public function getRegionalMetrics(UserQueryContext $context): Collection;

    /**
     * Get active assessments ordered by oldest assessed unit.
     *
     * @param  int  $limit  Number of results (default 50)
     * @return Collection Collection of active assessment records
     */
    public function getActiveAssessmentsOrderedByOldest(UserQueryContext $context, int $limit = 50): Collection;
}

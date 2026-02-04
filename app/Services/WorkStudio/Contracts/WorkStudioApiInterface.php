<?php

namespace App\Services\WorkStudio\Contracts;

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
    public function getJobGuids(): Collection;

    /**
     * Get system-wide aggregated metrics.
     *
     * @return Collection Collection of system-wide metric data
     */
    public function getSystemWideMetrics(): Collection;

    /**
     * Get metrics grouped by region.
     *
     * @return Collection Collection of regional metric data
     */
    public function getRegionalMetrics(): Collection;

    /**
     * Get active assessments ordered by oldest assessed unit.
     *
     * @param  int  $limit  Number of results (default 50)
     * @param  string|null  $domain  Domain filter (e.g., 'ASPLUNDH')
     * @return Collection Collection of active assessment records
     */
    public function getActiveAssessmentsOrderedByOldest(int $limit = 50, ?string $domain = null): Collection;
}

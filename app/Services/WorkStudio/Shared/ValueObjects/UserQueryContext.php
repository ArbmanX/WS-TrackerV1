<?php

declare(strict_types=1);

namespace App\Services\WorkStudio\Shared\ValueObjects;

use App\Models\User;
use App\Services\WorkStudio\Shared\Services\ResourceGroupAccessService;

/**
 * Immutable value object encapsulating user-specific query parameters.
 *
 * Two users with identical resource groups and contractors will produce
 * the same cacheHash(), allowing them to share cached query results.
 */
readonly class UserQueryContext
{
    /**
     * @param  array<int, string>  $resourceGroups  VEGJOB.REGION values (e.g., ['CENTRAL', 'HARRISBURG'])
     * @param  array<int, string>  $contractors  Contractor names for VEGJOB.CONTRACTOR filter
     * @param  string  $domain  User's WS domain (e.g., 'ASPLUNDH')
     * @param  string  $username  User's WS username
     * @param  int  $userId  Laravel user ID
     */
    public function __construct(
        public array $resourceGroups,
        public array $contractors,
        public string $domain,
        public string $username,
        public int $userId,
    ) {}

    /**
     * Build context from an authenticated user.
     *
     * Uses pre-computed ws_resource_groups if available, otherwise
     * resolves dynamically from ws_groups. Falls back to config defaults
     * for users who haven't completed onboarding.
     */
    public static function fromUser(User $user): self
    {
        if (! $user->ws_domain || ! $user->ws_username) {
            return self::fromConfig($user->id);
        }

        $resourceGroups = $user->ws_resource_groups;

        if (empty($resourceGroups) && ! empty($user->ws_groups)) {
            $resourceGroups = app(ResourceGroupAccessService::class)
                ->resolveRegionsFromGroups($user->ws_groups);
        }

        if (empty($resourceGroups)) {
            $resourceGroups = config('workstudio_resource_groups.roles.planner', []);
        }

        $contractors = [ucfirst(strtolower($user->ws_domain))];

        return new self(
            resourceGroups: $resourceGroups,
            contractors: $contractors,
            domain: strtoupper($user->ws_domain),
            username: $user->ws_username,
            userId: $user->id,
        );
    }

    /**
     * Fallback: build context from static config values (backward compat).
     */
    public static function fromConfig(?int $userId = null): self
    {
        return new self(
            resourceGroups: config('workstudio_resource_groups.roles.planner', []),
            contractors: config('ws_assessment_query.contractors', ['Asplundh']),
            domain: strtoupper(config('ws_assessment_query.contractors.0', 'ASPLUNDH')),
            username: 'system',
            userId: $userId ?? 0,
        );
    }

    /**
     * Deterministic hash for cache key scoping.
     *
     * Identical resource groups + contractors = identical hash = shared cache.
     */
    public function cacheHash(): string
    {
        $normalized = [
            'resourceGroups' => $this->sortedArray($this->resourceGroups),
            'contractors' => $this->sortedArray($this->contractors),
        ];

        return md5(json_encode($normalized));
    }

    /**
     * Context is valid when at least one region and one contractor exist.
     */
    public function isValid(): bool
    {
        return count($this->resourceGroups) > 0 && count($this->contractors) > 0;
    }

    /**
     * Sort array values for deterministic hashing.
     *
     * @return array<int, string>
     */
    private function sortedArray(array $items): array
    {
        $sorted = $items;
        sort($sorted);

        return array_values($sorted);
    }
}

<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;

/**
 * Facade for assessment query classes.
 *
 * Delegates to focused domain classes while preserving the original API
 * for backward compatibility with GetQueryService and other callers.
 *
 * @see AggregateQueries  System/region-level rollups
 * @see CircuitQueries    Per-circuit data and lookups by JOBGUID
 * @see ActivityQueries   Planner activity tracking
 * @see LookupQueries     Dynamic field value lookups
 */
class AssessmentQueries
{
    private AggregateQueries $aggregates;

    private CircuitQueries $circuits;

    private ActivityQueries $activities;

    private LookupQueries $lookups;

    public function __construct(private readonly UserQueryContext $context)
    {
        $this->aggregates = new AggregateQueries($context);
        $this->circuits = new CircuitQueries($context);
        $this->activities = new ActivityQueries($context);
        $this->lookups = new LookupQueries($context);
    }

    public function systemWideDataQuery(): string
    {
        return $this->aggregates->systemWideDataQuery();
    }

    public function groupedByRegionDataQuery(): string
    {
        return $this->aggregates->groupedByRegionDataQuery();
    }

    public function groupedByCircuitDataQuery(): string
    {
        return $this->circuits->groupedByCircuitDataQuery();
    }

    public function getAllByJobGuid(string $jobGuid): string
    {
        return $this->circuits->getAllByJobGuid($jobGuid);
    }

    public function getAllJobGUIDsForEntireScopeYear(): string
    {
        return $this->circuits->getAllJobGUIDsForEntireScopeYear();
    }

    public function getAllAssessmentsDailyActivities(): string
    {
        return $this->activities->getAllAssessmentsDailyActivities();
    }

    public function getActiveAssessmentsOrderedByOldest(int $limit = 5): string
    {
        return $this->activities->getActiveAssessmentsOrderedByOldest($limit);
    }

    public function getDistinctFieldValues(string $table, string $field, int $limit = 500): string
    {
        return $this->lookups->getDistinctFieldValues($table, $field, $limit);
    }
}

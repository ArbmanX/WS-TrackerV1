<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;

abstract class AbstractQueryBuilder
{
    use SqlFragmentHelpers;

    protected string $resourceGroupsSql;

    protected string $contractorsSql;

    protected string $excludedUsersSql;

    protected string $jobTypesSql;

    protected string $cycleTypesSql;

    protected string $scopeYear;

    protected string $domainFilter;

    protected string $excludedCycleTypesSql;

    public function __construct(protected readonly UserQueryContext $context)
    {
        $this->resourceGroupsSql = WSHelpers::toSqlInClause($context->resourceGroups);
        $this->contractorsSql = WSHelpers::toSqlInClause($context->contractors);
        $this->domainFilter = $context->domain;

        // System-level values stay in config
        $this->excludedUsersSql = WSHelpers::toSqlInClause(config('ws_assessment_query.excludedUsers'));
        $this->jobTypesSql = WSHelpers::toSqlInClause(config('ws_assessment_query.job_types.assessments'));
        $this->cycleTypesSql = WSHelpers::toSqlInClause(config('ws_assessment_query.cycle_types.maintenance'));
        $this->excludedCycleTypesSql = WSHelpers::toSqlInClause(config('ws_assessment_query.cycle_types.excluded_from_assessments'));
        $this->scopeYear = config('ws_assessment_query.scope_year');
    }
}

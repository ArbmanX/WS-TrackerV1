<?php

namespace App\Console\Commands\Traits;

use App\Services\WorkStudio\Assessments\Queries\AdditionalMetricsQueries;
use App\Services\WorkStudio\Client\GetQueryService;
use Illuminate\Support\Collection;


trait GetsAdditionalAssessmentMetrics
{
    

    public function getAdditionalMetrics(GetQueryService $queries, array $jobGuids) : Collection 
    {
            $sql = AdditionalMetricsQueries::buildBatched($jobGuids);

            return $queries->executeAndHandle($sql);

        return collect();
    }
}

<?php

namespace App\Console\Commands\Traits;

use App\Services\WorkStudio\Assessments\Queries\WinningUnitQuery;
use App\Services\WorkStudio\Client\GetQueryService;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait GetsDailyFootage
{
    use InteractsWithIO;
    /**
     * Fetch daily footage data from WorkStudio API, chunking the JOBGUID list.
     *
     * @param  array<int, string>  $jobGuids
     * @return Collection<int, array<string, mixed>>
     */
    public function getDailyFootageInChunks(GetQueryService $queryService, array $jobGuids, int $chunkSize = 200): Collection
    {
        $total = count($jobGuids);

        if ($total === 0) {
            $this->warn('No JOBGUIDs provided — skipping daily footage fetch.');

            return collect();
        }

        $this->info("Fetching daily footage for {$total} JOBGUIDs in chunks of {$chunkSize}...");

        $allResults = collect();

        foreach (array_chunk($jobGuids, $chunkSize) as $chunk) {
            $allResults = $allResults->merge(
                $this->getResults($queryService, $chunk)->filter()
            );
        }

        $this->info("Daily footage complete: {$allResults->count()} records returned.");

        return $allResults;
    }

    public function getResults(GetQueryService $queryService, array $jobGuids): Collection
    {
        return $queryService->executeAndHandle(
            WinningUnitQuery::build($jobGuids)
        );
    }
}
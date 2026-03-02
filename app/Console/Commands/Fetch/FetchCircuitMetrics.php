<?php

namespace App\Console\Commands\Fetch;

use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class FetchCircuitMetrics extends Command
{
    protected $signature = 'ws:fetch-circuit-metrics
        {--save : Save results to JSON file in storage}
        {--dry-run : Preview first 20 rows without saving}';

    protected $description = 'Fetch circuit-level grouped metrics (permissions, work measurements) from WorkStudio API';

    public function handle(GetQueryService $queryService): int
    {
        $context = UserQueryContext::fromConfig();

        $this->info('Fetching circuit metrics from WorkStudio...');

        $results = $queryService->getCircuitMetrics($context);

        $this->info("Received {$results->count()} circuit records.");

        if ($results->isEmpty()) {
            $this->warn('No circuit data returned from API.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->displayPreview($results);

            return self::SUCCESS;
        }

        if ($this->option('save')) {
            $this->saveToJson($results);
        } else {
            $this->displayPreview($results);
        }

        return self::SUCCESS;
    }

    private function displayPreview(Collection $rows): void
    {
        $preview = $rows->take(20)->map(fn (array $row) => [
            $row['Work_Order'] ?? '',
            $row['Extension'] ?? '',
            $row['Line_Name'] ?? '',
            $row['Region'] ?? '',
            $row['Status'] ?? '',
            $row['Total_Miles'] ?? '',
            $row['Percent_Complete'] ?? '',
            $row['Total_Units'] ?? '',
        ]);

        $this->table(
            ['WO', 'Ext', 'Line Name', 'Region', 'Status', 'Miles', '% Done', 'Units'],
            $preview->toArray()
        );

        if ($rows->count() > 20) {
            $this->info("Showing first 20 of {$rows->count()} circuits.");
        }
    }

    private function saveToJson(Collection $rows): void
    {
        $filename = 'circuit-metrics/circuit_metrics_'.now()->format('Y_m_d_His').'.json';

        Storage::put($filename, $rows->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Saved {$rows->count()} circuits to {$filename}");
    }
}

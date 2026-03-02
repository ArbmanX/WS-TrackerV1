<?php

namespace App\Console\Commands\Fetch;

use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FetchCircuitByJobGuid extends Command
{
    protected $signature = 'ws:fetch-circuit
        {jobguid : The JOBGUID to query}
        {--save : Save result to JSON file in storage}';

    protected $description = 'Fetch full circuit detail (stations, units, daily records) for a single JOBGUID';

    public function handle(GetQueryService $queryService): int
    {
        $jobGuid = $this->argument('jobguid');
        $context = UserQueryContext::fromConfig();

        $this->info("Fetching circuit data for JOBGUID: {$jobGuid}");

        try {
            $result = $queryService->getCircuitByJobGuid($context, $jobGuid);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result->isEmpty()) {
            $this->warn('No data returned for this JOBGUID.');

            return self::SUCCESS;
        }

        $data = $result->first();

        $this->displaySummary($data);

        if ($this->option('save')) {
            $this->saveToJson($data, $jobGuid);
        }

        return self::SUCCESS;
    }

    private function displaySummary(array $data): void
    {
        $this->table(['Field', 'Value'], [
            ['Line Name', $data['Line_Name'] ?? '—'],
            ['Work Order', ($data['Work_Order'] ?? '').' / '.($data['Extension'] ?? '')],
            ['Status', $data['Status'] ?? '—'],
            ['Region', $data['Region'] ?? '—'],
            ['Cycle Type', $data['Cycle_Type'] ?? '—'],
            ['Job Type', $data['Job_Type'] ?? '—'],
            ['Contractor', $data['Contractor'] ?? '—'],
            ['Forester', $data['Forester'] ?? '—'],
            ['Total Miles', $data['Total_Miles'] ?? '—'],
            ['Completed Miles', $data['Completed_Miles'] ?? '—'],
            ['% Complete', ($data['Percent_Complete'] ?? '—').'%'],
            ['Total Footage', $data['Total_Footage'] ?? '—'],
            ['Units Planned', $data['Total_Units_Planned'] ?? '—'],
            ['Approvals', $data['Total_Approvals'] ?? '—'],
            ['Pending', $data['Total_Pending'] ?? '—'],
            ['No Contacts', $data['Total_No_Contacts'] ?? '—'],
            ['Refusals', $data['Total_Refusals'] ?? '—'],
            ['Last Sync', $data['Last_Sync'] ?? '—'],
        ]);

        $stationCount = is_array($data['Stations'] ?? null) ? count($data['Stations']) : 0;
        $dailyCount = is_array($data['Daily_Records'] ?? null) ? count($data['Daily_Records']) : 0;

        $this->info("Stations: {$stationCount} | Daily records: {$dailyCount}");
    }

    private function saveToJson(array $data, string $jobGuid): void
    {
        $safeGuid = str_replace(['{', '}'], '', $jobGuid);
        $filename = "circuit-detail/{$safeGuid}.json";

        Storage::put($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Saved to {$filename}");
    }
}

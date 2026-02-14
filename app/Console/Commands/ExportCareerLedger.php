<?php

namespace App\Console\Commands;

use App\Services\WorkStudio\DataCollection\CareerLedgerService;
use Illuminate\Console\Command;

class ExportCareerLedger extends Command
{
    protected $signature = 'ws:export-career-ledger
        {--path= : Output path (default: config)}
        {--scope-year= : Filter by scope year}
        {--region= : Filter by region}';

    protected $description = 'Export career ledger entries to a JSON file';

    public function handle(CareerLedgerService $service): int
    {
        $path = $this->option('path') ?: config('ws_data_collection.career_ledger.bootstrap_path');

        if ($this->option('scope-year') || $this->option('region')) {
            $this->info('Note: --scope-year and --region filters are not yet implemented. Exporting all entries.');
        }

        $this->warn('This may take a while for large datasets (makes API calls).');
        $this->info("Exporting career ledger to {$path}...");

        try {
            $count = $service->exportToJson($path);
        } catch (\Throwable $e) {
            $this->error("Export failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Exported {$count} career entries to {$path}");

        return self::SUCCESS;
    }
}

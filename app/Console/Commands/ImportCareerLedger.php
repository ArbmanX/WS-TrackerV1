<?php

namespace App\Console\Commands;

use App\Services\WorkStudio\DataCollection\CareerLedgerService;
use Illuminate\Console\Command;

class ImportCareerLedger extends Command
{
    protected $signature = 'ws:import-career-ledger
        {--path= : Path to JSON file (default: config)}
        {--dry-run : Show what would be imported without writing}';

    protected $description = 'Import career ledger entries from a JSON bootstrap file';

    public function handle(CareerLedgerService $service): int
    {
        $path = $this->option('path') ?: config('ws_data_collection.career_ledger.bootstrap_path');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $fileSize = number_format(filesize($path) / 1024, 1);
        $this->info("Reading {$path} ({$fileSize} KB)...");

        if ($this->option('dry-run')) {
            return $this->dryRun($path);
        }

        try {
            $result = $service->importFromJson($path);
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Imported: {$result['imported']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}");

        return self::SUCCESS;
    }

    private function dryRun(string $path): int
    {
        $contents = file_get_contents($path);
        $entries = json_decode($contents, true);

        if (! is_array($entries)) {
            $this->error('Invalid JSON: expected an array of entries.');

            return self::FAILURE;
        }

        $count = count($entries);
        $this->info("Found {$count} entries in file.");

        if ($count > 0) {
            $sample = array_slice($entries, 0, 5);
            $rows = collect($sample)->map(fn (array $entry) => [
                $entry['planner'] ?? '-',
                $entry['job_guid'] ?? '-',
                $entry['unit_type'] ?? '-',
                $entry['scope_year'] ?? '-',
            ]);

            $this->table(['planner', 'job_guid', 'unit_type', 'scope_year'], $rows->toArray());
        }

        $this->warn("Dry run — {$count} entries would be imported. No changes made.");

        return self::SUCCESS;
    }
}

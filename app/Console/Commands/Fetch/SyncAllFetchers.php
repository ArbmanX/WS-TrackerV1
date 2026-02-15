<?php

namespace App\Console\Commands\Fetch;

use Illuminate\Console\Command;

class SyncAllFetchers extends Command
{
    protected $signature = 'ws:sync-all-fetchers
        {--year= : Scope year (passed to year-aware fetchers)}';

    protected $description = 'Run all WorkStudio fetch commands in dependency order with --seed/--save';

    /**
     * Ordered list of fetch commands.
     * persist: 'seed' for DB upsert, 'save' for file export.
     *
     * @var array<int, array{command: string, label: string, passYear: bool, persist: string}>
     */
    private array $steps = [
        ['command' => 'ws:fetch-circuits',    'label' => 'Circuits',    'passYear' => true,  'persist' => 'seed'],
        ['command' => 'ws:fetch-unit-types',  'label' => 'Unit Types',  'passYear' => false, 'persist' => 'seed'],
        ['command' => 'ws:fetch-users',       'label' => 'WS Users',    'passYear' => true,  'persist' => 'seed'],
        ['command' => 'ws:fetch-jobs',        'label' => 'SS Jobs',     'passYear' => true,  'persist' => 'seed'],
        ['command' => 'ws:fetch-job-types',   'label' => 'Job Types',   'passYear' => false, 'persist' => 'save'],
        ['command' => 'ws:fetch-cycle-types', 'label' => 'Cycle Types', 'passYear' => false, 'persist' => 'save'],
    ];

    public function handle(): int
    {
        $year = $this->option('year');
        $totalSteps = count($this->steps);

        $this->info("Running {$totalSteps} fetch commands in dependency order...");
        $this->newLine();

        $failed = [];
        $succeeded = [];

        foreach ($this->steps as $index => $step) {
            $stepNumber = $index + 1;
            $this->info("[{$stepNumber}/{$totalSteps}] Fetching {$step['label']}...");

            $arguments = $this->buildArguments($step, $year);

            $exitCode = $this->runStep($step['command'], $arguments);

            if ($exitCode !== self::SUCCESS) {
                $failed[] = $step['label'];
                $this->error("  ✗ {$step['label']} failed (exit code: {$exitCode})");
            } else {
                $succeeded[] = $step['label'];
                $this->info("  ✓ {$step['label']} complete");
            }

            $this->newLine();
        }

        $this->displaySummary($succeeded, $failed);

        return empty($failed) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Build the arguments array for a given step.
     *
     * @param  array{command: string, label: string, passYear: bool, persist: string}  $step
     * @return array<string, mixed>
     */
    private function buildArguments(array $step, ?string $year): array
    {
        $arguments = [];

        // Always persist when running the orchestrator
        $arguments["--{$step['persist']}"] = true;

        if ($year && $step['passYear']) {
            $arguments['--year'] = $year;
        }

        return $arguments;
    }

    /**
     * Execute a single fetch step and return its exit code.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function runStep(string $command, array $arguments): int
    {
        $exitCode = $this->call($command, $arguments);

        if ($exitCode !== self::SUCCESS) {
            $this->error("Error running {$command}: exit code {$exitCode}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Display final summary of all steps.
     *
     * @param  array<int, string>  $succeeded
     * @param  array<int, string>  $failed
     */
    private function displaySummary(array $succeeded, array $failed): void
    {
        $this->newLine();
        $this->info('=== Sync Summary ===');
        $this->info('Succeeded: '.count($succeeded).' / '.(count($succeeded) + count($failed)));

        if (! empty($failed)) {
            $this->error('Failed: '.implode(', ', $failed));
        }
    }
}

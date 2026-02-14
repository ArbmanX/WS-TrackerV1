<?php

namespace App\Console\Commands;

use App\Services\WorkStudio\Planners\PlannerCareerLedgerService;
use Illuminate\Console\Command;

class ExportPlannerCareer extends Command
{
    protected $signature = 'ws:export-planner-career
        {users?* : One or more FRSTR_USER usernames}
        {--output= : Output directory (default: storage/app/career)}
        {--current : Export active/QC/rework assessments instead of closed}
        {--all-years : Discover assessments across all years (default: scoped to config scope_year)}';

    protected $description = 'Export per-planner career data from assessments';

    public function handle(PlannerCareerLedgerService $service): int
    {
        $users = $this->argument('users');

        if (empty($users)) {
            $this->error('At least one FRSTR_USER username is required.');

            return self::FAILURE;
        }

        $current = $this->option('current');
        $allYears = $this->option('all-years');
        $outputDir = $this->option('output') ?: storage_path('app/career');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $mode = $current ? 'current (active/QC/rework)' : 'closed';
        $yearScope = $allYears ? 'all years' : 'scope year '.config('ws_assessment_query.scope_year');
        $this->info("Discovering {$mode} job assignments ({$yearScope}) for: ".implode(', ', $users));
        $this->info('Incremental mode: previously exported data will be updated if stale.');
        $this->warn('This makes API calls and may take a while.');

        try {
            $discovered = $service->discoverJobGuids($users, $current, $allYears);
            $this->info("Discovered {$discovered->count()} job assignment(s).");
        } catch (\Throwable $e) {
            $this->error("Discovery failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Exporting career data...');

        $results = $service->exportForUsers($users, $outputDir, $current);

        foreach ($results as $user => $path) {
            if (str_starts_with($path, 'ERROR:')) {
                $this->error("  {$user}: {$path}");
            } else {
                $this->info("  {$user}: {$path}");
            }
        }

        $successCount = collect($results)->reject(fn ($p) => str_starts_with($p, 'ERROR:'))->count();
        $this->info("Exported {$successCount}/".count($users).' planner career file(s).');

        return $successCount === count($users) ? self::SUCCESS : self::FAILURE;
    }
}

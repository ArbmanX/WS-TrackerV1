<?php

namespace App\Console\Commands;

use App\Services\WorkStudio\Planners\PlannerCareerLedgerService;
use Illuminate\Console\Command;

class ExportPlannerCareer extends Command
{
    protected $signature = 'ws:export-planner-career
        {users?* : One or more FRSTR_USER usernames}
        {--output= : Output directory (default: storage/app/{domain}/planners/career)}
        {--scope-year : Constrain discovery to configured scope year (default: all years)}';

    protected $description = 'Export per-planner career data from assessments';

    public function handle(PlannerCareerLedgerService $service): int
    {
        $users = $this->argument('users');

        if (empty($users)) {
            $this->error('At least one FRSTR_USER username is required.');

            return self::FAILURE;
        }

        $scopeYear = null;
        if ($this->option('scope-year')) {
            $scopeYear = (int) (config('ws_assessment_query.scope_year') ?: date('Y'));
        }

        $domain = strtolower($this->extractDomain($users[0]));
        $outputDir = $this->option('output') ?: storage_path("app/{$domain}/planners/career");

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $yearScope = $scopeYear !== null ? "scope year {$scopeYear}" : 'all years';
        $this->info("Discovering job assignments ({$yearScope}) for: ".implode(', ', $users));
        $this->info('Incremental mode: previously exported data will be updated if stale.');
        $this->warn('This makes API calls and may take a while.');

        try {
            $discovered = $service->discoverJobGuids($users, $scopeYear);
            $this->info("Discovered {$discovered->count()} job assignment(s).");
        } catch (\Throwable $e) {
            $this->error("Discovery failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Exporting career data...');

        $results = $service->exportForUsers($users, $outputDir);

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

    private function extractDomain(string $user): string
    {
        $domain = str_contains($user, '\\')
            ? substr($user, 0, strrpos($user, '\\'))
            : config('ws_assessment_query.contractors.0', 'asplundh');

        return preg_replace('/\s+/', '_', trim($domain));
    }
}

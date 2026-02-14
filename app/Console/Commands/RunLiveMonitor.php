<?php

namespace App\Console\Commands;

use App\Services\WorkStudio\DataCollection\GhostDetectionService;
use App\Services\WorkStudio\DataCollection\LiveMonitorService;
use Illuminate\Console\Command;

class RunLiveMonitor extends Command
{
    protected $signature = 'ws:run-live-monitor
        {--job-guid= : Snapshot a single assessment}
        {--include-ghost : Also run ghost detection checks}';

    protected $description = 'Run daily live monitor snapshots and optional ghost detection';

    public function handle(LiveMonitorService $monitor, GhostDetectionService $ghost): int
    {
        $jobGuid = $this->option('job-guid');

        if ($jobGuid) {
            $monitor->snapshotAssessment($jobGuid, []);
            $this->info("Snapshot completed for assessment {$jobGuid}.");
        } else {
            $result = $monitor->runDailySnapshot();
            $this->info("Snapshots: {$result['snapshots']}, New monitors: {$result['new']}, Closed: {$result['closed']}");
        }

        if ($this->option('include-ghost')) {
            $ownershipChanges = $ghost->checkForOwnershipChanges();

            $newGhosts = 0;
            foreach (\App\Models\GhostOwnershipPeriod::active()->get() as $period) {
                $newGhosts += $ghost->runComparison($period);
            }

            $this->info("Ghost checks: {$ownershipChanges} ownership changes, {$newGhosts} new ghost units");
        }

        return self::SUCCESS;
    }
}

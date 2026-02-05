<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WorkStudio\Services\ResourceGroupAccessService;
use Illuminate\Console\Command;

class BackfillUserResourceGroups extends Command
{
    protected $signature = 'ws:backfill-resource-groups {--dry-run : Show what would be updated without saving}';

    protected $description = 'Backfill ws_resource_groups for onboarded users who have ws_groups but no ws_resource_groups';

    public function handle(ResourceGroupAccessService $service): int
    {
        $query = User::whereNotNull('ws_validated_at')
            ->whereNull('ws_resource_groups')
            ->whereNotNull('ws_groups');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No users need backfilling.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} user(s) to backfill.");

        $updated = 0;

        $query->chunk(50, function ($users) use ($service, &$updated) {
            foreach ($users as $user) {
                $regions = $service->resolveRegionsFromGroups($user->ws_groups ?? []);

                if ($this->option('dry-run')) {
                    $this->line("  [DRY RUN] {$user->ws_username} → ".implode(', ', $regions));

                    continue;
                }

                $user->update(['ws_resource_groups' => $regions]);
                $updated++;
                $this->line("  Updated {$user->ws_username} → ".implode(', ', $regions));
            }
        });

        if ($this->option('dry-run')) {
            $this->warn("Dry run complete. {$count} user(s) would be updated.");
        } else {
            $this->info("Backfilled {$updated} user(s) successfully.");
        }

        return self::SUCCESS;
    }
}

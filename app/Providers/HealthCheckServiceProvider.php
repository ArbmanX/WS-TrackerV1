<?php

namespace App\Providers;

use App\Health\Checks\WorkStudioApiCheck;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class HealthCheckServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $checks = [
            // Database connectivity
            DatabaseCheck::new()
                ->name('Database'),

            // Cache functionality
            CacheCheck::new()
                ->name('Cache'),

            // Disk space (warn at 70%, fail at 90%)
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90)
                ->name('Disk Space'),

            // WorkStudio API reachability
            WorkStudioApiCheck::new()
                ->timeout(15)
                ->name('WorkStudio API'),
        ];

        // Production-only checks (environment & debug mode)
        if (app()->environment('production', 'staging')) {
            $checks[] = EnvironmentCheck::new()
                ->expectEnvironment('production')
                ->name('Environment');

            $checks[] = DebugModeCheck::new()
                ->name('Debug Mode');
        }

        Health::checks($checks);
    }
}

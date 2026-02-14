<?php

namespace App\Providers;

use App\Services\WorkStudio\Client\Contracts\WorkStudioApiInterface;
use App\Services\WorkStudio\Client\WorkStudioApiService;
use App\Services\WorkStudio\DataCollection\CareerLedgerService;
use App\Services\WorkStudio\DataCollection\GhostDetectionService;
use App\Services\WorkStudio\DataCollection\LiveMonitorService;
use App\Services\WorkStudio\Shared\Cache\CachedQueryService;
use App\Services\WorkStudio\Shared\Contracts\UserDetailsServiceInterface;
use App\Services\WorkStudio\Shared\Services\UserDetailsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class WorkStudioServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            WorkStudioApiInterface::class,
            WorkStudioApiService::class
        );

        $this->app->bind(
            UserDetailsServiceInterface::class,
            UserDetailsService::class
        );

        $this->app->singleton(CachedQueryService::class);

        $this->app->singleton(CareerLedgerService::class);
        $this->app->singleton(LiveMonitorService::class);
        $this->app->singleton(GhostDetectionService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Http::macro('workstudio', function () {
            return Http::timeout(config('workstudio.timeout', 60))
                ->connectTimeout(config('workstudio.connect_timeout', 10))
                ->withOptions(['verify' => false]);
        });
    }
}

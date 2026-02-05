<?php

namespace App\Providers;

use App\Services\WorkStudio\Contracts\UserDetailsServiceInterface;
use App\Services\WorkStudio\Contracts\WorkStudioApiInterface;
use App\Services\WorkStudio\Services\CachedQueryService;
use App\Services\WorkStudio\Services\UserDetailsService;
use App\Services\WorkStudio\WorkStudioApiService;
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

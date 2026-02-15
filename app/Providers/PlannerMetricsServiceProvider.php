<?php

namespace App\Providers;

use App\Services\PlannerMetrics\CoachingMessageGenerator;
use App\Services\PlannerMetrics\Contracts\CoachingMessageGeneratorInterface;
use App\Services\PlannerMetrics\Contracts\PlannerMetricsServiceInterface;
use App\Services\PlannerMetrics\PlannerMetricsService;
use Illuminate\Support\ServiceProvider;

class PlannerMetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PlannerMetricsServiceInterface::class, PlannerMetricsService::class);
        $this->app->bind(CoachingMessageGeneratorInterface::class, CoachingMessageGenerator::class);
    }
}

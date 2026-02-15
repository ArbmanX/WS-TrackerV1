<?php

use App\Livewire\PlannerMetrics\Overview;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Planner Metrics Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'onboarding'])
    ->prefix('planner-metrics')
    ->name('planner-metrics.')
    ->group(function () {
        Route::get('/', Overview::class)->name('overview');
    });

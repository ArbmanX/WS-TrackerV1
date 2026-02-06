<?php

use App\Livewire\Onboarding\ChangePassword;
use App\Livewire\Onboarding\WorkStudioSetup;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Onboarding Routes
|--------------------------------------------------------------------------
| These routes handle the user onboarding flow (password change, WorkStudio setup).
| They require authentication but not the onboarding middleware.
*/
Route::middleware(['auth'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/password', ChangePassword::class)->name('password');
    Route::get('/workstudio', WorkStudioSetup::class)->name('workstudio');
});

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
| Dashboard and other protected routes are defined in workstudioAPI.php
*/

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
*/
// JSON endpoint for monitoring services (no auth required for external monitors)
Route::get('/health', HealthCheckJsonResultsController::class)
    ->name('health.json');

// HTML dashboard (admin only)
Route::get('/health/dashboard', HealthCheckResultsController::class)
    ->middleware(['auth', 'permission:access-health-dashboard'])
    ->name('health.dashboard');

require __DIR__.'/workstudioAPI.php';
require __DIR__.'/data-management.php';

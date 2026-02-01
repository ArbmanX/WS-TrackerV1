<?php

use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
    ->middleware(['auth'])
    ->name('health.dashboard');

require __DIR__.'/workstudioAPI.php';
require __DIR__.'/settings.php';

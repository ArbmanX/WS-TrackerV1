<?php

use App\Livewire\Dashboard\Overview;
use App\Services\WorkStudio\Services\GetQueryService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
*/
Route::get('dashboard', Overview::class)
    ->middleware(['auth', 'verified', 'onboarding'])
    ->name('dashboard');

// Testing route - no auth required (remove in production)
Route::get('dashboard/test', Overview::class)
    ->name('dashboard.test');

/*
|--------------------------------------------------------------------------
| WorkStudio API Routes
|--------------------------------------------------------------------------
*/
Route::get('/assessment-jobguids', function (GetQueryService $queryService) {
    $data = $queryService->getJobGuids();
    if (config('app.debug')) {
        dump($data);
    }

    return response()->json($data);
});

Route::get('/system-wide-metrics', function (GetQueryService $queryService) {
    $data = $queryService->getSystemWideMetrics();
    if (config('app.debug')) {
        dump($data->first());
    }

    return response()->json($data);
});

Route::get('/regional-metrics', function (GetQueryService $queryService) {
    $data = $queryService->getRegionalMetrics();
    if (config('app.debug')) {
        dump($data);
    }

    return response()->json($data);
});

Route::get('/daily-activities/all-assessments', function (GetQueryService $queryService) {
    $data = $queryService->getDailyActivitiesForAllAssessments();
    if (config('app.debug')) {
        dump($data);
    }

    return response()->json($data);
});

Route::get('/allByJobGUID', function (GetQueryService $queryService) {
    $data = $queryService->getAll();
    if (config('app.debug')) {
        dump($data);
    }

    return response()->json($data);
});

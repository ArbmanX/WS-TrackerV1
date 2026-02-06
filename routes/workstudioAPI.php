<?php

use App\Livewire\Dashboard\Overview;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
*/
Route::get('dashboard', Overview::class)
    ->middleware(['auth', 'verified', 'onboarding'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| WorkStudio API Routes (Auth Protected)
|--------------------------------------------------------------------------
| These routes require authentication. They build a UserQueryContext from
| the authenticated user to scope all queries to the user's access level.
| TODO: CLN-009 — Refactor closures to use UserQueryContext properly.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/assessment-jobguids', function (GetQueryService $queryService) {
        $context = UserQueryContext::fromUser(auth()->user());
        $data = $queryService->getJobGuids($context);

        return response()->json($data);
    });

    Route::get('/system-wide-metrics', function (GetQueryService $queryService) {
        $context = UserQueryContext::fromUser(auth()->user());
        $data = $queryService->getSystemWideMetrics($context);

        return response()->json($data);
    });

    Route::get('/regional-metrics', function (GetQueryService $queryService) {
        $context = UserQueryContext::fromUser(auth()->user());
        $data = $queryService->getRegionalMetrics($context);

        return response()->json($data);
    });

    Route::get('/daily-activities/all-assessments', function (GetQueryService $queryService) {
        $context = UserQueryContext::fromUser(auth()->user());
        $data = $queryService->getDailyActivitiesForAllAssessments($context);

        return response()->json($data);
    });

    Route::get('/field-lookup/{table}/{field}', function (GetQueryService $queryService, string $table, string $field) {
        $context = UserQueryContext::fromUser(auth()->user());
        $data = $queryService->getDistinctFieldValues($context, $table, $field);

        return response()->json($data);
    });
});

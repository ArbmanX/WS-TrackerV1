<?php

use App\Services\WorkStudio\Services\GetQueryService;
use Illuminate\Support\Facades\Route;

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

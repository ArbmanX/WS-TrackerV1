<?php

use Illuminate\Support\Facades\Route;
use App\Services\WorkStudio\Services\GetQueryService;

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
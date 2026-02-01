<?php

use App\Services\WorkStudio\Managers\ApiCredentialManager;
use App\Services\WorkStudio\Services\GetQueryService;
use App\Services\WorkStudio\WorkStudioApiService;
use Illuminate\Support\Collection;

describe('WorkStudioApiService', function () {
    test('delegates executeQuery to GetQueryService', function () {
        $mockQueryService = Mockery::mock(GetQueryService::class);
        $mockCredentialManager = Mockery::mock(ApiCredentialManager::class);

        $expectedResult = ['data' => 'test'];
        $mockQueryService->shouldReceive('executeQuery')
            ->with('SELECT * FROM test', 1)
            ->once()
            ->andReturn($expectedResult);

        $service = new WorkStudioApiService($mockCredentialManager, $mockQueryService);
        $result = $service->executeQuery('SELECT * FROM test', 1);

        expect($result)->toBe($expectedResult);
    });

    test('delegates getJobGuids to GetQueryService', function () {
        $mockQueryService = Mockery::mock(GetQueryService::class);
        $mockCredentialManager = Mockery::mock(ApiCredentialManager::class);

        $expectedCollection = collect(['guid1', 'guid2']);
        $mockQueryService->shouldReceive('getJobGuids')
            ->once()
            ->andReturn($expectedCollection);

        $service = new WorkStudioApiService($mockCredentialManager, $mockQueryService);
        $result = $service->getJobGuids();

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toEqual($expectedCollection);
    });

    test('delegates getSystemWideMetrics to GetQueryService', function () {
        $mockQueryService = Mockery::mock(GetQueryService::class);
        $mockCredentialManager = Mockery::mock(ApiCredentialManager::class);

        $expectedCollection = collect(['metric' => 100]);
        $mockQueryService->shouldReceive('getSystemWideMetrics')
            ->once()
            ->andReturn($expectedCollection);

        $service = new WorkStudioApiService($mockCredentialManager, $mockQueryService);
        $result = $service->getSystemWideMetrics();

        expect($result)->toBeInstanceOf(Collection::class);
    });

    test('delegates getRegionalMetrics to GetQueryService', function () {
        $mockQueryService = Mockery::mock(GetQueryService::class);
        $mockCredentialManager = Mockery::mock(ApiCredentialManager::class);

        $expectedCollection = collect([['region' => 'North', 'count' => 50]]);
        $mockQueryService->shouldReceive('getRegionalMetrics')
            ->once()
            ->andReturn($expectedCollection);

        $service = new WorkStudioApiService($mockCredentialManager, $mockQueryService);
        $result = $service->getRegionalMetrics();

        expect($result)->toBeInstanceOf(Collection::class);
    });
});

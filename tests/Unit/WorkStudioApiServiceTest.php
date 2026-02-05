<?php

use App\Services\WorkStudio\Managers\ApiCredentialManager;
use App\Services\WorkStudio\Services\GetQueryService;
use App\Services\WorkStudio\ValueObjects\UserQueryContext;
use App\Services\WorkStudio\WorkStudioApiService;
use Illuminate\Support\Collection;

function makeTestContext(): UserQueryContext
{
    return new UserQueryContext(
        resourceGroups: ['CENTRAL', 'HARRISBURG'],
        contractors: ['Asplundh'],
        domain: 'ASPLUNDH',
        username: 'jsmith',
        userId: 1,
    );
}

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
        $context = makeTestContext();

        $expectedCollection = collect(['guid1', 'guid2']);
        $mockQueryService->shouldReceive('getJobGuids')
            ->withArgs(fn (UserQueryContext $ctx) => $ctx === $context)
            ->once()
            ->andReturn($expectedCollection);

        $service = new WorkStudioApiService($mockCredentialManager, $mockQueryService);
        $result = $service->getJobGuids($context);

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toEqual($expectedCollection);
    });

    test('delegates getSystemWideMetrics to GetQueryService', function () {
        $mockQueryService = Mockery::mock(GetQueryService::class);
        $mockCredentialManager = Mockery::mock(ApiCredentialManager::class);
        $context = makeTestContext();

        $expectedCollection = collect(['metric' => 100]);
        $mockQueryService->shouldReceive('getSystemWideMetrics')
            ->withArgs(fn (UserQueryContext $ctx) => $ctx === $context)
            ->once()
            ->andReturn($expectedCollection);

        $service = new WorkStudioApiService($mockCredentialManager, $mockQueryService);
        $result = $service->getSystemWideMetrics($context);

        expect($result)->toBeInstanceOf(Collection::class);
    });

    test('delegates getRegionalMetrics to GetQueryService', function () {
        $mockQueryService = Mockery::mock(GetQueryService::class);
        $mockCredentialManager = Mockery::mock(ApiCredentialManager::class);
        $context = makeTestContext();

        $expectedCollection = collect([['region' => 'North', 'count' => 50]]);
        $mockQueryService->shouldReceive('getRegionalMetrics')
            ->withArgs(fn (UserQueryContext $ctx) => $ctx === $context)
            ->once()
            ->andReturn($expectedCollection);

        $service = new WorkStudioApiService($mockCredentialManager, $mockQueryService);
        $result = $service->getRegionalMetrics($context);

        expect($result)->toBeInstanceOf(Collection::class);
    });

    test('delegates getActiveAssessmentsOrderedByOldest to GetQueryService', function () {
        $mockQueryService = Mockery::mock(GetQueryService::class);
        $mockCredentialManager = Mockery::mock(ApiCredentialManager::class);
        $context = makeTestContext();

        $expectedCollection = collect([['owner' => 'jsmith', 'line' => 'Test Line']]);
        $mockQueryService->shouldReceive('getActiveAssessmentsOrderedByOldest')
            ->withArgs(fn (UserQueryContext $ctx, int $limit) => $ctx === $context && $limit === 25)
            ->once()
            ->andReturn($expectedCollection);

        $service = new WorkStudioApiService($mockCredentialManager, $mockQueryService);
        $result = $service->getActiveAssessmentsOrderedByOldest($context, 25);

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toEqual($expectedCollection);
    });
});

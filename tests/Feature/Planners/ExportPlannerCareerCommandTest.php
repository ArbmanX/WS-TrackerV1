<?php

use App\Services\WorkStudio\Client\GetQueryService;

// ─── Argument Handling ───────────────────────────────────────────────────────

test('command fails when no users are provided', function () {
    $this->artisan('ws:export-planner-career')
        ->expectsOutput('At least one FRSTR_USER username is required.')
        ->assertExitCode(1);
});

test('command accepts user arguments and creates output directory', function () {
    $outputDir = sys_get_temp_dir().'/planner_career_test_'.uniqid();

    $mockQS = Mockery::mock(GetQueryService::class);
    $mockQS->shouldReceive('executeAndHandle')
        ->andReturn(collect());

    $this->app->bind(GetQueryService::class, fn () => $mockQS);

    $this->artisan('ws:export-planner-career', [
        'users' => ['jsmith'],
        '--output' => $outputDir,
    ])->assertExitCode(0);

    expect(is_dir($outputDir))->toBeTrue();

    // Clean up
    array_map('unlink', glob($outputDir.'/*'));
    rmdir($outputDir);
});

test('command outputs discovery info for all years by default', function () {
    $mockQS = Mockery::mock(GetQueryService::class);
    $mockQS->shouldReceive('executeAndHandle')
        ->andReturn(collect());

    $this->app->bind(GetQueryService::class, fn () => $mockQS);

    $outputDir = sys_get_temp_dir().'/planner_career_test_'.uniqid();

    $this->artisan('ws:export-planner-career', [
        'users' => ['jsmith'],
        '--output' => $outputDir,
    ])
        ->expectsOutputToContain('Discovering job assignments (all years)')
        ->expectsOutputToContain('Discovered 0 job assignment(s)')
        ->assertExitCode(0);

    array_map('unlink', glob($outputDir.'/*'));
    rmdir($outputDir);
});

test('command with --scope-year flag outputs scoped year info', function () {
    $mockQS = Mockery::mock(GetQueryService::class);
    $mockQS->shouldReceive('executeAndHandle')
        ->andReturn(collect());

    $this->app->bind(GetQueryService::class, fn () => $mockQS);

    $outputDir = sys_get_temp_dir().'/planner_career_test_'.uniqid();

    $this->artisan('ws:export-planner-career', [
        'users' => ['jsmith'],
        '--output' => $outputDir,
        '--scope-year' => true,
    ])
        ->expectsOutputToContain('Discovering job assignments (scope year')
        ->assertExitCode(0);

    array_map('unlink', glob($outputDir.'/*'));
    rmdir($outputDir);
});

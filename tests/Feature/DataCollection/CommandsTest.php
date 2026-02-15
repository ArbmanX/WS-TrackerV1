<?php

use App\Models\GhostOwnershipPeriod;
use App\Services\WorkStudio\DataCollection\GhostDetectionService;
use App\Services\WorkStudio\DataCollection\LiveMonitorService;

// --- RunLiveMonitor ---

test('live monitor runs daily snapshot by default', function () {
    $mockMonitor = Mockery::mock(LiveMonitorService::class);
    $mockMonitor->shouldReceive('runDailySnapshot')
        ->once()
        ->andReturn(['snapshots' => 5, 'new' => 2, 'closed' => 1]);
    $this->app->instance(LiveMonitorService::class, $mockMonitor);

    $mockGhost = Mockery::mock(GhostDetectionService::class);
    $this->app->instance(GhostDetectionService::class, $mockGhost);

    $this->artisan('ws:run-live-monitor')
        ->expectsOutputToContain('Snapshots: 5, New monitors: 2, Closed: 1')
        ->assertSuccessful();
});

test('live monitor snapshots single assessment with --job-guid', function () {
    $guid = '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}';

    $mockMonitor = Mockery::mock(LiveMonitorService::class);
    $mockMonitor->shouldReceive('snapshotAssessment')
        ->once()
        ->with($guid, []);
    $this->app->instance(LiveMonitorService::class, $mockMonitor);

    $mockGhost = Mockery::mock(GhostDetectionService::class);
    $this->app->instance(GhostDetectionService::class, $mockGhost);

    $this->artisan('ws:run-live-monitor', ['--job-guid' => $guid])
        ->expectsOutputToContain("Snapshot completed for assessment {$guid}")
        ->assertSuccessful();
});

test('live monitor runs ghost detection with --include-ghost', function () {
    $mockMonitor = Mockery::mock(LiveMonitorService::class);
    $mockMonitor->shouldReceive('runDailySnapshot')
        ->once()
        ->andReturn(['snapshots' => 3, 'new' => 1, 'closed' => 0]);
    $this->app->instance(LiveMonitorService::class, $mockMonitor);

    $mockGhost = Mockery::mock(GhostDetectionService::class);
    $mockGhost->shouldReceive('checkForOwnershipChanges')
        ->once()
        ->andReturn(2);
    $mockGhost->shouldReceive('runComparison')->never();
    $this->app->instance(GhostDetectionService::class, $mockGhost);

    // No active periods in DB — runComparison won't be called
    $this->artisan('ws:run-live-monitor', ['--include-ghost' => true])
        ->expectsOutputToContain('Snapshots: 3')
        ->expectsOutputToContain('Ghost checks: 2 ownership changes, 0 new ghost units')
        ->assertSuccessful();
});

test('live monitor ghost detection iterates active periods', function () {
    $period = GhostOwnershipPeriod::factory()->create();

    $mockMonitor = Mockery::mock(LiveMonitorService::class);
    $mockMonitor->shouldReceive('runDailySnapshot')
        ->once()
        ->andReturn(['snapshots' => 1, 'new' => 0, 'closed' => 0]);
    $this->app->instance(LiveMonitorService::class, $mockMonitor);

    $mockGhost = Mockery::mock(GhostDetectionService::class);
    $mockGhost->shouldReceive('checkForOwnershipChanges')
        ->once()
        ->andReturn(0);
    $mockGhost->shouldReceive('runComparison')
        ->once()
        ->with(Mockery::on(fn ($p) => $p->id === $period->id))
        ->andReturn(3);
    $this->app->instance(GhostDetectionService::class, $mockGhost);

    $this->artisan('ws:run-live-monitor', ['--include-ghost' => true])
        ->expectsOutputToContain('Ghost checks: 0 ownership changes, 3 new ghost units')
        ->assertSuccessful();
});

test('live monitor without --include-ghost skips ghost detection', function () {
    $mockMonitor = Mockery::mock(LiveMonitorService::class);
    $mockMonitor->shouldReceive('runDailySnapshot')
        ->once()
        ->andReturn(['snapshots' => 2, 'new' => 0, 'closed' => 0]);
    $this->app->instance(LiveMonitorService::class, $mockMonitor);

    $mockGhost = Mockery::mock(GhostDetectionService::class);
    $mockGhost->shouldNotReceive('checkForOwnershipChanges');
    $mockGhost->shouldNotReceive('runComparison');
    $this->app->instance(GhostDetectionService::class, $mockGhost);

    $this->artisan('ws:run-live-monitor')
        ->doesntExpectOutputToContain('Ghost checks')
        ->assertSuccessful();
});

<?php

use App\Models\GhostOwnershipPeriod;
use App\Services\WorkStudio\DataCollection\CareerLedgerService;
use App\Services\WorkStudio\DataCollection\GhostDetectionService;
use App\Services\WorkStudio\DataCollection\LiveMonitorService;

// --- ImportCareerLedger ---

test('import command imports entries from JSON file', function () {
    $data = [
        [
            'planner_username' => 'jsmith',
            'planner_display_name' => 'ASPLUNDH\\jsmith',
            'job_guid' => '{11111111-1111-1111-1111-111111111111}',
            'line_name' => 'Circuit-1234',
            'region' => 'CENTRAL',
            'scope_year' => '2026',
            'cycle_type' => 'Trim',
        ],
    ];

    $path = tempnam(sys_get_temp_dir(), 'career_').'.json';
    file_put_contents($path, json_encode($data));

    $mock = Mockery::mock(CareerLedgerService::class);
    $mock->shouldReceive('importFromJson')
        ->once()
        ->with($path)
        ->andReturn(['imported' => 1, 'skipped' => 0, 'errors' => 0]);
    $this->app->instance(CareerLedgerService::class, $mock);

    $this->artisan('ws:import-career-ledger', ['--path' => $path])
        ->expectsOutputToContain('Imported: 1, Skipped: 0, Errors: 0')
        ->assertSuccessful();

    unlink($path);
});

test('import command dry-run shows preview without writing', function () {
    $data = [
        ['planner' => 'jsmith', 'job_guid' => '{GUID-1}', 'unit_type' => 'SPM', 'scope_year' => '2026'],
        ['planner' => 'jdoe', 'job_guid' => '{GUID-2}', 'unit_type' => 'TRIM', 'scope_year' => '2026'],
    ];

    $path = tempnam(sys_get_temp_dir(), 'career_').'.json';
    file_put_contents($path, json_encode($data));

    $this->artisan('ws:import-career-ledger', ['--path' => $path, '--dry-run' => true])
        ->expectsOutputToContain('2 entries would be imported')
        ->assertSuccessful();

    unlink($path);
});

test('import command fails when file not found', function () {
    $this->artisan('ws:import-career-ledger', ['--path' => '/tmp/nonexistent.json'])
        ->expectsOutputToContain('File not found')
        ->assertFailed();
});

test('import command uses config default path', function () {
    // Override config to a guaranteed-nonexistent path so the test is filesystem-independent
    config()->set('ws_data_collection.career_ledger.bootstrap_path', '/tmp/nonexistent_bootstrap_'.uniqid().'.json');

    $this->artisan('ws:import-career-ledger')
        ->expectsOutputToContain('File not found')
        ->assertFailed();
});

test('import command reports failure on service exception', function () {
    $data = [['planner' => 'test']];
    $path = tempnam(sys_get_temp_dir(), 'career_').'.json';
    file_put_contents($path, json_encode($data));

    $mock = Mockery::mock(CareerLedgerService::class);
    $mock->shouldReceive('importFromJson')
        ->once()
        ->andThrow(new \RuntimeException('Database connection failed'));
    $this->app->instance(CareerLedgerService::class, $mock);

    $this->artisan('ws:import-career-ledger', ['--path' => $path])
        ->expectsOutputToContain('Import failed: Database connection failed')
        ->assertFailed();

    unlink($path);
});

// --- ExportCareerLedger ---

test('export command calls service and reports count', function () {
    $mock = Mockery::mock(CareerLedgerService::class);
    $mock->shouldReceive('exportToJson')
        ->once()
        ->andReturn(5);
    $this->app->instance(CareerLedgerService::class, $mock);

    $this->artisan('ws:export-career-ledger')
        ->expectsOutputToContain('Exported 5 career entries')
        ->assertSuccessful();
});

test('export command accepts custom path', function () {
    $path = tempnam(sys_get_temp_dir(), 'export_').'.json';

    $mock = Mockery::mock(CareerLedgerService::class);
    $mock->shouldReceive('exportToJson')
        ->once()
        ->with($path)
        ->andReturn(3);
    $this->app->instance(CareerLedgerService::class, $mock);

    $this->artisan('ws:export-career-ledger', ['--path' => $path])
        ->expectsOutputToContain('Exported 3 career entries')
        ->assertSuccessful();
});

test('export command shows info when filter options provided', function () {
    $mock = Mockery::mock(CareerLedgerService::class);
    $mock->shouldReceive('exportToJson')->once()->andReturn(0);
    $this->app->instance(CareerLedgerService::class, $mock);

    $this->artisan('ws:export-career-ledger', ['--scope-year' => '2026', '--region' => 'CENTRAL'])
        ->expectsOutputToContain('not yet implemented')
        ->assertSuccessful();
});

test('export command reports failure on service exception', function () {
    $mock = Mockery::mock(CareerLedgerService::class);
    $mock->shouldReceive('exportToJson')
        ->once()
        ->andThrow(new \RuntimeException('API timeout'));
    $this->app->instance(CareerLedgerService::class, $mock);

    $this->artisan('ws:export-career-ledger')
        ->expectsOutputToContain('Export failed: API timeout')
        ->assertFailed();
});

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

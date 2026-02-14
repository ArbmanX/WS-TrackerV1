<?php

use App\Events\AssessmentClosed;
use App\Models\AssessmentMonitor;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\DataCollection\LiveMonitorService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->mockQS = Mockery::mock(GetQueryService::class);
});

function makeLiveMonitorService($mock): LiveMonitorService
{
    return new LiveMonitorService($mock);
}

/**
 * Helper: build a single combined snapshot mock return.
 *
 * Returns a collect wrapping one flat row matching getDailySnapshot() columns,
 * with work_type_breakdown as a JSON string (FOR JSON PATH output).
 */
function snapshotMockReturn(int $totalUnits = 50): \Illuminate\Support\Collection
{
    return collect([[
        'total_units' => $totalUnits,
        'approved' => 30,
        'pending' => 10,
        'refused' => 1,
        'no_contact' => 5,
        'deferred' => 2,
        'ppl_approved' => 0,
        'work_units' => 40,
        'nw_units' => 10,
        'units_requiring_notes' => 20,
        'units_with_notes' => 15,
        'units_without_notes' => 5,
        'compliance_percent' => 75.0,
        'last_edit_date' => '2026-02-12',
        'last_edit_by' => 'jsmith',
        'pending_over_threshold' => 3,
        'work_type_breakdown' => '[{"unit":"SPM","UnitQty":15}]',
    ]]);
}

// --- snapshotAssessment ---

test('snapshotAssessment creates new monitor for first-time assessment', function () {
    $jobGuid = '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}';
    $assessmentData = [
        'Job_GUID' => $jobGuid,
        'Line_Name' => 'Circuit-1234',
        'Region' => 'CENTRAL',
        'Scope_Year' => '2026',
        'Cycle_Type' => 'Trim',
        'Status' => 'ACTIV',
        'Current_Owner' => 'ASPLUNDH\\jsmith',
        'Total_Miles' => 12.5,
        'Completed_Miles' => 5.0,
        'Percent_Complete' => 40.0,
    ];

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(snapshotMockReturn());

    $service = makeLiveMonitorService($this->mockQS);
    $service->snapshotAssessment($jobGuid, $assessmentData);

    $monitor = AssessmentMonitor::where('job_guid', $jobGuid)->first();

    expect($monitor)->not->toBeNull()
        ->and($monitor->line_name)->toBe('Circuit-1234')
        ->and($monitor->region)->toBe('CENTRAL')
        ->and($monitor->current_status)->toBe('ACTIV')
        ->and($monitor->daily_snapshots)->toBeArray()
        ->and($monitor->daily_snapshots)->toHaveCount(1)
        ->and($monitor->latest_snapshot)->toHaveKeys([
            'permission_breakdown', 'unit_counts', 'work_type_breakdown',
            'footage', 'notes_compliance', 'planner_activity', 'aging_units', 'suspicious',
        ])
        ->and($monitor->latest_snapshot['unit_counts']['total_units'])->toBe(50)
        ->and($monitor->latest_snapshot['work_type_breakdown'])->toBe([['unit' => 'SPM', 'UnitQty' => 15]])
        ->and($monitor->latest_snapshot['suspicious'])->toBeFalse();
});

test('snapshotAssessment appends to existing monitor', function () {
    $monitor = AssessmentMonitor::factory()->withSnapshots(2)->create();

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(snapshotMockReturn(60));

    $service = makeLiveMonitorService($this->mockQS);
    $service->snapshotAssessment($monitor->job_guid, [
        'Status' => 'QC',
        'Current_Owner' => 'ASPLUNDH\\jdoe',
    ]);

    $monitor->refresh();

    expect($monitor->daily_snapshots)->toHaveCount(3)
        ->and($monitor->current_status)->toBe('QC')
        ->and($monitor->current_planner)->toBe('ASPLUNDH\\jdoe');
});

test('snapshotAssessment sets suspicious when units drop to zero', function () {
    $monitor = AssessmentMonitor::factory()->withSnapshots(1)->create();
    $previousTotal = $monitor->latest_snapshot['unit_counts']['total_units'];

    expect($previousTotal)->toBeGreaterThan(0);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([[
            'total_units' => 0,
            'approved' => 0,
            'pending' => 0,
            'refused' => 0,
            'no_contact' => 0,
            'deferred' => 0,
            'ppl_approved' => 0,
            'work_units' => 0,
            'nw_units' => 0,
            'units_requiring_notes' => 0,
            'units_with_notes' => 0,
            'units_without_notes' => 0,
            'compliance_percent' => 0,
            'last_edit_date' => null,
            'last_edit_by' => null,
            'pending_over_threshold' => 0,
            'work_type_breakdown' => null,
        ]]));

    $service = makeLiveMonitorService($this->mockQS);
    $service->snapshotAssessment($monitor->job_guid, ['Status' => 'ACTIV']);

    $monitor->refresh();
    $today = now()->toDateString();

    expect($monitor->daily_snapshots[$today]['suspicious'])->toBeTrue();
});

// --- detectClosedAssessments ---

test('detectClosedAssessments dispatches events for monitors not in active list', function () {
    Event::fake([AssessmentClosed::class]);

    $active = AssessmentMonitor::factory()->create(['job_guid' => '{ACTIVE-GUID-1111}']);
    $closed = AssessmentMonitor::factory()->create(['job_guid' => '{CLOSED-GUID-2222}']);

    $service = makeLiveMonitorService($this->mockQS);
    $result = $service->detectClosedAssessments(collect(['{ACTIVE-GUID-1111}']));

    expect($result)->toHaveCount(1)
        ->and($result->first()->job_guid)->toBe('{CLOSED-GUID-2222}');

    Event::assertDispatched(AssessmentClosed::class, function ($event) use ($closed) {
        return $event->jobGuid === $closed->job_guid;
    });
});

test('detectClosedAssessments dispatches nothing when all are still active', function () {
    Event::fake([AssessmentClosed::class]);

    $m1 = AssessmentMonitor::factory()->create();
    $m2 = AssessmentMonitor::factory()->create();

    $service = makeLiveMonitorService($this->mockQS);
    $result = $service->detectClosedAssessments(collect([$m1->job_guid, $m2->job_guid]));

    expect($result)->toBeEmpty();
    Event::assertNotDispatched(AssessmentClosed::class);
});

test('detectClosedAssessments handles empty monitor table', function () {
    Event::fake([AssessmentClosed::class]);

    $service = makeLiveMonitorService($this->mockQS);
    $result = $service->detectClosedAssessments(collect(['{some-guid}']));

    expect($result)->toBeEmpty();
    Event::assertNotDispatched(AssessmentClosed::class);
});

// --- runDailySnapshot ---

test('runDailySnapshot processes active assessments and returns stats', function () {
    Event::fake([AssessmentClosed::class]);

    $guid1 = '{11111111-1111-1111-1111-111111111111}';
    $guid2 = '{22222222-2222-2222-2222-222222222222}';

    $this->mockQS->shouldReceive('getDailyActivitiesForAllAssessments')
        ->once()
        ->andReturn(collect([
            [
                ['Job_GUID' => $guid1, 'Status' => 'ACTIV', 'Line_Name' => 'Circuit-A', 'Region' => 'CENTRAL', 'Scope_Year' => '2026', 'Cycle_Type' => 'Trim', 'Current_Owner' => 'ASPLUNDH\\jsmith', 'Total_Miles' => 10.0, 'Completed_Miles' => 5.0, 'Percent_Complete' => 50.0],
                ['Job_GUID' => $guid2, 'Status' => 'QC', 'Line_Name' => 'Circuit-B', 'Region' => 'LEHIGH', 'Scope_Year' => '2026', 'Cycle_Type' => 'Trim', 'Current_Owner' => 'ASPLUNDH\\jdoe', 'Total_Miles' => 8.0, 'Completed_Miles' => 8.0, 'Percent_Complete' => 100.0],
                ['Job_GUID' => '{33333333-3333-3333-3333-333333333333}', 'Status' => 'CLOSE', 'Line_Name' => 'Circuit-C'],
            ],
        ]));

    // 1 executeAndHandle call per active assessment × 2 active = 2 total
    $this->mockQS->shouldReceive('executeAndHandle')
        ->twice()
        ->andReturn(snapshotMockReturn(50), snapshotMockReturn(40));

    $service = makeLiveMonitorService($this->mockQS);
    $stats = $service->runDailySnapshot();

    expect($stats['snapshots'])->toBe(2)
        ->and($stats['new'])->toBe(2)
        ->and($stats['closed'])->toBe(0);

    expect(AssessmentMonitor::count())->toBe(2);
});

test('runDailySnapshot detects closed assessments', function () {
    Event::fake([AssessmentClosed::class]);

    $closedMonitor = AssessmentMonitor::factory()->create([
        'job_guid' => '{CCCCCCCC-CCCC-CCCC-CCCC-CCCCCCCCCCCC}',
    ]);

    $activeGuid = '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}';

    $this->mockQS->shouldReceive('getDailyActivitiesForAllAssessments')
        ->once()
        ->andReturn(collect([
            [
                ['Job_GUID' => $activeGuid, 'Status' => 'ACTIV', 'Line_Name' => 'Circuit-A', 'Region' => 'CENTRAL', 'Scope_Year' => '2026', 'Cycle_Type' => 'Trim', 'Current_Owner' => 'ASPLUNDH\\jsmith', 'Total_Miles' => 10.0, 'Completed_Miles' => 5.0, 'Percent_Complete' => 50.0],
            ],
        ]));

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(snapshotMockReturn());

    $service = makeLiveMonitorService($this->mockQS);
    $stats = $service->runDailySnapshot();

    expect($stats['snapshots'])->toBe(1)
        ->and($stats['new'])->toBe(1)
        ->and($stats['closed'])->toBe(1);

    Event::assertDispatched(AssessmentClosed::class, function ($event) use ($closedMonitor) {
        return $event->jobGuid === $closedMonitor->job_guid;
    });
});

test('runDailySnapshot returns zeros when API returns no assessments', function () {
    Event::fake([AssessmentClosed::class]);

    $this->mockQS->shouldReceive('getDailyActivitiesForAllAssessments')
        ->once()
        ->andReturn(collect([null]));

    $service = makeLiveMonitorService($this->mockQS);
    $stats = $service->runDailySnapshot();

    expect($stats)->toBe(['snapshots' => 0, 'new' => 0, 'closed' => 0]);
});

test('runDailySnapshot counts existing monitors as not new', function () {
    Event::fake([AssessmentClosed::class]);

    $existingGuid = '{AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA}';
    AssessmentMonitor::factory()->withSnapshots(1)->create(['job_guid' => $existingGuid]);

    $this->mockQS->shouldReceive('getDailyActivitiesForAllAssessments')
        ->once()
        ->andReturn(collect([
            [
                ['Job_GUID' => $existingGuid, 'Status' => 'ACTIV', 'Current_Owner' => 'ASPLUNDH\\jsmith'],
            ],
        ]));

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(snapshotMockReturn());

    $service = makeLiveMonitorService($this->mockQS);
    $stats = $service->runDailySnapshot();

    expect($stats['snapshots'])->toBe(1)
        ->and($stats['new'])->toBe(0);
});

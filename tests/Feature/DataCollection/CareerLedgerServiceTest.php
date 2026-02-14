<?php

use App\Models\AssessmentMonitor;
use App\Models\PlannerCareerEntry;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\DataCollection\CareerLedgerService;

beforeEach(function () {
    $this->mockQS = Mockery::mock(GetQueryService::class);
});

function makeCareerService($mock): CareerLedgerService
{
    return new CareerLedgerService($mock);
}

// --- importFromJson ---

test('importFromJson imports entries from valid JSON file', function () {
    $data = [
        [
            'planner_username' => 'jsmith',
            'planner_display_name' => 'ASPLUNDH\\jsmith',
            'job_guid' => '{11111111-1111-1111-1111-111111111111}',
            'line_name' => 'Circuit-1234',
            'region' => 'CENTRAL',
            'scope_year' => '2026',
            'cycle_type' => 'Trim',
            'assessment_total_miles' => 10.5,
            'assessment_completed_miles' => 8.2,
            'assessment_pickup_date' => '2026-01-10',
            'assessment_qc_date' => '2026-02-01',
            'assessment_close_date' => '2026-02-05',
            'went_to_rework' => false,
            'rework_details' => null,
            'daily_metrics' => [['date' => '2026-01-10', 'footage' => 500]],
            'summary_totals' => [['unit' => 'SPM', 'qty' => 25]],
        ],
        [
            'planner_username' => 'jdoe',
            'job_guid' => '{22222222-2222-2222-2222-222222222222}',
            'line_name' => 'Circuit-5678',
            'region' => 'LEHIGH',
        ],
    ];

    $path = tempnam(sys_get_temp_dir(), 'career_');
    file_put_contents($path, json_encode($data));

    $service = makeCareerService($this->mockQS);
    $stats = $service->importFromJson($path);

    expect($stats)->toBe(['imported' => 2, 'skipped' => 0, 'errors' => 0])
        ->and(PlannerCareerEntry::count())->toBe(2);

    $entry = PlannerCareerEntry::where('planner_username', 'jsmith')->first();
    expect($entry->source)->toBe('bootstrap')
        ->and($entry->line_name)->toBe('Circuit-1234')
        ->and($entry->daily_metrics)->toBeArray()
        ->and($entry->went_to_rework)->toBeFalse();

    unlink($path);
});

test('importFromJson is idempotent — skips existing entries', function () {
    PlannerCareerEntry::factory()->create([
        'planner_username' => 'jsmith',
        'job_guid' => '{11111111-1111-1111-1111-111111111111}',
        'source' => 'bootstrap',
    ]);

    $data = [
        [
            'planner_username' => 'jsmith',
            'job_guid' => '{11111111-1111-1111-1111-111111111111}',
        ],
        [
            'planner_username' => 'jdoe',
            'job_guid' => '{22222222-2222-2222-2222-222222222222}',
        ],
    ];

    $path = tempnam(sys_get_temp_dir(), 'career_');
    file_put_contents($path, json_encode($data));

    $service = makeCareerService($this->mockQS);
    $stats = $service->importFromJson($path);

    expect($stats['imported'])->toBe(1)
        ->and($stats['skipped'])->toBe(1)
        ->and(PlannerCareerEntry::count())->toBe(2);

    unlink($path);
});

test('importFromJson throws on missing file', function () {
    $service = makeCareerService($this->mockQS);
    $service->importFromJson('/nonexistent/path.json');
})->throws(\InvalidArgumentException::class, 'Bootstrap file not found');

test('importFromJson throws on invalid JSON', function () {
    $path = tempnam(sys_get_temp_dir(), 'career_');
    file_put_contents($path, 'not valid json');

    $service = makeCareerService($this->mockQS);

    try {
        $service->importFromJson($path);
    } finally {
        unlink($path);
    }
})->throws(\InvalidArgumentException::class, 'Invalid JSON structure');

test('importFromJson counts errors for malformed entries', function () {
    $data = [
        ['planner_username' => 'jsmith', 'job_guid' => '{11111111-1111-1111-1111-111111111111}'],
        ['missing_required_fields' => true],
    ];

    $path = tempnam(sys_get_temp_dir(), 'career_');
    file_put_contents($path, json_encode($data));

    $service = makeCareerService($this->mockQS);
    $stats = $service->importFromJson($path);

    expect($stats['errors'])->toBeGreaterThanOrEqual(1)
        ->and($stats['imported'])->toBe(1);

    unlink($path);
});

// --- appendFromMonitor ---

test('appendFromMonitor creates career entry from closing monitor', function () {
    $monitor = AssessmentMonitor::factory()->withSnapshots(3)->create([
        'job_guid' => '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}',
        'current_planner' => 'ASPLUNDH\\jsmith',
        'region' => 'CENTRAL',
        'scope_year' => '2026',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->times(3)
        ->andReturn(
            // daily footage attribution
            collect([
                ['JOBGUID' => $monitor->job_guid, 'completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_meters' => 500, 'unit_count' => 10],
            ]),
            // work type breakdown
            collect([
                ['unit' => 'SPM', 'UnitQty' => 15, 'status' => 'CLOSE'],
            ]),
            // timeline
            collect([
                ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10', 'USERNAME' => 'jsmith'],
                ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01', 'USERNAME' => 'admin'],
                ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-05', 'USERNAME' => 'admin'],
            ]),
        );

    $service = makeCareerService($this->mockQS);
    $entry = $service->appendFromMonitor($monitor);

    expect($entry)->toBeInstanceOf(PlannerCareerEntry::class)
        ->and($entry->exists)->toBeTrue()
        ->and($entry->source)->toBe('live_monitor')
        ->and($entry->planner_username)->toBe('jsmith')
        ->and($entry->job_guid)->toBe($monitor->job_guid)
        ->and($entry->region)->toBe('CENTRAL')
        ->and($entry->daily_metrics)->toBeArray()
        ->and($entry->summary_totals)->toBeArray()
        ->and($entry->went_to_rework)->toBeFalse();
});

// --- exportToJson ---

test('exportToJson writes CLOSE assessments to JSON file', function () {
    $path = tempnam(sys_get_temp_dir(), 'export_').'.json';
    $guid = '{11111111-1111-1111-1111-111111111111}';

    $this->mockQS->shouldReceive('getDailyActivitiesForAllAssessments')
        ->once()
        ->andReturn(collect([
            [
                ['Job_GUID' => $guid, 'Status' => 'CLOSE', 'Current_Owner' => 'ASPLUNDH\\jsmith', 'Line_Name' => 'Circuit-1234', 'Region' => 'CENTRAL', 'Scope_Year' => '2026', 'Cycle_Type' => 'Trim', 'Total_Miles' => 10.0, 'Completed_Miles' => 10.0],
                ['Job_GUID' => '{22222222-2222-2222-2222-222222222222}', 'Status' => 'ACTIV', 'Current_Owner' => 'ASPLUNDH\\jdoe'],
            ],
        ]));

    $this->mockQS->shouldReceive('executeAndHandle')
        ->times(3)
        ->andReturn(
            // getDailyFootageAttributionBatch
            collect([
                ['JOBGUID' => $guid, 'completion_date' => '2026-01-15', 'daily_footage_meters' => 500],
            ]),
            // getAssessmentTimeline
            collect([
                ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-05'],
            ]),
            // getWorkTypeBreakdown
            collect([['unit' => 'SPM', 'UnitQty' => 15]]),
        );

    $service = makeCareerService($this->mockQS);
    $count = $service->exportToJson($path);

    expect($count)->toBe(1)
        ->and(file_exists($path))->toBeTrue();

    $exported = json_decode(file_get_contents($path), true);
    expect($exported)->toHaveCount(1)
        ->and($exported[0]['planner_username'])->toBe('jsmith')
        ->and($exported[0]['job_guid'])->toBe($guid)
        ->and($exported[0]['line_name'])->toBe('Circuit-1234')
        ->and($exported[0]['daily_metrics'])->toBeArray()
        ->and($exported[0]['summary_totals'])->toBeArray();

    unlink($path);
});

test('exportToJson returns zero and writes empty array when no CLOSE assessments', function () {
    $path = tempnam(sys_get_temp_dir(), 'export_').'.json';

    $this->mockQS->shouldReceive('getDailyActivitiesForAllAssessments')
        ->once()
        ->andReturn(collect([
            [
                ['Job_GUID' => '{GUID-1}', 'Status' => 'ACTIV'],
            ],
        ]));

    $service = makeCareerService($this->mockQS);
    $count = $service->exportToJson($path);

    expect($count)->toBe(0)
        ->and(json_decode(file_get_contents($path), true))->toBe([]);

    unlink($path);
});

test('exportToJson returns zero when API returns no assessments', function () {
    $path = tempnam(sys_get_temp_dir(), 'export_').'.json';

    $this->mockQS->shouldReceive('getDailyActivitiesForAllAssessments')
        ->once()
        ->andReturn(collect([null]));

    $service = makeCareerService($this->mockQS);
    $count = $service->exportToJson($path);

    expect($count)->toBe(0);

    @unlink($path);
});

// --- appendFromMonitor ---

test('appendFromMonitor detects rework in timeline and fetches rework details', function () {
    $monitor = AssessmentMonitor::factory()->withSnapshots()->create([
        'job_guid' => '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}',
        'current_planner' => 'ASPLUNDH\\jsmith',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->times(4)
        ->andReturn(
            collect([]),
            collect([]),
            // timeline with REWRK
            collect([
                ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                ['JOBSTATUS' => 'REWRK', 'LOGDATE' => '2026-02-03'],
                ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
            ]),
            // rework details
            collect([
                ['UNITGUID' => '{unit-1}', 'AUDIT_FAIL' => 'Incorrect species'],
            ]),
        );

    $service = makeCareerService($this->mockQS);
    $entry = $service->appendFromMonitor($monitor);

    expect($entry->went_to_rework)->toBeTrue()
        ->and($entry->rework_details)->toBeArray()
        ->and($entry->rework_details)->not->toBeEmpty();
});

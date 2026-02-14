<?php

use App\Models\PlannerJobAssignment;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\Planners\PlannerCareerLedgerService;

beforeEach(function () {
    $this->mockQS = Mockery::mock(GetQueryService::class);
});

function makePlannerCareerService($mock): PlannerCareerLedgerService
{
    return new PlannerCareerLedgerService($mock);
}

// ─── discoverJobGuids ────────────────────────────────────────────────────────

test('discoverJobGuids creates assignments from API results', function () {
    $guid1 = '{11111111-1111-1111-1111-111111111111}';
    $guid2 = '{22222222-2222-2222-2222-222222222222}';

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['FRSTR_USER' => 'jsmith', 'JOBGUID' => $guid1],
            ['FRSTR_USER' => 'jsmith', 'JOBGUID' => $guid2],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $assignments = $service->discoverJobGuids('jsmith');

    expect($assignments)->toHaveCount(2)
        ->and(PlannerJobAssignment::count())->toBe(2)
        ->and(PlannerJobAssignment::where('frstr_user', 'jsmith')->count())->toBe(2);

    $first = PlannerJobAssignment::where('job_guid', $guid1)->first();
    expect($first->status)->toBe('discovered')
        ->and($first->discovered_at)->not->toBeNull();
});

test('discoverJobGuids is idempotent — skips existing assignments', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'exported',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([['FRSTR_USER' => 'jsmith', 'JOBGUID' => $guid]]));

    $service = makePlannerCareerService($this->mockQS);
    $assignments = $service->discoverJobGuids('jsmith');

    expect(PlannerJobAssignment::count())->toBe(1);

    $existing = PlannerJobAssignment::first();
    expect($existing->status)->toBe('exported');
});

test('discoverJobGuids scopes JOBGUIDs to their actual user', function () {
    $guid1 = '{11111111-1111-1111-1111-111111111111}';
    $guid2 = '{22222222-2222-2222-2222-222222222222}';
    $guid3 = '{33333333-3333-3333-3333-333333333333}';

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['FRSTR_USER' => 'jsmith', 'JOBGUID' => $guid1],
            ['FRSTR_USER' => 'jsmith', 'JOBGUID' => $guid2],
            ['FRSTR_USER' => 'jdoe', 'JOBGUID' => $guid3],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $assignments = $service->discoverJobGuids(['jsmith', 'jdoe']);

    expect($assignments)->toHaveCount(3)
        ->and(PlannerJobAssignment::where('frstr_user', 'jsmith')->count())->toBe(2)
        ->and(PlannerJobAssignment::where('frstr_user', 'jdoe')->count())->toBe(1);

    // jsmith should NOT have guid3, jdoe should NOT have guid1/guid2
    expect(PlannerJobAssignment::where('frstr_user', 'jsmith')->pluck('job_guid')->toArray())
        ->toContain($guid1)
        ->toContain($guid2)
        ->not->toContain($guid3);

    expect(PlannerJobAssignment::where('frstr_user', 'jdoe')->pluck('job_guid')->toArray())
        ->toContain($guid3)
        ->not->toContain($guid1);
});

test('discoverJobGuids handles shared JOBGUID across users', function () {
    $sharedGuid = '{11111111-1111-1111-1111-111111111111}';

    // Both users worked on the same assessment
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['FRSTR_USER' => 'jsmith', 'JOBGUID' => $sharedGuid],
            ['FRSTR_USER' => 'jdoe', 'JOBGUID' => $sharedGuid],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $assignments = $service->discoverJobGuids(['jsmith', 'jdoe']);

    expect($assignments)->toHaveCount(2)
        ->and(PlannerJobAssignment::where('frstr_user', 'jsmith')->count())->toBe(1)
        ->and(PlannerJobAssignment::where('frstr_user', 'jdoe')->count())->toBe(1);
});

test('discoverJobGuids handles empty API response', function () {
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect());

    $service = makePlannerCareerService($this->mockQS);
    $assignments = $service->discoverJobGuids('jsmith');

    expect($assignments)->toBeEmpty()
        ->and(PlannerJobAssignment::count())->toBe(0);
});

// ─── exportForUser (consolidated single-query) ──────────────────────────────

test('exportForUser creates JSON file with career data from single API call', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    // Single API call returns all data — JSON columns for nested data
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-05'],
                ]),
                'work_type_breakdown' => json_encode([
                    ['unit' => 'SPM', 'UnitQty' => 15],
                ]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 500, 'unit_count' => 10],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    expect(file_exists($filePath))->toBeTrue()
        ->and($filePath)->toContain('jsmith_');

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments)->toHaveCount(1)
        ->and($assessments[0]['planner_username'])->toBe('jsmith')
        ->and($assessments[0]['job_guid'])->toBe($guid)
        ->and($assessments[0]['line_name'])->toBe('Circuit-1234')
        ->and($assessments[0]['region'])->toBe('CENTRAL')
        ->and($assessments[0]['daily_metrics'])->toBeArray()
        ->and($assessments[0]['daily_metrics'])->toHaveCount(1)
        ->and($assessments[0]['daily_metrics'][0]['assumed_status'])->toBe('Active')
        ->and($assessments[0]['summary_totals'])->toBeArray()
        ->and($assessments[0]['went_to_rework'])->toBeFalse()
        ->and($assessments[0]['rework_details'])->toBeNull();

    $assignment = PlannerJobAssignment::first();
    expect($assignment->status)->toBe('exported')
        ->and($assignment->export_path)->toBe($filePath);

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser writes empty JSON when no assignments exist', function () {
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    expect(file_exists($filePath))->toBeTrue();

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments)->toBe([])
        ->and($exported['assessment_count'])->toBe(0)
        ->and($exported['career_timeframe'])->toBeNull();

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser detects rework from timeline JSON column', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'REWRK', 'LOGDATE' => '2026-02-03'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => json_encode([
                    ['UNITGUID' => '{AAAAAAAA-1111-2222-3333-444444444444}', 'AUDIT_FAIL' => 'Wrong species'],
                ]),
                'daily_metrics' => json_encode([]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments[0]['went_to_rework'])->toBeTrue()
        ->and($assessments[0]['rework_details'])->toBeArray()
        ->and($assessments[0]['rework_details'])->not->toBeEmpty();

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser checks staleness for already-exported assignments', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    // Create an exported assignment with export_path but stale (updated_at in the past)
    $exportPath = $outputDir.'/jsmith_2026-02-01.json';
    $existingData = [[
        'planner_username' => 'jsmith',
        'job_guid' => $guid,
        'line_name' => 'Old-Circuit',
        'region' => 'CENTRAL',
        'scope_year' => '2026',
        'cycle_type' => 'Trim',
        'assessment_total_miles' => 10.5,
        'assessment_total_miles_planned' => 8.0,
        'assessment_pickup_date' => '2026-01-10',
        'assessment_qc_date' => null,
        'assessment_close_date' => null,
        'went_to_rework' => false,
        'rework_details' => null,
        'daily_metrics' => [
            ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5, 'assumed_status' => 'Active'],
        ],
        'summary_totals' => [],
    ]];
    file_put_contents($exportPath, json_encode(['assessments' => $existingData], JSON_PRETTY_PRINT));

    $assignment = PlannerJobAssignment::factory()->withExportPath($exportPath)->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'updated_at' => now()->subDays(5),
    ]);

    // Mock: getEditDates returns EDITDATE newer than updated_at (stale)
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['JOBGUID' => $guid, 'edit_date' => now()->toIso8601String()],
        ]));

    // Mock: getFullCareerData for stale GUID
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Updated-Circuit',
                'region' => 'HARRISBURG',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 12.0,
                'total_miles_planned' => 10.0,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([['unit' => 'SPM', 'UnitQty' => 20]]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-02-05', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 200, 'unit_count' => 8],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments)->toHaveCount(1)
        ->and($assessments[0]['line_name'])->toBe('Updated-Circuit')
        ->and($assessments[0]['region'])->toBe('HARRISBURG')
        ->and($assessments[0]['daily_metrics'])->toHaveCount(2)
        ->and($assessments[0]['daily_metrics'][0]['completion_date'])->toBe('2026-01-15')
        ->and($assessments[0]['daily_metrics'][1]['completion_date'])->toBe('2026-02-05');

    $assignment->refresh();
    expect($assignment->status)->toBe('exported')
        ->and($assignment->export_path)->toBe($filePath);

    unlink($filePath);
    rmdir($outputDir);
});

// ─── exportForUsers ──────────────────────────────────────────────────────────

// ─── assumed_status enrichment ───────────────────────────────────────────────

test('exportForUser assigns Active status to days between pickup and QC', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-10', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5],
                    ['completion_date' => '2026-01-20', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 200, 'unit_count' => 8],
                    ['completion_date' => '2026-01-31', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 150, 'unit_count' => 6],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    $metrics = $assessments[0]['daily_metrics'];

    expect($metrics)->toHaveCount(3)
        ->and($metrics[0]['assumed_status'])->toBe('Active')
        ->and($metrics[1]['assumed_status'])->toBe('Active')
        ->and($metrics[2]['assumed_status'])->toBe('Active');

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser assigns QC status to days between QC and close', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-20', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5],
                    ['completion_date' => '2026-02-01', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 200, 'unit_count' => 8],
                    ['completion_date' => '2026-02-05', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 150, 'unit_count' => 6],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    $metrics = $assessments[0]['daily_metrics'];

    expect($metrics[0]['assumed_status'])->toBe('Active')
        ->and($metrics[1]['assumed_status'])->toBe('QC')
        ->and($metrics[2]['assumed_status'])->toBe('QC');

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser assigns Rework status during rework periods', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'REWRK', 'LOGDATE' => '2026-02-03'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-06'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => json_encode([
                    ['UNITGUID' => '{AAAAAAAA-1111-2222-3333-444444444444}', 'AUDIT_FAIL' => 'Wrong species'],
                ]),
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-20', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5],
                    ['completion_date' => '2026-02-02', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 50, 'unit_count' => 2],
                    ['completion_date' => '2026-02-04', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 75, 'unit_count' => 3],
                    ['completion_date' => '2026-02-07', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 120, 'unit_count' => 4],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    $metrics = $assessments[0]['daily_metrics'];

    expect($metrics[0]['assumed_status'])->toBe('Active')   // 01-20: before QC
        ->and($metrics[1]['assumed_status'])->toBe('QC')     // 02-02: after QC, before rework
        ->and($metrics[2]['assumed_status'])->toBe('Rework') // 02-04: during rework period
        ->and($metrics[3]['assumed_status'])->toBe('QC');    // 02-07: after rework ended

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser assigns Closed status to days on/after close date', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-02-09', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5],
                    ['completion_date' => '2026-02-10', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 50, 'unit_count' => 2],
                    ['completion_date' => '2026-02-12', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 25, 'unit_count' => 1],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    $metrics = $assessments[0]['daily_metrics'];

    expect($metrics[0]['assumed_status'])->toBe('QC')      // 02-09: day before close
        ->and($metrics[1]['assumed_status'])->toBe('Closed') // 02-10: close date itself
        ->and($metrics[2]['assumed_status'])->toBe('Closed'); // 02-12: after close

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser handles multiple rework periods correctly', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'REWRK', 'LOGDATE' => '2026-02-03'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-05'],
                    ['JOBSTATUS' => 'REWRK', 'LOGDATE' => '2026-02-07'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-09'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-15'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-02-02', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 50, 'unit_count' => 2],
                    ['completion_date' => '2026-02-04', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 75, 'unit_count' => 3],
                    ['completion_date' => '2026-02-06', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 60, 'unit_count' => 2],
                    ['completion_date' => '2026-02-08', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 80, 'unit_count' => 4],
                    ['completion_date' => '2026-02-10', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 90, 'unit_count' => 5],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    $metrics = $assessments[0]['daily_metrics'];

    expect($metrics[0]['assumed_status'])->toBe('QC')      // 02-02: after QC, before 1st rework
        ->and($metrics[1]['assumed_status'])->toBe('Rework') // 02-04: during 1st rework (02-03 to 02-05)
        ->and($metrics[2]['assumed_status'])->toBe('QC')     // 02-06: between rework periods
        ->and($metrics[3]['assumed_status'])->toBe('Rework') // 02-08: during 2nd rework (02-07 to 02-09)
        ->and($metrics[4]['assumed_status'])->toBe('QC');    // 02-10: after 2nd rework ended

    unlink($filePath);
    rmdir($outputDir);
});

// ─── current mode ────────────────────────────────────────────────────────────

test('discoverJobGuids passes current flag to query layer', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([['FRSTR_USER' => 'jsmith', 'JOBGUID' => $guid]]));

    $service = makePlannerCareerService($this->mockQS);
    $assignments = $service->discoverJobGuids('jsmith', current: true);

    expect($assignments)->toHaveCount(1)
        ->and(PlannerJobAssignment::first()->job_guid)->toBe($guid);
});

test('exportForUser works for current assessments without close date', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'ACTIV',
                'line_name' => 'Circuit-5678',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 8.2,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-15'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-20', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 300, 'unit_count' => 12],
                    ['completion_date' => '2026-01-25', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 250, 'unit_count' => 10],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir, current: true);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments)->toHaveCount(1)
        ->and($assessments[0]['assessment_pickup_date'])->toBe('2026-01-15')
        ->and($assessments[0]['assessment_qc_date'])->toBeNull()
        ->and($assessments[0]['assessment_close_date'])->toBeNull()
        ->and($assessments[0]['daily_metrics'])->toHaveCount(2)
        ->and($assessments[0]['daily_metrics'][0]['assumed_status'])->toBe('Active')
        ->and($assessments[0]['daily_metrics'][1]['assumed_status'])->toBe('Active');

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser handles current QC assessment with assumed statuses', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'QC',
                'line_name' => 'Circuit-9999',
                'region' => 'HARRISBURG',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 5.0,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-20', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5],
                    ['completion_date' => '2026-02-05', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 50, 'unit_count' => 2],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir, current: true);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    $metrics = $assessments[0]['daily_metrics'];

    expect($metrics[0]['assumed_status'])->toBe('Active')  // before QC
        ->and($metrics[1]['assumed_status'])->toBe('QC');   // after QC, no close

    unlink($filePath);
    rmdir($outputDir);
});

// ─── exportForUsers ──────────────────────────────────────────────────────────

// ─── incremental export ──────────────────────────────────────────────────────

test('exportForUser first run sets export_path on assignments', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'total_miles_planned' => 8.0,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $assignment = PlannerJobAssignment::first();
    expect($assignment->status)->toBe('exported')
        ->and($assignment->export_path)->toBe($filePath);

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser deduplicates daily_metrics by completion_date', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    $exportPath = $outputDir.'/jsmith_2026-02-01.json';
    $existingData = [[
        'planner_username' => 'jsmith',
        'job_guid' => $guid,
        'line_name' => 'Circuit-1234',
        'region' => 'CENTRAL',
        'scope_year' => '2026',
        'cycle_type' => 'Trim',
        'assessment_total_miles' => 10.5,
        'assessment_total_miles_planned' => 8.0,
        'assessment_pickup_date' => '2026-01-10',
        'assessment_qc_date' => null,
        'assessment_close_date' => null,
        'went_to_rework' => false,
        'rework_details' => null,
        'daily_metrics' => [
            ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5, 'assumed_status' => 'Active'],
            ['completion_date' => '2026-01-20', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 150, 'unit_count' => 7, 'assumed_status' => 'Active'],
        ],
        'summary_totals' => [],
    ]];
    file_put_contents($exportPath, json_encode(['assessments' => $existingData], JSON_PRETTY_PRINT));

    PlannerJobAssignment::factory()->withExportPath($exportPath)->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'updated_at' => now()->subDays(5),
    ]);

    // Mock: getEditDates — stale
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['JOBGUID' => $guid, 'edit_date' => now()->toIso8601String()],
        ]));

    // Mock: getFullCareerData — returns overlapping + new metric
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'total_miles_planned' => 8.0,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    // Duplicate date with updated values
                    ['completion_date' => '2026-01-20', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 200, 'unit_count' => 9],
                    // New metric
                    ['completion_date' => '2026-01-25', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 300, 'unit_count' => 12],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    $metrics = $assessments[0]['daily_metrics'];

    // 3 unique dates: 01-15 (existing), 01-20 (overwritten), 01-25 (new)
    expect($metrics)->toHaveCount(3)
        ->and($metrics[0]['completion_date'])->toBe('2026-01-15')
        ->and($metrics[1]['completion_date'])->toBe('2026-01-20')
        ->and($metrics[1]['daily_footage_miles'])->toBe(200) // updated value
        ->and($metrics[2]['completion_date'])->toBe('2026-01-25');

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser skips up-to-date exported assignments', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    $exportPath = $outputDir.'/jsmith_2026-02-01.json';
    $existingData = [[
        'planner_username' => 'jsmith',
        'job_guid' => $guid,
        'line_name' => 'Circuit-1234',
        'region' => 'CENTRAL',
        'scope_year' => '2026',
        'cycle_type' => 'Trim',
        'assessment_total_miles' => 10.5,
        'assessment_total_miles_planned' => 8.0,
        'assessment_pickup_date' => '2026-01-10',
        'assessment_qc_date' => null,
        'assessment_close_date' => null,
        'went_to_rework' => false,
        'rework_details' => null,
        'daily_metrics' => [
            ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5, 'assumed_status' => 'Active'],
        ],
        'summary_totals' => [],
    ]];
    file_put_contents($exportPath, json_encode(['assessments' => $existingData], JSON_PRETTY_PRINT));

    PlannerJobAssignment::factory()->withExportPath($exportPath)->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'updated_at' => now(),
    ]);

    // Mock: getEditDates — EDITDATE is older than updated_at (up-to-date)
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['JOBGUID' => $guid, 'edit_date' => now()->subDays(1)->toIso8601String()],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    // File should contain original data unchanged — only 1 API call (getEditDates)
    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments)->toHaveCount(1)
        ->and($assessments[0]['line_name'])->toBe('Circuit-1234')
        ->and($assessments[0]['daily_metrics'])->toHaveCount(1);

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser handles new and stale assignments in same run', function () {
    $existingGuid = '{11111111-1111-1111-1111-111111111111}';
    $newGuid = '{22222222-2222-2222-2222-222222222222}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    $exportPath = $outputDir.'/jsmith_2026-02-01.json';
    $existingData = [[
        'planner_username' => 'jsmith',
        'job_guid' => $existingGuid,
        'line_name' => 'Old-Circuit',
        'region' => 'CENTRAL',
        'scope_year' => '2026',
        'cycle_type' => 'Trim',
        'assessment_total_miles' => 10.5,
        'assessment_total_miles_planned' => 8.0,
        'assessment_pickup_date' => '2026-01-10',
        'assessment_qc_date' => null,
        'assessment_close_date' => null,
        'went_to_rework' => false,
        'rework_details' => null,
        'daily_metrics' => [
            ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5, 'assumed_status' => 'Active'],
        ],
        'summary_totals' => [],
    ]];
    file_put_contents($exportPath, json_encode(['assessments' => $existingData], JSON_PRETTY_PRINT));

    // Existing stale assignment
    PlannerJobAssignment::factory()->withExportPath($exportPath)->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $existingGuid,
        'updated_at' => now()->subDays(5),
    ]);

    // New assignment (no export_path)
    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $newGuid,
        'status' => 'discovered',
    ]);

    $apiRow = function ($guid, $lineName) {
        return [
            'JOBGUID' => $guid,
            'STATUS' => 'CLOSE',
            'line_name' => $lineName,
            'region' => 'CENTRAL',
            'cycle_type' => 'Trim',
            'assigned_planner' => 'jsmith',
            'total_miles' => 10.0,
            'total_miles_planned' => 8.0,
            'timeline' => json_encode([
                ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
            ]),
            'work_type_breakdown' => json_encode([]),
            'rework_details' => null,
            'daily_metrics' => json_encode([
                ['completion_date' => '2026-02-05', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 200, 'unit_count' => 8],
            ]),
        ];
    };

    // Mock 1: getEditDates for existing
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['JOBGUID' => $existingGuid, 'edit_date' => now()->toIso8601String()],
        ]));

    // Mock 2: getFullCareerData for new
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([$apiRow($newGuid, 'New-Circuit')]));

    // Mock 3: getFullCareerData for stale
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([$apiRow($existingGuid, 'Updated-Circuit')]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments)->toHaveCount(2);

    $guids = array_column($assessments, 'job_guid');
    expect($guids)->toContain($existingGuid)
        ->and($guids)->toContain($newGuid);

    // Verify stale was updated
    $existingEntry = collect($assessments)->firstWhere('job_guid', $existingGuid);
    expect($existingEntry['line_name'])->toBe('Updated-Circuit');

    // Verify both assignments updated
    $assignments = PlannerJobAssignment::forUser('jsmith')->get();
    foreach ($assignments as $a) {
        expect($a->status)->toBe('exported')
            ->and($a->export_path)->toBe($filePath);
    }

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser falls back to full export when export_path is outside target dir', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    // Assignment has export_path in a different directory — treated as new for this outputDir
    PlannerJobAssignment::factory()->withExportPath('/tmp/nonexistent_file.json')->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'updated_at' => now()->subDays(5),
    ]);

    // Only one API call needed — no staleness check since it's treated as new
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'total_miles_planned' => 8.0,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments)->toHaveCount(1)
        ->and($assessments[0]['line_name'])->toBe('Circuit-1234');

    $assignment = PlannerJobAssignment::first();
    expect($assignment->status)->toBe('exported')
        ->and($assignment->export_path)->toBe($filePath);

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser re-enriches assumed_status on merged metrics', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    $exportPath = $outputDir.'/jsmith_2026-02-01.json';
    $existingData = [[
        'planner_username' => 'jsmith',
        'job_guid' => $guid,
        'line_name' => 'Circuit-1234',
        'region' => 'CENTRAL',
        'scope_year' => '2026',
        'cycle_type' => 'Trim',
        'assessment_total_miles' => 10.5,
        'assessment_total_miles_planned' => 8.0,
        'assessment_pickup_date' => '2026-01-10',
        'assessment_qc_date' => null,
        'assessment_close_date' => null,
        'went_to_rework' => false,
        'rework_details' => null,
        'daily_metrics' => [
            ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 100, 'unit_count' => 5, 'assumed_status' => 'Active'],
        ],
        'summary_totals' => [],
    ]];
    file_put_contents($exportPath, json_encode(['assessments' => $existingData], JSON_PRETTY_PRINT));

    PlannerJobAssignment::factory()->withExportPath($exportPath)->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'updated_at' => now()->subDays(5),
    ]);

    // Mock: getEditDates — stale
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['JOBGUID' => $guid, 'edit_date' => now()->toIso8601String()],
        ]));

    // Mock: getFullCareerData — now closed, timeline adds QC+CLOSE
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'total_miles_planned' => 8.0,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-02-12', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 50, 'unit_count' => 2],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    $metrics = $assessments[0]['daily_metrics'];

    // Existing metric should be re-enriched with current timeline
    expect($metrics)->toHaveCount(2)
        ->and($metrics[0]['completion_date'])->toBe('2026-01-15')
        ->and($metrics[0]['assumed_status'])->toBe('Active')
        ->and($metrics[1]['completion_date'])->toBe('2026-02-12')
        ->and($metrics[1]['assumed_status'])->toBe('Closed');

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser refreshes metadata on stale assessment', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    $exportPath = $outputDir.'/jsmith_2026-02-01.json';
    $existingData = [[
        'planner_username' => 'jsmith',
        'job_guid' => $guid,
        'line_name' => 'Old-Name',
        'region' => 'CENTRAL',
        'scope_year' => '2026',
        'cycle_type' => 'Trim',
        'assessment_total_miles' => 5.0,
        'assessment_total_miles_planned' => 3.0,
        'assessment_pickup_date' => '2026-01-10',
        'assessment_qc_date' => null,
        'assessment_close_date' => null,
        'went_to_rework' => false,
        'rework_details' => null,
        'daily_metrics' => [],
        'summary_totals' => [],
    ]];
    file_put_contents($exportPath, json_encode(['assessments' => $existingData], JSON_PRETTY_PRINT));

    PlannerJobAssignment::factory()->withExportPath($exportPath)->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'updated_at' => now()->subDays(5),
    ]);

    // Mock: getEditDates — stale
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['JOBGUID' => $guid, 'edit_date' => now()->toIso8601String()],
        ]));

    // Mock: getFullCareerData — metadata changed
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'New-Name',
                'region' => 'HARRISBURG',
                'cycle_type' => 'Removal',
                'assigned_planner' => 'jsmith',
                'total_miles' => 15.0,
                'total_miles_planned' => 12.5,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([['unit' => 'REM', 'UnitQty' => 5]]),
                'rework_details' => null,
                'daily_metrics' => json_encode([]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments[0]['line_name'])->toBe('New-Name')
        ->and($assessments[0]['region'])->toBe('HARRISBURG')
        ->and($assessments[0]['cycle_type'])->toBe('Removal')
        ->and($assessments[0]['assessment_total_miles'])->toEqual(15.0)
        ->and($assessments[0]['assessment_total_miles_planned'])->toEqual(12.5)
        ->and($assessments[0]['assessment_qc_date'])->toBe('2026-02-01')
        ->and($assessments[0]['assessment_close_date'])->toBe('2026-02-10')
        ->and($assessments[0]['summary_totals'])->toHaveCount(1);

    unlink($filePath);
    rmdir($outputDir);
});

// ─── exportForUsers ──────────────────────────────────────────────────────────

test('exportForUsers returns map of username to file path', function () {
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    $service = makePlannerCareerService($this->mockQS);
    $results = $service->exportForUsers(['jsmith', 'jdoe'], $outputDir);

    expect($results)->toHaveCount(2)
        ->and($results)->toHaveKeys(['jsmith', 'jdoe']);

    foreach ($results as $path) {
        if (file_exists($path)) {
            unlink($path);
        }
    }
    rmdir($outputDir);
});

// ─── contribution fields ─────────────────────────────────────────────────────

test('exportForUser computes total_contribution from queried user daily metrics', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'WO' => 'WO-100',
                'EXT' => '@',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'total_miles_planned' => 8.0,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 1.5, 'unit_count' => 5],
                    ['completion_date' => '2026-01-20', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 2.3, 'unit_count' => 8],
                    ['completion_date' => '2026-01-25', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 0.75, 'unit_count' => 3],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments[0]['total_contribution'])->toBe(4.55)
        ->and($assessments[0]['others_total_contribution'])->toBe([])
        ->and($assessments[0]['wo'])->toBe('WO-100')
        ->and($assessments[0]['ext'])->toBe('@');

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser computes others_total_contribution with stripped domain keys', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'WO' => 'WO-200',
                'EXT' => '@',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'total_miles_planned' => 8.0,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 3.0, 'unit_count' => 10],
                    ['completion_date' => '2026-01-16', 'FRSTR_USER' => 'ASPLUNDH\\jdoe', 'daily_footage_miles' => 1.2, 'unit_count' => 4],
                    ['completion_date' => '2026-01-17', 'FRSTR_USER' => 'ASPLUNDH\\jdoe', 'daily_footage_miles' => 0.8, 'unit_count' => 3],
                    ['completion_date' => '2026-01-18', 'FRSTR_USER' => 'ASPLUNDH\\bsmith', 'daily_footage_miles' => 2.5, 'unit_count' => 7],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments[0]['total_contribution'])->toEqual(3.0)
        ->and($assessments[0]['others_total_contribution'])->toEqual([
            'jdoe' => 2.0,
            'bsmith' => 2.5,
        ]);

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser recomputes contributions after stale merge', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    $exportPath = $outputDir.'/jsmith_2026-02-01.json';
    $existingData = [[
        'planner_username' => 'jsmith',
        'total_contribution' => 1.5,
        'job_guid' => $guid,
        'line_name' => 'Circuit-1234',
        'wo' => 'WO-300',
        'ext' => '@',
        'region' => 'CENTRAL',
        'scope_year' => '2026',
        'cycle_type' => 'Trim',
        'assessment_total_miles' => 10.5,
        'assessment_total_miles_planned' => 8.0,
        'assessment_pickup_date' => '2026-01-10',
        'assessment_qc_date' => null,
        'assessment_close_date' => null,
        'went_to_rework' => false,
        'rework_details' => null,
        'daily_metrics' => [
            ['completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 1.5, 'unit_count' => 5, 'assumed_status' => 'Active'],
        ],
        'others_total_contribution' => [],
        'summary_totals' => [],
    ]];
    file_put_contents($exportPath, json_encode(['assessments' => $existingData], JSON_PRETTY_PRINT));

    PlannerJobAssignment::factory()->withExportPath($exportPath)->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'updated_at' => now()->subDays(5),
    ]);

    // Mock: stale
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['JOBGUID' => $guid, 'edit_date' => now()->toIso8601String()],
        ]));

    // Mock: fresh data with another user's contribution
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'WO' => 'WO-300',
                'EXT' => '@',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'total_miles_planned' => 8.0,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([
                    ['completion_date' => '2026-01-20', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 2.0, 'unit_count' => 6],
                    ['completion_date' => '2026-01-21', 'FRSTR_USER' => 'ASPLUNDH\\jdoe', 'daily_footage_miles' => 1.0, 'unit_count' => 3],
                ]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    // 1.5 (existing) + 2.0 (new) = 3.5
    expect($assessments[0]['total_contribution'])->toEqual(3.5)
        ->and($assessments[0]['others_total_contribution'])->toEqual(['jdoe' => 1.0])
        ->and($assessments[0]['daily_metrics'])->toHaveCount(3);

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser includes wo and ext fields as null when missing from API', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            [
                'JOBGUID' => $guid,
                'STATUS' => 'CLOSE',
                'line_name' => 'Circuit-1234',
                'region' => 'CENTRAL',
                'cycle_type' => 'Trim',
                'assigned_planner' => 'jsmith',
                'total_miles' => 10.5,
                'timeline' => json_encode([
                    ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                    ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
                ]),
                'work_type_breakdown' => json_encode([]),
                'rework_details' => null,
                'daily_metrics' => json_encode([]),
            ],
        ]));

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    $assessments = $exported['assessments'];
    expect($assessments[0]['wo'])->toBeNull()
        ->and($assessments[0]['ext'])->toBeNull()
        ->and($assessments[0]['total_contribution'])->toEqual(0.0)
        ->and($assessments[0]['others_total_contribution'])->toEqual([]);

    unlink($filePath);
    rmdir($outputDir);
});

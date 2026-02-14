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
            ['JOBGUID' => $guid1],
            ['JOBGUID' => $guid2],
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
        ->andReturn(collect([['JOBGUID' => $guid]]));

    $service = makePlannerCareerService($this->mockQS);
    $assignments = $service->discoverJobGuids('jsmith');

    expect(PlannerJobAssignment::count())->toBe(1);

    $existing = PlannerJobAssignment::first();
    expect($existing->status)->toBe('exported');
});

test('discoverJobGuids handles multiple users', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([['JOBGUID' => $guid]]));

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

// ─── exportForUser ───────────────────────────────────────────────────────────

test('exportForUser creates JSON file with career data', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->times(4)
        ->andReturn(
            // getAssessmentMetadataBatch
            collect([
                ['JOBGUID' => $guid, 'line_name' => 'Circuit-1234', 'region' => 'CENTRAL', 'cycle_type' => 'Trim', 'total_miles' => 10.5],
            ]),
            // getDailyFootageAttributionBatch
            collect([
                ['JOBGUID' => $guid, 'completion_date' => '2026-01-15', 'FRSTR_USER' => 'jsmith', 'daily_footage_miles' => 500, 'unit_count' => 10],
            ]),
            // getAssessmentTimeline
            collect([
                ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-05'],
            ]),
            // getWorkTypeBreakdown
            collect([['unit' => 'SPM', 'UnitQty' => 15]]),
        );

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    expect(file_exists($filePath))->toBeTrue()
        ->and($filePath)->toContain('jsmith_');

    $exported = json_decode(file_get_contents($filePath), true);
    expect($exported)->toHaveCount(1)
        ->and($exported[0]['planner_username'])->toBe('jsmith')
        ->and($exported[0]['job_guid'])->toBe($guid)
        ->and($exported[0]['line_name'])->toBe('Circuit-1234')
        ->and($exported[0]['region'])->toBe('CENTRAL')
        ->and($exported[0]['daily_metrics'])->toBeArray()
        ->and($exported[0]['summary_totals'])->toBeArray()
        ->and($exported[0]['went_to_rework'])->toBeFalse();

    $assignment = PlannerJobAssignment::first();
    expect($assignment->status)->toBe('exported');

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
    expect($exported)->toBe([]);

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser detects rework and fetches rework details', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->create([
        'frstr_user' => 'jsmith',
        'job_guid' => $guid,
        'status' => 'discovered',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->times(5)
        ->andReturn(
            // metadata
            collect([['JOBGUID' => $guid, 'line_name' => 'Circuit-1234', 'region' => 'CENTRAL']]),
            // daily footage
            collect([]),
            // timeline with REWRK
            collect([
                ['JOBSTATUS' => 'ACTIV', 'LOGDATE' => '2026-01-10'],
                ['JOBSTATUS' => 'QC', 'LOGDATE' => '2026-02-01'],
                ['JOBSTATUS' => 'REWRK', 'LOGDATE' => '2026-02-03'],
                ['JOBSTATUS' => 'CLOSE', 'LOGDATE' => '2026-02-10'],
            ]),
            // work type breakdown
            collect([]),
            // rework details
            collect([
                ['UNITGUID' => '{AAAAAAAA-1111-2222-3333-444444444444}', 'AUDIT_FAIL' => 'Wrong species'],
            ]),
        );

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    expect($exported[0]['went_to_rework'])->toBeTrue()
        ->and($exported[0]['rework_details'])->toBeArray()
        ->and($exported[0]['rework_details'])->not->toBeEmpty();

    unlink($filePath);
    rmdir($outputDir);
});

test('exportForUser skips already-exported assignments', function () {
    $outputDir = sys_get_temp_dir().'/career_test_'.uniqid();
    mkdir($outputDir);

    PlannerJobAssignment::factory()->exported()->create([
        'frstr_user' => 'jsmith',
    ]);

    $service = makePlannerCareerService($this->mockQS);
    $filePath = $service->exportForUser('jsmith', $outputDir);

    $exported = json_decode(file_get_contents($filePath), true);
    expect($exported)->toBe([]);

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

<?php

use App\Models\Circuit;
use App\Models\SsJob;
use App\Models\WsUser;
use Illuminate\Support\Facades\Http;

function fakeSsJobsResponse(): array
{
    return [
        'Heading' => [
            'JOBGUID', 'WO', 'EXT', 'JOBTYPE', 'STATUS',
            'TAKEN', 'TAKENBY', 'MODIFIEDBY', 'VERSION', 'SYNCHVERSN',
            'ASSIGNEDTO', 'TITLE', 'PJOBGUID', 'EDITDATE',
        ],
        'Data' => [
            // Job 1 with two extensions
            ['{abc-111}', 'WO-001', 'EXT-A', 'Assessment', 'ACTIV', 'true', 'ASPLUNDH\\jsmith', 'ASPLUNDH\\jdoe', '1.0', '2.0', null, '12705', null, '2026-01-15 14:30:00'],
            ['{abc-111}', 'WO-001', 'EXT-B', 'Assessment', 'ACTIV', 'true', 'ASPLUNDH\\jsmith', 'ASPLUNDH\\jdoe', '1.0', '2.0', null, '12705', null, '2026-01-15 14:30:00'],
            // Job 2 — child of job 1
            ['{abc-222}', 'WO-002', 'EXT-C', 'Assessment Rework', 'QC', 'false', null, null, '1.0', null, 'somebody', '12705', '{abc-111}', '2026-02-01 09:00:00'],
            // Job 3 — different title (no circuit match)
            ['{abc-333}', 'WO-003', '', 'Assessment', 'SA', 'false', null, null, null, null, null, '99999', null, null],
        ],
    ];
}

test('dry-run does not modify database', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeSsJobsResponse())]);

    $this->artisan('ws:fetch-jobs --dry-run')
        ->assertSuccessful();

    expect(SsJob::count())->toBe(0);
});

test('creates ss_job records from API response', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeSsJobsResponse())]);

    $this->artisan('ws:fetch-jobs')
        ->assertSuccessful();

    // 4 raw rows → 3 unique JOBGUIDs
    expect(SsJob::count())->toBe(3);
});

test('groups extensions by job guid', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeSsJobsResponse())]);

    $this->artisan('ws:fetch-jobs')->assertSuccessful();

    $job = SsJob::find('{abc-111}');
    expect($job->extensions)->toContain('EXT-A')
        ->and($job->extensions)->toContain('EXT-B')
        ->and($job->extensions)->toHaveCount(2);
});

test('resolves circuit id from raw title', function () {
    $circuit = Circuit::factory()->create([
        'line_name' => '12705',
        'properties' => ['raw_line_name' => '12705'],
    ]);

    Http::fake(['*/GETQUERY' => Http::response(fakeSsJobsResponse())]);

    $this->artisan('ws:fetch-jobs')->assertSuccessful();

    $job = SsJob::find('{abc-111}');
    expect($job->circuit_id)->toBe($circuit->id);

    // Job with unmatched title has null circuit
    $job3 = SsJob::find('{abc-333}');
    expect($job3->circuit_id)->toBeNull();
});

test('resolves taken_by and modified_by from ws_users', function () {
    $jsmith = WsUser::factory()->create(['username' => 'ASPLUNDH\\jsmith']);
    $jdoe = WsUser::factory()->create(['username' => 'ASPLUNDH\\jdoe']);

    Http::fake(['*/GETQUERY' => Http::response(fakeSsJobsResponse())]);

    $this->artisan('ws:fetch-jobs')->assertSuccessful();

    $job = SsJob::find('{abc-111}');
    expect($job->taken_by_id)->toBe($jsmith->id)
        ->and($job->modified_by_id)->toBe($jdoe->id)
        ->and($job->taken)->toBeTrue();
});

test('sets parent job guid for child jobs', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeSsJobsResponse())]);

    $this->artisan('ws:fetch-jobs')->assertSuccessful();

    $child = SsJob::find('{abc-222}');
    expect($child->parent_job_guid)->toBe('{abc-111}')
        ->and($child->parentJob->job_guid)->toBe('{abc-111}');
});

test('parses edit date correctly', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeSsJobsResponse())]);

    $this->artisan('ws:fetch-jobs')->assertSuccessful();

    $job = SsJob::find('{abc-111}');
    expect($job->edit_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($job->edit_date->format('Y-m-d'))->toBe('2026-01-15');

    // Null edit date
    $job3 = SsJob::find('{abc-333}');
    expect($job3->edit_date)->toBeNull();
});

test('updates existing jobs on re-run', function () {
    // Pre-create a job with ACTIV status
    SsJob::factory()->create([
        'job_guid' => '{abc-111}',
        'status' => 'ACTIV',
        'work_order' => 'WO-001',
        'job_type' => 'Assessment',
        'raw_title' => '12705',
    ]);

    expect(SsJob::find('{abc-111}')->status)->toBe('ACTIV');

    // API response has QC status for this job
    $response = fakeSsJobsResponse();
    $response['Data'][0][4] = 'QC';
    $response['Data'][1][4] = 'QC';

    Http::fake(['*/GETQUERY' => Http::response($response)]);

    $this->artisan('ws:fetch-jobs')->assertSuccessful();

    // Should have 3 total (1 updated + 2 created)
    expect(SsJob::count())->toBe(3);

    $job = SsJob::find('{abc-111}');
    expect($job->status)->toBe('QC');
});

test('updates circuit properties with jobguids', function () {
    $circuit = Circuit::factory()->create([
        'line_name' => '12705',
        'properties' => ['raw_line_name' => '12705', '2026' => ['total_miles' => 8.24]],
    ]);

    Http::fake(['*/GETQUERY' => Http::response(fakeSsJobsResponse())]);

    $this->artisan('ws:fetch-jobs --year=2026')->assertSuccessful();

    $circuit->refresh();
    $yearData = $circuit->properties['2026'];
    expect($yearData)->toHaveKey('jobguids')
        ->and($yearData['total_miles'])->toBe(8.24)
        ->and($yearData['jobguids'])->toContain('{abc-111}')
        ->and($yearData['jobguids'])->toContain('{abc-222}');
});

test('handles API error response gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response([
        'protocol' => 'ERROR',
        'errorMessage' => 'Access denied',
    ])]);

    $this->artisan('ws:fetch-jobs')
        ->assertFailed();
});

test('handles empty data set gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response([
        'Heading' => ['JOBGUID', 'WO', 'EXT', 'JOBTYPE', 'STATUS', 'TAKEN', 'TAKENBY', 'MODIFIEDBY', 'VERSION', 'SYNCHVERSN', 'ASSIGNEDTO', 'TITLE', 'PJOBGUID', 'EDITDATE'],
        'Data' => [],
    ])]);

    $this->artisan('ws:fetch-jobs')
        ->assertSuccessful();

    expect(SsJob::count())->toBe(0);
});

test('filters empty extensions from extensions array', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeSsJobsResponse())]);

    $this->artisan('ws:fetch-jobs')->assertSuccessful();

    // Job 3 has empty EXT
    $job3 = SsJob::find('{abc-333}');
    expect($job3->extensions)->toBe([]);
});

<?php

use App\Models\Assessment;
use App\Models\Circuit;
use Illuminate\Support\Facades\Http;

function fakeAssessmentsHeading(): array
{
    return [
        'JOBGUID', 'PJOBGUID', 'WO', 'EXT', 'JOBTYPE', 'STATUS',
        'TAKEN', 'TAKENBY', 'MODIFIEDBY', 'VERSION', 'SYNCHVERSN',
        'ASSIGNEDTO', 'TITLE',
        'CYCLETYPE', 'REGION',
        'PLANNEDEMERGENT', 'VOLTAGE', 'COSTMETHOD', 'PROGRAMNAME', 'PERMISSIONING_REQUIRED',
        'PRCENT', 'LENGTH', 'LENGTHCOMP',
        'EDITDATE_OLE', 'EDITDATE',
        'SCOPE_YEAR',
    ];
}

function fakeAssessmentRow(array $overrides = []): array
{
    $defaults = [
        'JOBGUID' => '{aaa-111-111-111}',
        'PJOBGUID' => '',
        'WO' => 'WO-001',
        'EXT' => '@',
        'JOBTYPE' => 'Assessment Dx',
        'STATUS' => 'ACTIV',
        'TAKEN' => 'true',
        'TAKENBY' => 'ASPLUNDH\\jsmith',
        'MODIFIEDBY' => 'ASPLUNDH\\jdoe',
        'VERSION' => 5,
        'SYNCHVERSN' => 10,
        'ASSIGNEDTO' => null,
        'TITLE' => '12705',
        'CYCLETYPE' => 'Annual',
        'REGION' => 'EAST',
        'PLANNEDEMERGENT' => 'Planned',
        'VOLTAGE' => 69.0,
        'COSTMETHOD' => 'TM',
        'PROGRAMNAME' => 'Cycle Maintenance',
        'PERMISSIONING_REQUIRED' => 'false',
        'PRCENT' => 45,
        'LENGTH' => 8.5,
        'LENGTHCOMP' => 3.2,
        'EDITDATE_OLE' => 46065.75,
        'EDITDATE' => '2026-02-12 18:00:00',
        'SCOPE_YEAR' => 2026,
    ];

    $merged = array_merge($defaults, $overrides);
    $heading = fakeAssessmentsHeading();

    return array_map(fn (string $col) => $merged[$col], $heading);
}

function fakeAssessmentsResponse(?array $rows = null): array
{
    $rows ??= [
        fakeAssessmentRow(),
        fakeAssessmentRow([
            'JOBGUID' => '{aaa-222-222-222}',
            'PJOBGUID' => '{aaa-111-111-111}',
            'EXT' => 'C_a',
            'JOBTYPE' => 'Split_Assessment',
            'TAKEN' => 'false',
            'TAKENBY' => null,
            'MODIFIEDBY' => null,
            'VERSION' => 3,
            'SYNCHVERSN' => 8,
            'PRCENT' => 20,
            'LENGTH' => 4.0,
            'LENGTHCOMP' => 1.5,
            'EDITDATE_OLE' => 46066.5,
            'EDITDATE' => '2026-02-13 12:00:00',
        ]),
        fakeAssessmentRow([
            'JOBGUID' => '{bbb-333-333-333}',
            'WO' => 'WO-002',
            'STATUS' => 'QC',
            'TAKEN' => 'false',
            'TAKENBY' => null,
            'MODIFIEDBY' => null,
            'VERSION' => 1,
            'SYNCHVERSN' => 2,
            'ASSIGNEDTO' => 'somebody',
            'TITLE' => '99999',
            'CYCLETYPE' => null,
            'REGION' => 'WEST',
            'PRCENT' => 0,
            'LENGTH' => 5.0,
            'LENGTHCOMP' => 0.0,
            'EDITDATE_OLE' => 46060.0,
            'EDITDATE' => '2026-02-07 00:00:00',
        ]),
    ];

    return [
        'Heading' => fakeAssessmentsHeading(),
        'Data' => $rows,
    ];
}

function createMatchingCircuit(string $rawLineName = '12705'): Circuit
{
    return Circuit::factory()->create([
        'line_name' => $rawLineName,
        'properties' => ['raw_line_name' => $rawLineName],
    ]);
}

// ── Dry-run ──────────────────────────────────────────────

test('dry-run displays preview without modifying database', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments --dry-run')
        ->assertSuccessful();

    expect(Assessment::count())->toBe(0);
});

// ── Basic upsert ─────────────────────────────────────────

test('upserts assessment records from API response', function () {
    $circuit = createMatchingCircuit();
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments')
        ->assertSuccessful();

    // 3 rows, but {bbb-333} has unmatched circuit → 2 inserted
    expect(Assessment::count())->toBe(2);
});

test('maps all columns correctly from API row', function () {
    createMatchingCircuit();
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    $parent = Assessment::where('job_guid', '{aaa-111-111-111}')->first();
    expect($parent->work_order)->toBe('WO-001')
        ->and($parent->extension)->toBe('@')
        ->and($parent->job_type)->toBe('Assessment Dx')
        ->and($parent->status)->toBe('ACTIV')
        ->and($parent->scope_year)->toBe('2026')
        ->and($parent->taken)->toBeTrue()
        ->and($parent->taken_by_username)->toBe('ASPLUNDH\\jsmith')
        ->and($parent->modified_by_username)->toBe('ASPLUNDH\\jdoe')
        ->and($parent->version)->toBe(5)
        ->and($parent->sync_version)->toBe(10)
        ->and($parent->cycle_type)->toBe('Annual')
        ->and($parent->region)->toBe('EAST')
        ->and($parent->planned_emergent)->toBe('Planned')
        ->and($parent->voltage)->toBe(69.0)
        ->and($parent->cost_method)->toBe('TM')
        ->and($parent->program_name)->toBe('Cycle Maintenance')
        ->and($parent->permissioning_required)->toBeFalse()
        ->and($parent->percent_complete)->toBe(45)
        ->and($parent->length)->toBe(8.5)
        ->and($parent->length_completed)->toBe(3.2)
        ->and($parent->last_edited_ole)->toBe(46065.75)
        ->and($parent->last_edited->format('Y-m-d'))->toBe('2026-02-12');
});

// ── Circuit resolution ───────────────────────────────────

test('resolves circuit_id from raw_title against circuit properties', function () {
    $circuit = createMatchingCircuit();
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    $parent = Assessment::where('job_guid', '{aaa-111-111-111}')->first();
    expect($parent->circuit_id)->toBe($circuit->id);
});

test('skips records with unresolvable circuit and logs failure', function () {
    createMatchingCircuit('12705');
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    // {bbb-333} has title '99999' — no matching circuit → skipped
    expect(Assessment::where('job_guid', '{bbb-333-333-333}')->exists())->toBeFalse();

    // Verify log file was written
    $logPath = storage_path('logs/failed-assessment-fetch.log');
    expect(file_exists($logPath))->toBeTrue();
    $logContent = file_get_contents($logPath);
    expect($logContent)->toContain('{bbb-333-333-333}')
        ->and($logContent)->toContain('99999');
});

// ── Parent / child FK ordering ───────────────────────────

test('inserts parents before children via extension depth sort', function () {
    createMatchingCircuit();

    // Send child row BEFORE parent row — command should reorder by strlen(EXT)
    $rows = [
        fakeAssessmentRow([
            'JOBGUID' => '{child-222}',
            'PJOBGUID' => '{parent-111}',
            'EXT' => 'C_a',
            'JOBTYPE' => 'Split_Assessment',
            'EDITDATE_OLE' => 46066.5,
            'EDITDATE' => '2026-02-13 12:00:00',
        ]),
        fakeAssessmentRow([
            'JOBGUID' => '{parent-111}',
            'EXT' => '@',
            'EDITDATE_OLE' => 46065.75,
            'EDITDATE' => '2026-02-12 18:00:00',
        ]),
    ];

    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse($rows))]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    expect(Assessment::count())->toBe(2);

    $child = Assessment::where('job_guid', '{child-222}')->first();
    expect($child->parent_job_guid)->toBe('{parent-111}')
        ->and($child->parent->job_guid)->toBe('{parent-111}');
});

test('nullifies parent_job_guid on parent assessments with EXT @', function () {
    createMatchingCircuit();

    // API returns an Assessment Dx with @ extension but a non-empty PJOBGUID — should be nullified
    $rows = [
        fakeAssessmentRow([
            'JOBGUID' => '{parent-with-pjob}',
            'PJOBGUID' => '{some-other-guid}',
            'EXT' => '@',
            'JOBTYPE' => 'Assessment Dx',
        ]),
    ];

    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse($rows))]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    $assessment = Assessment::where('job_guid', '{parent-with-pjob}')->first();
    expect($assessment->parent_job_guid)->toBeNull();
});

// ── is_split flagging ────────────────────────────────────

test('flags parent assessments as is_split when they have children', function () {
    createMatchingCircuit();
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    $parent = Assessment::where('job_guid', '{aaa-111-111-111}')->first();
    expect($parent->is_split)->toBeTrue();

    // Child itself is NOT flagged as is_split
    $child = Assessment::where('job_guid', '{aaa-222-222-222}')->first();
    expect($child->is_split)->toBeFalse();
});

// ── discovered_at / last_synced_at ───────────────────────

test('sets discovered_at on first insert and preserves it on update', function () {
    createMatchingCircuit();
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    // First run
    $this->travel(-1)->hours();
    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    $assessment = Assessment::where('job_guid', '{aaa-111-111-111}')->first();
    $originalDiscoveredAt = $assessment->discovered_at->copy();
    $originalSyncedAt = $assessment->last_synced_at->copy();

    // Second run — 1 hour later
    $this->travelBack();
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);
    $this->artisan('ws:fetch-assessments --full')->assertSuccessful();

    $assessment->refresh();
    expect($assessment->discovered_at->equalTo($originalDiscoveredAt))->toBeTrue()
        ->and($assessment->last_synced_at->greaterThan($originalSyncedAt))->toBeTrue();
});

// ── Incremental sync ─────────────────────────────────────

test('incremental sync adds EDITDATE filter to API query', function () {
    $circuit = createMatchingCircuit();

    // Pre-seed an assessment with a known OLE value
    Assessment::factory()->create([
        'circuit_id' => $circuit->id,
        'last_edited_ole' => 46060.0,
    ]);

    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    Http::assertSent(function ($request) {
        $sql = $request->data()['SQL'] ?? '';

        return str_contains($sql, 'VEGJOB.EDITDATE > 46060');
    });
});

test('--full flag bypasses incremental sync', function () {
    $circuit = createMatchingCircuit();

    Assessment::factory()->create([
        'circuit_id' => $circuit->id,
        'last_edited_ole' => 46060.0,
    ]);

    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments --full')->assertSuccessful();

    Http::assertSent(function ($request) {
        $sql = $request->data()['SQL'] ?? '';

        return ! str_contains($sql, 'VEGJOB.EDITDATE >');
    });
});

// ── Status filtering ─────────────────────────────────────

test('defaults to planner_concern statuses when no --status provided', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments --dry-run')->assertSuccessful();

    Http::assertSent(function ($request) {
        $sql = $request->data()['SQL'] ?? '';

        return str_contains($sql, "SS.STATUS IN ('ACTIV'")
            && str_contains($sql, "'CLOSE'");
    });
});

test('--status filters to single status', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments --status=ACTIV --dry-run')->assertSuccessful();

    Http::assertSent(function ($request) {
        $sql = $request->data()['SQL'] ?? '';

        return str_contains($sql, "SS.STATUS = 'ACTIV'")
            && ! str_contains($sql, 'SS.STATUS IN');
    });
});

// ── Year filtering ───────────────────────────────────────

test('always joins xref table and selects scope year', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments --dry-run')->assertSuccessful();

    Http::assertSent(function ($request) {
        $sql = $request->data()['SQL'] ?? '';

        return str_contains($sql, 'WPStartDate_Assessment_Xrefs')
            && str_contains($sql, "COALESCE(NULLIF(SS.PJOBGUID, ''), SS.JOBGUID)")
            && str_contains($sql, 'SCOPE_YEAR');
    });
});

test('--year adds xref year filter to query', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments --year=2026 --dry-run')->assertSuccessful();

    Http::assertSent(function ($request) {
        $sql = $request->data()['SQL'] ?? '';

        return str_contains($sql, "'%2026%'");
    });
});

test('omits year filter when no --year provided', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments --dry-run')->assertSuccessful();

    Http::assertSent(function ($request) {
        $sql = $request->data()['SQL'] ?? '';

        return ! str_contains($sql, 'WP_STARTDATE LIKE');
    });
});

// ── Circuit property update ──────────────────────────────

test('updates circuit properties with jobguids grouped by cycle type and scope year', function () {
    $circuit = Circuit::factory()->create([
        'line_name' => '12705',
        'properties' => ['raw_line_name' => '12705'],
    ]);

    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    $circuit->refresh();
    $yearData = $circuit->properties['2026'];
    expect($yearData)->toHaveKey('Annual')
        ->and($yearData['Annual'])->toContain('{aaa-111-111-111}')
        ->and($yearData['Annual'])->toContain('{aaa-222-222-222}');
});

test('stores null scope_year when xref has no WP_STARTDATE', function () {
    createMatchingCircuit();

    $rows = [
        fakeAssessmentRow(['SCOPE_YEAR' => null]),
    ];

    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse($rows))]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    $assessment = Assessment::where('job_guid', '{aaa-111-111-111}')->first();
    expect($assessment->scope_year)->toBeNull();
});

// ── Error handling ───────────────────────────────────────

test('handles API error response gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response([
        'protocol' => 'ERROR',
        'errorMessage' => 'Access denied',
    ])]);

    $this->artisan('ws:fetch-assessments')
        ->assertFailed();
});

test('handles empty data set gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response([
        'Heading' => fakeAssessmentsHeading(),
        'Data' => [],
    ])]);

    $this->artisan('ws:fetch-assessments')
        ->assertSuccessful();

    expect(Assessment::count())->toBe(0);
});

// ── VEGJOB fields ────────────────────────────────────────

test('stores OLE float alongside converted timestamp', function () {
    createMatchingCircuit();
    Http::fake(['*/GETQUERY' => Http::response(fakeAssessmentsResponse())]);

    $this->artisan('ws:fetch-assessments')->assertSuccessful();

    $assessment = Assessment::where('job_guid', '{aaa-111-111-111}')->first();
    expect($assessment->last_edited_ole)->toBe(46065.75)
        ->and($assessment->last_edited)->not->toBeNull();
});

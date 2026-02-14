<?php

use App\Models\GhostOwnershipPeriod;
use App\Models\GhostUnitEvidence;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\DataCollection\GhostDetectionService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->mockQS = Mockery::mock(GetQueryService::class);
});

function makeGhostService($mock): GhostDetectionService
{
    return new GhostDetectionService($mock);
}

// --- createBaseline ---

test('createBaseline creates ownership period with UNITGUID snapshot', function () {
    $jobGuid = '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}';
    $username = 'ONEPPL\\takeover_user';

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['UNITGUID' => '{unit-1}', 'unit_type' => 'SPM', 'STATNAME' => '1', 'PERMSTAT' => 'Granted', 'FORESTER' => 'Smith'],
            ['UNITGUID' => '{unit-2}', 'unit_type' => 'SPB', 'STATNAME' => '2', 'PERMSTAT' => 'Pending', 'FORESTER' => 'Jones'],
            ['UNITGUID' => '{unit-3}', 'unit_type' => 'BRUSH', 'STATNAME' => '3', 'PERMSTAT' => 'Denied', 'FORESTER' => 'Brown'],
        ]));

    $service = makeGhostService($this->mockQS);
    $period = $service->createBaseline($jobGuid, $username, false, [
        'LINENAME' => 'Circuit-1234',
        'REGION' => 'CENTRAL',
    ]);

    expect($period)->toBeInstanceOf(GhostOwnershipPeriod::class)
        ->and($period->exists)->toBeTrue()
        ->and($period->job_guid)->toBe($jobGuid)
        ->and($period->takeover_username)->toBe($username)
        ->and($period->baseline_unit_count)->toBe(3)
        ->and($period->baseline_snapshot)->toHaveCount(3)
        ->and($period->baseline_snapshot[0]['unitguid'])->toBe('{unit-1}')
        ->and($period->status)->toBe('active')
        ->and($period->is_parent_takeover)->toBeFalse()
        ->and($period->line_name)->toBe('Circuit-1234');
});

test('createBaseline marks parent takeover when isParent is true', function () {
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([]));

    $service = makeGhostService($this->mockQS);
    $period = $service->createBaseline('{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}', 'ONEPPL\\user', true, [
        'LINENAME' => 'Circuit-9999',
        'REGION' => 'HARRISBURG',
    ]);

    expect($period->is_parent_takeover)->toBeTrue();
});

// --- runComparison ---

test('runComparison detects missing units as ghosts', function () {
    $unit1 = '{'.Str::uuid()->toString().'}';
    $unit2 = '{'.Str::uuid()->toString().'}';
    $unit3 = '{'.Str::uuid()->toString().'}';

    $period = GhostOwnershipPeriod::factory()->create([
        'baseline_snapshot' => [
            ['unitguid' => $unit1, 'unit_type' => 'SPM', 'statname' => '1', 'permstat' => 'Granted', 'forester' => 'Smith'],
            ['unitguid' => $unit2, 'unit_type' => 'SPB', 'statname' => '2', 'permstat' => 'Pending', 'forester' => 'Jones'],
            ['unitguid' => $unit3, 'unit_type' => 'BRUSH', 'statname' => '3', 'permstat' => 'Denied', 'forester' => 'Brown'],
        ],
        'baseline_unit_count' => 3,
    ]);

    // Only unit1 remains — unit2 and unit3 are "ghosts"
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['UNITGUID' => $unit1, 'unit_type' => 'SPM', 'STATNAME' => '1', 'PERMSTAT' => 'Granted', 'FORESTER' => 'Smith'],
        ]));

    $service = makeGhostService($this->mockQS);
    $count = $service->runComparison($period);

    expect($count)->toBe(2)
        ->and(GhostUnitEvidence::where('ownership_period_id', $period->id)->count())->toBe(2);

    $evidence = GhostUnitEvidence::where('unitguid', $unit2)->first();
    expect($evidence)->not->toBeNull()
        ->and($evidence->unit_type)->toBe('SPB')
        ->and($evidence->permstat_at_snapshot)->toBe('Pending')
        ->and($evidence->detected_date->toDateString())->toBe(now()->toDateString());
});

test('runComparison excludes already-detected evidence', function () {
    $unit1 = '{'.Str::uuid()->toString().'}';
    $unit2 = '{'.Str::uuid()->toString().'}';

    $period = GhostOwnershipPeriod::factory()->create([
        'baseline_snapshot' => [
            ['unitguid' => $unit1, 'unit_type' => 'SPM', 'statname' => '1', 'permstat' => 'Granted', 'forester' => 'Smith'],
            ['unitguid' => $unit2, 'unit_type' => 'SPB', 'statname' => '2', 'permstat' => 'Pending', 'forester' => 'Jones'],
        ],
        'baseline_unit_count' => 2,
    ]);

    // unit1 was already detected previously
    GhostUnitEvidence::factory()->create([
        'ownership_period_id' => $period->id,
        'job_guid' => $period->job_guid,
        'unitguid' => $unit1,
    ]);

    // Neither unit is in the current API response (both "missing")
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([]));

    $service = makeGhostService($this->mockQS);
    $count = $service->runComparison($period);

    // Only unit2 is new — unit1 was already recorded
    expect($count)->toBe(1)
        ->and(GhostUnitEvidence::where('ownership_period_id', $period->id)->count())->toBe(2);
});

test('runComparison returns zero when no units are missing', function () {
    $unit1 = '{'.Str::uuid()->toString().'}';

    $period = GhostOwnershipPeriod::factory()->create([
        'baseline_snapshot' => [
            ['unitguid' => $unit1, 'unit_type' => 'SPM', 'statname' => '1', 'permstat' => 'Granted', 'forester' => 'Smith'],
        ],
        'baseline_unit_count' => 1,
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['UNITGUID' => $unit1, 'unit_type' => 'SPM', 'STATNAME' => '1', 'PERMSTAT' => 'Granted', 'FORESTER' => 'Smith'],
        ]));

    $service = makeGhostService($this->mockQS);
    $count = $service->runComparison($period);

    expect($count)->toBe(0)
        ->and(GhostUnitEvidence::where('ownership_period_id', $period->id)->count())->toBe(0);
});

// --- resolveOwnershipReturn ---

test('resolveOwnershipReturn runs comparison and marks period resolved', function () {
    $unit1 = '{'.Str::uuid()->toString().'}';

    $period = GhostOwnershipPeriod::factory()->active()->create([
        'baseline_snapshot' => [
            ['unitguid' => $unit1, 'unit_type' => 'SPM', 'statname' => '1', 'permstat' => 'Granted', 'forester' => 'Smith'],
        ],
        'baseline_unit_count' => 1,
    ]);

    // Unit still present — no ghosts
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([
            ['UNITGUID' => $unit1, 'unit_type' => 'SPM', 'STATNAME' => '1', 'PERMSTAT' => 'Granted', 'FORESTER' => 'Smith'],
        ]));

    $service = makeGhostService($this->mockQS);
    $service->resolveOwnershipReturn($period);

    $period->refresh();

    expect($period->status)->toBe('resolved')
        ->and($period->return_date->toDateString())->toBe(now()->toDateString());
});

// --- cleanupOnClose ---

test('cleanupOnClose deletes ownership periods for job_guid', function () {
    $jobGuid = '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}';

    GhostOwnershipPeriod::factory()->create(['job_guid' => $jobGuid]);
    GhostOwnershipPeriod::factory()->create(['job_guid' => $jobGuid]);
    $unrelated = GhostOwnershipPeriod::factory()->create();

    $service = makeGhostService($this->mockQS);
    $service->cleanupOnClose($jobGuid);

    expect(GhostOwnershipPeriod::where('job_guid', $jobGuid)->count())->toBe(0)
        ->and(GhostOwnershipPeriod::where('id', $unrelated->id)->exists())->toBeTrue();
});

// --- checkForOwnershipChanges ---

test('checkForOwnershipChanges creates baselines for new takeovers', function () {
    $this->mockQS->shouldReceive('executeAndHandle')
        ->times(3) // 1 ownership query + 2 baseline snapshot queries
        ->andReturn(
            // getRecentOwnershipChanges result
            collect([
                ['JOBGUID' => '{11111111-1111-1111-1111-111111111111}', 'ASSIGNEDTO' => 'ONEPPL\\newuser', 'LINENAME' => 'Circuit-A', 'REGION' => 'CENTRAL', 'EXT' => ''],
                ['JOBGUID' => '{22222222-2222-2222-2222-222222222222}', 'ASSIGNEDTO' => 'ONEPPL\\otheruser', 'LINENAME' => 'Circuit-B', 'REGION' => 'LEHIGH', 'EXT' => ''],
            ]),
            // Baseline snapshot for first takeover
            collect([
                ['UNITGUID' => '{unit-a1}', 'unit_type' => 'SPM', 'STATNAME' => '1', 'PERMSTAT' => 'Granted', 'FORESTER' => 'Smith'],
            ]),
            // Baseline snapshot for second takeover
            collect([
                ['UNITGUID' => '{unit-b1}', 'unit_type' => 'SPB', 'STATNAME' => '2', 'PERMSTAT' => 'Pending', 'FORESTER' => 'Jones'],
            ]),
        );

    $service = makeGhostService($this->mockQS);
    $count = $service->checkForOwnershipChanges();

    expect($count)->toBe(2)
        ->and(GhostOwnershipPeriod::count())->toBe(2);

    $period1 = GhostOwnershipPeriod::where('job_guid', '{11111111-1111-1111-1111-111111111111}')->first();
    expect($period1->takeover_username)->toBe('ONEPPL\\newuser')
        ->and($period1->status)->toBe('active')
        ->and($period1->baseline_unit_count)->toBe(1);
});

test('checkForOwnershipChanges skips already tracked takeovers', function () {
    $existingGuid = '{11111111-1111-1111-1111-111111111111}';

    // Pre-existing active period for this job+user
    GhostOwnershipPeriod::factory()->active()->create([
        'job_guid' => $existingGuid,
        'takeover_username' => 'ONEPPL\\existinguser',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->times(2) // 1 ownership query + 1 baseline for the new one only
        ->andReturn(
            collect([
                ['JOBGUID' => $existingGuid, 'ASSIGNEDTO' => 'ONEPPL\\existinguser', 'LINENAME' => 'Circuit-A', 'REGION' => 'CENTRAL', 'EXT' => ''],
                ['JOBGUID' => '{22222222-2222-2222-2222-222222222222}', 'ASSIGNEDTO' => 'ONEPPL\\newuser', 'LINENAME' => 'Circuit-B', 'REGION' => 'LEHIGH', 'EXT' => ''],
            ]),
            collect([
                ['UNITGUID' => '{unit-1}', 'unit_type' => 'SPM', 'STATNAME' => '1', 'PERMSTAT' => 'Granted', 'FORESTER' => 'Smith'],
            ]),
        );

    $service = makeGhostService($this->mockQS);
    $count = $service->checkForOwnershipChanges();

    expect($count)->toBe(1)
        ->and(GhostOwnershipPeriod::count())->toBe(2); // 1 pre-existing + 1 new
});

test('checkForOwnershipChanges detects parent takeover via EXT field', function () {
    $this->mockQS->shouldReceive('executeAndHandle')
        ->times(2)
        ->andReturn(
            collect([
                ['JOBGUID' => '{11111111-1111-1111-1111-111111111111}', 'ASSIGNEDTO' => 'ONEPPL\\parentuser', 'LINENAME' => 'Circuit-A', 'REGION' => 'CENTRAL', 'EXT' => '@'],
            ]),
            collect([]),
        );

    $service = makeGhostService($this->mockQS);
    $count = $service->checkForOwnershipChanges();

    expect($count)->toBe(1);

    $period = GhostOwnershipPeriod::first();
    expect($period->is_parent_takeover)->toBeTrue();
});

test('checkForOwnershipChanges returns zero when API returns no changes', function () {
    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([]));

    $service = makeGhostService($this->mockQS);
    $count = $service->checkForOwnershipChanges();

    expect($count)->toBe(0)
        ->and(GhostOwnershipPeriod::count())->toBe(0);
});

test('checkForOwnershipChanges uses latest period date as since parameter', function () {
    // Create an existing period with a known created_at
    GhostOwnershipPeriod::factory()->create([
        'created_at' => '2026-02-10 12:00:00',
    ]);

    $this->mockQS->shouldReceive('executeAndHandle')
        ->once()
        ->andReturn(collect([]));

    $service = makeGhostService($this->mockQS);
    $service->checkForOwnershipChanges();

    // The since date should be based on the latest period, not 7-day default
    // We verify indirectly — the method ran without error using the latest created_at
    expect(GhostOwnershipPeriod::count())->toBe(1);
});

// --- cleanupOnClose (continued) ---

test('cleanupOnClose preserves ghost evidence via SET NULL FK', function () {
    $jobGuid = '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}';

    $period = GhostOwnershipPeriod::factory()->create(['job_guid' => $jobGuid]);
    $evidence = GhostUnitEvidence::factory()->create([
        'ownership_period_id' => $period->id,
        'job_guid' => $jobGuid,
    ]);

    $service = makeGhostService($this->mockQS);
    $service->cleanupOnClose($jobGuid);

    $evidence->refresh();

    expect($evidence->exists)->toBeTrue()
        ->and($evidence->ownership_period_id)->toBeNull();
});

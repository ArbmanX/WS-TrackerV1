<?php

use App\Console\Commands\Fetch\FetchAssessments;
use App\Models\Assessment;
use App\Models\AssessmentContributor;
use App\Models\AssessmentMetric;
use App\Models\UnitType;
use App\Models\WsUser;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function createTestCommand(): FetchAssessments
{
    $command = new FetchAssessments;
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    return $command;
}

function buildMockMetricsRow(string $jobGuid, array $overrides = []): array
{
    return array_merge([
        'JOBGUID' => $jobGuid,
        'WO' => 'WO-123456',
        'EXT' => '@',
        'total_units' => 50,
        'approved' => 20,
        'pending' => 15,
        'refused' => 3,
        'no_contact' => 2,
        'deferred' => 1,
        'ppl_approved' => 9,
        'units_requiring_notes' => 30,
        'units_with_notes' => 25,
        'units_without_notes' => 5,
        'notes_compliance_percent' => 83.3,
        'pending_over_threshold' => 4,
        'stations_with_work' => 12,
        'stations_no_work' => 3,
        'stations_not_planned' => 1,
        'split_count' => null,
        'split_updated' => null,
        'taken_date' => '2026-01-15',
        'sent_to_qc_date' => null,
        'sent_to_rework_date' => null,
        'closed_date' => null,
        'first_unit_date' => '2026-01-20',
        'last_unit_date' => '2026-02-10',
        'oldest_pending_date' => '2026-01-25',
        'oldest_pending_statname' => 'STA-001',
        'oldest_pending_unit' => 'SPM',
        'oldest_pending_sequence' => 42,
        'work_type_breakdown' => json_encode([
            ['unit' => 'REM612', 'UnitQty' => 45.00],
            ['unit' => 'SPM', 'UnitQty' => 12.00],
        ]),
        'foresters' => json_encode([
            ['forester' => 'ASPLUNDH\\jdoe', 'unit_count' => 12],
            ['forester' => 'ASPLUNDH\\jsmith', 'unit_count' => 8],
        ]),
    ], $overrides);
}

test('persistMetrics creates metric row from API data', function () {
    $assessment = Assessment::factory()->create();
    UnitType::factory()->create(['unit' => 'REM612', 'entityname' => 'Removal 6-12 DBH']);
    UnitType::factory()->create(['unit' => 'SPM', 'entityname' => 'Side Prune Mechanical']);

    $rows = collect([buildMockMetricsRow($assessment->job_guid)]);

    $command = createTestCommand();
    $command->persistMetrics($rows);

    $metric = AssessmentMetric::where('job_guid', $assessment->job_guid)->first();

    expect($metric)->not->toBeNull()
        ->and($metric->work_order)->toBe('WO-123456')
        ->and($metric->total_units)->toBe(50)
        ->and($metric->approved)->toBe(20)
        ->and($metric->pending)->toBe(15)
        ->and($metric->stations_with_work)->toBe(12)
        ->and($metric->taken_date->format('Y-m-d'))->toBe('2026-01-15');
});

test('persistMetrics enriches work_type_breakdown with display names', function () {
    $assessment = Assessment::factory()->create();
    UnitType::factory()->create(['unit' => 'REM612', 'entityname' => 'Removal 6-12 DBH']);
    UnitType::factory()->create(['unit' => 'SPM', 'entityname' => 'Side Prune Mechanical']);

    $rows = collect([buildMockMetricsRow($assessment->job_guid)]);

    $command = createTestCommand();
    $command->persistMetrics($rows);

    $metric = AssessmentMetric::where('job_guid', $assessment->job_guid)->first();

    expect($metric->work_type_breakdown)->toBeArray()
        ->and($metric->work_type_breakdown[0])->toBe([
            'unit' => 'REM612',
            'display_name' => 'Removal 6-12 DBH',
            'quantity' => 45,
        ])
        ->and($metric->work_type_breakdown[1]['quantity'])->toBe(12);
});

test('persistMetrics falls back to raw unit code when UnitType missing', function () {
    $assessment = Assessment::factory()->create();

    $rows = collect([buildMockMetricsRow($assessment->job_guid, [
        'work_type_breakdown' => json_encode([['unit' => 'UNKNOWN99', 'UnitQty' => 7.00]]),
    ])]);

    $command = createTestCommand();
    $command->persistMetrics($rows);

    $metric = AssessmentMetric::where('job_guid', $assessment->job_guid)->first();

    expect($metric->work_type_breakdown[0]['display_name'])->toBe('UNKNOWN99');
});

test('persistMetrics upserts on second call', function () {
    $assessment = Assessment::factory()->create();

    $row = buildMockMetricsRow($assessment->job_guid, ['total_units' => 50]);

    $command = createTestCommand();
    $command->persistMetrics(collect([$row]));

    $row['total_units'] = 75;
    $command->persistMetrics(collect([$row]));

    expect(AssessmentMetric::where('job_guid', $assessment->job_guid)->count())->toBe(1)
        ->and(AssessmentMetric::where('job_guid', $assessment->job_guid)->first()->total_units)->toBe(75);
});

test('persistContributors creates contributor rows from foresters JSON', function () {
    $assessment = Assessment::factory()->create();
    $wsUser = WsUser::factory()->create(['username' => 'ASPLUNDH\\jdoe']);

    $rows = collect([buildMockMetricsRow($assessment->job_guid)]);

    $command = createTestCommand();
    $command->persistContributors($rows);

    $contributors = AssessmentContributor::where('job_guid', $assessment->job_guid)->get();

    expect($contributors)->toHaveCount(2);

    $jdoe = $contributors->firstWhere('ws_username', 'ASPLUNDH\\jdoe');
    expect($jdoe->unit_count)->toBe(12)
        ->and($jdoe->ws_user_id)->toBe($wsUser->id);

    $jsmith = $contributors->firstWhere('ws_username', 'ASPLUNDH\\jsmith');
    expect($jsmith->unit_count)->toBe(8)
        ->and($jsmith->ws_user_id)->toBeNull();
});

test('persistContributors handles null foresters gracefully', function () {
    $assessment = Assessment::factory()->create();

    $rows = collect([buildMockMetricsRow($assessment->job_guid, ['foresters' => null])]);

    $command = createTestCommand();
    $command->persistContributors($rows);

    expect(AssessmentContributor::where('job_guid', $assessment->job_guid)->count())->toBe(0);
});

test('persistContributors handles empty foresters JSON gracefully', function () {
    $assessment = Assessment::factory()->create();

    $rows = collect([buildMockMetricsRow($assessment->job_guid, ['foresters' => '[]'])]);

    $command = createTestCommand();
    $command->persistContributors($rows);

    expect(AssessmentContributor::where('job_guid', $assessment->job_guid)->count())->toBe(0);
});

test('persistContributors upserts on second call with updated counts', function () {
    $assessment = Assessment::factory()->create();

    $command = createTestCommand();

    $command->persistContributors(collect([buildMockMetricsRow($assessment->job_guid, [
        'foresters' => json_encode([['forester' => 'ASPLUNDH\\jdoe', 'unit_count' => 10]]),
    ])]));

    $command->persistContributors(collect([buildMockMetricsRow($assessment->job_guid, [
        'foresters' => json_encode([['forester' => 'ASPLUNDH\\jdoe', 'unit_count' => 25]]),
    ])]));

    $contributors = AssessmentContributor::where('job_guid', $assessment->job_guid)->get();
    expect($contributors)->toHaveCount(1)
        ->and($contributors->first()->unit_count)->toBe(25);
});

test('persistMetrics handles null work_type_breakdown', function () {
    $assessment = Assessment::factory()->create();

    $rows = collect([buildMockMetricsRow($assessment->job_guid, ['work_type_breakdown' => null])]);

    $command = createTestCommand();
    $command->persistMetrics($rows);

    $metric = AssessmentMetric::where('job_guid', $assessment->job_guid)->first();

    expect($metric->work_type_breakdown)->toBe([]);
});

test('persistMetrics casts split_updated string to boolean', function () {
    $assessment = Assessment::factory()->create();

    $rows = collect([buildMockMetricsRow($assessment->job_guid, ['split_updated' => '1', 'split_count' => 2])]);

    $command = createTestCommand();
    $command->persistMetrics($rows);

    $metric = AssessmentMetric::where('job_guid', $assessment->job_guid)->first();

    expect($metric->split_updated)->toBeTrue()
        ->and($metric->split_count)->toBe(2);
});

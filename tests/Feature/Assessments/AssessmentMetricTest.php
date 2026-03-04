<?php

use App\Models\Assessment;
use App\Models\AssessmentMetric;

test('factory creates valid assessment metric', function () {
    $metric = AssessmentMetric::factory()->create();

    expect($metric)->toBeInstanceOf(AssessmentMetric::class)
        ->and($metric->job_guid)->not->toBeNull()
        ->and($metric->work_order)->not->toBeNull()
        ->and($metric->total_units)->toBeGreaterThanOrEqual(0)
        ->and($metric->work_type_breakdown)->toBeArray();
});

test('metric belongs to assessment via job_guid', function () {
    $assessment = Assessment::factory()->create();
    $metric = AssessmentMetric::factory()->create(['job_guid' => $assessment->job_guid]);

    expect($metric->assessment->id)->toBe($assessment->id);
});

test('assessment has one metrics relationship', function () {
    $assessment = Assessment::factory()->create();
    $metric = AssessmentMetric::factory()->create(['job_guid' => $assessment->job_guid]);

    expect($assessment->metrics)->toBeInstanceOf(AssessmentMetric::class)
        ->and($assessment->metrics->id)->toBe($metric->id);
});

test('work_type_breakdown round-trips as array', function () {
    $breakdown = [
        ['unit' => 'REM612', 'display_name' => 'Removal 6-12 DBH', 'quantity' => 45],
        ['unit' => 'SPM', 'display_name' => 'Side Prune Mechanical', 'quantity' => 12],
    ];

    $assessment = Assessment::factory()->create();
    $metric = AssessmentMetric::factory()->create([
        'job_guid' => $assessment->job_guid,
        'work_type_breakdown' => $breakdown,
    ]);

    $fresh = AssessmentMetric::find($metric->id);

    expect($fresh->work_type_breakdown)->toBe($breakdown);
});

test('date columns are cast to date', function () {
    $assessment = Assessment::factory()->create();
    $metric = AssessmentMetric::factory()->create([
        'job_guid' => $assessment->job_guid,
        'taken_date' => '2026-01-15',
        'first_unit_date' => '2026-02-01',
    ]);

    $fresh = AssessmentMetric::find($metric->id);

    expect($fresh->taken_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($fresh->taken_date->format('Y-m-d'))->toBe('2026-01-15')
        ->and($fresh->first_unit_date->format('Y-m-d'))->toBe('2026-02-01');
});

test('notes_compliance_percent cast as decimal', function () {
    $assessment = Assessment::factory()->create();
    $metric = AssessmentMetric::factory()->create([
        'job_guid' => $assessment->job_guid,
        'notes_compliance_percent' => 87.5,
    ]);

    $fresh = AssessmentMetric::find($metric->id);

    expect($fresh->notes_compliance_percent)->toBe('87.5');
});

test('split_updated cast as boolean', function () {
    $assessment = Assessment::factory()->create();
    $metric = AssessmentMetric::factory()->create([
        'job_guid' => $assessment->job_guid,
        'split_updated' => true,
    ]);

    $fresh = AssessmentMetric::find($metric->id);

    expect($fresh->split_updated)->toBeTrue();
});

test('job_guid is unique', function () {
    $assessment = Assessment::factory()->create();
    AssessmentMetric::factory()->create(['job_guid' => $assessment->job_guid]);

    expect(fn () => AssessmentMetric::factory()->create(['job_guid' => $assessment->job_guid]))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('upsert via firstOrNew updates existing row', function () {
    $assessment = Assessment::factory()->create();

    AssessmentMetric::factory()->create([
        'job_guid' => $assessment->job_guid,
        'total_units' => 50,
    ]);

    AssessmentMetric::firstOrNew(['job_guid' => $assessment->job_guid])
        ->fill(['total_units' => 75])
        ->save();

    expect(AssessmentMetric::where('job_guid', $assessment->job_guid)->count())->toBe(1)
        ->and(AssessmentMetric::where('job_guid', $assessment->job_guid)->first()->total_units)->toBe(75);
});

test('cascade deletes metrics when assessment is deleted', function () {
    $assessment = Assessment::factory()->create();
    AssessmentMetric::factory()->create(['job_guid' => $assessment->job_guid]);

    expect(AssessmentMetric::count())->toBe(1);

    $assessment->delete();

    expect(AssessmentMetric::count())->toBe(0);
});

test('withHighCompliance factory state', function () {
    $metric = AssessmentMetric::factory()->withHighCompliance()->create();

    expect((float) $metric->notes_compliance_percent)->toBeGreaterThanOrEqual(90.0);
});

test('withAgingUnits factory state', function () {
    $metric = AssessmentMetric::factory()->withAgingUnits()->create();

    expect($metric->pending_over_threshold)->toBeGreaterThanOrEqual(5)
        ->and($metric->oldest_pending_statname)->not->toBeNull()
        ->and($metric->oldest_pending_unit)->not->toBeNull();
});

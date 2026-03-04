<?php

use App\Models\Assessment;
use App\Models\AssessmentContributor;
use App\Models\WsUser;

test('factory creates valid assessment contributor', function () {
    $contributor = AssessmentContributor::factory()->create();

    expect($contributor)->toBeInstanceOf(AssessmentContributor::class)
        ->and($contributor->ws_username)->toStartWith('ASPLUNDH\\')
        ->and($contributor->unit_count)->toBeGreaterThanOrEqual(1)
        ->and($contributor->role)->toBeNull();
});

test('contributor belongs to assessment via job_guid', function () {
    $assessment = Assessment::factory()->create();
    $contributor = AssessmentContributor::factory()->create(['job_guid' => $assessment->job_guid]);

    expect($contributor->assessment->id)->toBe($assessment->id);
});

test('contributor belongs to ws_user', function () {
    $wsUser = WsUser::factory()->create();
    $contributor = AssessmentContributor::factory()->create(['ws_user_id' => $wsUser->id]);

    expect($contributor->wsUser->id)->toBe($wsUser->id);
});

test('assessment has many contributors', function () {
    $assessment = Assessment::factory()->create();
    AssessmentContributor::factory()->count(3)->create(['job_guid' => $assessment->job_guid]);

    expect($assessment->contributors)->toHaveCount(3)
        ->and($assessment->contributors->first())->toBeInstanceOf(AssessmentContributor::class);
});

test('composite uniqueness on job_guid and ws_username', function () {
    $assessment = Assessment::factory()->create();
    AssessmentContributor::factory()->create([
        'job_guid' => $assessment->job_guid,
        'ws_username' => 'ASPLUNDH\\jdoe',
    ]);

    expect(fn () => AssessmentContributor::factory()->create([
        'job_guid' => $assessment->job_guid,
        'ws_username' => 'ASPLUNDH\\jdoe',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('updateOrCreate updates existing contributor without duplication', function () {
    $assessment = Assessment::factory()->create();

    AssessmentContributor::factory()->create([
        'job_guid' => $assessment->job_guid,
        'ws_username' => 'ASPLUNDH\\jdoe',
        'unit_count' => 10,
    ]);

    AssessmentContributor::updateOrCreate(
        ['job_guid' => $assessment->job_guid, 'ws_username' => 'ASPLUNDH\\jdoe'],
        ['unit_count' => 25]
    );

    expect(AssessmentContributor::where('job_guid', $assessment->job_guid)->count())->toBe(1)
        ->and(AssessmentContributor::where('ws_username', 'ASPLUNDH\\jdoe')->first()->unit_count)->toBe(25);
});

test('role is nullable and updatable', function () {
    $contributor = AssessmentContributor::factory()->create();

    expect($contributor->role)->toBeNull();

    $contributor->update(['role' => 'Lead Assessor']);

    expect($contributor->fresh()->role)->toBe('Lead Assessor');
});

test('cascade deletes contributors when assessment is deleted', function () {
    $assessment = Assessment::factory()->create();
    AssessmentContributor::factory()->count(2)->create(['job_guid' => $assessment->job_guid]);

    expect(AssessmentContributor::count())->toBe(2);

    $assessment->delete();

    expect(AssessmentContributor::count())->toBe(0);
});

test('forester factory state sets role', function () {
    $contributor = AssessmentContributor::factory()->forester()->create();

    expect($contributor->role)->toBe('Forester');
});

test('qcReviewer factory state sets role', function () {
    $contributor = AssessmentContributor::factory()->qcReviewer()->create();

    expect($contributor->role)->toBe('QC Reviewer');
});

test('same ws_username allowed on different assessments', function () {
    $assessment1 = Assessment::factory()->create();
    $assessment2 = Assessment::factory()->create();

    AssessmentContributor::factory()->create([
        'job_guid' => $assessment1->job_guid,
        'ws_username' => 'ASPLUNDH\\jdoe',
    ]);

    $contributor2 = AssessmentContributor::factory()->create([
        'job_guid' => $assessment2->job_guid,
        'ws_username' => 'ASPLUNDH\\jdoe',
    ]);

    expect($contributor2)->toBeInstanceOf(AssessmentContributor::class)
        ->and(AssessmentContributor::count())->toBe(2);
});

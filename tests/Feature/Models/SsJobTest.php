<?php

use App\Models\Circuit;
use App\Models\SsJob;
use App\Models\WsUser;

test('can create an ss job with factory', function () {
    $job = SsJob::factory()->create();

    expect($job->job_guid)->toStartWith('{')
        ->and($job->job_guid)->toEndWith('}')
        ->and($job->work_order)->toStartWith('WO-')
        ->and($job->extensions)->toBeArray()
        ->and($job->job_type)->toBeIn(['Assessment', 'Assessment Rework'])
        ->and($job->status)->toBeIn(['SA', 'ACTIV', 'QC', 'REWRK', 'CLOSE']);
});

test('uses string primary key', function () {
    $job = SsJob::factory()->create();

    expect($job->getKeyType())->toBe('string')
        ->and($job->getIncrementing())->toBeFalse();
});

test('belongs to circuit', function () {
    $circuit = Circuit::factory()->create();
    $job = SsJob::factory()->create(['circuit_id' => $circuit->id]);

    expect($job->circuit->id)->toBe($circuit->id)
        ->and($job->circuit->line_name)->toBe($circuit->line_name);
});

test('belongs to taken by ws user', function () {
    $user = WsUser::factory()->create();
    $job = SsJob::factory()->create(['taken_by_id' => $user->id, 'taken' => true]);

    expect($job->takenBy->id)->toBe($user->id)
        ->and($job->takenBy->username)->toBe($user->username);
});

test('belongs to modified by ws user', function () {
    $user = WsUser::factory()->create();
    $job = SsJob::factory()->create(['modified_by_id' => $user->id]);

    expect($job->modifiedBy->id)->toBe($user->id);
});

test('self-referential parent job relationship', function () {
    $parent = SsJob::factory()->create();
    $child = SsJob::factory()->create(['parent_job_guid' => $parent->job_guid]);

    expect($child->parentJob->job_guid)->toBe($parent->job_guid);
});

test('self-referential child jobs relationship', function () {
    $parent = SsJob::factory()->create();
    SsJob::factory()->count(2)->create(['parent_job_guid' => $parent->job_guid]);

    expect($parent->childJobs)->toHaveCount(2);
});

test('circuit has many ss jobs', function () {
    $circuit = Circuit::factory()->create();
    SsJob::factory()->count(3)->create(['circuit_id' => $circuit->id]);

    expect($circuit->ssJobs)->toHaveCount(3);
});

test('withCircuit factory state creates circuit', function () {
    $job = SsJob::factory()->withCircuit()->create();

    expect($job->circuit)->not->toBeNull()
        ->and($job->circuit_id)->not->toBeNull();
});

test('withTakenBy factory state creates ws user', function () {
    $job = SsJob::factory()->withTakenBy()->create();

    expect($job->takenBy)->not->toBeNull()
        ->and($job->taken)->toBeTrue();
});

test('withStatus factory state sets status', function () {
    $job = SsJob::factory()->withStatus('QC')->create();

    expect($job->status)->toBe('QC');
});

test('extensions cast to array', function () {
    $job = SsJob::factory()->create(['extensions' => ['EXT-001', 'EXT-002']]);

    $fresh = SsJob::find($job->job_guid);
    expect($fresh->extensions)->toBe(['EXT-001', 'EXT-002']);
});

test('edit date cast to datetime', function () {
    $date = now()->subDays(5);
    $job = SsJob::factory()->create(['edit_date' => $date]);

    $fresh = SsJob::find($job->job_guid);
    expect($fresh->edit_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('nullable relationships return null', function () {
    $job = SsJob::factory()->create([
        'circuit_id' => null,
        'taken_by_id' => null,
        'modified_by_id' => null,
        'parent_job_guid' => null,
    ]);

    expect($job->circuit)->toBeNull()
        ->and($job->takenBy)->toBeNull()
        ->and($job->modifiedBy)->toBeNull()
        ->and($job->parentJob)->toBeNull();
});

<?php

use App\Models\PlannerJobAssignment;
use App\Models\WsUser;

// ─── Factory ─────────────────────────────────────────────────────────────────

test('factory creates valid PlannerJobAssignment', function () {
    $assignment = PlannerJobAssignment::factory()->create();

    expect($assignment->exists)->toBeTrue()
        ->and($assignment->frstr_user)->toBeString()
        ->and($assignment->job_guid)->toStartWith('{')
        ->and($assignment->status)->toBe('discovered')
        ->and($assignment->discovered_at)->not->toBeNull();
});

test('factory processed state sets status to processed', function () {
    $assignment = PlannerJobAssignment::factory()->processed()->create();

    expect($assignment->status)->toBe('processed');
});

test('factory exported state sets status to exported', function () {
    $assignment = PlannerJobAssignment::factory()->exported()->create();

    expect($assignment->status)->toBe('exported');
});

// ─── Scopes ──────────────────────────────────────────────────────────────────

test('forUser scope filters by frstr_user', function () {
    PlannerJobAssignment::factory()->create(['frstr_user' => 'jsmith']);
    PlannerJobAssignment::factory()->create(['frstr_user' => 'jdoe']);

    $results = PlannerJobAssignment::forUser('jsmith')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->frstr_user)->toBe('jsmith');
});

test('pending scope filters discovered status', function () {
    PlannerJobAssignment::factory()->create(['status' => 'discovered']);
    PlannerJobAssignment::factory()->processed()->create();
    PlannerJobAssignment::factory()->exported()->create();

    expect(PlannerJobAssignment::pending()->count())->toBe(1);
});

test('processed scope filters processed status', function () {
    PlannerJobAssignment::factory()->create(['status' => 'discovered']);
    PlannerJobAssignment::factory()->processed()->create();

    expect(PlannerJobAssignment::processed()->count())->toBe(1);
});

test('exported scope filters exported status', function () {
    PlannerJobAssignment::factory()->create();
    PlannerJobAssignment::factory()->exported()->create();

    expect(PlannerJobAssignment::exported()->count())->toBe(1);
});

// ─── Relationship ────────────────────────────────────────────────────────────

test('wsUser relationship matches by username', function () {
    $wsUser = WsUser::factory()->create(['username' => 'jsmith']);
    $assignment = PlannerJobAssignment::factory()->create(['frstr_user' => 'jsmith']);

    expect($assignment->wsUser)->not->toBeNull()
        ->and($assignment->wsUser->id)->toBe($wsUser->id);
});

test('wsUser relationship returns null for unmatched username', function () {
    $assignment = PlannerJobAssignment::factory()->create(['frstr_user' => 'nonexistent']);

    expect($assignment->wsUser)->toBeNull();
});

// ─── Unique Constraint ──────────────────────────────────────────────────────

test('unique constraint prevents duplicate frstr_user + job_guid', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    PlannerJobAssignment::factory()->create(['frstr_user' => 'jsmith', 'job_guid' => $guid]);

    expect(fn () => PlannerJobAssignment::factory()->create(['frstr_user' => 'jsmith', 'job_guid' => $guid]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same job_guid allowed for different users', function () {
    $guid = '{11111111-1111-1111-1111-111111111111}';
    PlannerJobAssignment::factory()->create(['frstr_user' => 'jsmith', 'job_guid' => $guid]);
    PlannerJobAssignment::factory()->create(['frstr_user' => 'jdoe', 'job_guid' => $guid]);

    expect(PlannerJobAssignment::where('job_guid', $guid)->count())->toBe(2);
});

// ─── Casts ───────────────────────────────────────────────────────────────────

test('discovered_at is cast to datetime', function () {
    $assignment = PlannerJobAssignment::factory()->create();

    expect($assignment->discovered_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

<?php

use App\Models\Assessment;
use App\Models\Region;
use App\Models\User;
use App\Models\UserWsIdentity;
use App\Models\WsUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('factory creates valid model', function () {
    $identity = UserWsIdentity::factory()->create();

    expect($identity)->toBeInstanceOf(UserWsIdentity::class)
        ->and($identity->user_id)->not->toBeNull()
        ->and($identity->ws_user_id)->not->toBeNull()
        ->and($identity->is_primary)->toBeFalse();
});

test('primary state sets is_primary to true', function () {
    $identity = UserWsIdentity::factory()->primary()->create();

    expect($identity->is_primary)->toBeTrue();
});

test('is_primary is cast to boolean', function () {
    $identity = UserWsIdentity::factory()->create(['is_primary' => 1]);

    expect($identity->is_primary)->toBeBool()->toBeTrue();
});

test('belongs to user', function () {
    $identity = UserWsIdentity::factory()->create();

    expect($identity->user)->toBeInstanceOf(User::class);
});

test('belongs to ws user', function () {
    $identity = UserWsIdentity::factory()->create();

    expect($identity->wsUser)->toBeInstanceOf(WsUser::class);
});

test('ws_user_id unique constraint prevents duplicates', function () {
    $wsUser = WsUser::factory()->create();
    UserWsIdentity::factory()->create(['ws_user_id' => $wsUser->id]);

    UserWsIdentity::factory()->create(['ws_user_id' => $wsUser->id]);
})->throws(\Illuminate\Database\QueryException::class);

// Relationship tests on existing models

test('user has many ws identities', function () {
    $user = User::factory()->create();
    UserWsIdentity::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->wsIdentities)->toHaveCount(3);
});

test('user has one primary ws identity', function () {
    $user = User::factory()->create();
    UserWsIdentity::factory()->create(['user_id' => $user->id, 'is_primary' => false]);
    UserWsIdentity::factory()->primary()->create(['user_id' => $user->id]);

    expect($user->primaryWsIdentity)->not->toBeNull()
        ->and($user->primaryWsIdentity->is_primary)->toBeTrue();
});

test('user belongs to many assessments', function () {
    $user = User::factory()->create();
    $assessments = Assessment::factory()->count(3)->create();
    $user->assessments()->attach($assessments->pluck('id'));

    expect($user->assessments)->toHaveCount(3);
});

test('user belongs to many regions', function () {
    $user = User::factory()->create();
    $regions = Region::factory()->count(2)->create();
    $user->regions()->attach($regions->pluck('id'));

    expect($user->regions)->toHaveCount(2);
});

test('ws user has one identity', function () {
    $wsUser = WsUser::factory()->create();
    UserWsIdentity::factory()->create(['ws_user_id' => $wsUser->id]);

    expect($wsUser->identity)->not->toBeNull()
        ->and($wsUser->identity)->toBeInstanceOf(UserWsIdentity::class);
});

test('ws user has one through user', function () {
    $user = User::factory()->create();
    $wsUser = WsUser::factory()->create();
    UserWsIdentity::factory()->create(['user_id' => $user->id, 'ws_user_id' => $wsUser->id]);

    expect($wsUser->user)->not->toBeNull()
        ->and($wsUser->user->id)->toBe($user->id);
});

test('assessment belongs to many assigned users', function () {
    $assessment = Assessment::factory()->create();
    $users = User::factory()->count(2)->create();
    $assessment->assignedUsers()->attach($users->pluck('id'));

    expect($assessment->assignedUsers)->toHaveCount(2);
});

test('region belongs to many assigned users', function () {
    $region = Region::factory()->create();
    $users = User::factory()->count(2)->create();
    $region->assignedUsers()->attach($users->pluck('id'));

    expect($region->assignedUsers)->toHaveCount(2);
});

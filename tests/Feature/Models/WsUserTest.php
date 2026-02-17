<?php

use App\Models\WsUser;

test('can create a ws user with factory', function () {
    $user = WsUser::factory()->create();

    expect($user->username)->toStartWith('ASPLUNDH\\')
        ->and($user->domain)->toBe('ASPLUNDH')
        ->and($user->display_name)->not->toBeNull()
        ->and($user->is_enabled)->toBeTrue()
        ->and($user->groups)->toBeArray()
        ->and($user->last_synced_at)->not->toBeNull();
});

test('unenriched factory state has null details', function () {
    $user = WsUser::factory()->unenriched()->create();

    expect($user->display_name)->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->is_enabled)->toBeNull()
        ->and($user->groups)->toBeNull()
        ->and($user->last_synced_at)->toBeNull();
});

test('disabled factory state sets is_enabled to false', function () {
    $user = WsUser::factory()->disabled()->create();

    expect($user->is_enabled)->toBeFalse();
});

test('username is unique', function () {
    WsUser::factory()->create(['username' => 'ASPLUNDH\\testuser']);

    expect(fn () => WsUser::factory()->create(['username' => 'ASPLUNDH\\testuser']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('groups cast to array', function () {
    $user = WsUser::factory()->create(['groups' => ['Admin', 'Field Crew']]);

    $fresh = WsUser::find($user->id);
    expect($fresh->groups)->toBe(['Admin', 'Field Crew']);
});

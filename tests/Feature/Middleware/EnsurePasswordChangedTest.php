<?php

use App\Models\User;
use App\Models\UserSetting;

it('allows guests to pass through without redirect', function () {
    $this->get(route('login'))
        ->assertOk();
});

it('redirects first-login users to password change page', function () {
    $user = User::factory()->create();
    UserSetting::factory()->firstLogin()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.password'));
});

it('redirects users with changed password but incomplete onboarding to workstudio setup', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'first_login' => false,
        'onboarding_completed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.workstudio'));
});

it('allows fully onboarded users to access protected routes', function () {
    $user = User::factory()->create();
    UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('creates settings for users without settings on first protected route access', function () {
    $user = User::factory()->create();

    expect($user->settings)->toBeNull();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.password'));

    $user->refresh();
    expect($user->settings)->not->toBeNull();
    expect($user->settings->first_login)->toBeTrue();
});

it('allows access to onboarding routes without redirect loop', function () {
    $user = User::factory()->create();
    UserSetting::factory()->firstLogin()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('onboarding.password'))
        ->assertOk();
});

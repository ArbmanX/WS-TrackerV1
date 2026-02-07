<?php

use App\Livewire\Onboarding\ChangePassword;
use App\Models\User;
use App\Models\UserSetting;
use Livewire\Livewire;

it('renders the change password page for authenticated users', function () {
    $user = User::factory()->create();
    UserSetting::factory()->firstLogin()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('onboarding.password'))
        ->assertOk()
        ->assertSeeLivewire(ChangePassword::class);
});

it('redirects unauthenticated users to login', function () {
    $this->get(route('onboarding.password'))
        ->assertRedirect(route('login'));
});

it('requires password confirmation', function () {
    $user = User::factory()->create();
    UserSetting::factory()->firstLogin()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ChangePassword::class)
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'DifferentPassword')
        ->call('setPassword')
        ->assertHasErrors(['password']);
});

it('updates password and marks first_login as false', function () {
    $user = User::factory()->create();
    $settings = UserSetting::factory()->firstLogin()->create(['user_id' => $user->id]);

    expect($settings->first_login)->toBeTrue();

    Livewire::actingAs($user)
        ->test(ChangePassword::class)
        ->set('password', 'NewSecurePassword123!')
        ->set('password_confirmation', 'NewSecurePassword123!')
        ->call('setPassword')
        ->assertRedirect(route('onboarding.theme'));

    $settings->refresh();
    expect($settings->first_login)->toBeFalse();
    expect($settings->onboarding_step)->toBe(1);
});

it('enforces password strength requirements', function () {
    $user = User::factory()->create();
    UserSetting::factory()->firstLogin()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ChangePassword::class)
        ->set('password', 'weak')
        ->set('password_confirmation', 'weak')
        ->call('setPassword')
        ->assertHasErrors(['password']);
});

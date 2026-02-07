<?php

use App\Livewire\Onboarding\ThemeSelection;
use App\Models\User;
use App\Models\UserSetting;
use Livewire\Livewire;

it('renders the theme selection page for authenticated users', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(1)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('onboarding.theme'))
        ->assertOk()
        ->assertSeeLivewire(ThemeSelection::class);
});

it('redirects unauthenticated users to login', function () {
    $this->get(route('onboarding.theme'))
        ->assertRedirect(route('login'));
});

it('loads the current theme from user settings', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(1)->create([
        'user_id' => $user->id,
        'theme' => 'dracula',
    ]);

    Livewire::actingAs($user)
        ->test(ThemeSelection::class)
        ->assertSet('selectedTheme', 'dracula');
});

it('defaults to corporate theme when system theme is set', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(1)->create([
        'user_id' => $user->id,
        'theme' => 'system',
    ]);

    Livewire::actingAs($user)
        ->test(ThemeSelection::class)
        ->assertSet('selectedTheme', 'system');
});

it('saves theme and advances to step 2', function () {
    $user = User::factory()->create();
    $settings = UserSetting::factory()->atStep(1)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ThemeSelection::class)
        ->set('selectedTheme', 'synthwave')
        ->call('continueToNext')
        ->assertRedirect(route('onboarding.workstudio'));

    $settings->refresh();
    expect($settings->theme)->toBe('synthwave');
    expect($settings->onboarding_step)->toBe(2);
});

it('can go back to the password step', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(1)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ThemeSelection::class)
        ->call('goBack')
        ->assertRedirect(route('onboarding.password'));
});

it('dispatches set-theme event when theme changes', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(1)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ThemeSelection::class)
        ->set('selectedTheme', 'dark')
        ->assertDispatched('set-theme', theme: 'dark');
});

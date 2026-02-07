<?php

use App\Livewire\Onboarding\Confirmation;
use App\Models\User;
use App\Models\UserSetting;
use Livewire\Livewire;

it('renders the confirmation page with user data', function () {
    $user = User::factory()->withWorkStudio()->create();
    UserSetting::factory()->atStep(3)->create([
        'user_id' => $user->id,
        'theme' => 'corporate',
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.confirmation'))
        ->assertOk()
        ->assertSeeLivewire(Confirmation::class);
});

it('redirects unauthenticated users to login', function () {
    $this->get(route('onboarding.confirmation'))
        ->assertRedirect(route('login'));
});

it('displays the correct summary data', function () {
    $user = User::factory()->withWorkStudio()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'ws_username' => 'jdoe',
        'ws_full_name' => 'Jane Doe',
        'ws_domain' => 'ASPLUNDH',
        'ws_resource_groups' => ['CENTRAL', 'HARRISBURG'],
    ]);
    UserSetting::factory()->atStep(3)->create([
        'user_id' => $user->id,
        'theme' => 'dracula',
    ]);

    Livewire::actingAs($user)
        ->test(Confirmation::class)
        ->assertSet('summary.name', 'Jane Doe')
        ->assertSet('summary.email', 'jane@example.com')
        ->assertSet('summary.theme', 'Dracula')
        ->assertSet('summary.ws_username', 'jdoe')
        ->assertSet('summary.ws_full_name', 'Jane Doe')
        ->assertSet('summary.ws_domain', 'ASPLUNDH')
        ->assertSet('summary.regions', ['CENTRAL', 'HARRISBURG']);
});

it('sets onboarding_completed_at and step 4 on confirm', function () {
    $user = User::factory()->withWorkStudio()->create();
    $settings = UserSetting::factory()->atStep(3)->create([
        'user_id' => $user->id,
        'theme' => 'corporate',
    ]);

    Livewire::actingAs($user)
        ->test(Confirmation::class)
        ->call('confirm')
        ->assertRedirect(route('dashboard'));

    $settings->refresh();
    expect($settings->onboarding_step)->toBe(4);
    expect($settings->onboarding_completed_at)->not->toBeNull();
});

it('can go back to the workstudio credentials step', function () {
    $user = User::factory()->withWorkStudio()->create();
    UserSetting::factory()->atStep(3)->create([
        'user_id' => $user->id,
        'theme' => 'corporate',
    ]);

    Livewire::actingAs($user)
        ->test(Confirmation::class)
        ->call('goBack')
        ->assertRedirect(route('onboarding.workstudio'));
});

<?php

use App\Livewire\Onboarding\WorkStudioSetup;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\WorkStudio\Contracts\UserDetailsServiceInterface;
use App\Services\WorkStudio\Exceptions\UserNotFoundException;
use Livewire\Livewire;
use Mockery\MockInterface;

it('renders the workstudio setup page for authenticated users', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'first_login' => false,
        'onboarding_completed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.workstudio'))
        ->assertOk()
        ->assertSeeLivewire(WorkStudioSetup::class);
});

it('requires username in DOMAIN\\username format', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'first_login' => false,
    ]);

    // Mock the service for validation test
    $this->mock(UserDetailsServiceInterface::class);

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->set('ws_username', 'invalid-format')
        ->call('validateWorkStudio')
        ->assertHasErrors(['ws_username']);
});

it('validates workstudio username and stores user details on success', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'first_login' => false,
        'onboarding_completed_at' => null,
    ]);

    $mockDetails = [
        'username' => 'jsmith',
        'full_name' => 'John Smith',
        'domain' => 'ASPLUNDH',
        'email' => 'jsmith@example.com',
        'enabled' => true,
        'groups' => ['WorkStudio\\Everyone', 'ASPLUNDH\\VEG_PLANNERS'],
    ];

    $this->mock(UserDetailsServiceInterface::class, function (MockInterface $mock) use ($mockDetails) {
        $mock->shouldReceive('getDetails')
            ->with('ASPLUNDH\\jsmith')
            ->once()
            ->andReturn($mockDetails);
    });

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->set('ws_username', 'ASPLUNDH\\jsmith')
        ->call('validateWorkStudio')
        ->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->ws_username)->toBe('jsmith');
    expect($user->ws_full_name)->toBe('John Smith');
    expect($user->ws_domain)->toBe('ASPLUNDH');
    expect($user->ws_groups)->toBe(['WorkStudio\\Everyone', 'ASPLUNDH\\VEG_PLANNERS']);
    expect($user->ws_validated_at)->not->toBeNull();

    $user->settings->refresh();
    expect($user->settings->onboarding_completed_at)->not->toBeNull();
});

it('shows error message when user not found in workstudio', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'first_login' => false,
    ]);

    $this->mock(UserDetailsServiceInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getDetails')
            ->once()
            ->andThrow(new UserNotFoundException('ASPLUNDH\\invalid'));
    });

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->set('ws_username', 'ASPLUNDH\\invalid')
        ->call('validateWorkStudio')
        ->assertSet('errorMessage', 'User not found in WorkStudio. Please check your username and try again.')
        ->assertNoRedirect();
});

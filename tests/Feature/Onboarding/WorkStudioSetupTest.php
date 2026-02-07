<?php

use App\Livewire\Onboarding\WorkStudioSetup;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\WorkStudio\Client\ApiCredentialManager;
use App\Services\WorkStudio\Client\HeartbeatService;
use App\Services\WorkStudio\Shared\Contracts\UserDetailsServiceInterface;
use App\Services\WorkStudio\Shared\Exceptions\UserNotFoundException;
use Livewire\Livewire;
use Mockery\MockInterface;

it('renders the workstudio setup page for authenticated users', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('onboarding.workstudio'))
        ->assertOk()
        ->assertSeeLivewire(WorkStudioSetup::class);
});

it('requires username in DOMAIN\\username format', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(2)->create(['user_id' => $user->id]);

    $this->mock(UserDetailsServiceInterface::class);
    $this->mock(HeartbeatService::class);
    $this->mock(ApiCredentialManager::class);

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->set('ws_username', 'invalid-format')
        ->set('ws_password', 'somepassword')
        ->call('validateWorkStudio')
        ->assertHasErrors(['ws_username']);
});

it('requires ws_password', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(2)->create(['user_id' => $user->id]);

    $this->mock(UserDetailsServiceInterface::class);
    $this->mock(HeartbeatService::class);
    $this->mock(ApiCredentialManager::class);

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->set('ws_username', 'ASPLUNDH\\jsmith')
        ->set('ws_password', '')
        ->call('validateWorkStudio')
        ->assertHasErrors(['ws_password']);
});

it('checks heartbeat before validating credentials', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(2)->create(['user_id' => $user->id]);

    $this->mock(HeartbeatService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isAlive')->once()->andReturn(false);
    });

    $this->mock(UserDetailsServiceInterface::class);
    $this->mock(ApiCredentialManager::class);

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->set('ws_username', 'ASPLUNDH\\jsmith')
        ->set('ws_password', 'testpass')
        ->call('validateWorkStudio')
        ->assertSet('errorMessage', 'WorkStudio server is not responding. Please try again later.')
        ->assertNoRedirect();
});

it('shows error on invalid credentials', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(2)->create(['user_id' => $user->id]);

    $this->mock(HeartbeatService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isAlive')->once()->andReturn(true);
    });

    $this->mock(ApiCredentialManager::class, function (MockInterface $mock) {
        $mock->shouldReceive('testCredentials')
            ->with('ASPLUNDH\\jsmith', 'wrongpass')
            ->once()
            ->andReturn(false);
    });

    $this->mock(UserDetailsServiceInterface::class);

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->set('ws_username', 'ASPLUNDH\\jsmith')
        ->set('ws_password', 'wrongpass')
        ->call('validateWorkStudio')
        ->assertSet('errorMessage', 'Invalid WorkStudio credentials. Please check your username and password.')
        ->assertNoRedirect();
});

it('validates workstudio credentials, stores details, and redirects to confirmation', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(2)->create([
        'user_id' => $user->id,
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

    $this->mock(HeartbeatService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isAlive')->once()->andReturn(true);
    });

    $this->mock(ApiCredentialManager::class, function (MockInterface $mock) {
        $mock->shouldReceive('testCredentials')
            ->with('ASPLUNDH\\jsmith', 'mypassword')
            ->once()
            ->andReturn(true);
        $mock->shouldReceive('storeCredentials')
            ->once();
    });

    $this->mock(UserDetailsServiceInterface::class, function (MockInterface $mock) use ($mockDetails) {
        $mock->shouldReceive('getDetails')
            ->with('ASPLUNDH\\jsmith')
            ->once()
            ->andReturn($mockDetails);
    });

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->set('ws_username', 'ASPLUNDH\\jsmith')
        ->set('ws_password', 'mypassword')
        ->call('validateWorkStudio')
        ->assertRedirect(route('onboarding.confirmation'));

    $user->refresh();
    expect($user->ws_username)->toBe('jsmith');
    expect($user->ws_full_name)->toBe('John Smith');
    expect($user->ws_domain)->toBe('ASPLUNDH');
    expect($user->ws_groups)->toBe(['WorkStudio\\Everyone', 'ASPLUNDH\\VEG_PLANNERS']);
    expect($user->ws_resource_groups)->toBeArray();
    expect($user->ws_resource_groups)->toContain('CENTRAL', 'HARRISBURG');
    expect($user->ws_validated_at)->not->toBeNull();

    $user->settings->refresh();
    expect($user->settings->onboarding_step)->toBe(3);
    expect($user->settings->onboarding_completed_at)->toBeNull();
});

it('shows error message when user not found in workstudio', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(2)->create(['user_id' => $user->id]);

    $this->mock(HeartbeatService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isAlive')->once()->andReturn(true);
    });

    $this->mock(ApiCredentialManager::class, function (MockInterface $mock) {
        $mock->shouldReceive('testCredentials')->once()->andReturn(true);
    });

    $this->mock(UserDetailsServiceInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getDetails')
            ->once()
            ->andThrow(new UserNotFoundException('ASPLUNDH\\invalid'));
    });

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->set('ws_username', 'ASPLUNDH\\invalid')
        ->set('ws_password', 'testpass')
        ->call('validateWorkStudio')
        ->assertSet('errorMessage', 'User not found in WorkStudio. Please check your username and try again.')
        ->assertNoRedirect();
});

it('can go back to the theme selection step', function () {
    $user = User::factory()->create();
    UserSetting::factory()->atStep(2)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(WorkStudioSetup::class)
        ->call('goBack')
        ->assertRedirect(route('onboarding.theme'));
});

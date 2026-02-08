<?php

use App\Livewire\UserManagement\CreateUser;
use App\Models\User;
use App\Models\UserSetting;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Helper: create an onboarded user with an optional role.
 */
function createOnboardedUser(?string $role = null): User
{
    $factory = User::factory()->withWorkStudio();

    if ($role) {
        $factory = $factory->withRole($role);
    }

    $user = $factory->create();
    UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);

    return $user;
}

// ──────────────────────────────────────────
// Authentication & Authorization
// ──────────────────────────────────────────

test('guests are redirected to login', function () {
    $this->get(route('user-management.create'))
        ->assertRedirect(route('login'));
});

test('user role gets 403', function () {
    $user = createOnboardedUser('user');

    $this->actingAs($user)
        ->get(route('user-management.create'))
        ->assertForbidden();
});

test('manager role can access create user', function () {
    $user = createOnboardedUser('manager');

    $this->actingAs($user)
        ->get(route('user-management.create'))
        ->assertOk();
});

test('sudo-admin can access create user page', function () {
    $user = createOnboardedUser('sudo-admin');

    $this->actingAs($user)
        ->get(route('user-management.create'))
        ->assertOk();
});

// ──────────────────────────────────────────
// Validation
// ──────────────────────────────────────────

test('name is required', function () {
    $user = createOnboardedUser('sudo-admin');

    Livewire::actingAs($user)
        ->test(CreateUser::class)
        ->set('email', 'test@example.com')
        ->set('role', 'user')
        ->call('createUser')
        ->assertHasErrors(['name' => 'required']);
});

test('email is required', function () {
    $user = createOnboardedUser('sudo-admin');

    Livewire::actingAs($user)
        ->test(CreateUser::class)
        ->set('name', 'Test User')
        ->set('role', 'user')
        ->call('createUser')
        ->assertHasErrors(['email' => 'required']);
});

test('email must be valid format', function () {
    $user = createOnboardedUser('sudo-admin');

    Livewire::actingAs($user)
        ->test(CreateUser::class)
        ->set('name', 'Test User')
        ->set('email', 'not-an-email')
        ->set('role', 'user')
        ->call('createUser')
        ->assertHasErrors(['email' => 'email']);
});

test('email must be unique', function () {
    $user = createOnboardedUser('sudo-admin');
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($user)
        ->test(CreateUser::class)
        ->set('name', 'Test User')
        ->set('email', 'taken@example.com')
        ->set('role', 'user')
        ->call('createUser')
        ->assertHasErrors(['email' => 'unique']);
});

test('role is required', function () {
    $user = createOnboardedUser('sudo-admin');

    Livewire::actingAs($user)
        ->test(CreateUser::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('createUser')
        ->assertHasErrors(['role' => 'required']);
});

test('role must be a valid database role', function () {
    $user = createOnboardedUser('sudo-admin');

    Livewire::actingAs($user)
        ->test(CreateUser::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('role', 'nonexistent-role')
        ->call('createUser')
        ->assertHasErrors(['role']);
});

// ──────────────────────────────────────────
// Successful Creation
// ──────────────────────────────────────────

test('successful creation creates user with role', function () {
    $admin = createOnboardedUser('sudo-admin');

    $component = Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->set('name', 'New User')
        ->set('email', 'newuser@example.com')
        ->set('role', 'planner')
        ->call('createUser')
        ->assertHasNoErrors()
        ->assertSet('userCreated', true)
        ->assertSet('createdUserName', 'New User')
        ->assertSet('createdUserEmail', 'newuser@example.com');

    $createdUser = User::where('email', 'newuser@example.com')->first();

    expect($createdUser)->not->toBeNull()
        ->and($createdUser->name)->toBe('New User')
        ->and($createdUser->email_verified_at)->not->toBeNull()
        ->and($createdUser->hasRole('planner'))->toBeTrue();
});

test('user setting is created with first_login true', function () {
    $admin = createOnboardedUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->set('name', 'New User')
        ->set('email', 'newuser@example.com')
        ->set('role', 'user')
        ->call('createUser');

    $createdUser = User::where('email', 'newuser@example.com')->first();

    expect($createdUser->settings)->not->toBeNull()
        ->and($createdUser->settings->first_login)->toBeTrue();
});

test('temporary password is displayed and works', function () {
    $admin = createOnboardedUser('sudo-admin');

    $component = Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->set('name', 'New User')
        ->set('email', 'newuser@example.com')
        ->set('role', 'user')
        ->call('createUser');

    $tempPassword = $component->get('temporaryPassword');
    $createdUser = User::where('email', 'newuser@example.com')->first();

    expect($tempPassword)->not->toBeEmpty()
        ->and(Hash::check($tempPassword, $createdUser->password))->toBeTrue();
});

test('create another resets form', function () {
    $admin = createOnboardedUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->set('name', 'New User')
        ->set('email', 'newuser@example.com')
        ->set('role', 'user')
        ->call('createUser')
        ->assertSet('userCreated', true)
        ->call('createAnother')
        ->assertSet('userCreated', false)
        ->assertSet('name', '')
        ->assertSet('email', '')
        ->assertSet('role', '')
        ->assertSet('temporaryPassword', '')
        ->assertSet('createdUserName', '')
        ->assertSet('createdUserEmail', '');
});

test('all roles are shown in dropdown', function () {
    $admin = createOnboardedUser('sudo-admin');

    $component = Livewire::actingAs($admin)
        ->test(CreateUser::class);

    $roles = $component->viewData('availableRoles');

    expect($roles)->toContain('sudo-admin')
        ->toContain('manager')
        ->toContain('planner')
        ->toContain('general-foreman')
        ->toContain('user')
        ->toHaveCount(5);
});

<?php

use App\Models\User;
use App\Models\UserSetting;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Helper: create an onboarded user with an optional role.
 */
function createUserWithRole(?string $role = null): User
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
// Data Management — Cache Controls
// ──────────────────────────────────────────

test('users without access-data-management get 403 on cache controls', function () {
    $user = createUserWithRole('user');

    $this->actingAs($user)
        ->get(route('data-management.cache'))
        ->assertForbidden();
});

test('users without access-data-management get 403 on query explorer', function () {
    $user = createUserWithRole('user');

    $this->actingAs($user)
        ->get(route('data-management.query-explorer'))
        ->assertForbidden();
});

test('manager can access cache controls', function () {
    $mock = Mockery::mock(\App\Services\WorkStudio\Shared\Cache\CachedQueryService::class);
    $mock->shouldReceive('getCacheStatus')->andReturn([]);
    $mock->shouldReceive('getDriverName')->andReturn('database');
    app()->instance(\App\Services\WorkStudio\Shared\Cache\CachedQueryService::class, $mock);

    $user = createUserWithRole('manager');

    $this->actingAs($user)
        ->get(route('data-management.cache'))
        ->assertOk();
});

test('manager cannot access query explorer', function () {
    $user = createUserWithRole('manager');

    $this->actingAs($user)
        ->get(route('data-management.query-explorer'))
        ->assertForbidden();
});

// ──────────────────────────────────────────
// Health Dashboard
// ──────────────────────────────────────────

test('users without access-health-dashboard get 403', function () {
    $user = createUserWithRole('user');

    $this->actingAs($user)
        ->get(route('health.dashboard'))
        ->assertForbidden();
});

test('sudo-admin can access health dashboard', function () {
    $user = createUserWithRole('sudo-admin');

    $this->actingAs($user)
        ->get(route('health.dashboard'))
        ->assertOk();
});

// ──────────────────────────────────────────
// Sudo-admin has full access
// ──────────────────────────────────────────

test('sudo-admin can access cache controls', function () {
    $mock = Mockery::mock(\App\Services\WorkStudio\Shared\Cache\CachedQueryService::class);
    $mock->shouldReceive('getCacheStatus')->andReturn([]);
    $mock->shouldReceive('getDriverName')->andReturn('database');
    app()->instance(\App\Services\WorkStudio\Shared\Cache\CachedQueryService::class, $mock);

    $user = createUserWithRole('sudo-admin');

    $this->actingAs($user)
        ->get(route('data-management.cache'))
        ->assertOk();
});

test('sudo-admin can access query explorer', function () {
    $user = createUserWithRole('sudo-admin');

    $this->actingAs($user)
        ->get(route('data-management.query-explorer'))
        ->assertOk();
});

// ──────────────────────────────────────────
// Dashboard — accessible to all authenticated users
// ──────────────────────────────────────────

test('user with no role can still access dashboard', function () {
    $user = User::factory()->withWorkStudio()->create();
    UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

// ──────────────────────────────────────────
// User Management — Create User
// ──────────────────────────────────────────

test('users without manage-users get 403 on create user', function () {
    $user = createUserWithRole('user');

    $this->actingAs($user)
        ->get(route('user-management.create'))
        ->assertForbidden();
});

test('manager cannot access create user', function () {
    $user = createUserWithRole('manager');

    $this->actingAs($user)
        ->get(route('user-management.create'))
        ->assertForbidden();
});

test('sudo-admin can access create user', function () {
    $user = createUserWithRole('sudo-admin');

    $this->actingAs($user)
        ->get(route('user-management.create'))
        ->assertOk();
});

// ──────────────────────────────────────────
// Seeder idempotency
// ──────────────────────────────────────────

test('seeder is idempotent — running twice does not duplicate roles', function () {
    // Seeder already ran in beforeEach. Run it again.
    $this->seed(RolePermissionSeeder::class);

    expect(\Spatie\Permission\Models\Role::count())->toBe(5);
    expect(\Spatie\Permission\Models\Permission::count())->toBe(7);
});

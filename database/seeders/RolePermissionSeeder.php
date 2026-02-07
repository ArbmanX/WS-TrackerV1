<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permissions grouped by domain area.
     *
     * @var array<string, string[]>
     */
    private const PERMISSIONS = [
        'dashboard' => [
            'view-dashboard',
        ],
        'data-management' => [
            'access-data-management',
            'manage-cache',
            'execute-queries',
        ],
        'admin' => [
            'access-pulse',
            'access-health-dashboard',
            'manage-users',
        ],
    ];

    /**
     * Role-to-permission mapping.
     *
     * @var array<string, string[]>
     */
    private const ROLES = [
        'sudo-admin' => [
            'view-dashboard',
            'access-data-management',
            'manage-cache',
            'execute-queries',
            'access-pulse',
            'access-health-dashboard',
            'manage-users',
        ],
        'manager' => [
            'view-dashboard',
            'access-data-management',
            'manage-users',
        ],
        'planner' => [
            'view-dashboard',
        ],
        'general-foreman' => [
            'view-dashboard',
        ],
        'user' => [
            'view-dashboard',
        ],
    ];

    /**
     * Seed the roles and permissions.
     *
     * Idempotent — safe to run multiple times.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        $allPermissions = collect(self::PERMISSIONS)->flatten();
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }
}

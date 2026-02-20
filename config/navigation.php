<?php

/**
 * Role-keyed sidebar navigation.
 *
 * Each role gets a distinct set of hub links — not a filtered master list.
 * The authenticated user's primary role determines which set they see.
 *
 * Hub item keys:
 *   label      - Display text
 *   route      - Named route (may not exist yet — sidebar handles gracefully)
 *   icon       - Heroicon name (outline variant)
 *   permission - Optional extra permission gate (beyond role membership)
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Role Priority
    |--------------------------------------------------------------------------
    |
    | When a user holds multiple Spatie roles, the first match in this
    | ordered list wins. Highest privilege first.
    |
    */
    'role_priority' => [
        'sudo-admin',
        'manager',
        'general-foreman',
        'planner',
        'user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Role
    |--------------------------------------------------------------------------
    |
    | If the authenticated user has no matching role, show this role's nav.
    |
    */
    'fallback_role' => 'user',

    /*
    |--------------------------------------------------------------------------
    | Hub Links per Role
    |--------------------------------------------------------------------------
    */
    'hubs' => [

        'sudo-admin' => [
            ['label' => 'Dashboard',   'route' => 'dashboard',                  'icon' => 'chart-bar',              'permission' => 'view-dashboard'],
            ['label' => 'Planners',    'route' => 'planner-metrics.overview',   'icon' => 'users'],
            ['label' => 'Assessments', 'route' => 'assessments.index',          'icon' => 'clipboard-document-list'],
            ['label' => 'Monitoring',  'route' => 'monitoring.index',           'icon' => 'signal'],
            ['label' => 'Admin',       'route' => 'admin.index',                'icon' => 'shield-check',           'permission' => 'manage-users'],
        ],

        'manager' => [
            ['label' => 'Dashboard',   'route' => 'dashboard',                  'icon' => 'chart-bar',              'permission' => 'view-dashboard'],
            ['label' => 'Planners',    'route' => 'planner-metrics.overview',   'icon' => 'users'],
            ['label' => 'Assessments', 'route' => 'assessments.index',          'icon' => 'clipboard-document-list'],
            ['label' => 'Monitoring',  'route' => 'monitoring.index',           'icon' => 'signal'],
            ['label' => 'Admin',       'route' => 'admin.index',                'icon' => 'shield-check',           'permission' => 'manage-users'],
        ],

        'general-foreman' => [
            ['label' => 'Dashboard',   'route' => 'dashboard',                  'icon' => 'chart-bar',              'permission' => 'view-dashboard'],
            ['label' => 'Team',        'route' => 'team.index',                 'icon' => 'user-group'],
            ['label' => 'Assessments', 'route' => 'assessments.index',          'icon' => 'clipboard-document-list'],
        ],

        'planner' => [
            ['label' => 'My Dashboard', 'route' => 'dashboard',                 'icon' => 'home'],
            ['label' => 'My Circuits',  'route' => 'circuits.index',            'icon' => 'map'],
            ['label' => 'My Progress',  'route' => 'planner-metrics.overview',  'icon' => 'arrow-trending-up'],
        ],

        'user' => [
            ['label' => 'Dashboard',   'route' => 'dashboard',                  'icon' => 'chart-bar',              'permission' => 'view-dashboard'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Settings Link (pinned to sidebar bottom, all roles)
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'label' => 'Settings',
        'route' => 'settings',
        'icon' => 'cog-6-tooth',
    ],

];

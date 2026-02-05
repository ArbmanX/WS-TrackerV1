<?php

return [
    /*
      |--------------------------------------------------------------------------
      | All Known Regions
      |--------------------------------------------------------------------------
      | Master list of all regions in WorkStudio. Add new regions here first.
      */
    'all' => [
        // Geographic/Operational
        'CENTRAL',
        'HARRISBURG',
        'LEHIGH',
        'LANCASTER',
        'DISTRIBUTION',

        // Planner Groups
        'PRE_PLANNER',
        'VEG_ASSESSORS',
        'VEG_PLANNERS',

        // Crew Groups (not accessible to planners)
        'VEG_CREW',
        'VEG_FOREMAN',
    ],

    'default' => [
        'DISTRIBUTION',
        'VEG_PLANNER',
    ],

    /*
      |--------------------------------------------------------------------------
      | Role-Based Region Access
      |--------------------------------------------------------------------------
      | Define which regions each role can access.
      | Use '*' for full access to all regions.
      */
    'roles' => [
        'planner' => [
            'CENTRAL',
            'HARRISBURG',
            'LEHIGH',
            'LANCASTER',
            'DISTRIBUTION',
            'PRE_PLANNER',
            'VEG_ASSESSORS',
            'VEG_PLANNERS',
        ],

        '*' => config('workstudio_resource_groups.all'),
        'admin' => config('workstudio_resource_groups.all'),      // Full access
        'sudo_admin' => config('workstudio_resource_groups.all'), // Full access
    ],

    /*
      |--------------------------------------------------------------------------
      | WS Group → Region Mapping
      |--------------------------------------------------------------------------
      | Maps WorkStudio group names (domain prefix stripped) to VEGJOB.REGION values.
      | Used by ResourceGroupAccessService::resolveRegionsFromGroups() during
      | onboarding to determine which regions a user can access.
      |
      | If a group name directly matches a known region in 'all', it's auto-resolved
      | without needing an entry here.
      */
    'group_to_region_map' => [
        'VEG_PLANNERS' => [
            'CENTRAL',
            'HARRISBURG',
            'LEHIGH',
            'LANCASTER',
            'DISTRIBUTION',
            'PRE_PLANNER',
            'VEG_ASSESSORS',
            'VEG_PLANNERS',
        ],
    ],
];

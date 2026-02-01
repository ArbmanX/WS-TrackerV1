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
            | User-Specific Region Restrictions (Optional)
            |--------------------------------------------------------------------------
            | Override role-based access for specific users.
            | Useful when a planner is limited to certain geographic regions.
            */

    'users' => [
        'Adam Miller' => ['LANCASTER', 'HARRISBURG'],
    ],
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WorkStudio API Configuration  DISTRIBUTION
    |--------------------------------------------------------------------------
    */

    'base_url' => env('WORKSTUDIO_BASE_URL', 'https://ppl02.geodigital.com:8372/DDOProtocol/'),
    'timeout' => env('WORKSTUDIO_TIMEOUT', 60),
    'connect_timeout' => env('WORKSTUDIO_CONNECT_TIMEOUT', 10),
    'max_retries' => 5,

    /*
    |--------------------------------------------------------------------------
    | View GUIDs
    |--------------------------------------------------------------------------
    | These are the WorkStudio view definition GUIDs for each data type.
    */

    'views' => [
        'vegetation_assessments' => '{A856F956-88DF-4807-90E2-7E12C25B5B32}',
        'work_jobs' => '{546D9963-9242-4945-8A74-15CA83CDA537}',
        'planned_units' => '{985AECEF-D75B-40F3-9F9B-37F21C63FF4A}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Mappings
    |--------------------------------------------------------------------------
    | Maps status codes to filter values and display captions.
    */

    'statuses' => [
        'new' => ['value' => 'SA', 'caption' => 'New'],
        'active' => ['value' => 'ACTIV', 'caption' => 'In Progress'],
        'qc' => ['value' => 'QC', 'caption' => 'QC'],
        'rework' => ['value' => 'REWRK', 'caption' => 'Rework'],
        'deferral' => ['value' => 'DEF', 'caption' => 'Deferral'],
        'closed' => ['value' => 'CLOSE', 'caption' => 'Closed'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Account
    |--------------------------------------------------------------------------
    | Fallback credentials when user credentials are unavailable.
    */

    'service_account' => [
        'username' => env('WORKSTUDIO_SERVICE_USERNAME', ''),
        'password' => env('WORKSTUDIO_SERVICE_PASSWORD', ''),

    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'rate_limit_delay' => 500000, // microseconds (0.5 seconds)
        'calls_before_delay' => 5,
    ],

    /*
    |==========================================================================
    | UNIFIED WORKSTUDIO CONFIGURATION
    |==========================================================================
    |
    | Everything below consolidates the separate config files into one place.
    | The original files still exist and are used by current code.
    | As code is refactored, it should migrate to these keys:
    |
    |   config('workstudio_resource_groups.*') → config('workstudio.regions.*')
    |   config('ws_cache.*')                   → config('workstudio.cache.*')
    |   config('ws_data_collection.*')         → config('workstudio.data_collection.*')
    |   config('ws_assessment_query.*')        → config('workstudio.assessments.*')
    |   config('planner_metrics.*')            → config('workstudio.planner_metrics.*')
    |
    | NOT consolidated (kept separate):
    |   config('workstudio_fields.*')          — large field lists, own file
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Regions (from workstudio_resource_groups.php)
    |--------------------------------------------------------------------------
    | Region lists, role-based access, and WS group → region mapping.
    */

    'regions' => [
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

            '*' => [
                'CENTRAL',
                'HARRISBURG',
                'LEHIGH',
                'LANCASTER',
                'DISTRIBUTION',
                'PRE_PLANNER',
                'VEG_ASSESSORS',
                'VEG_PLANNERS',
                'VEG_CREW',
                'VEG_FOREMAN',
            ],

            'admin' => [
                'CENTRAL',
                'HARRISBURG',
                'LEHIGH',
                'LANCASTER',
                'DISTRIBUTION',
                'PRE_PLANNER',
                'VEG_ASSESSORS',
                'VEG_PLANNERS',
                'VEG_CREW',
                'VEG_FOREMAN',
            ],

            'sudo_admin' => [
                'CENTRAL',
                'HARRISBURG',
                'LEHIGH',
                'LANCASTER',
                'DISTRIBUTION',
                'PRE_PLANNER',
                'VEG_ASSESSORS',
                'VEG_PLANNERS',
                'VEG_CREW',
                'VEG_FOREMAN',
            ],
        ],

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
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache (from ws_cache.php)
    |--------------------------------------------------------------------------
    | TTLs, dataset definitions, snapshot settings for WS data caching.
    */

    'cache' => [
        'prefix' => 'ws',

        'ttl' => [
            'system_wide_metrics' => (int) env('WS_CACHE_TTL_SYSTEM_WIDE_METRICS', 900),
            'regional_metrics' => (int) env('WS_CACHE_TTL_REGIONAL_METRICS', 900),
            'active_assessments' => (int) env('WS_CACHE_TTL_ACTIVE_ASSESSMENTS', 600),
        ],

        'datasets' => [
            'system_wide_metrics' => [
                'label' => 'System-Wide Metrics',
                'description' => 'Aggregated metrics across all regions and contractors.',
                'method' => 'getSystemWideMetrics',
            ],
            'regional_metrics' => [
                'label' => 'Regional Metrics',
                'description' => 'Metrics grouped by region for the scope year.',
                'method' => 'getRegionalMetrics',
            ],
            'active_assessments' => [
                'label' => 'Active Assessments',
                'description' => 'Currently checked-out assessments ordered by oldest.',
                'method' => 'getActiveAssessmentsOrderedByOldest',
            ],
        ],

        'registry_key' => '_cache_registry',

        'snapshot' => [
            'enabled' => (bool) env('WS_SNAPSHOT_ENABLED', true),
            'datasets' => ['system_wide_metrics', 'regional_metrics'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Collection (from ws_data_collection.php)
    |--------------------------------------------------------------------------
    | Live monitor, ghost detection, thresholds, sanity checks.
    */

    'data_collection' => [
        'live_monitor' => [
            'enabled' => (bool) env('WS_LIVE_MONITOR_ENABLED', true),
            'schedule' => 'daily',
        ],

        'ghost_detection' => [
            'enabled' => (bool) env('WS_GHOST_DETECTION_ENABLED', true),
            'oneppl_domain' => 'ONEPPL',
        ],

        'thresholds' => [
            'aging_unit_days' => 14,
            'notes_compliance_area_sqm' => 9.29, // 100 sq ft = 10'x10' minimum
        ],

        'sanity_checks' => [
            'flag_zero_count' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Assessments (from ws_assessment_query.php)
    |--------------------------------------------------------------------------
    | Scope year, contractors, cycle/job types, statuses, unit groups.
    */

    'assessments' => [
        'scope_year' => '2026',

        // Contractors are derived from User->ws_domain via UserQueryContext.
        // Kept as fallback default for UserQueryContext::fromConfig().
        'contractors' => [
            'Asplundh',
        ],

        'excluded_users' => [
            'ASPLUNDH\\jcompton',
            'ASPLUNDH\\joseam',
        ],

        'cycle_types' => [
            'maintenance' => [
                'Cycle Maintenance - Herbicide',
                'Cycle Maintenance - Trim',
                'FFP CPM Maintenance',
                'Lump Sum Maintenance',
            ],
            'storm' => [
                'PUC-STORM FOLLOW UP',
                'NON-PUC STORM',
                'Storm Follow Up',
            ],
            'projects' => [
                'Reactive',
                'Misc. Project Work',
                'Proactive Data Directed Maintenance',
                'Q4P',
            ],
            'data_driven' => [
                'VM Detection',
            ],
            // Types excluded from assessment queries (used in NOT IN filters)
            'excluded_from_assessments' => [
                'PUC-STORM FOLLOW UP',
                'NON-PUC STORM',
                'Storm Follow Up',
                'Reactive',
                'Misc. Project Work',
                'Proactive Data Directed Maintenance',
                'Q4P',
                'VM Detection',
            ],
        ],

        'job_types' => [
            'assessments' => [
                'Assessment Dx',
                'Split_Assessment',
            ],
            'work_jobs' => [
                'Work Dx',
            ],
            'not_used' => [
                'Job',
                'Reactive',
                'Tandem_Assessment',
                'WorkPlanner Program',
                'Cycle Maintenance - Trim',
                'Cycle Maintenance - Herbicide',
                'Special Projects',
                'Weekly',
                'Unit Based',
                'Capital Projects',
                'Contractor Payroll',
                'Damage Assessment',
                'Budget Forecast',
                'Pole Review',
                'Scenarios',
            ],
        ],

        'statuses' => [
            'planner_concern' => ['ACTIV', 'QC', 'REWRK', 'CLOSE', 'DEF'],

            'all' => [
                'new' => [
                    'value' => 'SA',
                    'caption' => 'New',
                ],
                'active' => [
                    'value' => 'ACTIV',
                    'caption' => 'In Progress',
                ],
                'qc' => [
                    'value' => 'QC',
                    'caption' => 'Pending Quality Control - You Will Be Notified If Any Changes Made.
                      -- Any Units Are Failed, Or If It Is Sent To Rework,
                      You Will Be Notified As Well',
                ],
                'rework' => [
                    'value' => 'REWRK',
                    'caption' => 'Sent To Rework - Check Audit Notes & Pending Permissions',
                ],
                'deferral' => ['value' => 'DEF', 'caption' => 'Deferral'],
                'closed' => ['value' => 'CLOSE', 'caption' => 'Closed'],
            ],
        ],

        // VEGUNIT.PERMSTAT values — single source of truth for permission status strings.
        // 'Pending' also includes NULL and empty string in query logic.
        'permission_statuses' => [
            'approved' => 'Approved',
            'pending' => 'Pending',
            'no_contact' => 'No Contact',
            'refused' => 'Refused',
            'deferred' => 'Deferred',
            'ppl_approved' => 'PPL Approved',
        ],

        // JOBVEGETATIONUNITS/VEGUNIT unit code groups for work measurement aggregation.
        'unit_groups' => [
            'removal_6_12' => ['REM612'],
            'removal_over_12' => ['REM1218', 'REM1824', 'REM2430', 'REM3036'],
            'ash_removal' => ['ASH612', 'ASH1218', 'ASH1824', 'ASH2430', 'ASH3036'],
            'vps' => ['VPS'],
            'brush' => ['BRUSH', 'HCB', 'BRUSHTRIM'],
            'herbicide' => ['HERBA', 'HERBNA'],
            'bucket_trim' => ['SPB', 'MPB'],
            'manual_trim' => ['SPM', 'MPM'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Planner Metrics (from planner_metrics.php)
    |--------------------------------------------------------------------------
    | Quota targets, staleness thresholds, period definitions, career data.
    */

    'planner_metrics' => [
        'quota_miles_per_week' => 6.5,

        'staleness_warning_days' => 7,
        'staleness_critical_days' => 14,
        'gap_warning_threshold' => 3.0,

        'periods' => ['week', 'month', 'year', 'scope-year'],
        'default_period' => 'week',

        'default_card_view' => 'quota',

        'week_starts_on' => \Carbon\Carbon::SUNDAY,
        'default_offset_flip_day' => 'Tuesday',
        'default_offset_flip_hour' => 17,
        'default_offset_timezone' => 'America/New_York',

        'career_json_path' => storage_path('app/asplundh/planners/career'),
    ],
];

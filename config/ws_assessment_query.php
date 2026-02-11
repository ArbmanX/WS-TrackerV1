<?php

return [
    'scope_year' => '2026',

    // Contractors are now derived from User->ws_domain via UserQueryContext.
    // Kept as fallback default for UserQueryContext::fromConfig().
    'contractors' => [
        'Asplundh',
    ],

    'excludedUsers' => [
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
            'Reactive',
            'Storm Follow Up',
            'Misc. Project Work',
            'PUC-STORM FOLLOW UP',
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

    // Resource groups consolidated in config/workstudio_resource_groups.php
    // Access via: config('workstudio_resource_groups.all'), config('workstudio_resource_groups.roles'), etc.

    'statuses' => [

        'planner_concern' => ['ACTIV', 'QC', 'REWRK', 'CLOSE'],

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
];

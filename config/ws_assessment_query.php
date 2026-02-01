<?php

return [
    'scope_year' => '2026',

    'contractors' => [
        'Asplundh',
    ],

    'excludedUsers' => [
        'ASPLUNDH\\jcompton',
        'ASPLUNDH\\joseam',
    ],

    'cycle_types' => [
        'Reactive',
        'Storm Follow Up',
        'Misc. Project Work',
        'PUC-STORM FOLLOW UP',
        // 'FFP CPM Maintenance'
    ],

    'job_types' => [
        'Assessment',
        'Assessment Dx',
        'Split_Assessment',
        'Tandem_Assessment',
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
];

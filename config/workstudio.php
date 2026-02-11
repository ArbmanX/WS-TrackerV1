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
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    | All WorkStudio cache keys will be prefixed with this value.
    */

    'prefix' => 'ws',

    /*
    |--------------------------------------------------------------------------
    | Per-Dataset TTL Defaults (seconds)
    |--------------------------------------------------------------------------
    | Override any TTL via .env: WS_CACHE_TTL_SYSTEM_WIDE_METRICS=900
    */

    'ttl' => [
        'system_wide_metrics' => (int) env('WS_CACHE_TTL_SYSTEM_WIDE_METRICS', 900),
        'regional_metrics' => (int) env('WS_CACHE_TTL_REGIONAL_METRICS', 900),
        'active_assessments' => (int) env('WS_CACHE_TTL_ACTIVE_ASSESSMENTS', 600),
        // 'daily_activities' => (int) env('WS_CACHE_TTL_DAILY_ACTIVITIES', 1800),
        // 'job_guids' => (int) env('WS_CACHE_TTL_JOB_GUIDS', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dataset Definitions
    |--------------------------------------------------------------------------
    | Label, description, and method name for each dataset.
    | Used by the admin dashboard to display dataset info.
    */

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

    // 'daily_activities' => [
    //     'label' => 'Daily Activities',
    //     'description' => 'Daily activity data for all assessments.',
    //     'method' => 'getDailyActivitiesForAllAssessments',
    // ],
    // 'job_guids' => [
    //     'label' => 'Job GUIDs',
    //     'description' => 'All job GUIDs for the entire scope year.',
    //     'method' => 'getJobGuids',
    // ],

    /*
    |--------------------------------------------------------------------------
    | Registry Key Name
    |--------------------------------------------------------------------------
    | Single cache key storing {cached_at, hit_count, miss_count} per dataset.
    */

    'registry_key' => '_cache_registry',

    /*
    |--------------------------------------------------------------------------
    | Snapshot Persistence
    |--------------------------------------------------------------------------
    | When enabled, fresh metric data (cache misses) is persisted to
    | local PostgreSQL for historical trend analysis.
    */

    'snapshot' => [
        'enabled' => (bool) env('WS_SNAPSHOT_ENABLED', true),
        'datasets' => ['system_wide_metrics', 'regional_metrics'],
    ],
];

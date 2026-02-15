<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quota Target
    |--------------------------------------------------------------------------
    |
    | Weekly footage quota in miles that planners are expected to meet.
    |
    */
    'quota_miles_per_week' => 6.5,

    /*
    |--------------------------------------------------------------------------
    | Staleness Thresholds
    |--------------------------------------------------------------------------
    |
    | Days since last WorkStudio edit before triggering warning/critical status.
    |
    */
    'staleness_warning_days' => 7,
    'staleness_critical_days' => 14,
    'gap_warning_threshold' => 3.0,

    /*
    |--------------------------------------------------------------------------
    | Period Definitions
    |--------------------------------------------------------------------------
    */
    'periods' => ['week', 'month', 'year', 'scope-year'],
    'default_period' => 'week',

    /*
    |--------------------------------------------------------------------------
    | Default Card View
    |--------------------------------------------------------------------------
    */
    'default_card_view' => 'quota',

    /*
    |--------------------------------------------------------------------------
    | Career JSON Path
    |--------------------------------------------------------------------------
    |
    | Directory containing career JSON files produced by PlannerCareerLedgerService.
    | Files follow the naming convention: {username}_{date}.json
    |
    */
    'career_json_path' => storage_path('app/asplundh/planners/career'),

];

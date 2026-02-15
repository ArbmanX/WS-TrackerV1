<?php

return [
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
];

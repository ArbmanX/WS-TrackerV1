# Configuration: `ws_data_collection`

> **File:** `config/ws_data_collection.php`
> **Used by:** [`LiveMonitorService`](./LiveMonitorService.md) | [`GhostDetectionService`](./GhostDetectionService.md)

---

## Full Config

```php
return [
    'live_monitor' => [
        'enabled'  => (bool) env('WS_LIVE_MONITOR_ENABLED', true),
        'schedule' => 'daily',
    ],

    'ghost_detection' => [
        'enabled'       => (bool) env('WS_GHOST_DETECTION_ENABLED', true),
        'oneppl_domain' => 'ONEPPL',
    ],

    'thresholds' => [
        'aging_unit_days'            => 14,
        'notes_compliance_area_sqm'  => 9.29,   // ~100 sq ft (10' x 10')
    ],

    'sanity_checks' => [
        'flag_zero_count' => true,
    ],
];
```

---

## Key Reference

### `live_monitor`

| Key | Env Var | Default | Description |
|:--|:--|:--|:--|
| `enabled` | `WS_LIVE_MONITOR_ENABLED` | `true` | Feature toggle for daily snapshots |
| `schedule` | — | `daily` | Hint for scheduler registration |

### `ghost_detection`

| Key | Env Var | Default | Description |
|:--|:--|:--|:--|
| `enabled` | `WS_GHOST_DETECTION_ENABLED` | `true` | Feature toggle for ghost detection |
| `oneppl_domain` | — | `ONEPPL` | Domain prefix matched in `JOBHISTORY.ASSIGNEDTO` |

### `thresholds`

| Key | Default | Used In | Description |
|:--|:--|:--|:--|
| `aging_unit_days` | `14` | [daily-snapshot.sql](./sql/daily-snapshot.sql) | Days before a pending unit is flagged as "aging" |
| `notes_compliance_area_sqm` | `9.29` | [daily-snapshot.sql](./sql/daily-snapshot.sql) | Minimum `JVU.AREA` (sqm) to require notes. ~100 sq ft |

### `sanity_checks`

| Key | Default | Used In | Description |
|:--|:--|:--|:--|
| `flag_zero_count` | `true` | [`LiveMonitorService`](./LiveMonitorService.md) | If true, flags snapshots where `total_units` drops to 0 from a previous non-zero value as `"suspicious": true` |

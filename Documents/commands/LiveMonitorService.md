# `LiveMonitorService`

> **Class:** `App\Services\WorkStudio\DataCollection\LiveMonitorService`
> **Called by:** [`ws:run-live-monitor`](./RunLiveMonitor.md)
> **Queries:** [`LiveMonitorQueries`](./LiveMonitorQueries.md)

---

## Purpose

Captures daily point-in-time snapshots of assessment health metrics by querying the WorkStudio API, then persists structured JSON snapshots into [`AssessmentMonitor`](./AssessmentMonitor.md) rows. Also detects assessment closures and dispatches [`AssessmentClosed`](./AssessmentClosed.md) events.

---

## Dependencies

```
LiveMonitorService
  ├── GetQueryService         ── executes SQL against WorkStudio API
  ├── LiveMonitorQueries      ── generates T-SQL strings
  ├── AssessmentMonitor       ── local PostgreSQL model
  └── AssessmentClosed        ── event dispatched on closure
```

---

## Public Methods

### `runDailySnapshot(): array`

Main cron entry point. Returns `['snapshots' => int, 'new' => int, 'closed' => int]`.

| Step | Detail |
|:--|:--|
| 1 | Calls `getDailyActivitiesForAllAssessments()` via `GetQueryService` |
| 2 | Filters to statuses: **ACTIV**, **QC**, **REWRK** |
| 3 | Loops each assessment → `snapshotAssessment()` |
| 4 | Calls `detectClosedAssessments()` for closure detection |

---

### `snapshotAssessment(string $jobGuid, array $assessmentData): void`

Captures a single assessment's daily snapshot.

**API call:** Executes [`LiveMonitorQueries::getDailySnapshot()`](./LiveMonitorQueries.md) — one combined query returning all metrics in a single row.

**Snapshot structure** (stored as JSON in `AssessmentMonitor.daily_snapshots[date]`):

```json
{
  "permission_breakdown": {
    "approved": 45,
    "pending": 12,
    "refused": 3,
    "no_contact": 1,
    "deferred": 0,
    "ppl_approved": 8
  },
  "unit_counts": {
    "work_units": 52,
    "nw_units": 17,
    "total_units": 69
  },
  "work_type_breakdown": [
    { "unit": "Trim", "UnitQty": 30 },
    { "unit": "Removal", "UnitQty": 22 }
  ],
  "footage": {
    "completed_miles": 4.25,
    "percent_complete": 62.5
  },
  "notes_compliance": {
    "units_with_notes": 40,
    "units_without_notes": 12,
    "compliance_percent": 76.9
  },
  "planner_activity": {
    "last_edit_date": "2026-02-18",
    "days_since_last_edit": 2
  },
  "aging_units": {
    "pending_over_threshold": 3,
    "threshold_days": 14
  },
  "suspicious": false
}
```

**Sanity check:** If the previous snapshot had `total_units > 0` and the new one has `total_units = 0`, the snapshot is flagged `"suspicious": true`. Controlled by `config('ws_data_collection.sanity_checks.flag_zero_count')`.

**First vs. subsequent snapshots:**
- **New monitor:** Fills metadata fields (`line_name`, `region`, `scope_year`, `cycle_type`, `total_miles`) from `$assessmentData`
- **Existing monitor:** Only updates `current_status` and `current_planner`

---

### `detectClosedAssessments(Collection $activeJobGuids): Collection`

Finds monitors in the database whose `job_guid` is **not** in the current active set.

For each closed monitor:
- Dispatches [`AssessmentClosed`](./AssessmentClosed.md) event
- Listener ([`ProcessAssessmentClose`](./AssessmentClosed.md#listener)) handles cleanup in a queued job

---

## Private Methods

| Method | Purpose |
|:--|:--|
| `milesToFeet(float)` | Conversion utility (miles * 5280) |
| `parseDdoDate(?string)` | Handles `/Date(...)` MS JSON wrapper and standard date strings |
| `buildServiceContext()` | Creates `UserQueryContext` for query builders — uses service account identity |

---

## Data Flow Diagram

```
                     WorkStudio API
                          │
           ┌──────────────┴──────────────┐
           ▼                             ▼
  getDailyActivities()          getDailySnapshot()
  (all assessments list)        (per-assessment metrics)
           │                             │
           ▼                             ▼
    Filter ACTIV/QC/REWRK        Parse & structure
           │                             │
           └──────────┬──────────────────┘
                      ▼
              AssessmentMonitor
            ┌─────────────────┐
            │  daily_snapshots │◄── JSON keyed by date
            │  latest_snapshot │◄── denormalized copy
            │  metadata fields │
            └─────────────────┘
                      │
                      ▼ (if assessment disappeared)
              AssessmentClosed event
                      │
                      ▼
           ProcessAssessmentClose
            └── cleanup + delete
```

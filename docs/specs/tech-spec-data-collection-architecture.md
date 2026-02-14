---
title: 'Assessment Data Collection & Lifecycle Intelligence'
slug: 'data-collection-architecture'
created: '2026-02-13'
status: 'ready'
brainstorm_source: 'BMAD_WS/analysis/brainstorming-session-2026-02-12.md'
business_rules: 'docs/specs/planner-activity-rules.md'
tech_stack:
  - PHP 8.4
  - Laravel 12
  - Livewire 4
  - Pest v4
  - PostgreSQL (JSONB)
  - DaisyUI v5
  - Tailwind CSS v4
files_to_create:
  - 'database/migrations/ (4 new migrations)'
  - 'app/Models/PlannerCareerEntry.php'
  - 'app/Models/AssessmentMonitor.php'
  - 'app/Models/GhostOwnershipPeriod.php'
  - 'app/Models/GhostUnitEvidence.php'
  - 'database/factories/ (4 new factories)'
  - 'app/Services/WorkStudio/DataCollection/CareerLedgerService.php'
  - 'app/Services/WorkStudio/DataCollection/LiveMonitorService.php'
  - 'app/Services/WorkStudio/DataCollection/GhostDetectionService.php'
  - 'app/Services/WorkStudio/DataCollection/Queries/CareerLedgerQueries.php'
  - 'app/Services/WorkStudio/DataCollection/Queries/LiveMonitorQueries.php'
  - 'app/Services/WorkStudio/DataCollection/Queries/GhostDetectionQueries.php'
  - 'app/Console/Commands/ImportCareerLedger.php'
  - 'app/Console/Commands/ExportCareerLedger.php'
  - 'app/Console/Commands/RunLiveMonitor.php'
  - 'app/Events/AssessmentClosed.php'
  - 'app/Listeners/ProcessAssessmentClose.php'
  - 'tests/Feature/DataCollection/ (new test directory)'
  - 'tests/Unit/DataCollection/ (new test directory)'
files_to_modify:
  - 'app/Console/Commands/ (scheduler registration)'
  - 'app/Providers/WorkStudioServiceProvider.php (service bindings)'
  - 'config/ws_data_collection.php (new config file)'
existing_code_to_reuse:
  - 'app/Services/WorkStudio/Assessments/Queries/DailyFootageQuery.php (First Unit Wins SQL — refactor into CareerLedgerQueries)'
  - 'app/Console/Commands/FetchDailyFootage.php (chunking, date parsing, API call patterns)'
  - 'app/Services/WorkStudio/Assessments/Queries/AbstractQueryBuilder.php (base class for new query builders)'
  - 'app/Services/WorkStudio/Assessments/Queries/SqlFragmentHelpers.php (date parsing, shared SQL fragments)'
code_patterns:
  - 'JSONB columns for flexible time-series data'
  - 'Constructor property promotion (PHP 8.4)'
  - 'Service classes with interface contracts'
  - 'Anonymous migration classes'
  - 'Derived tables (no CTEs — DDOProtocol limitation)'
  - 'Config-driven thresholds and unit classifications'
  - 'Fail-silent persistence with suspicious flagging'
test_patterns:
  - 'Pest v4 closure syntax'
  - 'Feature tests with RefreshDatabase + factories'
  - 'Mocked HTTP responses for API tests (Http::fake())'
  - 'JSONB assertion helpers'
related_docs:
  - 'BMAD_WS/analysis/brainstorming-session-2026-02-12.md'
  - 'docs/specs/planner-activity-rules.md'
  - 'docs/specs/assessment-completion-rules.md'
---

# Tech-Spec: Assessment Data Collection & Lifecycle Intelligence

**Created:** 2026-02-13
**Source:** Brainstorming session 2026-02-12 (3 phases: First Principles, Morphological Analysis, Constraint Mapping)

## Overview

### Problem Statement

WS-TrackerV1 fetches all assessment data live via the external WorkStudio DDOProtocol API. This means:

1. **No historical planner performance data** — once an assessment closes, daily activity breakdowns are only reconstructable by re-querying thousands of unit records from the API.
2. **No active assessment monitoring** — there's no persistent view of how active assessments are progressing day-over-day (permission completions, work type proportions, stale units).
3. **No ghost unit detection** — when utilities (ONEPPL) take ownership during QC and delete planner units, there is zero record of what was lost. No other system captures this.

### Solution

Three data collection domains, each with distinct lifecycle and storage patterns:

| Domain | Purpose | Trigger | Storage | Retention |
|--------|---------|---------|---------|-----------|
| **Career Ledger** | Planner performance history per assessment | Bootstrap + on-close | PostgreSQL JSONB | Permanent |
| **Live Monitor** | Daily assessment health snapshots | Daily cron | PostgreSQL JSONB | Lifecycle-scoped (until close) |
| **Ghost Detection** | Track unit deletions during utility ownership | Event-driven (ONEPPL takeover) | PostgreSQL | Evidence = permanent, scaffolding = lifecycle-scoped |

### Data Pipeline

```
[Bootstrap JSON]                [Daily Cron]                    [On Close Event]
ws:import-career-ledger    →    Live Monitor (daily)        →   Career Ledger append
All historical CLOSE data       ~30 ACTIV/QC/REWRK jobs         Delete monitor row
One-time on app install         Permission breakdowns            Delete ghost scaffolding
                                Unit counts, notes compliance    Keep ghost evidence
                                Edit recency, aging units
                                + Ghost Detection (on ONEPPL takeover)
```

### Scope

**In Scope:**
- 4 new database tables with JSONB columns
- 3 service classes (one per domain)
- 3 query builder classes for DDOProtocol API queries
- 2 artisan commands: `ws:import-career-ledger`, `ws:export-career-ledger`
- 1 scheduled command: `ws:run-live-monitor` (daily cron)
- Ghost detection triggered by JOBHISTORY ownership changes
- New config file `ws_data_collection.php` for thresholds and settings
- Tests for all services, models, and commands

**Out of Scope:**
- Frontend views/dashboards for these data domains (separate spec)
- Real-time sync or webhooks from WorkStudio
- Modifying existing live query system (CachedQueryService, GetQueryService)
- Raw unit-level archival (this spec stores pre-computed metrics, not raw VEGUNIT records)

## Fundamental Truths (from Brainstorming Phase 1)

These truths constrain all design decisions:

| # | Truth | Implication |
|---|-------|-------------|
| FP1 | Units have UNITGUID (unique, 38-char) | Ghost detection via set difference |
| FP2 | LASTEDITBY/LASTEDITDT exist on VEGUNIT | Edit attribution is free for non-deleted units |
| FP3 | JOBHISTORY tracks ownership changes | Ghost monitoring triggers on ONEPPL takeover |
| FP4 | Notes are PARCELCOMMENTS + ASSNOTE memos | Count presence, not content |
| FP5 | Audit fields live on VEGUNIT | Rework history captured per-unit |
| FP6 | Daily footage is reconstructable from ASSDDATE | No preemptive collection needed for history |
| FP7 | Career ledger = computed view of closed data | Persist the result, not the source |

**Critical constraint:** CLOSE is a terminal state. An assessment never goes from CLOSE back to REWRK. Career ledger entries are write-once, never updated.

## External Data Sources

### Primary: VEGUNIT (via DDOProtocol API)

All planner-scoped and date-scoped metrics come from VEGUNIT. Key fields:

| Field | Purpose |
|-------|---------|
| UNITGUID | Unique unit identifier — ghost detection key |
| JOBGUID | Links to assessment |
| UNIT | Unit type code (SPM, BRUSH, etc.) |
| PERMSTAT | Permission status |
| ASSDDATE | Date assessed — daily footage attribution |
| DATEPOP | Date unit was created (never empty) |
| LASTEDITDT | Last edit timestamp |
| FORESTER / FRSTR_USER | Planner display name / username |
| STATNAME | Station name — footage attribution key |
| PARCELCOMMENTS / ASSNOTE | Notes fields (presence check) |
| AUDIT_FAIL, AUDIT_USER, AUDITDATE, AUDITNOTE | QC audit fields |

### Supplementary: V_ASSESSMENT (View)

Pre-aggregated assessment-level rollup. One row per assessment per unit type.

| Field | Purpose |
|-------|---------|
| jobguid | Assessment identifier |
| pjobguid | Parent job GUID (child→parent link) |
| unit | Unit type code |
| UnitQty | Aggregated quantity — meters for trim/line, sq meters for polygon, count for discrete |
| status | Assessment status |

**Use for:** Work type breakdowns in Career Ledger `summary_totals` and Live Monitor `work_type_breakdown`. Much cheaper than aggregating raw VEGUNIT records.

**Cannot replace VEGUNIT for:** Daily footage attribution, per-planner breakdowns, permission counts, notes compliance, ghost detection.

### Supplementary: JOBVEGETATIONUNITS

Extended unit dimensions. Joined via `JOBGUID + STATNAME + SEQUENCE`.

| Field | Purpose |
|-------|---------|
| AREA | Square meters (polygon units: BRUSH, HCB) |
| ACRES | Pre-computed acre conversion |
| LENGTHWRK | Meters (trim/line units: SPM, SPB, MPM, MPB, HERB*) |
| NUMTREES | Count (tree/point units: VPS, REM*) |

**Use for:** Notes compliance threshold — polygon units with AREA >= 9.29 sq m (100 sq ft / 10'x10').

### Event Source: JOBHISTORY

Ownership changes that trigger ghost detection.

| Field | Purpose |
|-------|---------|
| JOBGUID | Links to assessment |
| USERNAME | Who performed action |
| ACTION | Transaction type |
| LOGDATE | When |
| OLDSTATUS / JOBSTATUS | Status transition |
| ASSIGNEDTO | Current assignee — ghost trigger detection |

### Unit Geometry Types

| Geometry | Unit Types | Measurement | Unit |
|----------|-----------|-------------|------|
| Polygon (area) | BRUSH, HCB, mowing | AREA / ACRES | Square meters / acres |
| Tree (point) | VPS, REM* | NUMTREES | Count |
| Trim (line) | SPM, SPB, MPM, MPB, HERB* | LENGTHWRK | Meters |

All measurements use **meters** as the base unit. Conversions to feet/miles/acres at display time.

### Split/Child Assessments

- Parent and child assessments have **separate JOBGUIDs**
- Parent EXT = `@`, children = `X_a`, `X_ab`, etc.
- Units transfer from child → parent on sync
- **Critical:** Parent assessment must NOT be taken ownership of — if it is, child→parent unit merge breaks silently (changes never merge when user syncs)
- One row per planner-per-JOBGUID works naturally for Career Ledger

## Database Schema

### Table 1: `planner_career_entries`

**Purpose:** One row per planner per closed assessment. Permanent historical record with JSONB daily breakdown.

**Lifecycle:** Created once on assessment CLOSE (or via bootstrap import). Never updated. Never deleted.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigIncrements | No | PK |
| planner_username | string(50) | No | FRSTR_USER (login username) |
| planner_display_name | string(100) | Yes | FORESTER (human-readable) |
| job_guid | string(38) | No | Assessment identifier |
| line_name | string(255) | No | Circuit name |
| region | string(50) | No | Region code |
| scope_year | string(10) | No | Work program year |
| cycle_type | string(50) | Yes | Trim, herbicide, etc. |
| assessment_total_miles | decimal(10,4) | Yes | VEGJOB.LENGTH at close |
| assessment_completed_miles | decimal(10,4) | Yes | Completed footage in miles |
| assessment_pickup_date | date | Yes | First JOBHISTORY entry for this planner |
| assessment_qc_date | date | Yes | JOBHISTORY where status → QC |
| assessment_close_date | date | Yes | Date assessment closed |
| went_to_rework | boolean | No | Default false |
| rework_details | jsonb | Yes | Audit info if went_to_rework = true |
| daily_metrics | jsonb | No | Date-keyed daily breakdown |
| summary_totals | jsonb | No | Pre-computed aggregates |
| source | string(20) | No | 'bootstrap' or 'live_monitor' |
| created_at | timestamp | No | |
| updated_at | timestamp | No | |

**Indexes:**
- `unique: planner_username, job_guid` — one entry per planner per assessment
- `index: job_guid`
- `index: region`
- `index: scope_year`
- `composite: planner_username, scope_year`

**daily_metrics JSONB structure:**
```json
{
  "2026-01-15": {
    "footage_feet": 2340.5,
    "stations_completed": 12,
    "work_units": 15,
    "nw_units": 3
  }
}
```

**summary_totals JSONB structure:**
```json
{
  "total_footage_feet": 48250.7,
  "total_footage_miles": 9.14,
  "total_stations": 245,
  "total_work_units": 312,
  "total_nw_units": 67,
  "working_days": 52,
  "avg_daily_footage_feet": 928.1,
  "first_activity_date": "2026-01-15",
  "last_activity_date": "2026-03-28"
}
```

**rework_details JSONB structure (only if went_to_rework = true):**
```json
{
  "rework_count": 1,
  "audit_user": "ONEPPL\\jsmith",
  "audit_date": "2026-03-15",
  "audit_notes": "3 units failed QC",
  "failed_unit_count": 3
}
```

**Career summary queries:** Computed on-the-fly using `jsonb_each(daily_metrics)` across all entries for a planner. Scale analysis: ~120 rows × ~30 JSONB keys = ~3,600 data points max for a 6-year career — trivial for PostgreSQL.

---

### Table 2: `assessment_monitors`

**Purpose:** One row per active assessment (ACTIV/QC/REWRK). Daily JSONB time-series snapshots.

**Lifecycle:** Created when assessment enters ACTIV. Updated daily by cron (new key added to `daily_snapshots`). On CLOSE: metrics rolled into `planner_career_entries`, then row deleted.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigIncrements | No | PK |
| job_guid | string(38) | No | Assessment identifier |
| line_name | string(255) | No | Circuit name |
| region | string(50) | No | Region code |
| scope_year | string(10) | No | Work program year |
| cycle_type | string(50) | Yes | Trim, herbicide, etc. |
| current_status | string(10) | No | ACTIV, QC, or REWRK |
| current_planner | string(100) | Yes | Current FORESTER assigned |
| total_miles | decimal(10,4) | Yes | VEGJOB.LENGTH — circuit total |
| daily_snapshots | jsonb | No | Date-keyed daily metrics (default `{}`) |
| latest_snapshot | jsonb | Yes | Denormalized copy of most recent day |
| first_snapshot_date | date | Yes | When monitoring began |
| last_snapshot_date | date | Yes | Most recent snapshot |
| created_at | timestamp | No | |
| updated_at | timestamp | No | |

**Indexes:**
- `unique: job_guid`
- `index: current_status`
- `index: region`

**daily_snapshots JSONB structure (date-keyed):**
```json
{
  "2026-02-10": {
    "permission_breakdown": {
      "Granted": 45,
      "Denied": 3,
      "Pending": 12,
      "Refused": 1,
      "Not Needed": 8
    },
    "unit_counts": {
      "work_units": 58,
      "nw_units": 11,
      "total_units": 69
    },
    "work_type_breakdown": {
      "SPM": 15, "SPB": 12, "REM612": 8, "BRUSH": 5,
      "HCB": 3, "HERBNA": 7, "NW": 8, "NOT": 2, "SENSI": 1
    },
    "footage": {
      "completed_feet": 24500.5,
      "completed_miles": 4.64,
      "percent_complete": 38.2
    },
    "notes_compliance": {
      "units_with_notes": 42,
      "units_without_notes": 27,
      "compliance_percent": 60.9
    },
    "planner_activity": {
      "last_edit_date": "2026-02-10",
      "days_since_last_edit": 0
    },
    "aging_units": {
      "pending_over_threshold": 4,
      "threshold_days": 14
    },
    "suspicious": false
  }
}
```

**latest_snapshot:** Identical structure to a single day's entry. Denormalized copy of the most recent `daily_snapshots` entry for O(1) dashboard reads.

**Sanity check:** If today's `total_units` = 0 but yesterday's was > 0, persist the snapshot with `"suspicious": true` flag rather than skipping. Preserves the data point for investigation.

**JSONB growth:** Max ~180 KB for a pathological 1-year stuck assessment (~365 keys × ~500 bytes). No concern.

---

### Table 3: `ghost_ownership_periods`

**Purpose:** Track each ONEPPL ownership takeover. Scaffolding for ghost detection.

**Lifecycle:** Created when ONEPPL takes ownership. Updated when ownership returns (return_date, status). Deleted on assessment CLOSE. Baseline snapshot used for daily UNITGUID comparisons.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigIncrements | No | PK |
| job_guid | string(38) | No | Assessment identifier |
| line_name | string(255) | No | Circuit name (for display) |
| region | string(50) | No | Region code |
| takeover_date | date | No | When ONEPPL took ownership |
| takeover_username | string(150) | No | Which ONEPPL user |
| return_date | date | Yes | When ownership returned (null = still owned) |
| baseline_unit_count | integer | No | How many units at takeover |
| baseline_snapshot | jsonb | No | Array of unit records at takeover |
| is_parent_takeover | boolean | No | True if parent assessment (EXT = '@') — flags child sync blocking |
| status | string(20) | No | 'active', 'resolved', or 'closed' |
| created_at | timestamp | No | |
| updated_at | timestamp | No | |

**Indexes:**
- `index: job_guid`
- `index: status`
- `composite: job_guid, status` — find active periods for an assessment

**baseline_snapshot JSONB structure (array of unit records):**
```json
[
  {
    "unitguid": "{ABC-123-...}",
    "unit": "SPM",
    "statname": "10",
    "permstat": "Granted",
    "forester": "Alice Johnson"
  }
]
```

**is_parent_takeover:** When true, indicates a parent assessment (EXT = `@`) was taken by ONEPPL. While owned, child assessments cannot merge units into the parent. Units from children will populate on the parent once released — it is a blocking condition, not permanent damage. Dashboard should alert when this flag is true.

---

### Table 4: `ghost_unit_evidence`

**Purpose:** Permanent record of confirmed deleted units. Never deleted.

**Lifecycle:** Created when a UNITGUID from the baseline snapshot is found missing during daily comparison or final ownership return check. Self-contained after creation — does not depend on scaffolding table.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigIncrements | No | PK |
| ownership_period_id | unsignedBigInteger | Yes | FK → ghost_ownership_periods (nullable after cleanup) |
| job_guid | string(38) | No | Denormalized — survives scaffolding deletion |
| line_name | string(255) | No | Denormalized |
| region | string(50) | No | Denormalized |
| unitguid | string(38) | No | The deleted unit's GUID |
| unit_type | string(20) | No | SPM, BRUSH, etc. — from baseline snapshot |
| statname | string(100) | No | Which station |
| permstat_at_snapshot | string(30) | Yes | Permission status when last seen |
| forester | string(100) | Yes | Who created the unit |
| detected_date | date | No | When we first noticed it missing |
| takeover_date | date | No | When ONEPPL took ownership (denormalized) |
| takeover_username | string(150) | No | Which ONEPPL user (denormalized) |
| created_at | timestamp | No | |

**Indexes:**
- `index: job_guid`
- `index: ownership_period_id`
- `index: detected_date`
- `index: unitguid`

**FK strategy:** `ownership_period_id` uses `ON DELETE SET NULL` — when scaffolding is cleaned up on assessment CLOSE, the FK is nulled but the evidence row survives intact with all denormalized fields.

## Configuration

### New file: `config/ws_data_collection.php`

```php
return [
    'career_ledger' => [
        'bootstrap_path' => storage_path('app/career-ledger-bootstrap.json'),
    ],

    'live_monitor' => [
        'enabled' => (bool) env('WS_LIVE_MONITOR_ENABLED', true),
        'schedule' => 'daily',  // cron schedule
    ],

    'ghost_detection' => [
        'enabled' => (bool) env('WS_GHOST_DETECTION_ENABLED', true),
        'oneppl_domain' => 'ONEPPL',  // domain prefix for ownership detection
    ],

    'thresholds' => [
        'aging_unit_days' => 14,  // days since DATEPOP with empty PERMSTAT
        'notes_compliance_area_sqm' => 9.29,  // 100 sq ft = 10'x10' minimum
    ],

    'sanity_checks' => [
        'flag_zero_count' => true,  // flag snapshot as suspicious if total_units drops to 0
    ],
];
```

## Service Architecture

### Directory Structure

```
app/Services/WorkStudio/DataCollection/
├── CareerLedgerService.php          # Bootstrap import + close-event append
├── LiveMonitorService.php           # Daily cron snapshot logic
├── GhostDetectionService.php        # Ownership tracking + UNITGUID comparison
└── Queries/
    ├── CareerLedgerQueries.php      # SQL for daily footage attribution (First Unit Wins)
    ├── LiveMonitorQueries.php       # SQL for permission/unit/notes metrics
    └── GhostDetectionQueries.php    # SQL for UNITGUID snapshots + JOBHISTORY events
```

### Event System: AssessmentClosed

When the daily cron detects an assessment has transitioned to CLOSE, it dispatches an `AssessmentClosed` event rather than handling the close inline. This decouples detection from processing.

**Event:** `app/Events/AssessmentClosed.php`
```php
class AssessmentClosed
{
    public function __construct(
        public readonly AssessmentMonitor $monitor,
        public readonly string $jobGuid,
    ) {}
}
```

**Listener:** `app/Listeners/ProcessAssessmentClose.php`
- Calls `CareerLedgerService::appendFromMonitor()` to create career entry
- Calls `GhostDetectionService::cleanupOnClose()` to delete ghost scaffolding
- Deletes the `AssessmentMonitor` row
- All wrapped in a DB transaction

**Registration:** In `EventServiceProvider` or via attribute-based discovery (Laravel 12 default).

### CareerLedgerService

**Responsibilities:**
- `importFromJson(string $path)` — Bootstrap: read JSON file, create `planner_career_entries` rows
- `exportToJson(string $path)` — Generate bootstrap JSON from API (queries all CLOSE assessments)
- `appendFromMonitor(AssessmentMonitor $monitor)` — On assessment CLOSE: transform monitor data into career entry

**Bootstrap flow:**
1. Read JSON file (shipped with app or generated via `ws:export-career-ledger`)
2. Validate structure
3. Bulk insert into `planner_career_entries` with `source = 'bootstrap'`
4. Report count of imported entries

**Close-event flow:**
1. Receive `AssessmentMonitor` model via `AssessmentClosed` event listener
2. Query VEGUNIT via `CareerLedgerQueries` for final daily_metrics (First Unit Wins attribution)
3. Query V_ASSESSMENT for summary work type breakdown
4. Query JOBHISTORY for rework details (if applicable)
5. Create `PlannerCareerEntry` with `source = 'live_monitor'`
6. Monitor row deletion handled by the listener after this returns

### Existing Code to Reuse: DailyFootageQuery

The First Unit Wins computation already exists in `app/Services/WorkStudio/Assessments/Queries/DailyFootageQuery.php`. Key patterns to carry forward into `CareerLedgerQueries`:

- **Derived table with ROW_NUMBER():** `PARTITION BY JOBGUID, STATNAME ORDER BY COALESCE(DATEPOP, ASSDDATE) ASC` — identifies the first unit per station
- **`unit_rank = 1` filter:** Only the completing unit gets footage credit
- **STATIONS.SPANLGTH join:** Gets the station length in meters for footage calculation
- **UNITS.SUMMARYGRP join:** Classifies work vs non-work units (`!= 'Summary-NonWork'` and not null/empty)
- **`COALESCE(DATEPOP, ASSDDATE)`:** DATEPOP preferred (date unit was populated on map), ASSDDATE as fallback
- **`/Date(...)/ wrapper parsing`:** Already handles DDOProtocol date format in PHP
- **Chunking by JOBGUID list:** `WSHelpers::toSqlInClause()` for safe IN clauses, configurable chunk size

`CareerLedgerQueries` should refactor this SQL into the `AbstractQueryBuilder` pattern (extending it with `SqlFragmentHelpers` trait) rather than duplicating the static method approach. The core SQL logic remains identical.

### LiveMonitorService

**Responsibilities:**
- `runDailySnapshot()` — Main cron entry point: snapshot all active assessments
- `snapshotAssessment(string $jobGuid)` — Snapshot a single assessment
- `detectClosedAssessments()` — Find assessments that transitioned to CLOSE since last run

**Daily cron flow:**
1. Query API for all assessments with status ACTIV/QC/REWRK
2. For each assessment:
   a. Query VEGUNIT for permission breakdown, unit counts, notes compliance, edit recency
   b. Query V_ASSESSMENT for work type breakdown
   c. Build snapshot JSONB
   d. Sanity check: compare total_units with previous snapshot, flag if suspicious
   e. Upsert `assessment_monitors` row (create if new, add snapshot key if exists)
   f. Update `latest_snapshot` column
3. Check for assessments that have moved to CLOSE since last run
4. For each newly closed: dispatch `AssessmentClosed` event (listener handles career ledger append, ghost cleanup, and monitor deletion)

**Rate limiting:** Respects existing `sync.rate_limit_delay` (0.5s every 5 calls). At ~30 active assessments × 2-3 queries each = ~60-90 API calls → ~6-9 seconds of throttle delay.

### GhostDetectionService

**Responsibilities:**
- `checkForOwnershipChanges()` — Query JOBHISTORY for new ONEPPL takeovers
- `createBaseline(string $jobGuid, string $username, bool $isParent)` — Snapshot all UNITGUIDs
- `runComparison(GhostOwnershipPeriod $period)` — Compare current UNITGUIDs vs baseline
- `resolveOwnershipReturn(GhostOwnershipPeriod $period)` — Final comparison + mark resolved
- `cleanupOnClose(string $jobGuid)` — Delete scaffolding, keep evidence

**Ownership detection flow:**
1. Query JOBHISTORY for recent ASSIGNEDTO changes to ONEPPL domain
2. For each new takeover:
   a. Determine if parent assessment (check EXT = '@')
   b. Snapshot all current UNITGUIDs + metadata from VEGUNIT
   c. Create `ghost_ownership_periods` row with baseline

**Daily comparison flow (runs as part of live monitor cron):**
1. For each `ghost_ownership_periods` with `status = 'active'`:
   a. Query current UNITGUIDs for that assessment
   b. Set difference: `baseline_unitguids - current_unitguids` = missing
   c. Exclude already-recorded evidence (by unitguid)
   d. Create `ghost_unit_evidence` rows for newly missing units

## Artisan Commands

### `ws:import-career-ledger`

```
Description: Import career ledger entries from a JSON bootstrap file
Usage: ws:import-career-ledger {--path= : Path to JSON file (default: config)}
                               {--dry-run : Show what would be imported without writing}
```

- Reads JSON file from configured path or `--path` option
- Validates JSON structure
- Skips entries where `planner_username + job_guid` already exists (idempotent)
- Shows progress bar
- Reports: imported count, skipped count, error count

### `ws:export-career-ledger`

```
Description: Generate career ledger bootstrap JSON from the WorkStudio API
Usage: ws:export-career-ledger {--path= : Output path (default: config)}
                               {--scope-year= : Filter by scope year}
                               {--region= : Filter by region}
```

- Queries API for all CLOSE assessments matching filters
- For each: runs First Unit Wins daily attribution logic
- Outputs JSON file
- Shows progress bar with API call count

### `ws:run-live-monitor`

```
Description: Run daily live monitor snapshot for all active assessments
Usage: ws:run-live-monitor {--job-guid= : Snapshot a single assessment}
                           {--include-ghost : Also run ghost detection checks}
```

- Default: snapshot all ACTIV/QC/REWRK assessments
- `--job-guid`: run for single assessment (useful for testing)
- `--include-ghost`: also run ghost detection comparison (default in scheduler, optional for manual runs)
- Handles close detection automatically

**Scheduler registration (routes/console.php or bootstrap/app.php):**
```php
Schedule::command('ws:run-live-monitor --include-ghost')->daily();
```

## Implementation Plan

### Phase 1: Database Layer

- [ ] **Task 1.1:** Create `planner_career_entries` migration
- [ ] **Task 1.2:** Create `assessment_monitors` migration
- [ ] **Task 1.3:** Create `ghost_ownership_periods` migration
- [ ] **Task 1.4:** Create `ghost_unit_evidence` migration (FK with ON DELETE SET NULL)
- [ ] **Task 1.5:** Create `config/ws_data_collection.php`
- [ ] **Task 1.6:** Run migrations, verify all tables

### Phase 2: Models & Factories

- [ ] **Task 2.1:** Create `PlannerCareerEntry` model + factory
  - Casts: `daily_metrics` (json), `summary_totals` (json), `rework_details` (json), `went_to_rework` (boolean), dates
  - Scopes: `forPlanner($username)`, `forRegion($region)`, `forScopeYear($year)`, `fromBootstrap()`, `fromLiveMonitor()`
  - Factory states: `withRework()`, `fromBootstrap()`, `fromLiveMonitor()`

- [ ] **Task 2.2:** Create `AssessmentMonitor` model + factory
  - Casts: `daily_snapshots` (json), `latest_snapshot` (json), dates
  - Scopes: `active()`, `inQc()`, `inRework()`, `forRegion($region)`
  - Methods: `addSnapshot(string $date, array $data)`, `isSuspicious(): bool`
  - Factory states: `withSnapshots($days)`, `inQc()`, `inRework()`

- [ ] **Task 2.3:** Create `GhostOwnershipPeriod` model + factory
  - Casts: `baseline_snapshot` (json), `is_parent_takeover` (boolean), dates
  - Relationships: `hasMany(GhostUnitEvidence::class, 'ownership_period_id')`
  - Scopes: `active()`, `resolved()`, `parentTakeovers()`
  - Factory states: `active()`, `resolved()`, `parentTakeover()`

- [ ] **Task 2.4:** Create `GhostUnitEvidence` model + factory
  - Relationships: `belongsTo(GhostOwnershipPeriod::class, 'ownership_period_id')`
  - Scopes: `forAssessment($jobGuid)`, `detectedBetween($start, $end)`
  - No `updated_at` — immutable after creation (use `const UPDATED_AT = null`)

### Phase 3: Query Builders

All query builder classes extend `AbstractQueryBuilder` and use the `SqlFragmentHelpers` trait, following the existing pattern in `app/Services/WorkStudio/Assessments/Queries/`.

- [ ] **Task 3.1:** Create `CareerLedgerQueries` class (extends `AbstractQueryBuilder`)
  - Method: `getDailyFootageAttribution(string $jobGuid): string` — Refactored from `DailyFootageQuery::build()`. Same First Unit Wins SQL (derived table + ROW_NUMBER), adapted to AbstractQueryBuilder pattern
  - Method: `getReworkDetails(string $jobGuid): string` — Audit field extraction from VEGUNIT
  - Method: `getAssessmentTimeline(string $jobGuid): string` — JOBHISTORY pickup/QC/close dates
  - Method: `getWorkTypeBreakdown(string $jobGuid): string` — V_ASSESSMENT query for unit quantities

- [ ] **Task 3.2:** Create `LiveMonitorQueries` class (extends `AbstractQueryBuilder`)
  - Method: `getPermissionBreakdown(string $jobGuid): string`
  - Method: `getUnitCounts(string $jobGuid): string`
  - Method: `getNotesCompliance(string $jobGuid): string` — joins JOBVEGETATIONUNITS for polygon area threshold
  - Method: `getEditRecency(string $jobGuid): string`
  - Method: `getAgingUnits(string $jobGuid, int $thresholdDays): string`
  - Method: `getWorkTypeBreakdown(string $jobGuid): string` — V_ASSESSMENT query (may share with CareerLedgerQueries)

- [ ] **Task 3.3:** Create `GhostDetectionQueries` class (extends `AbstractQueryBuilder`)
  - Method: `getRecentOwnershipChanges(string $domain, string $since): string` — JOBHISTORY scan
  - Method: `getUnitGuidsForAssessment(string $jobGuid): string` — snapshot query with unit metadata
  - Method: `getAssessmentExtension(string $jobGuid): string` — parent check (EXT field)

### Phase 4: Service Layer & Events

- [ ] **Task 4.1:** Create `CareerLedgerService`
  - Methods: `importFromJson()`, `exportToJson()`, `appendFromMonitor()`
  - Depends on: `GetQueryService`, `CareerLedgerQueries`
  - Follows First Unit Wins rules per `planner-activity-rules.md`
  - Reuses SQL patterns from existing `DailyFootageQuery::build()`

- [ ] **Task 4.2:** Create `LiveMonitorService`
  - Methods: `runDailySnapshot()`, `snapshotAssessment()`, `detectClosedAssessments()`
  - Depends on: `GetQueryService`, `LiveMonitorQueries`
  - Implements suspicious flag sanity check
  - Dispatches `AssessmentClosed` event when close detected (does NOT handle close inline)

- [ ] **Task 4.3:** Create `GhostDetectionService`
  - Methods: `checkForOwnershipChanges()`, `createBaseline()`, `runComparison()`, `resolveOwnershipReturn()`, `cleanupOnClose()`
  - Depends on: `GetQueryService`, `GhostDetectionQueries`

- [ ] **Task 4.4:** Create `AssessmentClosed` event + `ProcessAssessmentClose` listener
  - Event carries: `AssessmentMonitor` model + `jobGuid`
  - Listener calls: `CareerLedgerService::appendFromMonitor()`, `GhostDetectionService::cleanupOnClose()`, deletes monitor row
  - Wrapped in DB transaction

- [ ] **Task 4.5:** Register services in `WorkStudioServiceProvider`

### Phase 5: Artisan Commands

- [ ] **Task 5.1:** Create `ws:import-career-ledger` command
- [ ] **Task 5.2:** Create `ws:export-career-ledger` command
- [ ] **Task 5.3:** Create `ws:run-live-monitor` command
- [ ] **Task 5.4:** Register scheduler for daily live monitor run

### Phase 6: Testing

- [ ] **Task 6.1:** Model tests — casts, relationships, scopes, factory states for all 4 models
- [ ] **Task 6.2:** CareerLedgerService tests — bootstrap import, JSON validation, close-event append, idempotency
- [ ] **Task 6.3:** LiveMonitorService tests — daily snapshot, suspicious flag logic, close detection
- [ ] **Task 6.4:** GhostDetectionService tests — baseline creation, comparison logic, evidence recording, cleanup
- [ ] **Task 6.5:** Query builder tests — SQL structure validation (no CTEs, correct joins)
- [ ] **Task 6.6:** Artisan command tests — import/export, dry-run, progress reporting

### Phase 7: Documentation & Cleanup

- [ ] **Task 7.1:** Update CHANGELOG.md
- [ ] **Task 7.2:** Run `vendor/bin/pint`
- [ ] **Task 7.3:** Run full test suite
- [ ] **Task 7.4:** Update `docs/project-context.md` with new tables/services

## Acceptance Criteria

### Career Ledger

- [ ] **AC-1:** Given a valid bootstrap JSON file, when `ws:import-career-ledger` runs, then `planner_career_entries` rows are created with correct JSONB structures
- [ ] **AC-2:** Given a career entry already exists for planner+job_guid, when the same bootstrap is re-run, then no duplicate is created (idempotent)
- [ ] **AC-3:** Given an assessment monitor with daily snapshots, when the assessment closes, then a career entry is created with `source = 'live_monitor'` and the monitor row is deleted
- [ ] **AC-4:** Given a planner with multiple career entries, when queried with `jsonb_each(daily_metrics)`, then a complete daily timeline is returned across all assessments

### Live Monitor

- [ ] **AC-5:** Given active assessments exist, when the daily cron runs, then each assessment gets a new date key in `daily_snapshots` with permission breakdown, unit counts, work type breakdown, footage, notes compliance, edit recency, and aging units
- [ ] **AC-6:** Given a snapshot where total_units = 0 but the previous day had > 0, then the snapshot is persisted with `"suspicious": true`
- [ ] **AC-7:** Given the cron runs, when `latest_snapshot` is read, then it matches the most recent date key in `daily_snapshots`
- [ ] **AC-8:** Given a new assessment enters ACTIV, when the cron runs, then a new `assessment_monitors` row is created

### Ghost Detection

- [ ] **AC-9:** Given JOBHISTORY shows ONEPPL takeover, when ghost detection runs, then a `ghost_ownership_periods` row is created with baseline UNITGUID snapshot
- [ ] **AC-10:** Given a parent assessment (EXT = '@') is taken by ONEPPL, then `is_parent_takeover = true` on the ownership period
- [ ] **AC-11:** Given UNITGUIDs in the baseline are missing from current query results, then `ghost_unit_evidence` rows are created with denormalized fields
- [ ] **AC-12:** Given ownership returns to planner, then `ghost_ownership_periods` is updated with `return_date` and `status = 'resolved'`
- [ ] **AC-13:** Given an assessment closes, then `ghost_ownership_periods` rows are deleted but `ghost_unit_evidence` rows survive (FK set to null)

### Data Integrity

- [ ] **AC-14:** No DDOProtocol queries use CTEs (`WITH ... AS`) — all use derived tables
- [ ] **AC-15:** All JSONB columns validate expected structure before persistence
- [ ] **AC-16:** Rate limiting respected: max 5 API calls per 0.5s batch

## Constraint Summary (from Phase 3)

| Constraint | Risk | Mitigation |
|---|---|---|
| API rate limits | Low | Within limits at 10x scale (~300 assessments) |
| JSONB growth | Low | Max ~180 KB for pathological 1-year assessment |
| Network failures | Medium | Per-assessment isolation + suspicious flag on zero-count |
| Ghost false positives | Low | Baseline-only comparison (ONEPPL-added units not tracked) |
| Career ledger conflicts | None | CLOSE is terminal — write-once, never updated |
| Bootstrap scale | Low | One-time, artisan command with progress bar |
| No-CTE queries | Low | Derived table patterns already proven in codebase |
| Empty API response trap | Medium | Suspicious flag when total_units drops to zero unexpectedly |

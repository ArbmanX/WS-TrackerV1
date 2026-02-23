# `ws:run-live-monitor`

> **Command Class:** `App\Console\Commands\RunLiveMonitor`
> **Signature:** `php artisan ws:run-live-monitor [options]`

---

## Overview

Orchestrates daily assessment monitoring and optional ghost unit detection. This is a **thin command** — all business logic lives in the two injected services.

```
ws:run-live-monitor
  │
  ├─▶ LiveMonitorService          ── snapshots & closure detection
  │     ├─▶ LiveMonitorQueries    ── SQL generation
  │     ├─▶ GetQueryService       ── API execution
  │     └─▶ AssessmentMonitor     ── local model (snapshots stored as JSON)
  │
  └─▶ GhostDetectionService      ── ownership change tracking
        ├─▶ GhostDetectionQueries ── SQL generation
        ├─▶ GhostOwnershipPeriod  ── baseline snapshots
        └─▶ GhostUnitEvidence     ── individual missing units
```

**Related docs:**
[LiveMonitorService](./LiveMonitorService.md) | [GhostDetectionService](./GhostDetectionService.md) | [SQL Queries](./LiveMonitorQueries.md)

---

## Options

| Option | Type | Default | Description |
|:--|:--|:--|:--|
| `--job-guid=` | `string` | *(none)* | Snapshot a single assessment by GUID |
| `--include-ghost` | `flag` | off | Also run ghost detection checks after monitoring |

---

## Usage

```bash
# Daily cron — snapshot all active assessments
php artisan ws:run-live-monitor

# Single assessment snapshot
php artisan ws:run-live-monitor --job-guid="{XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}"

# Full run — snapshots + ghost detection
php artisan ws:run-live-monitor --include-ghost
```

---

## Execution Flow

### Path A — Single Assessment (`--job-guid`)

```
1. snapshotAssessment(jobGuid, [])
   └─▶ Queries WorkStudio API for unit-level metrics
   └─▶ Upserts one AssessmentMonitor row
2. Print confirmation
```

> **Note:** When called with `--job-guid`, the second argument is an empty array `[]` — footage and metadata fields will default to zero/null since no daily activities row is provided.

### Path B — Daily Snapshot (default)

```
1. runDailySnapshot()
   ├─▶ Fetch all active assessments via getDailyActivitiesForAllAssessments()
   ├─▶ Filter to ACTIV / QC / REWRK statuses
   ├─▶ For each: snapshotAssessment(guid, assessmentRow)
   └─▶ detectClosedAssessments() → dispatches AssessmentClosed events
2. Print stats: {snapshots}, {new}, {closed}
```

### Path C — Ghost Detection (`--include-ghost`)

Runs **after** either Path A or B:

```
1. ghost.checkForOwnershipChanges()
   └─▶ Scans JOBHISTORY for ONEPPL takeovers
   └─▶ Creates GhostOwnershipPeriod baselines for new takeovers
2. For each active GhostOwnershipPeriod:
   └─▶ ghost.runComparison(period)
       └─▶ Compares current UNITGUIDs vs baseline
       └─▶ Creates GhostUnitEvidence for missing units
3. Print stats: {ownership changes}, {new ghost units}
```

---

## Output Examples

```
Snapshots: 42, New monitors: 3, Closed: 1
```

```
Snapshot completed for assessment {XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}.
```

```
Snapshots: 42, New monitors: 3, Closed: 1
Ghost checks: 2 ownership changes, 5 new ghost units
```

---

## Configuration

All values in [`config/ws_data_collection.php`](./Config.md):

| Key | Default | Used By |
|:--|:--|:--|
| `live_monitor.enabled` | `true` | Feature toggle |
| `live_monitor.schedule` | `daily` | Cron schedule hint |
| `ghost_detection.enabled` | `true` | Feature toggle |
| `ghost_detection.oneppl_domain` | `ONEPPL` | Domain prefix for ownership scans |
| `thresholds.aging_unit_days` | `14` | Days before pending units are "aging" |
| `thresholds.notes_compliance_area_sqm` | `9.29` | Min area (sqm) to require notes (~100 sq ft) |
| `sanity_checks.flag_zero_count` | `true` | Flag snapshots where units drop to zero |

---

## Events Dispatched

| Event | When | Listener |
|:--|:--|:--|
| [`AssessmentClosed`](./AssessmentClosed.md) | Monitor detects assessment no longer active | `ProcessAssessmentClose` (queued) |

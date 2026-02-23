# `AssessmentMonitor`

> **Model:** `App\Models\AssessmentMonitor`
> **Table:** `assessment_monitors`
> **Used by:** [`LiveMonitorService`](./LiveMonitorService.md) | [`ProcessAssessmentClose`](./AssessmentClosed.md#listener)

---

## Purpose

Stores daily health snapshots for each monitored assessment. Each row tracks one assessment over time, with a JSON blob of date-keyed snapshots providing a historical audit trail.

---

## Schema

| Column | Type | Description |
|:--|:--|:--|
| `id` | bigint | Auto-increment PK |
| `job_guid` | string | WorkStudio assessment GUID |
| `line_name` | string | Circuit/line name |
| `region` | string | Regional grouping |
| `scope_year` | string | Assessment scope year |
| `cycle_type` | string | e.g. `Cycle Maintenance - Trim` |
| `current_status` | string | Latest status: `ACTIV`, `QC`, `REWRK`, etc. |
| `current_planner` | string | Currently assigned planner |
| `total_miles` | decimal(8,4) | Total circuit miles |
| `daily_snapshots` | json | Date-keyed snapshot history (see below) |
| `latest_snapshot` | json | Denormalized copy of most recent snapshot |
| `first_snapshot_date` | date | When monitoring began |
| `last_snapshot_date` | date | Most recent snapshot date |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## JSON Structure: `daily_snapshots`

```json
{
  "2026-02-18": { /* snapshot */ },
  "2026-02-19": { /* snapshot */ },
  "2026-02-20": { /* snapshot */ }
}
```

Each snapshot contains the structure documented in [`LiveMonitorService`](./LiveMonitorService.md#snapshotassessment).

---

## Key Method: `addSnapshot(string $date, array $snapshot)`

Appends a snapshot to the `daily_snapshots` JSON, updates `latest_snapshot` (denormalized for fast reads), and tracks snapshot date bounds.

```php
$monitor->addSnapshot('2026-02-20', $snapshot);
$monitor->save();
```

---

## Scopes

| Scope | Filter |
|:--|:--|
| `active()` | `current_status = 'ACTIV'` |
| `inQc()` | `current_status = 'QC'` |
| `inRework()` | `current_status = 'REWRK'` |
| `forRegion($region)` | `region = $region` |

---

## Lifecycle

```
  Created          ── first snapshot (metadata filled from daily activities)
     │
     ▼
  Updated daily    ── addSnapshot() appends to JSON, updates status/planner
     │
     ▼
  Closed           ── detectClosedAssessments() fires AssessmentClosed
     │
     ▼
  Deleted          ── ProcessAssessmentClose listener removes the row
```

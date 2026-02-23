# `LiveMonitorQueries`

> **Class:** `App\Services\WorkStudio\DataCollection\Queries\LiveMonitorQueries`
> **Extends:** `AbstractQueryBuilder` (shared constructor + `SqlFragmentHelpers`)
> **Called by:** [`LiveMonitorService`](./LiveMonitorService.md)

---

## Purpose

Generates the T-SQL query that powers assessment daily snapshots. Consolidates what was originally **6 separate API calls** into a single combined query returning one row per assessment with all health metrics.

---

## `getDailySnapshot(string $jobGuid, int $agingThresholdDays): string`

### Parameters

| Param | Source | Description |
|:--|:--|:--|
| `$jobGuid` | Loop iteration in `LiveMonitorService` | Assessment GUID (validated via `validateGuid()`) |
| `$agingThresholdDays` | `config('ws_data_collection.thresholds.aging_unit_days')` | Default: **14** days |

### SQL Fragment Helpers Used

| Helper | Purpose |
|:--|:--|
| `validateGuid()` | Regex check — prevents SQL injection |
| `validUnitFilter('VU')` | Excludes invalid/deleted units |
| `parseMsDateToDate('VU.ASSDDATE')` | Strips `/Date(...)` wrapper → `CAST` to `DATE` |
| `WSSQLCaster::cast('VU.EDITDATE')` | OLE float → `DATETIME` conversion |

### Full SQL

See: [`daily-snapshot.sql`](./sql/daily-snapshot.sql)

### Tables Joined

| Alias | Table | Join | Purpose |
|:--|:--|:--|:--|
| `VU` | `VEGUNIT` | *base* | Vegetation units — one row per unit on assessment |
| `U` | `UNITS` | `INNER JOIN ON U.UNIT = VU.UNIT` | Unit type metadata (`SUMMARYGRP` → work vs non-work) |
| `JVU` | `JOBVEGETATIONUNITS` | `LEFT JOIN` on GUID + STATNAME + SEQUENCE | Area measurements (for notes compliance threshold) |
| `VA` | `V_ASSESSMENT` | *subquery* | Work type breakdown (unit + quantity, returned as JSON) |

### Result Columns

| Column | Type | Metric Group |
|:--|:--|:--|
| `total_units` | int | Unit counts |
| `approved` | int | Permission breakdown |
| `pending` | int | Permission breakdown |
| `refused` | int | Permission breakdown |
| `no_contact` | int | Permission breakdown |
| `deferred` | int | Permission breakdown |
| `ppl_approved` | int | Permission breakdown |
| `work_units` | int | Unit counts |
| `nw_units` | int | Unit counts |
| `units_requiring_notes` | int | Notes compliance |
| `units_with_notes` | int | Notes compliance |
| `units_without_notes` | int | Notes compliance |
| `compliance_percent` | decimal(5,1) | Notes compliance |
| `last_edit_date` | datetime | Planner activity |
| `last_edit_by` | string | Planner activity |
| `pending_over_threshold` | int | Aging units |
| `work_type_breakdown` | json | Work types (via `FOR JSON PATH`) |

---

## Key Logic

### Permission Counting
`PERMSTAT` values are counted via `CASE WHEN` aggregates. **Pending** includes `NULL`, empty string, and literal `'Pending'` — three states that all mean "not yet permissioned."

### Work vs. Non-Work Units
Determined by `UNITS.SUMMARYGRP`:
- **Work:** `Summary-TRIM`, `Summary-REMOVAL`, `Summary`, `SummaryAudit`, `Removal`
- **Non-work:** `Summary-NonWork`, empty, or `NULL`

### Notes Compliance
Only units with `JVU.AREA >= 9.29 sqm` (~100 sq ft) are required to have notes. A unit has notes if either `PARCELCOMMENTS` or `ASSNOTE` has content (checked via `DATALENGTH > 0`).

### Aging Units
Units with pending permission status where `ASSDDATE` (date assessed) is older than the threshold. The `/Date(...)` wrapper is stripped and cast to `DATE` for the `DATEDIFF` calculation.

### Work Type Breakdown
A correlated subquery against `V_ASSESSMENT` returns unit types and quantities as a JSON array via `FOR JSON PATH`.

# `GhostDetectionQueries`

> **Class:** `App\Services\WorkStudio\DataCollection\Queries\GhostDetectionQueries`
> **Extends:** `AbstractQueryBuilder` (shared constructor + `SqlFragmentHelpers`)
> **Called by:** [`GhostDetectionService`](./GhostDetectionService.md)

---

## Purpose

Generates T-SQL queries for ghost unit detection — scanning ownership changes in `JOBHISTORY` and capturing unit-level snapshots from `VEGUNIT`.

---

## Methods

### `getRecentOwnershipChanges(string $domain, string $since): string`

Finds assessments recently taken over by a specific domain (typically `ONEPPL`).

**Parameters:**

| Param | Example | Description |
|:--|:--|:--|
| `$domain` | `'ONEPPL'` | Domain prefix — matched via `LIKE '{$domain}%'` |
| `$since` | `'2026-02-13'` | ISO date — only changes after this date |

**SQL:** See [`ownership-changes.sql`](./sql/ownership-changes.sql)

**Tables:**

| Alias | Table | Purpose |
|:--|:--|:--|
| `JH` | `JOBHISTORY` | Action log — captures `ASSIGNEDTO` changes |
| `SS` | `SS` | Job metadata — work order, extension |
| `VEGJOB` | `VEGJOB` | Line name, region |

**Filters:**
- `ASSIGNEDTO LIKE 'ONEPPL%'` — ownership change to ONEPPL
- `LOGDATE >= $since` — only recent changes
- `REGION IN (...)` — scoped to user's resource groups
- `STATUS IN ('ACTIV', 'QC', 'REWRK')` — only live assessments

**Result columns:** `JOBGUID`, `USERNAME`, `ACTION`, `LOGDATE`, `OLDSTATUS`, `JOBSTATUS`, `ASSIGNEDTO`, `WO`, `EXT`, `LINENAME`, `REGION`

---

### `getUnitGuidsForAssessment(string $jobGuid): string`

Returns all valid units on an assessment — used for both baseline capture and daily comparison.

**Security:** `$jobGuid` validated via `validateGuid()` regex before SQL interpolation.

**SQL:** See [`unit-guids.sql`](./sql/unit-guids.sql)

**Tables:**

| Alias | Table | Purpose |
|:--|:--|:--|
| `VU` | `VEGUNIT` | One row per vegetation unit |

**Filters:**
- `JOBGUID = $jobGuid`
- `validUnitFilter()` — excludes invalid/deleted units

**Result columns:** `UNITGUID`, `unit_type` (aliased from `UNIT`), `STATNAME`, `PERMSTAT`, `FORESTER`, `FRSTR_USER`

---

### `getAssessmentExtension(string $jobGuid): string`

Lookups the `EXT` field from `SS` to determine if an assessment is a parent (`@`) or split.

**Used for:** Setting `is_parent_takeover` flag on `GhostOwnershipPeriod`. Parent takeovers block child-to-parent unit sync in WorkStudio, making ghost detection more critical.

**Result columns:** `JOBGUID`, `EXT`, `WO`, `STATUS`, `TAKENBY`

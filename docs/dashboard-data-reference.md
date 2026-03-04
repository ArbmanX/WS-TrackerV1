# Dashboard Overview — Data Reference

> Every data point on `overview.blade.php`, what it is, where it comes from,
> its constraints/filters, units, and where/when it gets displayed.

**Livewire Component:** `App\Livewire\Dashboard\Overview`
**View:** `resources/views/livewire/dashboard/overview.blade.php`
**Route:** `GET /dashboard` (name: `dashboard`)

---

## Global Constraints (apply to ALL data)

All queries on this dashboard must filter to:

| Constraint | Value |
|---|---|
| Assessment status | `Active` only |
| Work activity | `completed_miles > 0` OR has activity |
| Time scope | Current contract cycle / fiscal year |
| Contract period | `2025-10-01` to `2026-06-30` (hardcoded) |

---

## 1. System Wide Overview Card

**Variable:** `$totalStats` via `$this->systemMetrics` (Livewire computed)
**Source:** `CachedQueryService::getSystemWideMetrics()`
**Display:** Top-left card, always visible on desktop. Mobile: collapsible accordion.
**Border accent:** `primary`

### 1a. Stat Row (5 metrics)

| Metric | Variable | Derivation | Unit | Display location |
|---|---|---|---|---|
| Overall % | `$overallPercent` | `($completedMiles / $totalMiles) * 100` | % | First stat, large text |
| Completed / Total | `$completedMiles`, `$totalMiles` | From `systemMetrics.completed_miles`, `systemMetrics.total_miles` | miles | Subtitle under % |
| Miles remaining | `$summaryStats['remaining_miles']` | `total_miles - completed_miles` | miles | Second stat |
| Weeks left | `$weeksRemaining` | `ceil(days_remaining / 7)` where days_remaining = days until 2026-06-30 | weeks | Third stat |
| Active planners | `$activePlanners` | `systemMetrics.active_planners` — COUNT(DISTINCT planner) with activity this week | count | Fourth stat |
| Mi/wk needed | `$milesPerWeekNeeded` | `remaining_miles / weeksRemaining` | mi/wk | Fifth stat |

### 1b. Miles Burndown Chart

**Variable:** `$burndownSnapshots`
**Source:** TODO — `system_wide_snapshots` table
**Query:** `SELECT captured_at::date as day, MAX(completed_miles) as completed, MAX(total_miles) as total FROM system_wide_snapshots GROUP BY day ORDER BY day`
**Display:** Line chart below stat row inside System Wide card. Always visible on desktop.

| Data series | Derivation | Visual |
|---|---|---|
| Actual remaining | `total - completed` per snapshot day | Solid line + filled area, `primary` color |
| Target pace | Linear interpolation from first snapshot remaining → 0 at contract end | Dashed line, `error` color at 50% opacity |

**Chart data shape:**
```php
['date' => 'YYYY-MM-DD', 'remaining' => int]  // array of snapshots
```

**Chart config:** Contract start `$contractStart`, contract end `$contractEnd`.

---

## 2. Miles by Region Card

**Variable:** `$milesPipelineByRegion`
**Source:** TODO — Active assessments grouped by region, SUM miles per status bucket
**Display:** Top-right card. Desktop: horizontal stacked bar. Mobile: collapsible accordion, vertical stacked bar.
**Border accent:** `secondary`

| Field | Unit | Description |
|---|---|---|
| `region` | — | Region name (Central, Harrisburg, Lancaster, Lehigh, Distribution) |
| `not_started` | miles | Miles with no work activity |
| `in_progress` | miles | Miles currently being worked |
| `pending_qc` | miles | Miles awaiting QC review |
| `rework` | miles | Miles sent back for rework |
| `closed` | miles | Miles completed and closed |

**Chart type:** 100% stacked bar (values converted to percentages).
**Axis orientation:** Horizontal bars on desktop (`indexAxis: 'y'`), vertical on mobile (`indexAxis: 'x'`). Auto-switches on resize.

**Color mapping:**
| Status | Color variable |
|---|---|
| Closed | `--color-primary` |
| QC | `--color-secondary` |
| Rework | `--color-error` |
| In Progress | `--color-warning` |
| Not Started | `--color-base-content` at 55% opacity |

---

## 3. Quick Actions — Planners CTA

**Variable:** `$ctaPlanners`
**Source:** TODO — `PlannerMetricsService`, `PlannerCareerLedgerService`
**Display:** First card in Quick Actions grid (below charts). Desktop: always visible. Mobile: always visible.
**Border accent:** `info`
**Alpine state:** `view` toggles between `'production'` and `'quota'`

### 3a. Production View

| Metric | Key | Unit | Description |
|---|---|---|---|
| Active | `production.active` | count | Active planners (falls back to `$activePlanners` from systemMetrics) |
| Behind | `production.behind_quota` | count | Planners behind their quota |

### 3b. Weekly Quota View

| Metric | Key | Unit | Description |
|---|---|---|---|
| Target mi/wk | `quota.target_mi_wk` | mi/wk | Weekly miles target (hardcoded 42.0) |
| Actual mi/wk | `quota.actual_mi_wk` | mi/wk | Derived from `$milesPerWeekNeeded` |

**Action link:** "Review roster" → `planner-metrics.overview` (`/planner-metrics`) — LIVE

---

## 4. Quick Actions — Assessments CTA

**Variable:** `$ctaAssessmentsByRegion`
**Source:** TODO — Assessment model grouped by region and status
**Display:** Second card in Quick Actions grid. Desktop: always visible. Mobile: always visible.
**Border accent:** `primary`
**Alpine state:** `region` filters stats (default: `'all'`)

| Region key | Chip label |
|---|---|
| `all` | All |
| `Central` | Central |
| `Harrisburg` | Hbg |
| `Lancaster` | Lanc |
| `Lehigh` | Lehigh |
| `Distribution` | Dist |

### Stats per region

| Metric | Key | Unit | Description |
|---|---|---|---|
| Active | `active` | count | Active assessments in region |
| In QC | `in_qc` | count | Assessments in QC review |
| Rework | `rework` | count | Assessments in rework |

**Action link:** "Review pipeline" → `assessments.index` — NOT BUILT (disabled)

---

## 5. Quick Actions — Admin CTA

**Variable:** `$ctaAdmin` (partially used)
**Source:** TODO — `AlertService`, `PlannerMetricsService`
**Display:** Third card in Quick Actions grid. Desktop: always visible. Mobile: always visible.
**Border accent:** `warning`
**Alpine state:** `tab` toggles between `'tools'`, `'users'`, `'monitoring'`

### 5a. Tools Tab

| Link | Route | Status |
|---|---|---|
| Query Explorer | `data-management.query-explorer` (`/data-management/query-explorer`) | LIVE |
| Cache Controls | `data-management.cache` (`/data-management/cache`) | LIVE |

### 5b. Users Tab

| Link | Route | Status |
|---|---|---|
| Create User | `user-management.create` (`/user-management/create`) | LIVE |

### 5c. Monitoring Tab

| Link | Route | Status |
|---|---|---|
| Ghost Detections | — | NOT BUILT (shows "Soon") |
| Sync Status | — | NOT BUILT (shows "Soon") |

**Note:** `$ctaAdmin['alerts']` and `$ctaAdmin['stale_planners']` are defined but not currently rendered in any card view.

---

## 6. Permissions (Slide-out Panel + Mobile Accordion)

**Variable:** `$permissionsSystemWide`
**Source:** TODO — COUNT permits grouped by `permission_status`, scoped to active assessments
**Partial:** `_permissions-body.blade.php`
**Display:**
- **Desktop (md+):** Slide-out panel, right edge tab labeled "Permissions". Click to open. `accent: warning`.
- **Mobile (<md):** Inline accordion card below Quick Actions grid.

**Border accent:** `warning`

| Status | Color variable |
|---|---|
| Approved | `--color-primary` |
| PPL Approved | `--color-secondary` |
| Pending | `--color-warning` |
| No Contact | `--color-base-300` |
| Refused | `--color-error` |
| Deferred | `--color-base-content` at 70% opacity |

### Data shape

```php
['status' => string, 'count' => int]  // array of 6 status rows
```

### Rendered elements

| Element | Description |
|---|---|
| Total Permits | Sum of all status counts (computed client-side) |
| Stacked bar | Horizontal bar with proportional segments per status |
| Status rows | Each status with color dot, label, and count |

---

## 7. Work Breakdown (Slide-out Panel + Mobile Accordion)

**Variables:** `$workTypeBreakdown`, `$summaryStats`
**Source:** TODO — SUM(quantity) grouped by `work_type`, scoped to active assessments
**Partial:** `_quick-stats-body.blade.php`
**Display:**
- **Desktop (md+):** Slide-out panel, right edge tab labeled "Work Breakdown". Click to open. `accent: accent`.
- **Mobile (<md):** Inline accordion card below Permissions accordion.

**Border accent:** `accent`

### 7a. Vertical Work Types Bar

**Variable:** `$sortedWorkTypes` (derived: top 8 from `$workTypeBreakdown` by `total_qty`)

| Field | Unit | Description |
|---|---|---|
| `work_type` | — | Code (HCB, HERBNA, HERBA, MPB, MPM, SPB, SPM, etc.) |
| `label` | — | Human-readable name |
| `total_qty` | varies | Quantity (acres, miles, or count depending on type) |

**Colors:** First 7 segments use `primary`, `secondary`, `accent`, `warning`, `info`, `success`, `error`. 8th uses `base-content` at 65% opacity.

### 7b. Trimming Stats

**Source:** `$summaryStats`

| Metric | Key | Unit | Query filter |
|---|---|---|---|
| Bucket trim | `bucket_trim_miles` | miles | `work_type = 'MPB'` |
| Manual trim | `manual_trim_miles` | miles | `work_type = 'MPM'` |

**Ratio bar:** Bucket proportion = `bucket / (bucket + manual) * 100%`. Color: `primary`.

### 7c. Brush Acres Stats

**Source:** `$summaryStats`

| Metric | Key | Unit | Query filter |
|---|---|---|---|
| Herbicide | `herbicide_acres` | acres | `work_type IN ('HERBA', 'HERBNA')` |
| HCB | `hcb_acres` | acres | `work_type = 'HCB'` |

**Ratio bar:** Herbicide proportion = `herbicide / (herbicide + hcb) * 100%`. Color: `secondary`.

### 7d. Removals & VPS Stats

**Source:** `$summaryStats`

| Metric | Key | Unit | Query filter |
|---|---|---|---|
| Removals | `rem_6_12_count` + `rem_other_count` | count (each) | `work_type IN ('REM612', 'REM1218', 'REM1824', 'REM24P')` |
| VPS | `vps_count` | count (each) | `work_type = 'VPS'` |

**Ratio bar:** Removals proportion = `(rem_6_12 + rem_other) / (rem_6_12 + rem_other + vps) * 100%`. Color: `accent`.

---

## Summary Stats (defined but not all directly rendered)

These live in `$summaryStats` and feed multiple sections:

| Key | Value (mock) | Unit | Used by |
|---|---|---|---|
| `total_miles` | 1800.0 | miles | Work Breakdown (indirect) |
| `completed_miles` | 1131.3 | miles | Work Breakdown (indirect) |
| `remaining_miles` | 668.7 | miles | Stat row (#1a), mi/wk derivation |
| `days_remaining` | computed | days | Weeks left derivation |
| `herbicide_acres` | 7730.0 | acres | Work Breakdown 7c |
| `hcb_acres` | 29080.4 | acres | Work Breakdown 7c |
| `vps_count` | 49 | count | Work Breakdown 7d |
| `rem_6_12_count` | 66 | count | Work Breakdown 7d |
| `rem_other_count` | 32 | count | Work Breakdown 7d |
| `bucket_trim_miles` | 11021.5 | miles | Work Breakdown 7b |
| `manual_trim_miles` | 3001.9 | miles | Work Breakdown 7b |
| `single_phase_miles` | 1120.0 | miles | **Not currently rendered** |
| `multi_phase_miles` | 680.0 | miles | **Not currently rendered** |

---

## Data Status Summary

| # | Section | Data source | Status |
|---|---|---|---|
| 1a | Stat Row | `CachedQueryService::getSystemWideMetrics()` | **LIVE** (via Livewire computed) |
| 1b | Burndown | `system_wide_snapshots` table | **MOCK** — hardcoded array |
| 2 | Miles by Region | Active assessments by region/status | **MOCK** — hardcoded array |
| 3 | Planners CTA | PlannerMetricsService | **MOCK** — partially derived from live `$activePlanners` |
| 4 | Assessments CTA | Assessment model by region | **MOCK** — hardcoded array |
| 5 | Admin CTA | AlertService | **MOCK** — hardcoded, links are live |
| 6 | Permissions | Permits by status | **MOCK** — hardcoded array |
| 7 | Work Breakdown | Work items by type | **MOCK** — hardcoded array + `$summaryStats` |

---

## Responsive Behavior

| Breakpoint | Behavior |
|---|---|
| **Desktop (lg+)** | 2-column chart grid. 3-column CTA grid. Slide-out panels on right edge. |
| **Tablet (md–lg)** | 2-column chart grid. 3-column CTA grid. Slide-out panels on right edge. |
| **Mobile (<md)** | Single column, all cards stack. Charts become accordion-collapsible. Permissions + Work Breakdown render as inline accordions (no slide-out). |

## Files

| File | Role |
|---|---|
| `app/Livewire/Dashboard/Overview.php` | Livewire component — `systemMetrics`, `regionalMetrics` computed properties |
| `resources/views/livewire/dashboard/overview.blade.php` | Main view — all mock data, charts, CTA cards |
| `resources/views/livewire/dashboard/_permissions-body.blade.php` | Permissions panel/accordion body |
| `resources/views/livewire/dashboard/_quick-stats-body.blade.php` | Work Breakdown panel/accordion body |
| `resources/views/components/ui/slide-out-panel.blade.php` | Reusable slide-out panel component (tab + body) |

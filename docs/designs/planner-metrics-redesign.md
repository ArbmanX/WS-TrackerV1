# Design Spec: Planner Metrics Page Redesign

**Created:** 2026-02-16
**Status:** draft
**Branch:** `feature/planner-metrics-redesign`

## Design Direction

"Clean Slate" — Modern SaaS dashboard aesthetic. Constrained single-column layout, unified quota + health view, inline accordion for circuit details. No drawer. No coaching messages.

### Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Layout | Single-column, `max-w-5xl mx-auto` | Focused reading flow, avoids stretching data across wide screens |
| Quota/Health toggle | **Removed** — unified view | Both datasets come from the same service calls; splitting forces context switching |
| Period selector | **Week only** + navigation arrows | Simplifies controls; most users check weekly |
| Circuit detail | **Accordion** (one open at a time) | Replaces side drawer; keeps context inline, avoids z-index conflicts with app shell |
| Coaching messages | **Removed** from this view | Reduces card height, cleaner aesthetic |
| Summary strip | **4 stat cards** across top | Team-level metrics at a glance |
| Sort | Keep A-Z / Needs Attention toggle | Still useful for scanning |

---

## Visual Spec

### Page Structure

```
┌──────────────────── max-w-5xl mx-auto ────────────────────┐
│                                                            │
│  ┌─ Header ──────────────────────────────────────────────┐ │
│  │  Planner Metrics                                      │ │
│  │  Weekly planner performance overview                  │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                            │
│  ┌─ Stat Cards Row ─────────────────────────────────────┐  │
│  │ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ │  │
│  │ │ On Track │ │ Team Avg │ │  Aging   │ │  Miles   │ │  │
│  │ │   5/8    │ │   74%    │ │   813    │ │  37.3    │ │  │
│  │ │ planners │ │ attainmt │ │  units   │ │ this wk  │ │  │
│  │ └──────────┘ └──────────┘ └──────────┘ └──────────┘ │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                            │
│  ┌─ Controls ────────────────────────────────────────────┐ │
│  │  ◀  Feb 8 – Feb 14, 2026  ▶     [A-Z] [Attention]   │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                            │
│  ┌─ Planner List ────────────────────────────────────────┐ │
│  │                                                        │ │
│  │  ┌─ Planner Row ────────────────────────────────────┐ │ │
│  │  │  ● amiller                    6wk streak   2 ▾  │ │ │
│  │  │  ████████████████████░░░  7.8 / 6.5 mi  119.7%  │ │ │
│  │  │  31 aging · 68.5% complete · Edit 3d ago         │ │ │
│  │  ├─ Accordion (expanded) ───────────────────────────┤ │ │
│  │  │  ┌─ Circuit ──────┐  ┌─ Circuit ──────┐         │ │ │
│  │  │  │ LETORT 69/12   │  │ QUARRYVILLE    │         │ │ │
│  │  │  │ Lancaster  37% │  │ Lancaster 100% │         │ │ │
│  │  │  │ ████░░ 9.7mi   │  │ ██████ 57.8mi  │         │ │ │
│  │  │  │ ●12 ●8 ●3      │  │ ●45 ●2         │         │ │ │
│  │  │  └────────────────┘  └────────────────┘         │ │ │
│  │  └──────────────────────────────────────────────────┘ │ │
│  │                                                        │ │
│  │  ┌─ Planner Row (collapsed) ────────────────────────┐ │ │
│  │  │  ● jfarh                                    1 ▾  │ │ │
│  │  │  ░░░░░░░░░░░░░░░░░  0 / 6.5 mi         0.2%    │ │ │
│  │  │  0 aging · 46% complete · Edit 8d ago            │ │ │
│  │  └──────────────────────────────────────────────────┘ │ │
│  │                                                        │ │
│  │  ... more planners ...                                 │ │
│  │                                                        │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                            │
│  Last updated: Feb 16, 2026 1:05 AM                        │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

### Component Details

#### 1. Stat Cards Row

Four `stat` cards (DaisyUI `stats` component) in a horizontal row. These aggregate across ALL planners.

| Card | Value | Description | Color Logic |
|------|-------|-------------|-------------|
| On Track | `5/8` | Planners with `status === 'success'` / total | `text-success` if >50%, `text-warning` if >25%, `text-error` otherwise |
| Team Avg | `74%` | Average `percent_complete` (quota) across all planners | Same thresholds |
| Aging Units | `813` | Sum of `pending_over_threshold` across all planners | `text-success` if 0, `text-warning` if <100, `text-error` if >=100 |
| Team Miles | `37.3` | Sum of `period_miles` across all planners | Neutral — `text-base-content` |

On mobile (`< sm`): 2x2 grid. On desktop: single row.

#### 2. Controls Row

Single horizontal row with:
- **Left:** Period navigation — `◀` button, period label (e.g., "Feb 8 – Feb 14, 2026"), `▶` button
- **Right:** Sort toggle — DaisyUI `join` buttons `[A-Z | Needs Attention]`

Clean, compact. No view toggle (Quota/Health). No period type selector (Week/Month/Year).

#### 3. Planner Row (Card)

Each planner is a single card with a **left accent border** (existing pattern, keep it):

```blade
<div class="card bg-base-100 shadow-sm border-l-4 border-l-{status}">
```

**Row 1 — Header:**
- Left: Status dot (colored circle) + planner display name (bold)
- Right: Streak badge (if >= 1 week, show `badge badge-sm badge-success`) + Circuit count button (`N ▾` — triggers accordion)

**Row 2 — Quota Progress:**
- Full-width DaisyUI `progress` bar (colored by status)
- Below bar: `period_miles` (large, bold, tabular-nums) + `/ quota_target mi` (muted) + `percent_complete%` (right-aligned, medium weight)

**Row 3 — Health Indicators:**
- Inline text row: `{pending_over_threshold} aging` (colored) · `{percent_complete}% complete` · `Edit {days_since_last_edit}d ago` (colored)
- Aging count color: success (0), warning (<5), error (>=5)
- Edit staleness color: success (<3d), base-content (<7d), warning (<14d), error (>=14d)

**No coaching message.** Removed per user request.

#### 4. Accordion (Circuit Detail)

When user clicks the circuit count button (`N ▾`), the section below the card expands to show circuit details.

**Behavior:**
- Only one accordion open at a time — opening another auto-closes the previous
- Smooth transition: `x-show` + `x-collapse` (Alpine.js collapse plugin) or CSS `collapse` transition
- Chevron rotates on open (`▾` → `▴`)

**Accordion Content:**
- 2-column grid of circuit cards (`grid grid-cols-1 sm:grid-cols-2 gap-3`)
- Each circuit card (same as current drawer design, compact):
  - Line name (bold) + region (muted)
  - Progress bar (`progress progress-primary h-1.5`)
  - `completed_miles / total_miles mi` + `percent_complete%`
  - Permission badges row (existing badge pattern)
- Empty state: "No active circuits" with map icon (same as current drawer)

**Livewire state:**
- `public ?string $expandedPlanner = null` (replaces `$drawerPlanner`)
- `toggleAccordion(string $username)` — if same user, close; if different, switch
- `#[Computed] expandedCircuits(): array` — same logic as current `drawerCircuits()`

#### 5. Permission Badges (in accordion circuit cards)

Keep existing pattern — small colored badges with counts:
```
●12 Approved (badge-success)
●8  Pending (badge-warning)
●3  No Contact (badge-info)
●2  Refused (badge-error)
```

---

## Data Architecture

### Unified Metrics Approach

Currently the service has separate `getQuotaMetrics()` and `getHealthMetrics()` methods. The unified view needs data from both.

**Recommended approach:** Add a `getUnifiedMetrics(int $offset = 0): array` method that returns:

```php
[
    'username' => string,
    'display_name' => string,
    // Quota fields
    'period_miles' => float,
    'quota_target' => float,
    'quota_percent' => float,      // renamed from percent_complete to avoid collision
    'streak_weeks' => int,
    'gap_miles' => float,
    // Health fields
    'days_since_last_edit' => int|null,
    'pending_over_threshold' => int,
    'permission_breakdown' => array,
    'total_miles' => float,
    'overall_percent' => float,    // overall circuit completion
    'active_assessment_count' => int,
    // Shared
    'status' => string,
    'circuits' => list<array>,
]
```

**Why this works:** `getQuotaMetrics()` already calls `resolveHealthSignal()` internally, which fetches `pending_over_threshold`, `permission_breakdown`, `total_miles`, and overall `percent_complete`. These values are computed but just not included in the quota return array. We just need to pass them through.

### Summary Stats (Computed)

Derived from the unified planner array in a Livewire `#[Computed]` property:

```php
#[Computed]
public function summaryStats(): array
{
    $planners = $this->planners;
    $total = count($planners);
    $onTrack = collect($planners)->where('status', 'success')->count();

    return [
        'on_track' => $onTrack,
        'total_planners' => $total,
        'team_avg_percent' => $total ? round(collect($planners)->avg('quota_percent'), 1) : 0,
        'total_aging' => collect($planners)->sum('pending_over_threshold'),
        'total_miles' => round(collect($planners)->sum('period_miles'), 1),
    ];
}
```

---

## What Gets Removed

| Current Feature | Action |
|-----------------|--------|
| Quota/Health view toggle | **Removed** — unified view |
| Period type selector (Week/Month/Year/Scope) | **Removed** — week only |
| `$cardView` URL parameter | **Removed** |
| `$period` URL parameter | **Removed** (hardcoded to `'week'`) |
| Side drawer (backdrop + panel) | **Removed** — replaced by accordion |
| `_coaching-message.blade.php` include | **Not rendered** (partial kept, just not used) |
| `$drawerPlanner` property | **Replaced** by `$expandedPlanner` |
| `CoachingMessageGeneratorInterface` dependency | **Removed** from component |
| `_health-card.blade.php` partial | **Removed** — unified row replaces both |
| `_quota-card.blade.php` partial | **Removed** — unified row replaces both |

## What Gets Added

| New Element | File |
|-------------|------|
| `_planner-row.blade.php` | New partial — unified planner row with accordion |
| `_stat-cards.blade.php` | New partial — 4 summary stat cards |
| `_circuit-accordion.blade.php` | New partial — accordion content (circuits grid) |
| `getUnifiedMetrics()` | New method on service + interface |
| `summaryStats` computed | New computed property on Overview component |

## Files Modified

| File | Changes |
|------|---------|
| `overview.blade.php` | Complete rewrite — new layout structure |
| `Overview.php` | Remove view/period toggle, add unified metrics, summary stats, accordion state |
| `PlannerMetricsService.php` | Add `getUnifiedMetrics()` method |
| `PlannerMetricsServiceInterface.php` | Add `getUnifiedMetrics()` signature |
| `OverviewTest.php` | Update for new component behavior |
| `OverviewDrawerTest.php` | **Rename/rewrite** as `OverviewAccordionTest.php` |
| `PlannerMetricsServiceTest.php` | Add tests for `getUnifiedMetrics()` |

## Files Removed

| File | Reason |
|------|--------|
| `_quota-card.blade.php` | Replaced by `_planner-row.blade.php` |
| `_health-card.blade.php` | Replaced by `_planner-row.blade.php` |
| `_circuit-drawer.blade.php` | Replaced by `_circuit-accordion.blade.php` |

---

## Mobile Behavior

- Stat cards: 2x2 grid on `< sm`, 4-column row on `sm+`
- Controls: Stack vertically on `< sm` (period nav above, sort below)
- Planner rows: Full width, single column (no change needed)
- Accordion circuits: Single column on `< sm`, 2-column on `sm+`
- Progress bar and metrics: Full width, numbers wrap naturally

---

## Implementation Order

1. **Service layer** — Add `getUnifiedMetrics()` to interface + service
2. **Service tests** — Test unified return structure
3. **Component** — Rewrite `Overview.php` (remove view/period, add accordion + summary)
4. **Views** — Create new partials, rewrite `overview.blade.php`
5. **Component tests** — Update/rewrite `OverviewTest.php` + accordion tests
6. **Cleanup** — Delete old partials, remove unused drawer test file

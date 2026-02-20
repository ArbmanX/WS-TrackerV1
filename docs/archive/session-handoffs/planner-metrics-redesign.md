# Session Handoff: Planner Metrics Redesign
Check the ../wip.md file
**Created:** 2026-02-16
**Design spec:** `docs/designs/planner-metrics-redesign.md`
**Branch to create:** `feature/planner-metrics-redesign`
**Base branch:** merge `feature/planner-metrics-circuit-drawer` to main first (has uncommitted work)

## What to Build

Redesign the Planner Metrics page from a card-grid with Quota/Health toggle to a **unified single-column layout with inline accordion** for circuit details.

### Key Changes

1. **Unified view** — remove Quota/Health toggle, show both per planner in one row
2. **Week-only** — remove period type selector (Week/Month/Year/Scope), keep navigation arrows
3. **Stat cards** — 4 DaisyUI stat cards at top (On Track, Team Avg, Aging Units, Team Miles)
4. **Accordion** — replaces side drawer for circuit details, one open at a time
5. **No coaching messages** — removed from this view
6. **Constrained width** — `max-w-5xl mx-auto`, single column

### Implementation Order

1. **Service layer** — Add `getUnifiedMetrics(int $offset = 0)` to `PlannerMetricsServiceInterface` + `PlannerMetricsService`
   - Merges quota fields (`period_miles`, `quota_target`, `quota_percent`, `streak_weeks`, `gap_miles`) with health fields (`pending_over_threshold`, `permission_breakdown`, `total_miles`, `overall_percent`)
   - Data already available — `getQuotaMetrics()` already calls `resolveHealthSignal()` internally, just need to pass all fields through
   - Rename `percent_complete` to `quota_percent` (quota attainment) and add `overall_percent` (circuit completion) to avoid collision

2. **Service tests** — Add tests for `getUnifiedMetrics()` return structure in `PlannerMetricsServiceTest.php`

3. **Livewire component** — Rewrite `Overview.php`:
   - Remove: `$cardView`, `$period` properties (and their URL bindings)
   - Remove: `switchView()`, `switchPeriod()` methods
   - Remove: `coachingMessages` computed, `drawerPlanner`/`drawerCircuits`/`drawerDisplayName`
   - Add: `$expandedPlanner` property
   - Add: `toggleAccordion(string $username)` — if same user close, if different switch
   - Add: `summaryStats` computed (aggregates from planner data)
   - Add: `expandedCircuits` computed (same logic as old `drawerCircuits`)
   - Change: `planners` computed calls `getUnifiedMetrics()` instead of quota/health
   - Hardcode period to `'week'`

4. **Blade views** — Rewrite `overview.blade.php` + new partials:
   - `overview.blade.php` — new layout: header, stat cards, controls row, planner list
   - `_stat-cards.blade.php` (NEW) — 4 stat cards from `$this->summaryStats`
   - `_planner-row.blade.php` (NEW) — unified row with accordion trigger
   - `_circuit-accordion.blade.php` (NEW) — circuit grid, reuses existing circuit card design
   - DELETE: `_quota-card.blade.php`, `_health-card.blade.php`, `_circuit-drawer.blade.php`

5. **Tests** — Update/rewrite:
   - `OverviewTest.php` — update for new component behavior (no view toggle, no period selector)
   - Rename `OverviewDrawerTest.php` → `OverviewAccordionTest.php` — test accordion open/close/switch
   - `PlannerMetricsServiceTest.php` — add `getUnifiedMetrics()` tests

6. **Cleanup** — delete old partials, verify no references remain

### Visual Reference

```
┌───────────── max-w-5xl mx-auto ──────────────┐
│ Planner Metrics                               │
│                                               │
│ [On Track 5/8] [Avg 74%] [813 aging] [37 mi] │
│                                               │
│ ◀  Feb 8 – Feb 14, 2026  ▶    [A-Z | Attn]  │
│                                               │
│ ┌─ amiller ────────── 6wk  2 ▾ ─────────────┐│
│ │ ████████████████░░  7.8/6.5 mi  119.7%     ││
│ │ 31 aging · 68.5% complete · Edit 3d ago    ││
│ ├─ Circuits (expanded) ─────────────────────┤││
│ │ [LETORT 37%] [QUARRYVILLE 100%]           │││
│ └────────────────────────────────────────────┘│
│                                               │
│ ┌─ jfarh ───────────────────────── 1 ▾ ─────┐│
│ │ ░░░░░░░░░░░░░░░░  0/6.5 mi  0.2%         ││
│ │ 0 aging · 46% complete · Edit 8d ago      ││
│ └────────────────────────────────────────────┘│
└───────────────────────────────────────────────┘
```

### Patterns to Follow

- **Factory states:** `withWorkStudio()`, `withRole('role-name')`, `onboarded()`
- **Test setup:** `$this->seed(RolePermissionSeeder::class)` for permission-gated routes
- **Career fixtures:** `writeCareerFixture()` helper + `config()->set('planner_metrics.career_json_path', $tmpDir)`
- **DaisyUI only:** theme variables, never hardcoded colors
- **Accordion state:** Alpine.js `x-show` + `x-collapse` for smooth transitions, Livewire `$expandedPlanner` for server state

### Files to Read First

- `docs/designs/planner-metrics-redesign.md` — full visual spec with all details
- `app/Services/PlannerMetrics/PlannerMetricsService.php` — current service (see `resolveHealthSignal()` for health data already available in quota path)
- `app/Livewire/PlannerMetrics/Overview.php` — current component to rewrite

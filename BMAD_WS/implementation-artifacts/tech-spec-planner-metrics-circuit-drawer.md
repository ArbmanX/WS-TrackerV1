---
title: 'Planner Metrics Circuit Drawer'
slug: 'planner-metrics-circuit-drawer'
created: '2026-02-15'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4]
tech_stack: [Livewire 4, DaisyUI 5, Alpine.js, Tailwind v4, Pest 4]
files_to_modify:
  - app/Services/PlannerMetrics/PlannerMetricsService.php
  - app/Services/PlannerMetrics/Contracts/PlannerMetricsServiceInterface.php
  - app/Livewire/PlannerMetrics/Overview.php
  - resources/views/livewire/planner-metrics/overview.blade.php
  - resources/views/livewire/planner-metrics/_quota-card.blade.php
  - resources/views/livewire/planner-metrics/_health-card.blade.php
  - resources/views/livewire/planner-metrics/_circuit-drawer.blade.php (NEW)
  - tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php
  - tests/Feature/PlannerMetrics/OverviewDrawerTest.php (NEW)
code_patterns:
  - 'DaisyUI drawer with drawer-end + drawer-open class toggle'
  - 'Livewire computed properties for derived drawer data'
  - 'AssessmentMonitor::latest_snapshot JSON column for circuit details'
  - 'wire:click on card edge triggers drawer open'
test_patterns:
  - 'Pest 4 with RefreshDatabase'
  - 'AssessmentMonitor::factory()->withSnapshots() for snapshot data'
  - 'PlannerJobAssignment factory for planner-to-GUID mapping'
  - 'Livewire::test() for component assertions'
  - 'writeCareerFixture() helper for JSON career files'
---

# Tech-Spec: Planner Metrics Circuit Drawer

**Created:** 2026-02-15

## Overview

### Problem Statement

Planner metrics cards display an `active_assessment_count` (jobs) number but provide no way to see which circuits make up that count. Users must leave the dashboard to investigate which active assessments belong to a given planner.

### Solution

Add a DaisyUI `drawer drawer-end` component at the page level. When a planner card's right-edge trigger is clicked, the drawer slides in showing that planner's active circuits with medium detail. Data piggybacks on existing `AssessmentMonitor` records already fetched by `resolveHealthSignal()` — no new API calls needed.

### Scope

**In Scope:**
- Clickable trigger area on the right edge of each card
- DaisyUI page-level drawer (`drawer-end`), toggled via Livewire `$drawerPlanner` property
- Each circuit row shows: line name, region, completed miles / total miles, percent complete progress bar, permission breakdown badges
- Data source: `AssessmentMonitor` records matching `PlannerJobAssignment` GUIDs with `current_status = 'ACTIV'`
- Works from both Quota and Health card views
- Read-only display
- Empty state when planner has zero active circuits

**Out of Scope:**
- Circuit drill-down / navigation to detail pages
- Filtering or sorting within the drawer
- Any write actions on circuits
- Rich detail (daily activity, station-level data, `CircuitQueries` deep fetch)

## Context for Development

### Codebase Patterns

- **Livewire 4:** Computed properties (`#[Computed]`), URL-bound state (`#[Url]`), `wire:click` actions
- **DaisyUI v5 drawer:** `drawer drawer-end` wrapper, `drawer-content` for page, `drawer-side` for panel, `drawer-overlay` for backdrop. `drawer-open` class forces drawer visible (no checkbox needed when using Livewire state)
- **AssessmentMonitor model:** `latest_snapshot` JSON column contains `permission_breakdown`, `footage`, `planner_activity`, `aging_units`, `unit_counts`
- **PlannerJobAssignment model:** `forNormalizedUser($username)` scope maps planner → job GUIDs
- **Card partials:** `_quota-card.blade.php` (wrapped in `<a>` tag) and `_health-card.blade.php` (plain `<div>`) both receive `$planner` array
- **Permission badge pattern:** Already implemented in health card — reuse `$permissionIcons` map for drawer

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `app/Livewire/PlannerMetrics/Overview.php` | Main component — add drawer state + computed |
| `app/Services/PlannerMetrics/PlannerMetricsService.php` | Service — modify `resolveHealthSignal()` to include circuit details |
| `app/Services/PlannerMetrics/Contracts/PlannerMetricsServiceInterface.php` | Interface — add `circuits` key to return type docblocks |
| `resources/views/livewire/planner-metrics/overview.blade.php` | Main template — wrap in drawer layout |
| `resources/views/livewire/planner-metrics/_quota-card.blade.php` | Quota card — add trigger, change `<a>` to `<div>` |
| `resources/views/livewire/planner-metrics/_health-card.blade.php` | Health card — add trigger |
| `app/Models/AssessmentMonitor.php` | Model with `latest_snapshot` cast + `scopeActive()` |
| `app/Models/PlannerJobAssignment.php` | Model with `forNormalizedUser()` scope |
| `database/factories/AssessmentMonitorFactory.php` | Factory with `withSnapshots()` state |
| `tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php` | Existing tests — add circuit data assertions |

### Technical Decisions

1. **Page-level DaisyUI drawer, not per-card panel** — DaisyUI drawer is a grid layout component designed for page-level sidebars. Using one drawer with dynamic content (set by clicked card) is cleaner than custom Alpine panels per card.
2. **`drawer-open` class toggle via Livewire** — When `$drawerPlanner` is non-null, the drawer gets `drawer-open` class. No checkbox toggle needed. Overlay click calls `wire:click="closeDrawer"`.
3. **Eager data loading** — `resolveHealthSignal()` already fetches `AssessmentMonitor` records per planner. Adding a `circuits` array to its return piggybacks on existing queries with zero additional DB calls. Circuit details come from the `$monitors` collection that's already in memory.
4. **Quota card `<a>` → `<div>`** — The quota card is currently wrapped in `<a href="#">` which conflicts with `wire:click` on a child trigger. Change to `<div>` with same styling since the link goes nowhere.
5. **Shared permission badge map** — Extract the `$permissionIcons` map from `_health-card.blade.php` into the drawer partial. Both partials can define it independently (small duplication is fine for blade partials).

## Implementation Plan

### Tasks

- [x] **Task 1: Add `circuits` array to `resolveHealthSignal()` return**
  - File: `app/Services/PlannerMetrics/PlannerMetricsService.php`
  - Action: Before the `foreach ($monitors as $monitor)` aggregation loop, build a `$circuits` array by mapping each monitor to its display data:
    ```php
    $circuits = $monitors->map(fn ($monitor) => [
        'job_guid' => $monitor->job_guid,
        'line_name' => $monitor->line_name,
        'region' => $monitor->region,
        'total_miles' => (float) $monitor->total_miles,
        'completed_miles' => $monitor->latest_snapshot['footage']['completed_miles'] ?? 0,
        'percent_complete' => $monitor->latest_snapshot['footage']['percent_complete'] ?? 0,
        'permission_breakdown' => $monitor->latest_snapshot['permission_breakdown'] ?? [],
    ])->values()->all();
    ```
  - Add `'circuits' => $circuits` to the populated return array
  - **Also update the empty default return** (line ~264 of service) to include `'circuits' => []`:
    ```php
    if ($monitors->isEmpty()) {
        return [
            'days_since_last_edit' => null,
            'pending_over_threshold' => 0,
            'permission_breakdown' => [],
            'total_miles' => 0,
            'percent_complete' => 0,
            'active_assessment_count' => 0,
            'circuits' => [],
        ];
    }
    ```
  - Notes: The `$monitors` collection is already fetched and in memory — this adds zero DB queries

- [x] **Task 1b: Update `PlannerMetricsServiceInterface` return type docblocks**
  - File: `app/Services/PlannerMetrics/Contracts/PlannerMetricsServiceInterface.php`
  - Action: Add `circuits` key to the `@return` docblock for both `getQuotaMetrics()` and `getHealthMetrics()`:
    ```php
    // In getQuotaMetrics() @return:
    *     circuits: list<array{job_guid: string, line_name: string, region: string, total_miles: float, completed_miles: float, percent_complete: float, permission_breakdown: array<string, int>}>,

    // In getHealthMetrics() @return:
    *     circuits: list<array{job_guid: string, line_name: string, region: string, total_miles: float, completed_miles: float, percent_complete: float, permission_breakdown: array<string, int>}>,
    ```
  - Notes: Maintains contract consistency — IDE autocomplete and static analysis stay correct

- [x] **Task 2: Add drawer state and computed properties to Overview component**
  - File: `app/Livewire/PlannerMetrics/Overview.php`
  - Action: Add public property and methods:
    ```php
    public ?string $drawerPlanner = null;

    public function openDrawer(string $username): void
    {
        $this->drawerPlanner = $username;
    }

    public function closeDrawer(): void
    {
        $this->drawerPlanner = null;
    }

    #[Computed]
    public function drawerCircuits(): array
    {
        if (! $this->drawerPlanner) {
            return [];
        }

        $planner = collect($this->planners)->firstWhere('username', $this->drawerPlanner);

        return $planner['circuits'] ?? [];
    }

    #[Computed]
    public function drawerDisplayName(): string
    {
        if (! $this->drawerPlanner) {
            return '';
        }

        $planner = collect($this->planners)->firstWhere('username', $this->drawerPlanner);

        return $planner['display_name'] ?? $this->drawerPlanner;
    }
    ```
  - **Update `clearCache()` method** to also reset drawer state and unset drawer computed properties:
    ```php
    private function clearCache(): void
    {
        unset($this->planners, $this->coachingMessages, $this->periodLabel, $this->resolvedOffset);
        unset($this->drawerCircuits, $this->drawerDisplayName);
        $this->drawerPlanner = null;
    }
    ```
  - This ensures AC 7 is satisfied: switching views/periods/sorts automatically closes the drawer and clears stale data
  - Notes: Computed props derive from existing `planners()` computed — no additional queries

- [x] **Task 3: Wrap overview template in DaisyUI drawer layout**
  - File: `resources/views/livewire/planner-metrics/overview.blade.php`
  - Action: Wrap entire template content in drawer structure:
    ```blade
    <div @class(['drawer drawer-end', 'drawer-open' => $drawerPlanner])>
        <div class="drawer-content">
            {{-- existing page content (header, controls, grid, footer) --}}
        </div>
        <div class="drawer-side z-50">
            <div class="drawer-overlay" wire:click="closeDrawer"></div>
            <div class="bg-base-100 min-h-full w-96 p-6 shadow-lg">
                @if($drawerPlanner)
                    @include('livewire.planner-metrics._circuit-drawer', [
                        'circuits' => $this->drawerCircuits,
                        'plannerName' => $this->drawerDisplayName,
                    ])
                @endif
            </div>
        </div>
    </div>
    ```
  - Notes: `z-50` ensures drawer overlays both the loading spinner (`z-10`) and the app-shell sidebar (`z-40`). `w-96` = 384px. `drawer-open` class is conditionally applied based on Livewire state.

- [x] **Task 4: Add drawer trigger to quota card**
  - File: `resources/views/livewire/planner-metrics/_quota-card.blade.php`
  - Action:
    1. Change outer `<a href="#">` to `<div>` (keeps all existing `@class` directives)
    2. Wrap in a flex container with the drawer trigger on the right:
    ```blade
    <div class="flex">
        <div {{-- existing card with all classes --}}>
            {{-- existing card-body content unchanged --}}
        </div>
        <button
            type="button"
            wire:click="openDrawer('{{ $planner['username'] }}')"
            class="flex items-center px-1.5 rounded-r-lg bg-base-200/50 hover:bg-primary/10 transition-colors"
            title="View {{ $planner['display_name'] }}'s circuits"
        >
            <x-heroicon-m-chevron-right class="size-4 text-base-content/40" />
        </button>
    </div>
    ```
  - Notes: The card itself keeps all existing styling. The trigger is a thin vertical strip on the right with a chevron icon.

- [x] **Task 5: Add drawer trigger to health card**
  - File: `resources/views/livewire/planner-metrics/_health-card.blade.php`
  - Action: Same pattern as Task 4 — wrap in flex container, add trigger button on right edge
  - Notes: Health card is already a `<div>`, no tag change needed. Same trigger markup.

- [x] **Task 6: Create circuit drawer partial**
  - File: `resources/views/livewire/planner-metrics/_circuit-drawer.blade.php` (NEW)
  - Action: Create the drawer content template:
    ```blade
    @php
        $permissionIcons = [
            'Approved' => ['label' => 'Approved', 'class' => 'badge-success'],
            'Pending' => ['label' => 'Pending', 'class' => 'badge-warning'],
            'No Contact' => ['label' => 'No Contact', 'class' => 'badge-info'],
            'Refused' => ['label' => 'Refused', 'class' => 'badge-error'],
            'Deferred' => ['label' => 'Deferred', 'class' => 'badge-neutral'],
            'PPL Approved' => ['label' => 'PPL', 'class' => 'badge-success badge-outline'],
        ];
    @endphp

    {{-- Header (fixed) --}}
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">{{ $plannerName }}'s Circuits</h3>
        <button type="button" wire:click="closeDrawer" class="btn btn-sm btn-ghost btn-circle">
            <x-heroicon-m-x-mark class="size-4" />
        </button>
    </div>

    <p class="text-sm text-base-content/60 mb-4">
        {{ count($circuits) }} active {{ Str::plural('circuit', count($circuits)) }}
    </p>

    {{-- Circuit List --}}
    @forelse($circuits as $circuit)
        <div class="card card-compact bg-base-200 mb-3">
            <div class="card-body">
                <div class="flex items-start justify-between">
                    <div>
                        <h4 class="font-medium text-sm">{{ $circuit['line_name'] }}</h4>
                        <span class="text-xs text-base-content/60">{{ $circuit['region'] }}</span>
                    </div>
                    <span class="text-xs font-medium tabular-nums">
                        {{ $circuit['percent_complete'] }}%
                    </span>
                </div>

                <progress
                    class="progress progress-primary w-full h-1.5"
                    value="{{ min($circuit['percent_complete'], 100) }}"
                    max="100"
                ></progress>

                <div class="text-xs text-base-content/60">
                    {{ $circuit['completed_miles'] }} / {{ $circuit['total_miles'] }} mi
                </div>

                @if(!empty($circuit['permission_breakdown']))
                    <div class="flex flex-wrap gap-1">
                        @foreach($circuit['permission_breakdown'] as $status => $count)
                            @if($count > 0 && isset($permissionIcons[$status]))
                                <span
                                    class="badge badge-xs {{ $permissionIcons[$status]['class'] }}"
                                    title="{{ $permissionIcons[$status]['label'] }}"
                                >
                                    {{ $count }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center py-8 text-base-content/50">
            <x-heroicon-o-map class="size-8 mx-auto mb-2" />
            <p class="text-sm">No active circuits</p>
        </div>
    @endforelse
    ```

- [x] **Task 7: Add service tests for circuits in health signal**
  - File: `tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php`
  - Action: Add tests verifying `circuits` array is present and correctly structured:
    - Test `resolveHealthSignal` returns `circuits` key with correct count
    - Test each circuit has expected keys (`job_guid`, `line_name`, `region`, `total_miles`, `completed_miles`, `percent_complete`, `permission_breakdown`)
    - Test circuits array is empty when no active monitors exist
    - Test circuits array handles null `latest_snapshot` gracefully (defaults to 0/empty)
  - Notes: Use existing `writeCareerFixture()` helper + `AssessmentMonitor::factory()->withSnapshots()` + `PlannerJobAssignment` factory

- [x] **Task 8: Add Livewire component tests for drawer interaction**
  - File: `tests/Feature/PlannerMetrics/OverviewDrawerTest.php` (NEW)
  - Action: Test drawer behavior via `Livewire::test(Overview::class)`:
    - **State tests:**
      - Test `openDrawer` sets `$drawerPlanner` property
      - Test `closeDrawer` resets `$drawerPlanner` to null
      - Test `switchView` resets `$drawerPlanner` to null (AC 7)
      - Test `switchPeriod` resets `$drawerPlanner` to null (AC 7)
    - **Computed property tests:**
      - Test `drawerCircuits` computed returns circuits for selected planner with correct count
      - Test `drawerCircuits` returns empty array when no planner selected
      - Test `drawerDisplayName` returns correct name for selected planner
    - **Render tests (assert actual circuit data, not just component state):**
      - Test drawer renders circuit line name (`->assertSee('Circuit-1234')`)
      - Test drawer renders circuit region (`->assertSee('NORTH')`)
      - Test drawer renders miles (`->assertSee('/ 15.5 mi')`)
      - Test drawer renders "No active circuits" empty state when planner has zero monitors
      - Test drawer does NOT render when `$drawerPlanner` is null (`->assertDontSee("'s Circuits")`)
      - Test drawer renders `drawer-open` class when planner selected (`->assertSeeHtml('drawer-open')`)
  - Notes: Seed career fixtures + `AssessmentMonitor::factory()->withSnapshots()` + `PlannerJobAssignment` factory. Use `$this->seed(RolePermissionSeeder::class)` if route is permission-gated.

### Acceptance Criteria

- [ ] **AC 1:** Given a planner metrics card in Quota view, when I click the right-edge chevron trigger, then the drawer slides in from the right showing that planner's active circuits.
- [ ] **AC 2:** Given a planner metrics card in Health view, when I click the right-edge chevron trigger, then the same drawer slides in with that planner's circuits.
- [ ] **AC 3:** Given the drawer is open, when I click the overlay backdrop or the X button, then the drawer closes.
- [ ] **AC 4:** Given a planner with 3 active circuits, when I open their drawer, then I see 3 circuit cards each showing line name, region, miles progress, percent complete, and permission badges.
- [ ] **AC 5:** Given a planner with 0 active circuits, when I open their drawer, then I see an empty state message "No active circuits" with an icon.
- [ ] **AC 6:** Given a circuit with a null `latest_snapshot`, when the drawer renders, then the circuit row shows 0% complete, 0 miles, and no permission badges (no errors).
- [ ] **AC 7:** Given any drawer open, when I switch between Quota and Health view, then the drawer closes (state resets on view switch).

## Additional Context

### Dependencies

- **No new packages** — uses existing DaisyUI drawer, Livewire, heroicons
- **No new API calls** — data from existing `AssessmentMonitor` records already fetched by `resolveHealthSignal()`
- **No new models or migrations** — reads from existing `assessment_monitors` and `planner_job_assignments` tables

### Testing Strategy

**Unit/Feature Tests (Pest 4):**
- `PlannerMetricsServiceTest.php` — verify `circuits` key in `resolveHealthSignal()` output, correct structure, null-snapshot handling
- `OverviewDrawerTest.php` (new) — Livewire component tests for `openDrawer`/`closeDrawer` actions, computed property values, rendered output

**Manual Testing:**
1. Open planner metrics page in Quota view
2. Click a card's right-edge trigger → drawer should slide in
3. Verify circuit list matches the jobs count on the card
4. Verify each circuit shows line name, region, miles, progress bar, permission badges
5. Click overlay or X to close → drawer should close
6. Switch to Health view, repeat steps 2-5
7. Test with a planner that has 0 active circuits → empty state
8. Test drawer opens quickly (no loading delay — data is eager-loaded)

### Notes

- **Performance:** Circuit data is extracted from the `$monitors` collection already in memory during `resolveHealthSignal()`. No additional DB queries. For planners with many circuits (10-20), the drawer content is lightweight blade partials — no performance concern.
- **Future:** If users want drill-down into a circuit (clicking a circuit row → detail page), that's a clean extension point. Each circuit has `job_guid` ready for routing.
- **Drawer width:** `w-96` (384px) fits medium detail well. If users want more width later, a single class change handles it.
- **Mobile:** DaisyUI drawer handles mobile naturally — full-width overlay on small screens. The card trigger chevron is touch-friendly.

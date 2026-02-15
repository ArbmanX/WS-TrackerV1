---
title: 'Planner Metrics Dashboard — Phase 1 Overview Page'
slug: 'planner-metrics-overview'
created: '2026-02-15'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Laravel 12', 'Livewire 4', 'PostgreSQL', 'DaisyUI v5', 'Tailwind v4', 'Alpine.js', 'Pest 4']
files_to_modify:
  - resources/views/components/layout/sidebar.blade.php  # Add nav entry
  - routes/web.php                                       # Include new route file
  - routes/planner-metrics.php                           # NEW — route definitions
  - app/Livewire/PlannerMetrics/Overview.php             # NEW — page component
  - resources/views/livewire/planner-metrics/overview.blade.php  # NEW — page view
  - resources/views/livewire/planner-metrics/_quota-card.blade.php   # NEW — quota card partial
  - resources/views/livewire/planner-metrics/_health-card.blade.php  # NEW — health card partial
  - resources/views/livewire/planner-metrics/_coaching-message.blade.php  # NEW — coaching partial
  - app/Services/PlannerMetrics/Contracts/PlannerMetricsServiceInterface.php  # NEW — service interface
  - app/Services/PlannerMetrics/Contracts/CoachingMessageGeneratorInterface.php  # NEW — generator interface
  - app/Services/PlannerMetrics/PlannerMetricsService.php       # NEW — data aggregation
  - app/Services/PlannerMetrics/CoachingMessageGenerator.php    # NEW — coaching tips
  - app/Providers/PlannerMetricsServiceProvider.php             # NEW — service bindings
  - bootstrap/providers.php                                     # MODIFY — register provider
  - config/planner_metrics.php                           # NEW — thresholds, quota target
  - database/factories/AssessmentMonitorFactory.php      # FIX — permission_breakdown values
  - database/factories/PlannerCareerEntryFactory.php     # FIX — daily_metrics field names
  - database/factories/PlannerJobAssignmentFactory.php   # FIX — add domain prefix + normalized_username
  - database/migrations/xxxx_add_normalized_username_to_planner_job_assignments.php  # NEW — indexable username column
  - app/Models/PlannerJobAssignment.php                  # MODIFY — add scope for normalized_username
  - tests/Feature/PlannerMetrics/OverviewTest.php        # NEW — feature tests
  - tests/Unit/Services/PlannerMetricsServiceTest.php    # NEW — service unit tests
  - tests/Unit/Services/CoachingMessageGeneratorTest.php # NEW — generator unit tests
code_patterns:
  - 'Livewire #[Layout] + #[Url] + #[Computed] pattern (Dashboard/Overview.php)'
  - 'Sidebar nav array with section/items/permission structure'
  - 'Route file with middleware chain + prefix + name prefix'
  - 'PlannerCareerEntry scopes: forPlanner(), forScopeYear()'
  - 'AssessmentMonitor scopes: active(), forRegion()'
  - 'PlannerJobAssignment scopes: forUser()'
  - 'daily_metrics JSONB: FLAT ARRAY of objects, each with completion_date, daily_footage_miles (float), unit_count, stations[]'
  - 'latest_snapshot JSONB: permission_breakdown{}, footage{completed_miles, percent_complete}, planner_activity{days_since_last_edit}, aging_units{pending_over_threshold}'
test_patterns:
  - 'Pest 4 with uses(RefreshDatabase::class)'
  - 'Factory states for data setup (withSnapshots, inQc, inRework, etc.)'
  - 'Livewire::test(Component::class)->assertStatus(200)->assertSee()'
  - 'Service unit tests: create factory data, call service method, assert return structure'
---

# Tech-Spec: Planner Metrics Dashboard — Phase 1 Overview Page

**Created:** 2026-02-15

## Overview

### Problem Statement

Planners and supervisors have no structured way to track weekly footage progress or team health. Planners rely on gut feel ("I've been busy this week"), supervisors use ad-hoc check-ins or manual WorkStudio browsing. There is no centralized, quantified feedback loop for the 6.5 mi/week quota that planners are expected to meet.

The data already exists — `planner_career_entries` stores daily footage metrics in JSONB, `assessment_monitors` tracks active assessment health snapshots, and `planner_job_assignments` bridges planners to assessments. But none of this data is surfaced in the UI.

### Solution

Add a "Planner Metrics" section to the sidebar with an overview page displaying a card grid of all planners. Each card shows at-a-glance KPIs with two views:

- **Quota View** (default): Weekly miles vs. 6.5 target, progress bar, streak count, coaching message for behind-quota planners
- **Health View**: Units pending over threshold (count), days since last edit, permission breakdown, assessment progress

A page-level toggle switches ALL cards simultaneously between views. A period toggle (week/month/year/scope-year) controls the time window. Cards are sorted by "needs attention" (behind-quota first in quota view, most stale first in health view).

Data is served from local PostgreSQL via a new `PlannerMetricsService` (Eloquent-based). A `CoachingMessageGenerator` produces contextual tips referencing the planner's actual assessments.

### Scope

**In Scope:**
- Livewire page component with responsive card grid (handles 4-16 planners)
- Quota card blade partial (progress bar, streak, coaching message, status accent)
- Health card blade partial (staleness, aging units, permission badges, progress summary)
- Page-level view toggle (`#[Url] $cardView`) + period toggle (`#[Url] $period`)
- `PlannerMetricsService` — aggregates data from 3 tables
- `CoachingMessageGenerator` — produces contextual coaching tips
- Sidebar navigation entry (no permission gating initially)
- Route registration in new `planner-metrics.php` route file
- Pest tests (unit for service + generator, feature for route + component)

**Out of Scope:**
- Detail page (Phase 2 — separate spec)
- Leaderboards, rankings, gamification
- Custom Asplundh DaisyUI theme (separate task)
- Adding all 35 DaisyUI themes to config (separate task)
- Permission gating (will be added later)
- Push notifications or email alerts
- Export/print functionality

## Context for Development

### Codebase Patterns

**Livewire Page Components:**
- Use `#[Layout('components.layout.app-shell', ['title' => '...', 'breadcrumbs' => [...]])]`
- `#[Url]` properties for URL-persisted state
- `#[Computed]` properties for cached data queries
- Reference: `app/Livewire/Dashboard/Overview.php`

**Sidebar Navigation:**
- Array of sections with items in `resources/views/components/layout/sidebar.blade.php`
- Each item: `label`, `route`, `icon`, optional `permission`
- Permission gating: `auth()->user()?->can()` check

**Route Registration:**
- New route file in `routes/planner-metrics.php`
- Include in `routes/web.php` via `require`
- Middleware chain: `['auth', 'verified', 'onboarding']` (no permission for now)
- Prefix: `planner-metrics`, name prefix: `planner-metrics.`

**Service Layer:**
- Services in `app/Services/` with interface + concrete class
- Dedicated service provider for container bindings
- Livewire components resolve services via `app()` in `#[Computed]` methods (NOT constructor injection — Livewire 4 re-hydrates components on each request, so `__construct()` injection is not supported)
- Reference: `Dashboard/Overview.php` pattern — uses `app(CachedQueryService::class)` inline

**Blade Views:**
- Header row (title, description, action buttons)
- Stats/card grid using DaisyUI classes
- Loading overlays with `wire:loading.flex`
- DaisyUI semantic colors only, never hardcoded

**Testing:**
- Pest 4 with `RefreshDatabase` trait
- Factory states for data setup
- Feature tests for routes + Livewire component rendering
- Unit tests for service methods and generator logic

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `app/Livewire/Dashboard/Overview.php` | Reference Livewire page component pattern |
| `resources/views/livewire/dashboard/overview.blade.php` | Reference Blade view structure |
| `resources/views/components/layout/sidebar.blade.php` | Sidebar nav array to add new entry |
| `resources/views/components/layout/app-shell.blade.php` | Layout component for page wrapper |
| `routes/data-management.php` | Reference route file structure |
| `routes/web.php` | Where to include new route file |
| `app/Models/PlannerCareerEntry.php` | Model with scopes (forPlanner, forScopeYear) |
| `app/Models/AssessmentMonitor.php` | Model with scopes (active, forRegion) |
| `app/Models/PlannerJobAssignment.php` | Model with scopes (forUser, processed) |
| `database/factories/PlannerCareerEntryFactory.php` | Factory with daily_metrics generation |
| `database/factories/AssessmentMonitorFactory.php` | Factory for assessment health data |
| `config/ws_assessment_query.php` | Permission status values (lines 112-119) |
| `BMAD_WS/planning-artifacts/ux-design-specification.md` | Full UX design spec (1032 lines) |

### Data Structures (from deep investigation)

**`planner_career_entries.daily_metrics` JSONB (real export structure):**
```json
[
  {
    "completion_date": "2024-09-11",
    "FRSTR_USER": "ASPLUNDH\\jmartinez",
    "daily_footage_miles": 5.01,
    "unit_count": 41,
    "station_list": "10,100,110,...",
    "stations": [
      { "statname": "10", "span_length_ft": 185.8, "winning_unit": "SPB", "coords": { "lat": 40.01, "long": -76.04 } }
    ]
  }
]
```
**Key:** This is a **flat array** (NOT date-keyed). Each element has `completion_date` as a field. Iterate with `foreach ($metrics as $metric)` and access `$metric['daily_footage_miles']` and `$metric['completion_date']`. The `PlannerCareerLedgerService::mergeAndDeduplicateDailyMetrics()` calls `array_values()` which collapses any date-keying to sequential indices.

**Real data reference:** `storage/app/asplundh/planners/career/cnewcombe_2026-02-14.json`

**IMPORTANT — Factory mismatch:** `PlannerCareerEntryFactory.generateDailyMetrics()` currently generates date-keyed objects with wrong field names (`footage_feet`, `stations_completed`). Task 2b fixes this to produce a flat array matching the real format.

**`planner_career_entries.summary_totals` JSONB:**
```json
{
  "total_footage_feet": 65000.0,
  "total_footage_miles": 12.31,
  "total_stations": 280,
  "total_work_units": 350,
  "total_nw_units": 45,
  "working_days": 30,
  "avg_daily_footage_feet": 2166.7,
  "first_activity_date": "2024-09-11",
  "last_activity_date": "2024-11-15"
}
```

**`assessment_monitors.latest_snapshot` JSONB (from factory/service):**
```json
{
  "permission_breakdown": { "Approved": 30, "Pending": 8, "No Contact": 2, "Refused": 1, "Deferred": 0, "PPL Approved": 5 },
  "unit_counts": { "work_units": 55, "nw_units": 12, "total_units": 67 },
  "footage": { "completed_feet": 35000, "completed_miles": 6.63, "percent_complete": 72.5 },
  "planner_activity": { "last_edit_date": "2026-02-12", "days_since_last_edit": 3 },
  "aging_units": { "pending_over_threshold": 4, "threshold_days": 14 }
}
```
**Note:** Factory currently uses `Granted`/`Denied` — must be fixed to match config values.

**Planner-to-assessment bridge:**
- `planner_job_assignments.frstr_user` → `assessment_monitors.job_guid` (via `planner_job_assignments.job_guid`)
- **Username domain mismatch:** `planner_career_entries.planner_username` stores **stripped** usernames (e.g., `jsmith`) via `extractUsername()`. `planner_job_assignments.frstr_user` stores **domain-qualified** usernames (e.g., `ASPLUNDH\jsmith`). Task 2c adds a `normalized_username` column (indexed) to `planner_job_assignments` with the stripped value. The service uses: `PlannerJobAssignment::forNormalizedUser($username)->pluck('job_guid')` → then `AssessmentMonitor::query()->whereIn('job_guid', $guids)->active()`

**Config thresholds:**
- `ws_data_collection.thresholds.aging_unit_days` = 14 (existing)
- New config needed: `planner_metrics.quota_miles_per_week` = 6.5
- New config needed: `planner_metrics.staleness_warning_days` = 7
- New config needed: `planner_metrics.staleness_critical_days` = 14

### Technical Decisions

1. **No permission gating initially** — All authenticated+onboarded users can access. Permission added later.
2. **Dedicated `PlannerMetricsService`** — Service class for flexibility, not Eloquent queries in the component.
3. **Dedicated `CoachingMessageGenerator`** — Separate class for coaching tip logic, testable in isolation.
4. **New route file** — `routes/planner-metrics.php` follows the domain-split route pattern.
5. **No CachedQueryService** — Data is local PostgreSQL, not WS API. Standard Eloquent queries.
6. **Blade partials for card faces** — `_quota-card.blade.php` and `_health-card.blade.php` are stateless display partials.
7. **URL-driven state** — `$cardView` and `$period` are `#[Url]` properties. Bookmarkable, back-button friendly.

## Implementation Plan

### Tasks

#### Phase A: Foundation (no dependencies)

- [ ] **Task 1: Create config file**
  - File: `config/planner_metrics.php` (NEW)
  - Action: Create config with quota target, thresholds, and period definitions
  - Values:
    ```php
    'quota_miles_per_week' => 6.5,
    'staleness_warning_days' => 7,
    'staleness_critical_days' => 14,
    'periods' => ['week', 'month', 'year', 'scope-year'],
    'default_period' => 'week',
    'default_card_view' => 'quota',
    ```

- [ ] **Task 2a: Fix AssessmentMonitorFactory permission values**
  - File: `database/factories/AssessmentMonitorFactory.php`
  - Action: Replace `generateSnapshot()` permission_breakdown keys. Current keys: `Granted`, `Denied`, `Pending`, `Refused`, `Not Needed`. Replace with config-driven values:
    ```php
    'permission_breakdown' => [
        'Approved' => fake()->numberBetween(20, 50),
        'Pending' => fake()->numberBetween(5, 20),    // keep — already correct key
        'No Contact' => fake()->numberBetween(0, 5),
        'Refused' => fake()->numberBetween(0, 3),     // keep — already correct key
        'Deferred' => fake()->numberBetween(0, 2),
        'PPL Approved' => fake()->numberBetween(3, 15),
    ],
    ```
  - Reference: `config/ws_assessment_query.php` lines 112-119

- [ ] **Task 2b: Fix PlannerCareerEntryFactory daily_metrics field names**
  - File: `database/factories/PlannerCareerEntryFactory.php`
  - Action: Replace `generateDailyMetrics()` to produce a **flat array** matching the real export format:
    ```php
    private function generateDailyMetrics(\DateTimeInterface $startDate, int $days): array
    {
        $metrics = [];
        $date = CarbonImmutable::instance($startDate);

        for ($i = 0; $i < min($days, 10); $i++) {
            $metrics[] = [  // flat array, NOT date-keyed
                'completion_date' => $date->format('Y-m-d'),
                'FRSTR_USER' => 'ASPLUNDH\\' . fake()->userName(),
                'daily_footage_miles' => fake()->randomFloat(2, 0.5, 8.0),
                'unit_count' => fake()->numberBetween(5, 50),
                'station_list' => implode(',', range(10, 10 * fake()->numberBetween(3, 15), 10)),
                'stations' => [],  // empty for factory — full structure not needed for metric tests
            ];
            $date = $date->addDay();
        }

        return $metrics;
    }
    ```
  - Why: Current factory uses date-keyed objects with wrong field names (`footage_feet`, `stations_completed`). Real data is a flat array where each element has `completion_date` as a field. Service iterates by `$metric['completion_date']` and reads `$metric['daily_footage_miles']`.

- [ ] **Task 2c: Add `normalized_username` column to `planner_job_assignments`**
  - File: `database/migrations/xxxx_add_normalized_username_to_planner_job_assignments.php` (NEW)
  - Action: Add `normalized_username` string column (nullable, indexed) to `planner_job_assignments`. Backfill existing rows in PHP to avoid PostgreSQL backslash escaping issues:
    ```php
    // up()
    Schema::table('planner_job_assignments', function (Blueprint $table) {
        $table->string('normalized_username')->nullable()->after('frstr_user')->index();
    });
    // Backfill in PHP — avoids fragile SQL backslash escaping through PHP→PDO→PostgreSQL layers
    PlannerJobAssignment::whereNull('normalized_username')->each(function ($record) {
        $username = $record->frstr_user;
        $record->update([
            'normalized_username' => str_contains($username, '\\')
                ? substr($username, strrpos($username, '\\') + 1)
                : $username,
        ]);
    });
    ```
  - File: `app/Models/PlannerJobAssignment.php` (MODIFY)
  - Action: Add `normalized_username` to `$fillable`, add scope:
    ```php
    public function scopeForNormalizedUser(Builder $query, string $username): void
    {
        $query->where('normalized_username', $username);
    }
    ```
  - Why: Avoids `LIKE '%\\username'` full table scan. Import commands should also populate this column going forward (update `ws:import-career-ledger` to set `normalized_username` during import). Exact match with index.
  - File: `database/factories/PlannerJobAssignmentFactory.php` (MODIFY)
  - Action: Update factory to generate domain-qualified `frstr_user` and stripped `normalized_username`:
    ```php
    public function definition(): array
    {
        $username = fake()->userName();
        return [
            'frstr_user' => 'ASPLUNDH\\' . $username,
            'normalized_username' => $username,
            'job_guid' => '{' . Str::uuid()->toString() . '}',
            'status' => 'discovered',
            'discovered_at' => now(),
        ];
    }
    ```
  - Add `'normalized_username'` to `PlannerJobAssignment::$fillable`

- [ ] **Task 3: Create route file**
  - File: `routes/planner-metrics.php` (NEW)
  - Action: Create route file with:
    ```php
    Route::middleware(['auth', 'verified', 'onboarding'])
        ->prefix('planner-metrics')
        ->name('planner-metrics.')
        ->group(function () {
            Route::get('/', Overview::class)->name('overview');
        });
    ```
  - File: `routes/web.php` (MODIFY)
  - Action: Add `require __DIR__.'/planner-metrics.php';` after the existing require statements

- [ ] **Task 4: Add sidebar navigation entry**
  - File: `resources/views/components/layout/sidebar.blade.php`
  - Action: Add new section to `$navigation` array after the 'Dashboard' section:
    ```php
    [
        'section' => 'Planner Metrics',
        'items' => [
            [
                'label' => 'Overview',
                'route' => 'planner-metrics.overview',
                'icon' => 'users',
            ],
        ],
    ],
    ```
  - Notes: No `permission` key — all auth+onboarded users see it. Icon `users` from Heroicons.

#### Phase B: Service Layer (depends on Tasks 1, 2a, 2b)

- [ ] **Task 5: Create interfaces and service provider**
  - File: `app/Services/PlannerMetrics/Contracts/PlannerMetricsServiceInterface.php` (NEW)
  - File: `app/Services/PlannerMetrics/Contracts/CoachingMessageGeneratorInterface.php` (NEW)
  - File: `app/Providers/PlannerMetricsServiceProvider.php` (NEW)
  - Action: Create interfaces defining the public API for both services. Register bindings in provider:
    ```php
    // PlannerMetricsServiceProvider
    public function register(): void
    {
        $this->app->bind(PlannerMetricsServiceInterface::class, PlannerMetricsService::class);
        $this->app->bind(CoachingMessageGeneratorInterface::class, CoachingMessageGenerator::class);
    }
    ```
  - Register provider in `bootstrap/providers.php`

- [ ] **Task 6: Create PlannerMetricsService**
  - File: `app/Services/PlannerMetrics/PlannerMetricsService.php` (NEW)
  - Action: Create service implementing `PlannerMetricsServiceInterface` with three public methods:

  **`getQuotaMetrics(string $period = 'week'): array`**
  - Queries `PlannerCareerEntry` for all planners via `getDistinctPlanners()`
  - **Multi-entry aggregation:** A planner has multiple `planner_career_entries` rows (one per assessment). Sum `daily_footage_miles` across ALL entries for a given planner — do NOT filter by active/closed status. Historical entries contain completed footage that counts toward the planner's total output. Deduplication is not needed because each entry covers a different assessment with non-overlapping stations.
  - Computes period miles by iterating the flat array: `foreach ($entry->daily_metrics as $metric)`, filter by `$metric['completion_date']` within date window, sum `$metric['daily_footage_miles']`:
    - `week`: Monday (Carbon `startOfWeek()`) of current week → now
    - `month`: 1st of current month → now
    - `year`: Jan 1 of current year → now
    - `scope-year`: Use entries' `scope_year` column matching current scope year (sum ALL dates in matching entries)
  - **Streak algorithm (pseudocode):**
    ```
    streak = 0
    current_monday = today's Monday
    check_monday = current_monday - 1 week  // start from PRIOR completed week
    loop:
      week_miles = 0
      for each planner_career_entry:
        for each metric in entry.daily_metrics:  // flat array iteration
          if metric['completion_date'] in [check_monday, check_monday+6]:
            week_miles += metric['daily_footage_miles']
      if week_miles >= config quota → streak++, check_monday -= 1 week
      else → break
    return streak
    ```
    - Week = Monday 00:00 → Sunday 23:59 (ISO 8601)
    - Current (incomplete) week does NOT count toward streak
    - No data for a week = 0 miles = streak broken
  - Computes quota target for period: week=6.5, month=6.5*weeks_in_month, year=6.5*weeks_elapsed, scope-year=6.5*weeks_in_scope
  - **Also resolves health signal per planner** via shared private method:
    ```php
    /**
     * Shared by both getQuotaMetrics() and getHealthMetrics().
     * @return array{
     *   days_since_last_edit: int|null,
     *   pending_over_threshold: int,
     *   permission_breakdown: array<string, int>,
     *   total_miles: float,
     *   percent_complete: float,
     *   active_assessment_count: int,
     * }
     */
    private function resolveHealthSignal(string $username): array
    ```
    Queries `PlannerJobAssignment::forNormalizedUser($username)->pluck('job_guid')` → `AssessmentMonitor::whereIn('job_guid', $guids)->active()`. Aggregates across all active assessments: worst (max) `days_since_last_edit`, sum `pending_over_threshold`, sum `permission_breakdown` counts, sum miles. Returns null `days_since_last_edit` if no active assessments.
    `getQuotaMetrics()` uses `days_since_last_edit` and `active_assessment_count` from this. `getHealthMetrics()` uses all fields.
  - Returns array of planner data objects (unsorted — component handles sorting):
    ```php
    [
        [
            'username' => 'cnewcombe',
            'display_name' => 'C Newcombe',
            'period_miles' => 4.3,
            'quota_target' => 6.5,
            'percent_complete' => 66.2,
            'streak_weeks' => 4,
            'last_week_miles' => 5.8,         // prior completed week total (for coaching)
            'days_since_last_edit' => 4,      // from health signal (for quota card + coaching nudge)
            'active_assessment_count' => 3,
            'status' => 'warning', // 'success' | 'warning' | 'error'
            'gap_miles' => 2.2,
        ],
    ]
    ```
  - Status logic: `success` if miles >= target, `warning` if gap < 3 mi, `error` if gap >= 3 mi

  **`getHealthMetrics(): array`**
  - For each distinct planner from `getDistinctPlanners()` (canonical list from `planner_career_entries`):
    - **Username bridge:** Uses `PlannerJobAssignment::forNormalizedUser($username)->pluck('job_guid')` — exact match on indexed `normalized_username` column (added in Task 2c)
    - Get their active `assessment_monitors` via: `AssessmentMonitor::whereIn('job_guid', $guids)->active()`
    - Extract from `latest_snapshot`: `planner_activity.days_since_last_edit`, `aging_units.pending_over_threshold` (integer COUNT of units exceeding threshold, NOT days), `permission_breakdown`, `footage.completed_miles`, `footage.percent_complete`
    - Aggregate across all active assessments: worst (max) days_since_last_edit, sum pending_over_threshold, sum permission counts, sum miles
  - **Display name:** Use `planner_display_name` from `planner_career_entries` (populated during import)
  - Returns array (unsorted — component handles sorting):
    ```php
    [
        [
            'username' => 'cnewcombe',
            'display_name' => 'C Newcombe',
            'days_since_last_edit' => 4,
            'pending_over_threshold' => 6,   // COUNT of units pending > 14 days (NOT days)
            'permission_breakdown' => ['Approved' => 30, 'Pending' => 8, ...],
            'total_miles' => 45.2,
            'percent_complete' => 68.0,
            'active_assessment_count' => 3,
            'status' => 'warning', // based on staleness/aging thresholds
        ],
    ]
    ```
  - Status logic: `success` if days_since_last_edit < warning_days AND pending_over_threshold == 0, `warning` if days_since_last_edit >= warning_days OR pending_over_threshold > 0, `error` if days_since_last_edit >= critical_days OR pending_over_threshold >= 5

  **`getDistinctPlanners(): array`**
  - Returns unique `[username, display_name]` pairs from `planner_career_entries`
  - Used by both methods to ensure consistent planner list and canonical (stripped) usernames
  - Groups by `planner_username`, takes first `planner_display_name`

  - Notes: All thresholds from `config('planner_metrics.*')`. Date window computation uses Carbon. Period target scaling is config-driven.

- [ ] **Task 7: Create CoachingMessageGenerator**
  - File: `app/Services/PlannerMetrics/CoachingMessageGenerator.php` (NEW)
  - Action: Create generator implementing `CoachingMessageGeneratorInterface` with one public method:

  **`generate(array $plannerMetrics): ?string`**
  - `$plannerMetrics` is a single planner's entry from `getQuotaMetrics()` return array (includes `gap_miles`, `last_week_miles`, `streak_weeks`, `status`, `days_since_last_edit`)
  - Returns null if planner is on-pace (`status === 'success'` and `streak_weeks < 3`)
  - Message types based on conditions:

  | Condition | Type | Pattern |
  |-----------|------|---------|
  | `gap_miles > 0 AND gap_miles < 3` | `encouraging` | "You're {gap_miles} mi away — a strong day gets you there." |
  | `gap_miles >= 3` | `recovery` | "Last week you hit {last_week_miles} mi. Two strong days would close the gap." |
  | `gap_miles > 0 AND days_since_last_edit >= 4` | `nudge` | "Your last edit was {days_since_last_edit} days ago. Picking up where you left off?" |
  | `streak_weeks >= 3 AND status === 'success'` | `celebration` | "{streak_weeks} weeks on target! Keep the momentum going." |

  - Only return ONE message (priority: nudge > recovery > encouraging > celebration)
  - `last_week_miles` and `days_since_last_edit` are both available in the `$plannerMetrics` array (quota metrics now includes health signal)
  - Messages reference actual planner data, never generic templates
  - Notes: Stateless — all data passed in. Easy to test in isolation.

#### Phase C: UI Layer (depends on Tasks 3, 6, 7)

- [ ] **Task 8: Create Livewire page component**
  - File: `app/Livewire/PlannerMetrics/Overview.php` (NEW)
  - Action: Create Livewire component:
    ```php
    #[Layout('components.layout.app-shell', ['title' => 'Planner Metrics', 'breadcrumbs' => [['label' => 'Planner Metrics']]])]
    class Overview extends Component
    {
        #[Url]
        public string $cardView = 'quota';

        #[Url]
        public string $period = 'week';

        #[Url]
        public string $sortBy = 'alpha';  // 'alpha' | 'attention'

        public function mount(): void
        {
            // Validate URL parameters on initial page load (#[Url] sets values before mount)
            if (! in_array($this->cardView, ['quota', 'health'])) {
                $this->cardView = config('planner_metrics.default_card_view', 'quota');
            }
            if (! in_array($this->period, config('planner_metrics.periods', []))) {
                $this->period = config('planner_metrics.default_period', 'week');
            }
            if (! in_array($this->sortBy, ['alpha', 'attention'])) {
                $this->sortBy = 'alpha';
            }
        }

        #[Computed]
        public function planners(): array
        {
            $data = match ($this->cardView) {
                'health' => app(PlannerMetricsServiceInterface::class)->getHealthMetrics(),
                default => app(PlannerMetricsServiceInterface::class)->getQuotaMetrics($this->period),
            };

            return $this->sortPlanners($data);
        }

        #[Computed]
        public function coachingMessages(): array
        {
            if ($this->cardView !== 'quota') return [];
            $generator = app(CoachingMessageGeneratorInterface::class);
            return collect($this->planners)
                ->mapWithKeys(fn ($p) => [$p['username'] => $generator->generate($p)])
                ->filter()
                ->all();
        }

        public function switchView(string $view): void
        {
            if (! in_array($view, ['quota', 'health'])) return;
            $this->cardView = $view;
            $this->clearCache();
        }

        public function switchPeriod(string $period): void
        {
            if (! in_array($period, config('planner_metrics.periods', []))) return;
            $this->period = $period;
            $this->clearCache();
        }

        public function switchSort(string $sort): void
        {
            if (! in_array($sort, ['alpha', 'attention'])) return;
            $this->sortBy = $sort;
            $this->clearCache();
        }

        private function clearCache(): void
        {
            unset($this->planners, $this->coachingMessages);
        }

        private function sortPlanners(array $data): array
        {
            return match ($this->sortBy) {
                'attention' => match ($this->cardView) {
                    'health' => collect($data)->sortByDesc('days_since_last_edit')->values()->all(),
                    default => collect($data)->sortByDesc('gap_miles')->values()->all(),
                },
                default => collect($data)->sortBy('display_name')->values()->all(),  // alphabetical
            };
        }

        public function render(): View { /* returns overview view */ }
    }
    ```
  - Notes:
    - Services resolved via `app()` in `#[Computed]` — Livewire 4 does NOT support `__construct()` injection
    - `mount()` validates ALL `#[Url]` params on initial load (Livewire sets them before mount runs)
    - Default sort is **alphabetical** (supervisors develop spatial memory for card positions). "Sort by attention" toggle available for audit mode.
    - `clearCache()` helper centralizes computed invalidation (future-proof for additional computed properties)
    - Sorting is done in the component, not the service — service returns unsorted data

- [ ] **Task 9: Create overview Blade view**
  - File: `resources/views/livewire/planner-metrics/overview.blade.php` (NEW)
  - Action: Create page layout:
    - **Header row:** "Planner Metrics" title + description
    - **Controls row:** View toggle (`tabs tabs-boxed` or `join` — Quota | Health) + Period toggle (`join` — Week | Month | Year | Scope Year) + Sort toggle (`join` — A-Z | Needs Attention)
    - **Period toggle visibility:** Period toggle is only shown in quota view (`@if($cardView === 'quota')`). Health view has no time-window concept — it always shows current state.
    - **Sort toggle:** Always visible. Default "A-Z" (alphabetical). "Needs Attention" sorts by gap_miles desc (quota) or days_since_last_edit desc (health). `wire:click="switchSort('attention')"`. Persisted in URL via `$sortBy`.
    - **Card grid:** `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5 xl:gap-6`
    - **Cards:** Loop `$this->planners`, conditionally include `_quota-card` or `_health-card` partial based on `$cardView`
    - **Loading state:** Wrap card grid in `wire:loading` overlay: `<div wire:loading.flex class="...">Loading...</div>` and `<div wire:loading.remove>` around the card grid. Use a DaisyUI `loading loading-spinner` component.
    - **Empty state:** "No planner data available" message when no planners exist
    - **Data freshness:** "Last updated: {timestamp}" footer
  - Notes: `wire:click="switchView('health')"` on toggle buttons. `wire:click="switchPeriod('month')"` on period buttons. Active state via `@class(['btn-primary' => $cardView === 'quota'])`. The Livewire component will catch exceptions internally and return empty array — the empty state message handles this gracefully.

- [ ] **Task 10: Create quota card partial**
  - File: `resources/views/livewire/planner-metrics/_quota-card.blade.php` (NEW)
  - Action: Stateless Blade partial receiving `$planner` prop:
    ```
    ┌─────────────────────────────────────┐
    │ ▌ Planner Name           🔥 4wk    │  card header
    │ ▌ ████████████░░░░░░               │  progress bar
    │ ▌ 4.3 / 6.5 mi             66%    │  metric row
    │ ▌ 3 jobs · Last edit 4d ago       │  supporting row (health signal)
    │ ▌                                  │
    │ ▌ 💬 You're 1.8 mi away —         │  coaching (behind only)
    │ ▌    a strong day gets you there   │
    └─────────────────────────────────────┘
    ```
  - Structure: `<a>` tag wrapping entire card (future: links to detail page). `card card-compact bg-base-100 shadow-sm border-l-4 border-l-{status}`. Progress bar: `progress progress-{status} w-full h-2`. Streak badge: `badge badge-sm badge-success`. Metric text: `text-3xl font-bold tabular-nums`.
  - **Supporting row includes `days_since_last_edit`** — this is the #1 signal supervisors act on ("who hasn't touched WorkStudio?"). Display as "Last edit {N}d ago". Color: `text-success` if < 3d, `text-base-content` if 3-6d, `text-warning` if 7-13d, `text-error` if >= 14d.
  - Notes: Status maps to DaisyUI classes: `success`→`border-l-success progress-success`, `warning`→`border-l-warning progress-warning`, `error`→`border-l-error progress-error`. Coaching message only rendered when `$planner['status'] !== 'success'`.

- [ ] **Task 11: Create health card partial**
  - File: `resources/views/livewire/planner-metrics/_health-card.blade.php` (NEW)
  - Action: Stateless Blade partial receiving `$planner` prop:
    ```
    ┌─────────────────────────────────────┐
    │ ▌ Planner Name           3 jobs    │  header
    │ ▌ 6 units pending > 14d       ⚠️   │  aging units (COUNT, not days)
    │ ▌ Last edit: 4 days ago            │  staleness
    │ ▌ ✅30 ⏳8 📵2 ❌1 ⏸0 ✅5        │  permission badges
    │ ▌ 45.2 mi — 68% complete           │  progress summary
    └─────────────────────────────────────┘
    ```
  - Structure: Same card wrapper as quota card. Permission badges: `badge badge-sm` with tooltips for full status name. Staleness uses `text-warning` or `text-error` based on thresholds.
  - **Aging units metric:** Shows `pending_over_threshold` (integer count of units pending > threshold days), NOT a day count. Display as "{count} units pending > {threshold}d". `pending_over_threshold === 0` → `text-success`, `> 0` → `text-warning`, `>= 5` → `text-error`.
  - Notes: "No active assessments" state when `active_assessment_count === 0` — compact card with last active date, no error styling.

- [ ] **Task 12: Create coaching message partial**
  - File: `resources/views/livewire/planner-metrics/_coaching-message.blade.php` (NEW)
  - Action: Simple partial receiving `$message` string prop (nullable):
    ```blade
    @if($message)
    <div class="mt-3 rounded-lg bg-base-200 p-3 text-sm text-base-content">
        {{ $message }}
    </div>
    @endif
    ```
  - Notes: Intentionally minimal — no icon, no border color. The card's border accent provides emotional context.

#### Phase D: Tests (depends on all above)

- [ ] **Task 13: Create PlannerMetricsService unit tests**
  - File: `tests/Unit/Services/PlannerMetricsServiceTest.php` (NEW)
  - Tests:
    - `it returns empty array when no career entries exist`
    - `it calculates weekly miles from daily_metrics JSONB`
    - `it calculates monthly miles aggregation`
    - `it computes streak count for consecutive on-target weeks`
    - `it resets streak when a week is below quota`
    - `it returns planners unsorted from service`
    - `it includes days_since_last_edit in quota metrics return`
    - `it returns health metrics from assessment_monitors via job_guid bridge`
    - `it aggregates health metrics across multiple active assessments per planner`
    - `it uses forNormalizedUser scope for username bridge`
    - `it returns success/warning/error status based on thresholds`
    - `it handles planner with zero active assessments in health view`
    - `it respects config values for quota target and thresholds`

- [ ] **Task 14: Create CoachingMessageGenerator unit tests**
  - File: `tests/Unit/Services/CoachingMessageGeneratorTest.php` (NEW)
  - Tests:
    - `it returns null for on-pace planner`
    - `it returns encouraging message for small gap (< 2 mi)`
    - `it returns recovery message for large gap (>= 3 mi)`
    - `it returns nudge message when behind and stale edits`
    - `it returns celebration message for streak milestone`
    - `it prioritizes nudge over encouraging when both apply`
    - `it uses actual planner data in message text`

- [ ] **Task 15: Create feature tests for overview page**
  - File: `tests/Feature/PlannerMetrics/OverviewTest.php` (NEW)
  - Tests:
    - `it renders overview page for authenticated user`
    - `it redirects unauthenticated user to login`
    - `it displays planner cards in quota view by default`
    - `it toggles to health view when clicking health toggle`
    - `it persists cardView in URL parameter`
    - `it persists period in URL parameter`
    - `it shows empty state when no planner data exists`
    - `it displays coaching message for behind-quota planner`
    - `it shows correct progress bar fill percentage`
    - `it sorts cards alphabetically by default`
    - `it sorts cards by gap_miles when sortBy=attention in quota view`
    - `it sorts cards by days_since_last_edit when sortBy=attention in health view`
    - `it shows days_since_last_edit on quota cards`
  - Pattern: `Livewire::test(Overview::class)->assertStatus(200)->assertSee('Planner Metrics')`. Use factories to create test data.
  - **Mock-binding pattern for feature tests:** Bind mock services in the container before `Livewire::test()`:
    ```php
    $mock = Mockery::mock(PlannerMetricsServiceInterface::class);
    $mock->shouldReceive('getQuotaMetrics')->andReturn([...]);
    $this->app->bind(PlannerMetricsServiceInterface::class, fn () => $mock);
    Livewire::test(Overview::class)->assertSee('...');
    ```
    - `it hides period toggle when in health view`
    - `it validates invalid URL params via mount() and falls back to defaults`
    - `it persists sortBy in URL parameter`

#### Phase E: Finalize (depends on all above)

- [ ] **Task 16: Update CHANGELOG.md**
  - File: `CHANGELOG.md`
  - Action: Add entry under `[Unreleased]` documenting the Planner Metrics Dashboard feature
  - Run `vendor/bin/pint --dirty` before commit

### Acceptance Criteria

#### Core Functionality

- [ ] **AC-1:** Given an authenticated user, when they navigate to `/planner-metrics`, then they see a card grid showing all planners in quota view with current week period
- [ ] **AC-2:** Given a planner with 4.3 miles this week (quota 6.5), when viewing the quota card, then the progress bar shows ~66% fill with `warning` accent and the text reads "4.3 / 6.5 mi"
- [ ] **AC-3:** Given a planner with >= 6.5 miles this week, when viewing their quota card, then the card shows `success` accent, progress bar is full, and no coaching message appears
- [ ] **AC-4:** Given a behind-quota planner, when viewing their quota card, then a coaching message with contextual tip appears beneath the progress bar
- [ ] **AC-5:** Given a user on the overview page, when they click the "Health" toggle, then ALL cards switch to health view simultaneously and the URL updates to `?cardView=health`
- [ ] **AC-6:** Given a user viewing health cards, when a planner has `days_since_last_edit >= 7`, then their card shows `warning` accent on the staleness indicator. When `pending_over_threshold > 0`, the aging units metric shows the count with warning styling.
- [ ] **AC-7:** Given a user on the overview page in quota view, when they click "Month" in the period toggle, then cards re-render with monthly aggregated data and the URL updates to `?period=month`

#### Edge Cases

- [ ] **AC-8:** Given no planner career entries exist, when viewing the overview page, then a friendly empty state message is shown instead of an empty grid
- [ ] **AC-9:** Given a planner with zero active assessments, when viewing their health card, then the card shows "No active assessments" state without error styling
- [ ] **AC-10:** Given a user navigates directly to `?cardView=health&period=month`, when the page loads, then the correct view and period are pre-selected (URL-driven state)
- [ ] **AC-11:** Given a planner with a 4-week streak that breaks this week, when viewing their quota card, then streak shows 0 (not the old value)

#### Sorting

- [ ] **AC-12:** Given multiple planners in any view with default sort, when the page renders, then planners are sorted alphabetically by display_name (supervisors develop spatial memory for card positions)
- [ ] **AC-13:** Given a user clicks "Needs Attention" sort in quota view, when cards re-render, then planners are sorted by largest gap_miles first. In health view, sorted by highest days_since_last_edit first. URL updates to `?sortBy=attention`.

#### Validation & UI Behavior

- [ ] **AC-14:** Given a user in health view, when viewing the controls row, then the period toggle is hidden (health view has no time-window concept)
- [ ] **AC-15:** Given a user navigates to `?cardView=invalid&period=bogus&sortBy=evil`, when the page loads, then the component ignores the invalid values via `mount()` validation and renders with defaults (quota view, week period, alphabetical sort)
- [ ] **AC-16:** Given a planner in quota view whose last edit was 8 days ago, when viewing their quota card, then the supporting row shows "Last edit 8d ago" with `text-warning` styling
- [ ] **AC-17:** Given a planner in quota view whose last edit was today, when viewing their quota card, then the supporting row shows "Last edit 0d ago" with `text-success` styling

#### Responsive

- [ ] **AC-18:** Given a viewport width of 1280px+, when viewing the overview, then cards display in a 4-column grid with consistent spacing
- [ ] **AC-19:** Given a viewport width of 768-1023px, when viewing the overview, then cards display in a 2-column grid

## Additional Context

### Dependencies

- **No new packages required** — all built on existing stack (Laravel, Livewire, DaisyUI, Pest)
- **Data dependency:** `planner_career_entries` must be populated via `ws:export-planner-career` command (uses `PlannerCareerLedgerService`). Real data reference: `storage/app/asplundh/planners/career/`. If table is empty, empty state is shown.
- **Data dependency:** `assessment_monitors` must be populated via `ws:run-live-monitor` command for health view data. Health view degrades gracefully if no monitors exist.
- **Data dependency:** `planner_job_assignments` must be populated via `ws:import-career-ledger` for the planner→assessment bridge.

### Testing Strategy

**Unit Tests (service + generator):**
- `PlannerMetricsServiceTest` — 13 tests covering both methods, edge cases, health signal bridge, status logic
- `CoachingMessageGeneratorTest` — 7 tests covering all message types, priority, null return
- Use factories with specific `daily_metrics` JSONB to control exact period miles (factory must use `daily_footage_miles` field — see Task 2b)
- Use `AssessmentMonitorFactory::withSnapshots()` for health data
- Test username bridge: factory creates `planner_career_entries` with stripped usernames and `planner_job_assignments` with domain-qualified + normalized usernames — verify service uses `forNormalizedUser()` scope
- Migration backfill: include a test that creates a `PlannerJobAssignment` with `frstr_user = 'ASPLUNDH\\jsmith'` and `normalized_username = null`, runs the backfill logic, and asserts `normalized_username = 'jsmith'`

**Feature Tests (route + component):**
- `OverviewTest` — 16 tests covering rendering, toggles, URL state, empty state, sorting (alpha + attention), validation (mount), period toggle visibility, mock-binding pattern, days_since_last_edit on quota cards
- Use `Livewire::test()` for component assertions
- Factory data for each test scenario

**Manual Testing:**
- Verify card grid at 4, 8, 12, 16 planners on various viewport widths
- Verify all 37 DaisyUI themes render cards correctly (semantic colors)
- Verify coaching messages are contextual and not repetitive
- Verify URL state persists across page refreshes

### Notes

**High-risk items:**
- **Daily metrics JSONB aggregation** — `daily_metrics` is a **flat array** of objects (NOT date-keyed). Each element has `completion_date` and `daily_footage_miles`. Service iterates with `foreach` and filters by date. PostgreSQL JSONB querying could be slow at scale. Mitigation: for Phase 1, load entries into PHP and aggregate in-memory (< 100 entries per planner per year). Optimize to PostgreSQL JSONB operators in Phase 2 if needed.
- **Streak calculation** — Counting consecutive weeks requires iterating backward through date-keyed daily_metrics. Must handle gaps (no data for a week = streak broken). Week = Monday→Sunday (ISO 8601). Current incomplete week excluded. Implement in PHP, not SQL. Pseudocode provided in Task 6.
- **Username domain mismatch** — Resolved by Task 2c: `normalized_username` column (indexed) added to `planner_job_assignments`. Service uses `forNormalizedUser()` scope for exact-match lookups instead of `LIKE` pattern.

**Design decisions:**
- **Streak grace period:** New planners (< 2 weeks of data) get streak = 0 which is fair — they haven't had a chance to build one. Partial first-week fairness is a Phase 2 concern.
- **Display name:** Uses `planner_username` (stripped) for now. Phase 2 will resolve to `ws_users` and `users` tables for verified display names.
- **Period URL persistence:** The `$period` param persists in the URL even when in health view (where it's hidden). This means switching back to quota from a bookmark like `?cardView=health&period=month` pre-selects month. Acceptable — not confusing enough to warrant reset logic.

**Known limitations:**
- Coaching messages are deterministic (same data = same message). Rotation to avoid staleness is deferred to Phase 2.
- No real-time updates — data freshness depends on when `ws:export-career-ledger` and `ws:run-live-monitor` last ran. The "last updated" timestamp makes this visible.
- Period "scope-year" relies on the `scope_year` column, which may span non-calendar boundaries. The service treats it as a filter, not a date range.

**Future considerations (out of scope):**
- Phase 2: Detail page at `/planner-metrics/{username}`
- Permission gating with new `access-planner-metrics` permission
- Leaderboard / ranking overlay
- Streak badges and milestone celebrations
- Card click → detail page navigation (currently `<a>` tag with `href="#"` placeholder)

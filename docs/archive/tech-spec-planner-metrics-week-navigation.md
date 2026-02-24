---
title: 'Planner Metrics Week Navigation & Boundary Overhaul'
slug: 'planner-metrics-week-navigation'
created: '2026-02-15'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Livewire 4', 'Alpine.js', 'DaisyUI v5', 'Tailwind v4', 'Carbon', 'Pest 4']
files_to_modify:
  - 'config/planner_metrics.php'
  - 'app/Services/PlannerMetrics/Contracts/PlannerMetricsServiceInterface.php'
  - 'app/Services/PlannerMetrics/PlannerMetricsService.php'
  - 'app/Livewire/PlannerMetrics/Overview.php'
  - 'resources/views/livewire/planner-metrics/overview.blade.php'
  - 'tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php'
code_patterns:
  - '#[Url] attributes for URL-driven Livewire state'
  - 'CarbonImmutable for all date math'
  - 'Config-driven period lists and thresholds'
  - 'DaisyUI join component for button groups'
  - 'wire:click for state changes, wire:loading for overlays'
test_patterns:
  - 'Pest 4 with RefreshDatabase'
  - 'Temp directory fixtures via writeCareerFixture()'
  - 'config()->set() for runtime config overrides'
  - 'CarbonImmutable::setTestNow() for time freezing'
---

# Tech-Spec: Planner Metrics Week Navigation & Boundary Overhaul

**Created:** 2026-02-15

## Overview

### Problem Statement

The planner metrics dashboard currently shows only the current partial week (Monday–today) with no ability to view historical weeks. All week boundaries use ISO Monday–Sunday instead of the operational Sunday–Saturday standard used by field planners. The scope-year uses calendar year (Jan 1 – Dec 31) instead of the planner fiscal year (July 1 – June 30). Users cannot review past performance or compare weeks.

### Solution

Add prev/next navigation across all period views (week, month, year, scope-year), globally shift week calculations to Sunday–Saturday, default to the previous completed week until Tuesday 5 PM ET (then flip to current week), and shift scope-year boundaries to July 1 – June 30. Add a navigation strip above the metric cards showing the active date range with arrow controls.

### Scope

**In Scope:**
- Global week boundary shift to Sunday 00:00 – Saturday 23:59 (streaks, last week, all calculations)
- Prev/next navigation arrows on all periods (week, month, year, scope-year)
- Default to previous completed week; auto-flip to current week at Tuesday 5 PM ET
- Scope-year boundaries shifted to July 1 – June 30
- Period label format: abbreviated month + day + year (e.g., "Feb 9 – Feb 15, 2026")
- Unlimited history depth (as far back as data exists)
- Navigation UI above metric cards

**Out of Scope:**
- Calendar date-range picker widget
- Changes to health metrics view logic
- Changes to career JSON file format or data collection pipeline
- Per-planner timezone settings (server uses ET)

## Context for Development

### Codebase Patterns

- Livewire `#[Url]` attributes for URL-driven state persistence across page loads
- DaisyUI `join` component for button groups, `btn-primary` for active state
- `wire:click` for state changes, `wire:loading` for loading overlays
- Config-driven period list in `config/planner_metrics.php`
- `CarbonImmutable` for all date calculations — never mutable Carbon
- String comparison on `Y-m-d` formatted dates for period filtering (lexicographic sort)
- Service interface with PHPDoc return type annotations
- Computed properties with `#[Computed]` attribute for cached data fetching

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `config/planner_metrics.php` | Period list, quota, thresholds — add week_starts_on and offset config |
| `app/Services/PlannerMetrics/Contracts/PlannerMetricsServiceInterface.php` | Service contract — add offset param, add label method |
| `app/Services/PlannerMetrics/PlannerMetricsService.php` | All date math lives here — getDateWindow, streak, lastWeek, scope-year |
| `app/Livewire/PlannerMetrics/Overview.php` | Livewire component — add offset property, navigation methods |
| `resources/views/livewire/planner-metrics/overview.blade.php` | Blade view — add navigation strip above card grid |
| `resources/views/livewire/planner-metrics/_quota-card.blade.php` | Quota card partial — no changes needed |
| `tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php` | Service tests — update week boundaries, add offset tests |

### Technical Decisions

- **Sunday start via Carbon constant:** Use `Carbon::SUNDAY` (value `0`) in config. Pass to `startOfWeek(Carbon::SUNDAY)`. This is cleaner than subtracting days from Monday.
- **Offset parameter (not date string):** Use `int $offset` (0 = current, -1 = previous, -2 = two ago) rather than passing raw date strings. Simpler URL params, no validation needed.
- **Auto-default offset:** A dedicated `getDefaultOffset()` method checks if current time is before Tuesday 5 PM ET. If so, returns `-1` (previous week). Otherwise `0` (current week). The Livewire component uses a nullable `$offset` — `null` means "use auto-default".
- **Scope-year = fiscal year:** July 1 of current year (or previous year if before July 1) through June 30 of next year. Offset shifts by fiscal years.
- **Period label via service:** Add `getPeriodLabel(string $period, int $offset): string` to interface. The service generates the human-readable label. This keeps date formatting logic out of the view layer.

## Implementation Plan

### Tasks

- [x] Task 1: Add config values for week start day and offset defaults
  - File: `config/planner_metrics.php`
  - Action: Add three new config keys:
    ```php
    'week_starts_on' => \Carbon\Carbon::SUNDAY, // 0
    'default_offset_flip_day' => 'Tuesday',
    'default_offset_flip_hour' => 17, // 5 PM
    'default_offset_timezone' => 'America/New_York',
    ```
  - Notes: Using Carbon constant ensures consistency. Flip day/hour are configurable for easy adjustment later.

- [x] Task 2: Update service interface with offset parameter and label method
  - File: `app/Services/PlannerMetrics/Contracts/PlannerMetricsServiceInterface.php`
  - Action: Change `getQuotaMetrics` signature and add new method:
    ```php
    public function getQuotaMetrics(string $period = 'week', int $offset = 0): array;
    public function getPeriodLabel(string $period, int $offset = 0): string;
    ```
  - Notes: Offset is 0-based (0 = current period, -1 = previous, etc). Negative values only.

- [x] Task 3: Shift week boundaries to Sunday–Saturday in service
  - File: `app/Services/PlannerMetrics/PlannerMetricsService.php`
  - Action: Update `getDateWindow()` to use `config('planner_metrics.week_starts_on')` for `startOfWeek()`. A Sunday–Saturday week means:
    - `$start = $reference->startOfWeek(Carbon::SUNDAY)->format('Y-m-d')`
    - `$end = $reference->endOfWeek(Carbon::SATURDAY)->format('Y-m-d')` (for completed weeks)
    - For current week (offset 0): `$end = $reference->format('Y-m-d')` (today, partial week)
    - For past weeks (offset < 0): `$end` is Saturday (full completed week)
  - Notes: `endOfWeek(Carbon::SATURDAY)` gives Saturday 23:59:59, but since we format to `Y-m-d` and compare strings, Saturday date is sufficient.

- [x] Task 4: Add offset support to `getDateWindow()` and `calculatePeriodMiles()`
  - File: `app/Services/PlannerMetrics/PlannerMetricsService.php`
  - Action: `getDateWindow()` accepts `int $offset = 0`. For each period:
    - **week:** `$reference = $now->startOfWeek(SUNDAY)->addWeeks($offset)` → Sunday to Saturday (or today if offset=0)
    - **month:** `$reference = $now->startOfMonth()->addMonths($offset)` → 1st to end-of-month (or today if offset=0)
    - **year:** `$reference = $now->startOfYear()->addYears($offset)` → Jan 1 to Dec 31 (or today if offset=0)
    - **scope-year:** Calculate fiscal year start (Jul 1), add offset years → Jul 1 to Jun 30 (or today if offset=0)
  - Pass offset through `getQuotaMetrics()` → `calculatePeriodMiles()` → `getDateWindow()`
  - Notes: When offset < 0, the end date is the last day of that period (full period). When offset = 0, end date is today (partial period).

- [x] Task 5: Shift streak calculation to Sunday–Saturday
  - File: `app/Services/PlannerMetrics/PlannerMetricsService.php`
  - Action: In `calculateStreak()`, change:
    - `$checkSunday = $now->startOfWeek(Carbon::SUNDAY)->subWeek()` (previous completed Sun–Sat week)
    - `$weekStart = $checkSunday->format('Y-m-d')`
    - `$weekEnd = $checkSunday->addDays(6)->format('Y-m-d')` (Saturday)
    - Loop: `$checkSunday = $checkSunday->subWeek()`
  - Notes: Streak always counts backwards from the last fully completed week, regardless of offset.

- [x] Task 6: Shift `calculateLastWeekMiles()` to Sunday–Saturday
  - File: `app/Services/PlannerMetrics/PlannerMetricsService.php`
  - Action: Change:
    - `$lastSunday = $now->startOfWeek(Carbon::SUNDAY)->subWeek()` (start of previous week)
    - `$lastSaturday = $lastSunday->addDays(6)`
  - Notes: "Last week" is always the most recent completed Sun–Sat, regardless of offset.

- [x] Task 7: Shift scope-year to July 1 – June 30
  - File: `app/Services/PlannerMetrics/PlannerMetricsService.php`
  - Action: In `calculatePeriodMiles()` for the `scope-year` branch, change from filtering by `$currentScopeYear` integer to filtering by date range:
    - Determine fiscal year start: if `$now->month >= 7` then `Jul 1 of $now->year`, else `Jul 1 of ($now->year - 1)`
    - Apply offset: `$fiscalStart->addYears($offset)`
    - `$end` = `$fiscalStart->addYear()->subDay()` (Jun 30) — or today if current fiscal year and offset = 0
    - Filter `daily_metrics` by `completion_date` within this range (same pattern as other periods)
  - Also update `calculateQuotaTarget()` for `scope-year`: weeks elapsed since fiscal year start (not calendar year start)
  - Notes: The `scope_year` field on assessments is no longer used for filtering — date range is authoritative.

- [x] Task 8: Add `getDefaultOffset()` method
  - File: `app/Services/PlannerMetrics/PlannerMetricsService.php`
  - Action: New private method:
    ```php
    private function getDefaultOffset(string $period): int
    {
        if ($period !== 'week') {
            return 0;
        }
        $tz = config('planner_metrics.default_offset_timezone', 'America/New_York');
        $now = CarbonImmutable::now($tz);
        $flipDay = config('planner_metrics.default_offset_flip_day', 'Tuesday');
        $flipHour = (int) config('planner_metrics.default_offset_flip_hour', 17);

        // Before flip day+hour → show previous week
        if ($now->englishDayOfWeek === $flipDay && $now->hour < $flipHour) {
            return -1;
        }
        if ($now->dayOfWeekIso < CarbonImmutable::parse($flipDay)->dayOfWeekIso) {
            return -1; // Sunday or Monday (before Tuesday)
        }
        return 0;
    }
    ```
  - Notes: Only applies to `week` period. Other periods default to current (offset 0). Uses timezone-aware check.

- [x] Task 9: Add `getPeriodLabel()` method
  - File: `app/Services/PlannerMetrics/PlannerMetricsService.php`
  - Action: New public method implementing the interface:
    ```php
    public function getPeriodLabel(string $period, int $offset = 0): string
    {
        $now = CarbonImmutable::now();
        [$start, $end] = $this->getDateWindow($period, $now, $offset);
        $startDate = CarbonImmutable::parse($start);
        $endDate = CarbonImmutable::parse($end);

        // Same year: "Feb 9 – Feb 15, 2026"
        // Cross year: "Dec 28, 2025 – Jan 3, 2026"
        if ($startDate->year === $endDate->year) {
            return $startDate->format('M j') . ' – ' . $endDate->format('M j, Y');
        }
        return $startDate->format('M j, Y') . ' – ' . $endDate->format('M j, Y');
    }
    ```
  - Notes: Uses en-dash (–) not hyphen. Abbreviated month via Carbon `M` format.

- [x] Task 10: Update Livewire component with offset navigation
  - File: `app/Livewire/PlannerMetrics/Overview.php`
  - Action:
    - Add property: `#[Url] public ?int $offset = null;` (null = auto-default)
    - In `mount()`: if `$this->offset === null`, compute via service `getDefaultOffset()`
    - Add `navigateOffset(int $direction)` method: `$this->offset += $direction; $this->clearCache();`
    - Add `resetOffset()` method: `$this->offset = null; $this->clearCache();` (return to auto-default)
    - Update `planners()` computed: pass `$this->offset` to `getQuotaMetrics($this->period, $this->offset ?? 0)`
    - Add `periodLabel()` computed: returns `app(PlannerMetricsServiceInterface::class)->getPeriodLabel($this->period, $this->offset ?? 0)`
    - Update `switchPeriod()`: reset offset to null when period changes
    - Update `clearCache()`: also unset `periodLabel`
    - Add `wire:loading` target for `navigateOffset, resetOffset`
  - Notes: `null` offset means "auto" — the service computes the smart default. Once user navigates manually, offset becomes an explicit integer.

- [x] Task 11: Add navigation strip to Blade view
  - File: `resources/views/livewire/planner-metrics/overview.blade.php`
  - Action: Add a navigation bar between the controls row and the card grid. Design:
    ```blade
    {{-- Period Navigation --}}
    @if($cardView === 'quota')
    <div class="flex items-center justify-center gap-2">
        <button
            type="button"
            wire:click="navigateOffset(-1)"
            class="btn btn-sm btn-ghost btn-circle"
            aria-label="Previous period"
        >
            <x-heroicon-m-chevron-left class="size-4" />
        </button>

        <button
            type="button"
            wire:click="resetOffset"
            class="btn btn-sm btn-ghost font-medium min-w-48 tabular-nums"
        >
            {{ $this->periodLabel }}
        </button>

        <button
            type="button"
            wire:click="navigateOffset(1)"
            class="btn btn-sm btn-ghost btn-circle"
            @disabled($offset === 0 || $offset === null)
            aria-label="Next period"
        >
            <x-heroicon-m-chevron-right class="size-4" />
        </button>
    </div>
    @endif
    ```
  - Update loading overlay `wire:target` to include `navigateOffset, resetOffset`
  - Notes: Next arrow is disabled when at current period (offset 0 or null). Center button resets to auto-default. Ghost buttons keep it minimal. `min-w-48` prevents layout shift when label changes.

- [x] Task 12: Update existing tests for Sunday–Saturday boundaries
  - File: `tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php`
  - Action: Update all tests that use `startOfWeek()` to use `startOfWeek(Carbon::SUNDAY)`:
    - `it calculates weekly miles from daily_metrics in JSON` (line 48)
    - `it computes streak count for consecutive on-target weeks` (line 91)
    - `it resets streak when a week is below quota` (line 113)
    - `it includes all expected keys in quota metrics return` (line 153)
    - `it returns success/warning/error status based on thresholds` (line 268)
    - `it picks the most recent JSON file per planner` (line 347)
    - `it handles underscored usernames in filenames` (line 411)
    - `it respects config values for quota target and thresholds` (line 324)
  - Notes: Replace every `$now->startOfWeek()` or `CarbonImmutable::now()->startOfWeek()` with `->startOfWeek(Carbon::SUNDAY)`.

- [x] Task 13: Add new tests for offset navigation
  - File: `tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php`
  - Action: Add test cases:
    - `it returns previous week data when offset is -1`
    - `it returns two weeks ago data when offset is -2`
    - `it shows full Sun-Sat range for past weeks`
    - `it shows partial week (Sun through today) for current week offset 0`
    - `it defaults to previous week before Tuesday 5 PM ET` (freeze time to Monday)
    - `it defaults to current week after Tuesday 5 PM ET` (freeze time to Wednesday)
    - `it calculates scope-year from July 1 to June 30`
    - `it shifts scope-year with offset`
    - `it generates correct period label format`
    - `it generates cross-year period label when range spans years`
  - Pattern: Use `CarbonImmutable::setTestNow()` to freeze time. Create fixtures with known dates. Assert period_miles matches expected window.
  - Notes: Remember to call `CarbonImmutable::setTestNow()` (not `Carbon::setTestNow()` — immutable).

### Acceptance Criteria

- [x] AC 1: Given the planner metrics page loads on a Monday, when no URL offset is provided, then the default view shows the previous completed Sunday–Saturday week's data.
- [x] AC 2: Given the planner metrics page loads on a Wednesday, when no URL offset is provided, then the default view shows the current (partial) Sunday–through-today week's data.
- [x] AC 3: Given the user is viewing weekly metrics, when they click the left arrow, then the offset decrements by 1 and the previous week's data is shown with the correct date range label.
- [x] AC 4: Given the user is viewing a past week (offset < 0), when they click the right arrow, then the offset increments by 1 toward the current week.
- [x] AC 5: Given the user has navigated to a past week, when they click the center date label, then the offset resets to the auto-default (null).
- [x] AC 6: Given the user is viewing the current week (offset = 0), when they look at the right arrow, then it is disabled (cannot go forward past current).
- [x] AC 7: Given any weekly calculation (streak, last_week_miles, period_miles), when weeks are computed, then Sunday is day 1 and Saturday is day 7 of each week.
- [x] AC 8: Given the period is set to scope-year, when viewing the current scope year, then the date range shows July 1 through today (or June 30 if past fiscal year).
- [x] AC 9: Given the user switches from "week" to "month" period, when the view updates, then the offset resets to auto-default and the navigation arrows show month boundaries.
- [x] AC 10: Given a past completed week (offset < 0), when the date range label displays, then it shows the full Sun–Sat range in "Feb 9 – Feb 15, 2026" format.
- [x] AC 11: Given the period label spans two calendar years, when displayed, then both years are shown: "Dec 29, 2025 – Jan 3, 2026".

## Additional Context

### Dependencies

- No new packages required. All changes use existing Carbon, Livewire, and DaisyUI capabilities.
- `heroicon-m-chevron-left` and `heroicon-m-chevron-right` — already available via blade-heroicons package (used elsewhere in the app).

### Testing Strategy

**Unit tests (service layer):**
- Freeze time with `CarbonImmutable::setTestNow()` to deterministic dates
- Test each period type (week, month, year, scope-year) with offset 0 and negative offsets
- Test default offset flip logic at boundary times (Monday 11 PM, Tuesday 4 PM, Tuesday 6 PM)
- Test scope-year fiscal boundaries (before and after July 1)
- Test period label formatting including cross-year ranges
- Update all existing week-based tests to Sunday start

**Livewire component tests (optional, lower priority):**
- Test `navigateOffset()` increments/decrements offset
- Test `resetOffset()` sets offset to null
- Test `switchPeriod()` resets offset
- Test disabled state of forward arrow at offset 0

**Manual testing:**
- Load planner metrics page — should default to previous week (if before Tuesday 5 PM ET)
- Click left arrow — should show older week with correct label
- Click right arrow — should return toward current week
- Click center label — should snap back to auto-default
- Switch to month/year/scope-year — arrows should navigate those periods
- Verify scope-year shows Jul 1 start date

### Notes

- Career JSON files contain historical data going back to assessment start. Unlimited backward navigation is safe — the service simply returns 0 miles for periods with no data.
- The `scope_year` integer field on assessments will be ignored for filtering after this change — date ranges are authoritative. The field still exists in the JSON but is not used for scope-year calculations.
- Streak calculation always counts from the most recent completed week backward, regardless of the viewing offset. It does not shift with navigation.
- `last_week_miles` similarly always reflects the most recent completed week relative to today, not relative to the viewed offset.
- The `getDefaultOffset()` only applies to `week` period. Month, year, and scope-year always default to current (offset 0).

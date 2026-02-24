---
title: 'Refactor Planner Metrics to Career JSON + Remove CareerLedgerService'
slug: 'refactor-planner-metrics-remove-career-ledger-service'
created: '2026-02-15'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack: [Laravel, Livewire, Pest, PostgreSQL]
files_to_modify:
  - app/Services/PlannerMetrics/PlannerMetricsService.php
  - app/Listeners/ProcessAssessmentClose.php
  - app/Providers/WorkStudioServiceProvider.php
  - config/ws_data_collection.php
  - config/planner_metrics.php
  - tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php
  - tests/Feature/DataCollection/CommandsTest.php
  - tests/Feature/DataCollection/ProcessAssessmentCloseTest.php
code_patterns:
  - JSON file reading with file_get_contents + json_decode
  - Glob pattern scanning for latest file per planner
  - Collection-based iteration over assessments array
test_patterns:
  - JSON fixture files in tests/fixtures/
  - Config override for career_json_path
---

# Tech-Spec: Refactor Planner Metrics to Career JSON + Remove CareerLedgerService

**Created:** 2026-02-15

## Overview

### Problem Statement

`PlannerMetricsService` reads quota/footage data from the `PlannerCareerEntry` PostgreSQL model, populated by `CareerLedgerService`. The career JSON files produced by `PlannerCareerLedgerService::exportForUser()` already contain all needed footage data (`daily_metrics`, `total_contribution`, `assessment_total_miles`). The DB model is an unnecessary middleman.

### Solution

Refactor `PlannerMetricsService` to parse career JSON files from disk. Remove `CareerLedgerService` and everything it exclusively supports. Health view stays unchanged on `AssessmentMonitor`.

### Scope

**In Scope:**
- Refactor `PlannerMetricsService` to read career JSON files
- Remove `CareerLedgerService`, `CareerLedgerQueries`, import/export commands
- Remove `PlannerCareerEntry` model, factory, migration
- Update `ProcessAssessmentClose` listener
- Drop `planner_career_entries` table
- Update affected tests

**Out of Scope:**
- `PlannerCareerLedgerService` (keep)
- `AssessmentMonitor` / health view (unchanged)
- `CoachingMessageGenerator` (unchanged)
- View templates (no key changes bubble up — output shape is identical)

## Context for Development

### Key Mapping: DB Model → Career JSON

The daily_metrics keys are **identical** — no rename needed:

| PlannerCareerEntry (DB) | Career JSON | Notes |
|---|---|---|
| `$entry->daily_metrics` | `$assessment['daily_metrics']` | Same nested array structure |
| `$metric['completion_date']` | `$metric['completion_date']` | Same key |
| `$metric['daily_footage_miles']` | `$metric['daily_footage_miles']` | Same key |
| `$entry->scope_year` | `$assessment['scope_year']` | Same key |
| `$entry->planner_username` | Derived from filename | `{username}_{date}.json` |
| `$entry->planner_display_name` | `$assessment['planner_username']` | Strip domain prefix |

### Career JSON Structure (on disk)

Files at `storage/app/asplundh/planners/career/{username}_{date}.json`:

```json
{
  "career_timeframe": "1yrs 11months 21days",
  "total_career_miles": 983.35,
  "assessment_count": 53,
  "total_career_unit_count": 450,
  "assessments": [
    {
      "planner_username": "ASPLUNDH\\tgibson",
      "total_contribution": 16.44,
      "job_guid": "{GUID}",
      "scope_year": 2025,
      "daily_metrics": [
        {
          "completion_date": "2025-03-15",
          "FRSTR_USER": "ASPLUNDH\\tgibson",
          "daily_footage_miles": 2.34,
          "unit_count": 5,
          "assumed_status": "Active",
          "stations": [...]
        }
      ],
      ...
    }
  ]
}
```

### Current Data Flow (before)

```
PlannerCareerEntry (PostgreSQL) ← populated by CareerLedgerService
    ↓
PlannerMetricsService::getQuotaMetrics()
    ↓ iterates $entry->daily_metrics
Livewire Overview component
```

### New Data Flow (after)

```
Career JSON files (disk) ← produced by PlannerCareerLedgerService
    ↓
PlannerMetricsService::getQuotaMetrics()
    ↓ iterates $assessment['daily_metrics']
Livewire Overview component
```

### Files to Reference

| File | Purpose |
|---|---|
| `app/Services/PlannerMetrics/PlannerMetricsService.php` | **MODIFY** — swap data source from DB to JSON |
| `app/Listeners/ProcessAssessmentClose.php` | **MODIFY** — remove CareerLedgerService dep |
| `app/Providers/WorkStudioServiceProvider.php` | **MODIFY** — remove singleton registration |
| `config/ws_data_collection.php` | **MODIFY** — remove `career_ledger` key |
| `config/planner_metrics.php` | **MODIFY** — add `career_json_path` |
| `tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php` | **MODIFY** — JSON fixtures |
| `tests/Feature/DataCollection/CommandsTest.php` | **MODIFY** — remove career ledger test sections |
| `tests/Feature/DataCollection/ProcessAssessmentCloseTest.php` | **MODIFY** — remove career entry assertion |

### Technical Decisions

1. **File discovery**: Glob `{career_json_path}/*_{date}.json`, pick most recent per username (sort by filename descending, take first match per prefix)
2. **Display name**: Strip domain from `planner_username` field (`ASPLUNDH\tgibson` → `tgibson`). No DB lookup for display names.
3. **Caching**: Use `Livewire\Attributes\Computed` caching already in place on the Overview component — no additional cache layer needed
4. **Migration**: Create drop migration for `planner_career_entries` table. Delete the original create migration file.

## Implementation Plan

### Task 1: Add config + migration (no dependencies)

**Files:**
- `config/planner_metrics.php` — add `career_json_path` key
- New migration: `drop_planner_career_entries_table`

**Changes:**

`config/planner_metrics.php` — add:
```php
'career_json_path' => storage_path('app/asplundh/planners/career'),
```

New migration:
```php
Schema::dropIfExists('planner_career_entries');
```

### Task 2: Refactor PlannerMetricsService (core change)

**File:** `app/Services/PlannerMetrics/PlannerMetricsService.php`

**Changes:**
1. Remove `use App\Models\PlannerCareerEntry` import
2. Add private method `loadAllCareerData(): array` that:
   - Globs `config('planner_metrics.career_json_path')` for `*.json` files
   - Groups by username prefix (filename before last `_`)
   - Takes most recent file per username (last when sorted alpha — date in filename)
   - Returns `[username => careerJsonArray, ...]`
3. Replace `getDistinctPlanners()`:
   - Was: DB query on `PlannerCareerEntry`
   - Now: Keys from `loadAllCareerData()`, derive display_name by stripping domain from first assessment's `planner_username`
4. Replace `getQuotaMetrics()`:
   - Was: `PlannerCareerEntry::forPlanner($username)->get()` then iterate `$entry->daily_metrics`
   - Now: `$careerData[$username]['assessments']` then iterate `$assessment['daily_metrics']`
5. Replace `calculatePeriodMiles()`:
   - Was: Collection of `PlannerCareerEntry` models
   - Now: Array of assessment arrays
   - Inner loop logic identical (reads `completion_date`, `daily_footage_miles`)
   - Scope-year filter: `collect($assessments)->where('scope_year', ...)` instead of `$entries->where('scope_year', ...)`
6. Replace `calculateStreak()` and `calculateLastWeekMiles()`:
   - Same pattern — swap collection iteration for array iteration
   - Daily metric keys are identical, no rename needed

**The output shape (array returned to Livewire) does NOT change.** All keys (`period_miles`, `quota_target`, `percent_complete`, `streak_weeks`, `last_week_miles`, `days_since_last_edit`, `active_assessment_count`, `status`, `gap_miles`) stay exactly the same.

**Health metrics method stays unchanged** — still reads `AssessmentMonitor` via `resolveHealthSignal()`. But `getDistinctPlanners()` is also called by `getHealthMetrics()`, so it inherits the JSON-based planner list.

### Task 3: Update ProcessAssessmentClose listener

**File:** `app/Listeners/ProcessAssessmentClose.php`

Remove `CareerLedgerService` import and constructor param. Remove `$this->careerLedger->appendFromMonitor()` call. Keep `GhostDetectionService` and `$event->monitor->delete()`.

```php
// Before
public function __construct(
    private CareerLedgerService $careerLedger,
    private GhostDetectionService $ghostDetection,
) {}

// After
public function __construct(
    private GhostDetectionService $ghostDetection,
) {}
```

Transaction body becomes:
```php
DB::transaction(function () use ($event) {
    $this->ghostDetection->cleanupOnClose($event->jobGuid);
    $event->monitor->delete();
});
```

### Task 4: Update WorkStudioServiceProvider

**File:** `app/Providers/WorkStudioServiceProvider.php`

Remove:
```php
use App\Services\WorkStudio\DataCollection\CareerLedgerService;
// ...
$this->app->singleton(CareerLedgerService::class);
```

### Task 5: Update config

**File:** `config/ws_data_collection.php`

Remove the `career_ledger` key:
```php
// Remove this:
'career_ledger' => [
    'bootstrap_path' => storage_path('app/career-ledger-bootstrap.json'),
],
```

### Task 6: Delete files (11 files)

| File | Reason |
|---|---|
| `app/Services/WorkStudio/DataCollection/CareerLedgerService.php` | Being removed |
| `app/Services/WorkStudio/DataCollection/Queries/CareerLedgerQueries.php` | Only used by CareerLedgerService |
| `app/Console/Commands/ExportCareerLedger.php` | Uses CareerLedgerService |
| `app/Console/Commands/ImportCareerLedger.php` | Uses CareerLedgerService |
| `app/Models/PlannerCareerEntry.php` | No producers/consumers after refactor |
| `database/factories/PlannerCareerEntryFactory.php` | Factory for deleted model |
| `database/migrations/2026_02_13_171957_create_planner_career_entries_table.php` | Migration for dropped table |
| `tests/Feature/DataCollection/CareerLedgerServiceTest.php` | Tests deleted service |
| `tests/Unit/DataCollection/CareerLedgerQueriesTest.php` | Tests deleted queries |
| `tests/Feature/DataCollection/PlannerCareerEntryTest.php` | Tests deleted model |

### Task 7: Update tests

**File:** `tests/Feature/DataCollection/CommandsTest.php`
- Remove lines 1-138 (ImportCareerLedger + ExportCareerLedger test sections and CareerLedgerService import)
- Keep lines 140-234 (RunLiveMonitor tests)

**File:** `tests/Feature/DataCollection/ProcessAssessmentCloseTest.php`
- Remove `PlannerCareerEntry` import and `CareerLedgerService` import
- Test 1: Rewrite — listener now only cleans up ghosts + deletes monitor (no career entry assertion)
- Constructor: `new ProcessAssessmentClose($ghostService)` (single arg)
- Remove assertion: `PlannerCareerEntry::where(...)->exists()->toBeTrue()`
- Tests 2-3: Unchanged (event structure + ShouldQueue interface)

**File:** `tests/Feature/PlannerMetrics/PlannerMetricsServiceTest.php`
- Remove `use PlannerCareerEntry`
- Replace `PlannerCareerEntry::factory()->create([...])` with JSON fixture files
- Create test fixture directory: `tests/fixtures/planners/career/`
- Create fixture file(s) matching the career JSON format
- Use `config()->set('planner_metrics.career_json_path', ...)` to point at fixture dir
- All assertions stay identical (output shape unchanged)

### Acceptance Criteria

```gherkin
Given career JSON files exist in the configured directory
When PlannerMetricsService::getQuotaMetrics('week') is called
Then it returns the same output shape as before with correct period_miles calculated from JSON daily_metrics

Given no career JSON files exist
When getQuotaMetrics() or getHealthMetrics() is called
Then it returns an empty array

Given multiple JSON files exist for the same planner (different dates)
When getDistinctPlanners() is called
Then only the most recent file per planner is used

Given an assessment closes (AssessmentClosed event)
When ProcessAssessmentClose handles it
Then ghost cleanup runs, monitor is deleted, and NO career entry is created

Given the application boots
When CareerLedgerService is resolved from the container
Then it throws (class no longer exists)

Given the planner_career_entries migration runs
Then the table is dropped
```

## Additional Context

### Dependencies

- `PlannerCareerLedgerService` must continue producing JSON files (unchanged)
- `AssessmentMonitor` must continue being populated by `LiveMonitorService` (unchanged)
- `PlannerJobAssignment` still used by `resolveHealthSignal()` for the username→GUID bridge

### Testing Strategy

- JSON fixture files simulate career exports
- Config override points service at fixture directory
- All existing assertion shapes stay the same — validates output contract didn't change
- Edge cases: empty directory, single assessment, multiple files per planner

### Notes

- Display name limitation: will show stripped username (e.g., `tgibson`) rather than formatted name. Can be enhanced later by looking up `WsUser` or adding display_name to the export.
- File I/O performance: reading 4-5 JSON files (~400KB each) is negligible compared to the API calls that produce them. No caching needed beyond Livewire computed property.

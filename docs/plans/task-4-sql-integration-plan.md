# SQL Queries Integration Plan

## Executive Summary

This plan details the integration of optimized SQL queries from the `/home/arbman/WorkStudioDev/bmad_workstudio_skill/workstudio_output/bmb-creations/query-optimizations/` directory into the WS-TrackerV1 project. The integration involves two main components:

1. **GetQueryService Optimization** - Refactoring the existing `getAllByJobGuid()` query with CTE-based optimizations
2. **Planner Activity System** - A new feature for tracking planner activity with "First Unit Wins" footage attribution

---

## Part 1: GetQueryService Optimization Integration

### 1.1 Current State Analysis

The existing `GetQueryService.php` at `/app/Services/WorkStudio/Services/GetQueryService.php` uses `AssessmentQueries.php` which contains the problematic query identified in `get-query-service-optimization.md`.

**Issues identified in the original query:**
- 7 redundant VEGUNIT subqueries (85% unnecessary table scans)
- Redundant SS self-join
- 120+ column JSON payload
- Non-deterministic TOP 1 forester selection
- Repeated date parsing

### 1.2 Refactoring Steps for AssessmentQueries.php

**File:** `/app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php`

#### Step 1: Add Optimized CTE Helper Methods to SqlFragmentHelpers

Add these new methods to the `SqlFragmentHelpers.php` trait:

```php
/**
 * Build CTE for pre-aggregated VEGUNIT statistics.
 * Consolidates 7 separate subqueries into a single table scan.
 */
private static function unitStatsCte(string $jobGuidRef): string
{
    $validUnit = self::validUnitFilter();

    return "UnitStats AS (
        SELECT
            JOBGUID,
            COUNT(CASE WHEN ASSDDATE IS NOT NULL AND ASSDDATE != '' THEN 1 END) AS Total_Units_Planned,
            COUNT(CASE WHEN PERMSTAT = 'Approved' THEN 1 END) AS Total_Approvals,
            COUNT(CASE WHEN PERMSTAT IN ('Pending', '') OR PERMSTAT IS NULL THEN 1 END) AS Total_Pending,
            COUNT(CASE WHEN PERMSTAT = 'No Contact' THEN 1 END) AS Total_No_Contacts,
            COUNT(CASE WHEN PERMSTAT = 'Refusal' THEN 1 END) AS Total_Refusals,
            COUNT(CASE WHEN PERMSTAT = 'Deferred' THEN 1 END) AS Total_Deferred,
            COUNT(CASE WHEN PERMSTAT = 'PPL Approved' THEN 1 END) AS Total_PPL_Approved,
            MAX(CASE WHEN FORESTER IS NOT NULL AND FORESTER != '' THEN FORESTER END) AS Forester
        FROM VEGUNIT
        WHERE JOBGUID = {$jobGuidRef}
          AND {$validUnit}
        GROUP BY JOBGUID
    )";
}

/**
 * Build CTE for pre-aggregated station footage.
 */
private static function stationFootageCte(string $jobGuidRef): string
{
    return "StationFootage AS (
        SELECT
            JOBGUID,
            CAST(SUM(SPANLGTH) AS DECIMAL(10,2)) AS Total_Footage
        FROM STATIONS
        WHERE JOBGUID = {$jobGuidRef}
        GROUP BY JOBGUID
    )";
}
```

#### Step 2: Create Optimized getAllByJobGuid Method

Replace the current `getAllByJobGuid()` method with an optimized version that:
- Uses CTEs instead of 7 correlated subqueries
- Removes the redundant SS self-join
- Adds deterministic forester selection via MAX()
- Pre-calculates scope year in CTE

#### Step 3: Add Column Reduction Option

Create a configuration option in `config/ws_assessment_query.php`:

```php
'vegunit_json_columns' => [
    'summary' => ['UNITGUID', 'UNIT', 'STATNAME', 'PERMSTAT', 'ASSDDATE', 'FORESTER', 'ADDRESS', 'CITY', 'FIRSTNAME', 'LASTNAME', 'PHONE'],
    'detail' => [...], // Full column list
],
```

---

## Part 2: Planner Activity System Integration

### 2.1 New Files to Create

The Planner Activity system requires the following new files:

| Source File | Target Location | Purpose |
|-------------|-----------------|---------|
| `PlannerActivityService.php` | `app/Services/WorkStudio/Services/PlannerActivityService.php` | Business logic |
| `PlannerActivityController.php` | `app/Http/Controllers/Api/WorkStudio/PlannerActivityController.php` | API endpoints |
| `planner-activity-routes.php` | `routes/planner-activity.php` | Route definitions |
| `ws_assessment_query.php` (partial) | Merge into `config/ws_assessment_query.php` | Configuration |
| `planner-activity-query.sql` | Reference only | SQL documentation |

### 2.2 Architectural Refactoring Required

The source `PlannerActivityService.php` uses Laravel's `DB::` facade directly, which contradicts the project's convention of executing raw SQL through the WorkStudio GETQUERY API. **Critical refactoring is needed.**

#### Current Architecture Pattern (WS-TrackerV1):
```
Controller -> GetQueryService -> WorkStudio API (GETQUERY protocol)
```

#### Required Refactoring for Planner Activity:
```
PlannerActivityController -> GetQueryService -> PlannerActivityQueries (SQL strings) -> WorkStudio API
```

### 2.3 Step-by-Step Integration

#### Step 1: Create PlannerActivityQueries Class

**Location:** `/app/Services/WorkStudio/AssessmentsDx/Queries/PlannerActivityQueries.php`

This class should follow the same pattern as `AssessmentQueries.php`:
- Static methods returning SQL strings
- Use the `SqlFragmentHelpers` trait
- Accept parameters for jobGuid, date filters, and planner filters

**Methods to implement:**
- `dailyActivityQuery(string $jobGuid, ?string $startDate, ?string $endDate, ?string $planner): string`
- `plannerSummaryQuery(string $jobGuid, ?string $startDate, ?string $endDate): string`
- `plannerDetailQuery(string $jobGuid, string $plannerName, ?string $startDate, ?string $endDate): string`
- `multiPlannerStationsQuery(string $jobGuid): string`
- `assessmentProgressQuery(string $jobGuid): string`

#### Step 2: Add SQL Fragment Helpers for Planner Activity

Add to `SqlFragmentHelpers.php`:

```php
/**
 * Parse /Date(ms)/ format using Unix epoch conversion.
 * More robust than the string-based approach.
 */
private static function parseMsDateToDateUnix(string $column): string
{
    return "CAST(
        DATEADD(SECOND,
            CAST(REPLACE(REPLACE({$column}, '/Date(', ''), ')/', '') AS BIGINT) / 1000,
            '1970-01-01'
        ) AS DATE
    )";
}

/**
 * Get non-work unit codes for SQL IN clause.
 */
private static function nonWorkUnitsInClause(): string
{
    $units = config('ws_assessment_query.non_work_units', ['NW', 'NOT', 'SENSI']);
    return "'" . implode("', '", $units) . "'";
}
```

#### Step 3: Create Refactored PlannerActivityService

**Location:** `/app/Services/WorkStudio/Services/PlannerActivityService.php`

This service should delegate to `GetQueryService` for all database access:

```php
<?php

namespace App\Services\WorkStudio\Services;

use App\Services\WorkStudio\AssessmentsDx\Queries\PlannerActivityQueries;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlannerActivityService
{
    public function __construct(
        private GetQueryService $queryService,
    ) {}

    public function getDailyActivity(
        string $jobGuid,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $plannerFilter = null
    ): Collection {
        $sql = PlannerActivityQueries::dailyActivityQuery(
            $jobGuid,
            $startDate?->toDateString(),
            $endDate?->toDateString(),
            $plannerFilter
        );

        return $this->queryService->executeAndHandle($sql);
    }

    // ... additional methods
}
```

#### Step 4: Create PlannerActivityController

**Location:** `/app/Http/Controllers/Api/WorkStudio/PlannerActivityController.php`

The controller from the source files can be used with minor modifications:
- Add namespace declaration for V1 project
- Inject `PlannerActivityService`
- Add proper Laravel Form Request validation classes

#### Step 5: Update Configuration

Merge the following into `/config/ws_assessment_query.php`:

```php
// Non-work unit codes (get footage but not unit count)
'non_work_units' => ['NW', 'NOT', 'SENSI'],

// Work unit codes (for reference)
'work_units' => [
    'SPM', 'SPB', 'MPM', 'MPB',
    'REM612', 'REM1218', 'REM1824', 'REM2430', 'REM3036',
    'ASH612', 'ASH1218', 'ASH1824', 'ASH2430', 'ASH3036',
    'VPS', 'BRUSH', 'HCB', 'BRUSHTRIM', 'HERBA', 'HERBNA',
],

// Conversion factor
'meters_to_miles' => 1609.34,

// Excluded station patterns
'excluded_station_patterns' => ['%EX%'],
```

#### Step 6: Add Routes

**Option A:** Add to existing `/routes/workstudioAPI.php`

**Option B:** Create new `/routes/planner-activity.php` and include in `bootstrap/app.php`

Routes to add:
```php
Route::prefix('assessments/{jobGuid}/planner-activity')
    ->group(function () {
        Route::get('daily', [PlannerActivityController::class, 'daily']);
        Route::get('summary', [PlannerActivityController::class, 'summary']);
        Route::get('planner/{plannerName}', [PlannerActivityController::class, 'plannerDetail']);
        Route::get('multi-planner-stations', [PlannerActivityController::class, 'multiPlannerStations']);
        Route::get('progress', [PlannerActivityController::class, 'progress']);
    });
```

---

## Part 3: Testing Plan

### 3.1 Unit Tests

Create test file: `tests/Feature/Services/PlannerActivityServiceTest.php`

Tests to implement:
- `test_daily_activity_returns_expected_structure`
- `test_daily_activity_filters_by_date_range`
- `test_daily_activity_filters_by_planner`
- `test_planner_summary_aggregates_correctly`
- `test_non_work_units_excluded_from_work_count`
- `test_first_unit_wins_footage_attribution`
- `test_multi_planner_stations_detected`
- `test_assessment_progress_calculates_correctly`

### 3.2 Integration Tests

Create test file: `tests/Feature/Api/PlannerActivityApiTest.php`

Tests to implement:
- API endpoint accessibility
- JSON response structure validation
- Query parameter validation (dates, planner filter)
- Error handling for invalid jobGuid

### 3.3 Manual Testing Checklist

- [ ] Execute each query variant with known test jobGuid
- [ ] Compare query execution times before/after optimization
- [ ] Verify JSON response structure matches documentation
- [ ] Test date filtering boundaries
- [ ] Verify "First Unit Wins" logic with multi-planner stations

---

## Part 4: Performance and Caching Considerations

### 4.1 Query Optimization Benefits

From the `get-query-service-optimization.md` analysis:
- **Query execution time:** 60-80% reduction expected
- **JSON payload size:** ~90% reduction (when using summary columns)
- **Table scans:** 7+ reduced to 1 for VEGUNIT statistics

### 4.2 Caching Recommendations

Add Laravel caching for expensive queries:

```php
// In PlannerActivityService
public function getAssessmentProgress(string $jobGuid): object
{
    return Cache::remember(
        "planner-activity-progress:{$jobGuid}",
        now()->addMinutes(15),
        fn() => $this->fetchAssessmentProgress($jobGuid)
    );
}
```

### 4.3 Index Recommendations

Recommend the following indexes to the database administrator:

```sql
-- Primary index for unit statistics
CREATE NONCLUSTERED INDEX IX_VEGUNIT_JobStats
ON VEGUNIT (JOBGUID, UNIT, PERMSTAT)
INCLUDE (ASSDDATE, FORESTER, FRSTR_USER)
WHERE UNIT IS NOT NULL AND UNIT != '' AND UNIT != 'NW';

-- Index for planner activity queries
CREATE NONCLUSTERED INDEX IX_VEGUNIT_PlannerActivity
ON VEGUNIT (JOBGUID, STATNAME, UNIT)
INCLUDE (FORESTER, FRSTR_USER, ASSDDATE, EDITDATE);

-- Station footage index
CREATE INDEX IX_STATIONS_Footage
ON STATIONS (JOBGUID, STATNAME)
INCLUDE (SPANLGTH)
WHERE STATNAME NOT LIKE '%EX%';
```

---

## Part 5: Implementation Sequence

### Phase 1: Foundation (Low Risk)

1. Merge configuration additions into `config/ws_assessment_query.php`
2. Add new SQL fragment helpers to `SqlFragmentHelpers.php`
3. Create `PlannerActivityQueries.php` with SQL string methods

### Phase 2: Service Layer (Medium Risk)

4. Create `PlannerActivityService.php` with GetQueryService delegation
5. Add unit tests for service methods
6. Manually test SQL queries via GetQueryService

### Phase 3: API Layer

7. Create API controller directory: `app/Http/Controllers/Api/WorkStudio/`
8. Create `PlannerActivityController.php`
9. Add routes to workstudioAPI.php
10. Add API integration tests

### Phase 4: GetQueryService Optimization

11. Add CTE helper methods to SqlFragmentHelpers
12. Create optimized version of `getAllByJobGuid()` in AssessmentQueries
13. Add configuration for column selection (summary vs detail)
14. Test performance improvement

### Phase 5: Documentation and Cleanup

15. Update CHANGELOG.md
16. Add API documentation
17. Create PHPDoc blocks for all new methods

---

## Critical Files for Implementation

- `/app/Services/WorkStudio/AssessmentsDx/Queries/SqlFragmentHelpers.php` - Add new CTE helper methods and date parsing utilities
- `/app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php` - Refactor getAllByJobGuid() with CTE-based optimization
- `/app/Services/WorkStudio/Services/GetQueryService.php` - Add new delegation methods for planner activity queries
- `/config/ws_assessment_query.php` - Merge non_work_units and other configuration values
- `/routes/workstudioAPI.php` - Add planner activity API route definitions

## Source Files Reference

Located at `/home/arbman/WorkStudioDev/bmad_workstudio_skill/workstudio_output/bmb-creations/query-optimizations/`:

| File | Purpose |
|------|---------|
| `get-query-service-optimization.md` | Analysis of current query issues and optimization recommendations |
| `planner-activity-query.sql` | Raw SQL for planner activity queries |
| `PLANNER-ACTIVITY-README.md` | Documentation for planner activity feature |
| `PlannerActivityController.php` | Source controller (needs refactoring) |
| `PlannerActivityService.php` | Source service (needs refactoring for GetQueryService pattern) |
| `planner-activity-routes.php` | Route definitions |
| `ws_assessment_query.php` | Configuration values to merge |

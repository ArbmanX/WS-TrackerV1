# Session Handoff — Phase 4 Ready

**Start message:** `Read docs/session-handoffs/2026-02-13-phase4-ready.md and start Phase 4`

## Branch & Status

- **Branch:** `feature/data-collection-architecture` (all work uncommitted)
- **Full tech spec:** `docs/specs/tech-spec-data-collection-architecture.md` (only read if you need something not covered below)
- **WIP tracker:** `docs/wip.md`
- **Test suite:** 443 passed, 0 failures, 3 pre-existing skips

### Completed Phases

| Phase | What | Tests |
|-------|------|-------|
| 1 | 4 migrations + config | Verified via migrate |
| 2 | 4 models + 4 factories | 45 tests, 126 assertions |
| 3 | 3 query builder classes | 59 tests, 139 assertions |

## Phase 4: What to Build

### Directory Structure

```
app/Services/WorkStudio/DataCollection/
├── CareerLedgerService.php          # NEW — Phase 4
├── LiveMonitorService.php           # NEW — Phase 4
├── GhostDetectionService.php        # NEW — Phase 4
└── Queries/                         # EXISTS — Phase 3
    ├── CareerLedgerQueries.php      # 5 methods (getDailyFootageAttribution, getBatch, getReworkDetails, getAssessmentTimeline, getWorkTypeBreakdown)
    ├── LiveMonitorQueries.php       # 6 methods (getPermissionBreakdown, getUnitCounts, getNotesCompliance, getEditRecency, getAgingUnits, getWorkTypeBreakdown)
    └── GhostDetectionQueries.php    # 3 methods (getRecentOwnershipChanges, getUnitGuidsForAssessment, getAssessmentExtension)

app/Events/AssessmentClosed.php      # NEW — Phase 4
app/Listeners/ProcessAssessmentClose.php  # NEW — Phase 4
```

### Task 4.1: CareerLedgerService

**File:** `app/Services/WorkStudio/DataCollection/CareerLedgerService.php`

**Constructor deps:** `GetQueryService`, `CareerLedgerQueries`

**Methods:**

1. `importFromJson(string $path): array` — Bootstrap import
   - Read JSON file, validate structure
   - Bulk insert into `planner_career_entries` with `source = 'bootstrap'`
   - Skip where `planner_username + job_guid` already exists (idempotent)
   - Return `['imported' => int, 'skipped' => int, 'errors' => int]`

2. `exportToJson(string $path): int` — Generate bootstrap JSON from API
   - Query API for all CLOSE assessments
   - For each: run `CareerLedgerQueries::getDailyFootageAttributionBatch()` (chunk by JOBGUID)
   - Write JSON file, return count

3. `appendFromMonitor(AssessmentMonitor $monitor): PlannerCareerEntry` — On close event
   - Query `CareerLedgerQueries::getDailyFootageAttribution($monitor->job_guid)` for daily_metrics
   - Query `CareerLedgerQueries::getWorkTypeBreakdown($monitor->job_guid)` for summary_totals
   - Query `CareerLedgerQueries::getAssessmentTimeline($monitor->job_guid)` for dates
   - If status was REWRK: query `CareerLedgerQueries::getReworkDetails($monitor->job_guid)`
   - Create `PlannerCareerEntry` with `source = 'live_monitor'`

### Task 4.2: LiveMonitorService

**File:** `app/Services/WorkStudio/DataCollection/LiveMonitorService.php`

**Constructor deps:** `GetQueryService`, `LiveMonitorQueries`

**Methods:**

1. `runDailySnapshot(): array` — Main cron entry point
   - Get all ACTIV/QC/REWRK assessments (use existing `AssessmentQueries::getActiveAssessmentsOrderedByOldest`)
   - For each: call `snapshotAssessment()`
   - Call `detectClosedAssessments()`
   - Return stats `['snapshots' => int, 'new' => int, 'closed' => int]`

2. `snapshotAssessment(string $jobGuid, array $assessmentData): void`
   - Query all 6 LiveMonitorQueries methods for this jobGuid
   - Build snapshot JSONB matching this structure:
     ```php
     [
         'permission_breakdown' => [...],
         'unit_counts' => ['work_units' => int, 'nw_units' => int, 'total_units' => int],
         'work_type_breakdown' => [...],
         'footage' => ['completed_feet' => float, 'completed_miles' => float, 'percent_complete' => float],
         'notes_compliance' => ['units_with_notes' => int, 'units_without_notes' => int, 'compliance_percent' => float],
         'planner_activity' => ['last_edit_date' => string, 'days_since_last_edit' => int],
         'aging_units' => ['pending_over_threshold' => int, 'threshold_days' => int],
         'suspicious' => bool,
     ]
     ```
   - **Sanity check:** If `total_units = 0` and previous snapshot had `> 0`, set `suspicious = true`
   - Upsert `AssessmentMonitor` — use `addSnapshot(string $date, array $data)` method (already on model)
   - For new assessments: create `AssessmentMonitor` row first

3. `detectClosedAssessments(): Collection`
   - Compare current active assessments with existing `assessment_monitors` rows
   - Any monitor row whose job_guid is no longer in the active list → dispatch `AssessmentClosed` event

### Task 4.3: GhostDetectionService

**File:** `app/Services/WorkStudio/DataCollection/GhostDetectionService.php`

**Constructor deps:** `GetQueryService`, `GhostDetectionQueries`

**Methods:**

1. `checkForOwnershipChanges(): int` — Find new ONEPPL takeovers
   - Query `GhostDetectionQueries::getRecentOwnershipChanges('ONEPPL', $since)`
   - For each new takeover: call `createBaseline()`
   - `$since` = last check date (from most recent `ghost_ownership_periods.created_at` or 7 days ago)

2. `createBaseline(string $jobGuid, string $username, bool $isParent): GhostOwnershipPeriod`
   - Query `GhostDetectionQueries::getUnitGuidsForAssessment($jobGuid)` for snapshot
   - Create `GhostOwnershipPeriod` with baseline_snapshot JSONB

3. `runComparison(GhostOwnershipPeriod $period): int` — Daily ghost check
   - Query current UNITGUIDs for assessment
   - Set difference: `baseline_unitguids - current_unitguids` = missing
   - Exclude already-recorded evidence (by unitguid)
   - Create `GhostUnitEvidence` rows for newly missing
   - Return count of new ghosts found

4. `resolveOwnershipReturn(GhostOwnershipPeriod $period): void`
   - Final comparison (same as runComparison)
   - Update period: `return_date`, `status = 'resolved'`

5. `cleanupOnClose(string $jobGuid): void`
   - Delete `ghost_ownership_periods` WHERE `job_guid` (FK ON DELETE SET NULL preserves evidence)

### Task 4.4: AssessmentClosed Event + Listener

**Event:** `app/Events/AssessmentClosed.php`
```php
class AssessmentClosed
{
    public function __construct(
        public readonly AssessmentMonitor $monitor,
        public readonly string $jobGuid,
    ) {}
}
```

**Listener:** `app/Listeners/ProcessAssessmentClose.php`
- `CareerLedgerService::appendFromMonitor($event->monitor)`
- `GhostDetectionService::cleanupOnClose($event->jobGuid)`
- Delete the `AssessmentMonitor` row
- Wrap in `DB::transaction()`
- Use Laravel 12 attribute-based event discovery (no manual registration needed)

### Task 4.5: Register in WorkStudioServiceProvider

Add bindings for the 3 new services in `app/Providers/WorkStudioServiceProvider.php`.

## Key Patterns to Follow

### How GetQueryService works

Query builders return SQL strings. Services execute them via `GetQueryService::executeQuery()`:

```php
$sql = $this->queries->getPermissionBreakdown($jobGuid);
$results = $this->queryService->executeQuery($sql);
// $results is ?array — the raw JSON response from DDOProtocol API
```

`executeQuery()` takes a raw SQL string and an optional `$userId` for credentials. For service-account queries (cron jobs), pass `null` for userId.

### How UserQueryContext works

Query builders need `UserQueryContext` in their constructor. For cron/service contexts (no authenticated user), build one from config:

```php
// From an authenticated user (Livewire, controller):
$context = UserQueryContext::fromUser(auth()->user());

// For cron/service account — build from config with all regions:
$context = new UserQueryContext(
    resourceGroups: config('workstudio_resource_groups.all_regions', []),
    contractors: config('ws_assessment_query.contractors'),
    domain: config('workstudio.service_account.domain', 'ASPLUNDH'),
    username: 'service',
    userId: null,
);
```

### DDOProtocol date parsing in PHP

API returns dates as `/Date(2026-01-05T15:12:33.803Z)/`. Parse with:
```php
Carbon::createFromFormat('m-d-Y', $value)  // for CONVERT(VARCHAR(10), date, 110) format
// OR strip the wrapper manually:
$raw = str_replace(['/Date(', ')/'], '', $value);
Carbon::parse($raw);
```

### Existing model methods to use

- `AssessmentMonitor::addSnapshot(string $date, array $snapshot): void` — handles daily_snapshots accumulation + latest_snapshot denorm
- `PlannerCareerEntry` scopes: `forPlanner()`, `forRegion()`, `forScopeYear()`, `fromBootstrap()`, `fromLiveMonitor()`
- `GhostOwnershipPeriod` scopes: `active()`, `resolved()`, `parentTakeovers()`
- `GhostUnitEvidence` scopes: `forAssessment()`, `detectedBetween()`
- Factory states: `withWorkStudio()` for User, `withSnapshots($days)` for monitors, `withRework()` for career entries

### Test patterns

```php
// Feature test with RefreshDatabase
uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

// Mock HTTP for API calls
Http::fake([
    '*/GETQUERY' => Http::response(['Protocol' => 'QUERYRESULT', 'Data' => [...]], 200),
]);

// Permission-gated routes need seeding
$this->seed(RolePermissionSeeder::class);
$user = User::factory()->withWorkStudio()->withRole('admin')->create();
```

## Config Values the Services Need

```php
// ws_data_collection.php
config('ws_data_collection.thresholds.aging_unit_days')          // 14
config('ws_data_collection.thresholds.notes_compliance_area_sqm') // 9.29
config('ws_data_collection.ghost_detection.oneppl_domain')       // 'ONEPPL'
config('ws_data_collection.sanity_checks.flag_zero_count')       // true
config('ws_data_collection.career_ledger.bootstrap_path')        // storage_path(...)

// ws_assessment_query.php (existing)
config('ws_assessment_query.scope_year')
config('ws_assessment_query.job_types.assessments')
config('ws_assessment_query.excludedUsers')
```

## Gotchas

- **DDOProtocol does NOT support CTEs** — all queries use derived tables (already handled in Phase 3 query builders)
- **CLOSE is terminal** — career entries are write-once, never updated
- **Spatie caches permissions** — call `forgetCachedPermissions()` in seeders
- **`CarbonImmutable` loop bug** — use `$date = $date->addDay()` not just `$date->addDay()` (bug fixed in Phase 2 factories)
- **`WITHIN GROUP` contains 'WITH'** — CTE test regex: `->not->toMatch('/\bWITH\b(?!IN)/')`
- **Artisan boolean options** return strings — use `filter_var($val, FILTER_VALIDATE_BOOLEAN)` for PostgreSQL booleans

## Remaining Phases After 4

- Phase 5: Artisan Commands (`ws:import-career-ledger`, `ws:export-career-ledger`, `ws:run-live-monitor`)
- Phase 6: Integration Testing
- Phase 7: Documentation & Cleanup (CHANGELOG, pint, project-context.md update)

## Files NOT to Read (already done, don't waste tokens)

- `docs/TODO.md` — large file, not relevant to Phase 4 implementation
- `docs/project-context.md` — MEMORY.md covers what you need
- `docs/CODE-REVIEW.md` — historical, not relevant
- `docs/plans/` — old task plans, superseded by tech spec
- Phase 1-3 test files — they pass, no action needed

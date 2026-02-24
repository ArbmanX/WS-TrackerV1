---
title: 'FetchAssessments Service Layer Refactor'
slug: 'fetch-assessments-service-refactor'
created: '2026-02-23'
status: 'completed'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Laravel 12', 'PostgreSQL', 'Pest 4']
files_to_modify:
  - app/Services/WorkStudio/Client/GetQueryService.php             # MODIFY — use Http::workstudio() macro in executeQuery()
  - app/Services/WorkStudio/Assessments/Queries/FetchAssessmentQueries.php  # NEW — standalone SQL query class
  - app/Console/Commands/Fetch/FetchAssessments.php               # MODIFY — delegate to query class + GetQueryService, add --users flag, fix --full logic
  - tests/Feature/Commands/FetchAssessmentsCommandTest.php         # MODIFY — update tests for refactored command
code_patterns:
  - 'Standalone query class pattern: DailyFootageQuery — static methods, no UserQueryContext, no AbstractQueryBuilder'
  - 'Command injects GetQueryService via handle() parameter — constructor injection pattern'
  - 'Http::workstudio() macro: timeout(60), connectTimeout(10), verify=false — defined in WorkStudioServiceProvider'
  - 'GetQueryService::executeAndHandle() → executeQuery() + transformArrayResponse() (heading/data array_combine)'
  - 'ApiCredentialManager::getServiceAccountCredentials() returns {username, password, user_id: null, type: service}'
  - 'ApiCredentialManager::formatDbParameters() static method for DBParameters POST body string'
  - 'WSHelpers::toSqlInClause() for SQL IN clause formatting'
  - 'WSSQLCaster::cast() for OLE date column casting in SQL'
  - 'Config source: config(workstudio.assessments.*) and config(workstudio.statuses.*)'
test_patterns:
  - 'Pest 4 with uses(RefreshDatabase::class)'
  - 'Http::fake([*/GETQUERY => Http::response(...)]) for API mocking'
  - 'Http::assertSent(fn($request) => str_contains($request->data()[SQL], ...)) for SQL verification'
  - 'Helper functions: fakeAssessmentRow(overrides), fakeAssessmentsResponse(rows), createMatchingCircuit(name)'
  - 'Factory states: Assessment::factory()->split(parentGuid), ->withStatus(), ->withJobType()'
  - 'Circuit::factory()->create([properties => [raw_line_name => ...]]) for resolution tests'
  - 'Time travel: $this->travel(-1)->hours() for timestamp preservation tests'
---

# Tech-Spec: FetchAssessments Service Layer Refactor

**Created:** 2026-02-23

## Overview

### Problem Statement

The `FetchAssessments` command bypasses the WorkStudio service layer entirely. It builds SQL inline (~30 lines of string concatenation), makes raw `Http::withBasicAuth()` calls without the `workstudio` macro, and duplicates response parsing logic (heading/data array_combine) that already exists in `GetQueryService`. This means:

- SSL settings, timeouts, and retry logic from the `workstudio` macro are not applied
- SQL construction is tangled with command orchestration logic
- The `--full` flag has a broken condition that still applies incremental filtering when records exist in the database
- No way to filter by user or fetch specific JOBGUIDs programmatically

### Solution

Extract the SQL into a dedicated `FetchAssessmentQueries` class, route HTTP calls through `GetQueryService` using the `Http::workstudio()` macro, add a `--users` flag, and provide a JOBGUID-scoped query method for programmatic use.

### In Scope

- New `FetchAssessmentQueries` query class with:
  - Main fetch query (existing SQL refactored out of the command)
  - Stub method accepting username(s) + optional scope year (implementation deferred)
  - JOBGUID-scoped query method (array of GUIDs = sole WHERE condition)
- Refactor `FetchAssessments` command to inject `GetQueryService` and delegate API calls
- `--users` flag on command (one or multiple usernames, no filtering if absent)
- Fix `GetQueryService::executeQuery()` to use `Http::workstudio()` macro
- Fix `--full` flag logic

### Out of Scope

- `QueryExplorer` Livewire component refactor
- `WorkStudioApiService` constructor refactor
- Other fetch commands
- N+1 circuit query optimization in `updateCircuitJobGuids()`
- Username WHERE clause implementation (deferred — stub only)

---

## Implementation Tasks

### Task 1: Fix `GetQueryService::executeQuery()` to use `Http::workstudio()` macro

- File: `app/Services/WorkStudio/Client/GetQueryService.php`
- Action: In `executeQuery()` (line 41), replace `Http::withBasicAuth(...)` with `Http::workstudio()->withBasicAuth(...)`
- Details:
  - Current: `Http::withBasicAuth($credentials['username'], $credentials['password'])->timeout(120)->connectTimeout(30)->withOptions([...])->post(...)`
  - Replace with: `Http::workstudio()->withBasicAuth($credentials['username'], $credentials['password'])->timeout(120)->withOptions(['on_stats' => ...])->post(...)`
  - The macro already sets `connectTimeout(10)` — the existing `connectTimeout(30)` override can stay if desired, but `timeout(120)` should remain since `executeQuery` is used by many callers
  - Keep the `on_stats` transfer time logger as-is
- Why first: This is the foundation — all other tasks delegate through this method

### Task 2: Create `FetchAssessmentQueries` standalone query class

- File: `app/Services/WorkStudio/Assessments/Queries/FetchAssessmentQueries.php` (NEW)
- Action: Create standalone query class following `DailyFootageQuery` pattern
- Methods:

**`buildFetchQuery(?string $year, ?string $status, ?float $maxEditDateOle): string`**
- Extract the full SQL from `FetchAssessments::fetchFromApi()` (lines 74–117)
- Parameters replace the inline conditionals:
  - `$year` — if set, adds `AND xref.WP_STARTDATE LIKE '%{$year}%'`
  - `$status` — if set, adds `AND SS.STATUS = '{$status}'`; if null, uses `config('workstudio.statuses.planner_concern')` IN clause
  - `$maxEditDateOle` — if set, adds `AND VEGJOB.EDITDATE > {$maxEditDateOle}` (incremental sync)
- Uses `WSHelpers::toSqlInClause()` for job types and statuses
- Uses `WSSQLCaster::cast('VEGJOB.EDITDATE')` for the EDITDATE column
- Ends with `ORDER BY SS.JOBGUID`

**`buildFetchByJobGuids(array $jobGuids): string`**
- Takes an array of JOBGUID strings
- Builds a minimal query: same SELECT columns as `buildFetchQuery`, same FROM/JOIN structure
- WHERE clause is ONLY `SS.JOBGUID IN ({jobGuids})` — no status, year, job type, or EDITDATE filtering
- Uses `WSHelpers::toSqlInClause($jobGuids)` for the IN clause

**`forUsers(array|string $usernames, ?string $scopeYear = null): void`** (STUB)
- Accepts single username string or array of usernames
- Accepts optional scope year
- Method body: empty — implementation deferred
- Docblock: explains this will be implemented later for user-scoped queries

### Task 3: Refactor `FetchAssessments` command

- File: `app/Console/Commands/Fetch/FetchAssessments.php`
- Action: Delegate API call to `GetQueryService` via `FetchAssessmentQueries`

**Signature changes:**
- Add `{--users=* : Filter by username(s). Accepts multiple values}` to `$signature`

**`handle()` method changes:**
- Add `GetQueryService $queryService` parameter (Laravel auto-injects)
- Capture `$users = $this->option('users')` (returns array, empty if not provided)
- Pass `$queryService` through to `fetchFromApi()`

**`fetchFromApi()` refactor:**
- Remove all inline SQL construction (lines 68–117)
- Determine `$maxEditDateOle`:
  ```php
  $maxEditDateOle = null;
  if (!$full) {
      $maxEditDateOle = Assessment::max('last_edited_ole');
  }
  ```
- Build SQL via: `$sql = FetchAssessmentQueries::buildFetchQuery($year, $status, $maxEditDateOle)`
- Execute via: `$rows = $queryService->executeAndHandle($sql)`
- Return the `Collection` directly (no more manual `array_combine` — `executeAndHandle` does it)
- Remove the raw `Http::withBasicAuth()` call, the `ApiCredentialManager` resolution, the manual response parsing, and the try/catch around the HTTP call (errors now propagate from `GetQueryService`)

**`--users` handling:**
- If `$users` is non-empty, call `FetchAssessmentQueries::forUsers($users, $year)` (stub — no-op for now)

**`--full` fix:**
- The broken condition `if ($assessments->count() > 0 || !$full)` is replaced by the `$maxEditDateOle` logic above — `$maxEditDateOle` is only calculated when `!$full`, so `--full` correctly bypasses it

### Task 4: Update tests

- File: `tests/Feature/Commands/FetchAssessmentsCommandTest.php`
- Action: Update tests to work with the refactored command

**What changes:**
- The SQL is now built by `FetchAssessmentQueries` and executed via `GetQueryService::executeAndHandle()` which calls `executeQuery()` — `Http::fake()` still intercepts at the same level (`*/GETQUERY`), so most tests should work with minimal changes
- `Http::assertSent()` SQL assertions remain valid — the SQL content is the same, just built elsewhere
- The `--full` test (line 314) should now correctly verify that `--full` bypasses EDITDATE filtering even when DB has records

**New tests to add:**
- `--users flag passes usernames to command without error` — verify the flag is accepted and doesn't crash (stub is a no-op)
- `--users flag accepts multiple values` — verify `--users=alice --users=bob` parses correctly
- `FetchAssessmentQueries::buildFetchByJobGuids filters only by provided GUIDs` — unit test verifying the SQL contains only JOBGUID IN clause, no status/year/jobtype filters
- `FetchAssessmentQueries::buildFetchQuery includes incremental filter when maxEditDateOle provided` — unit test
- `FetchAssessmentQueries::buildFetchQuery omits incremental filter when maxEditDateOle is null` — unit test

**Existing tests that may need adjustment:**
- Tests that assert on SQL content should still pass since the SQL structure is identical
- The incremental sync test should be updated to verify the `--full` fix works correctly when DB has records

---

## Acceptance Criteria

- [ ] AC 1: Given `GetQueryService::executeQuery()` is called, when the HTTP request is made, then it uses `Http::workstudio()` macro (verify SSL disabled, timeout from config)
- [ ] AC 2: Given `FetchAssessmentQueries::buildFetchQuery()` is called with no arguments, when the SQL is generated, then it contains job type IN clause from config, planner_concern status IN clause from config, and ORDER BY SS.JOBGUID
- [ ] AC 3: Given `buildFetchQuery()` is called with `$year = '2026'`, when the SQL is generated, then it contains `xref.WP_STARTDATE LIKE '%2026%'`
- [ ] AC 4: Given `buildFetchQuery()` is called with `$status = 'ACTIV'`, when the SQL is generated, then it contains `SS.STATUS = 'ACTIV'` (not an IN clause)
- [ ] AC 5: Given `buildFetchQuery()` is called with `$maxEditDateOle = 46060.0`, when the SQL is generated, then it contains `VEGJOB.EDITDATE > 46060`
- [ ] AC 6: Given `buildFetchQuery()` is called with `$maxEditDateOle = null`, when the SQL is generated, then it does NOT contain `VEGJOB.EDITDATE >`
- [ ] AC 7: Given `buildFetchByJobGuids(['{GUID-1}', '{GUID-2}'])` is called, when the SQL is generated, then the WHERE clause contains ONLY `SS.JOBGUID IN (...)` with no status, year, or job type conditions
- [ ] AC 8: Given `ws:fetch-assessments --full` is run AND the database has existing assessments, when the command executes, then no EDITDATE incremental filter is applied to the API query
- [ ] AC 9: Given `ws:fetch-assessments --users=alice --users=bob` is run, when the command parses options, then it captures `['alice', 'bob']` and passes them to the stub method without error
- [ ] AC 10: Given `ws:fetch-assessments` is run without `--users`, when the command executes, then no user filtering is applied and the command completes normally
- [ ] AC 11: Given the command receives a valid API response, when `executeAndHandle()` returns the collection, then rows are correctly mapped with heading/data combine (same output as before)
- [ ] AC 12: Given all existing tests in `FetchAssessmentsCommandTest.php`, when run after refactor, then all 19 tests pass (behavioral parity)

---

## Dependencies

- No new packages or migrations required
- `GetQueryService` must be updated (Task 1) before command refactor (Task 3) can be tested end-to-end
- Existing `Http::workstudio()` macro in `WorkStudioServiceProvider` — no changes needed

## Testing Strategy

**Unit tests (new):**
- `FetchAssessmentQueries::buildFetchQuery()` — verify SQL output for each parameter combination
- `FetchAssessmentQueries::buildFetchByJobGuids()` — verify JOBGUID-only WHERE clause

**Feature tests (existing + updates):**
- All 19 existing tests should pass with behavioral parity
- Add `--users` flag tests (acceptance, multi-value)
- Update `--full` test to assert the fix works when DB has records

**Manual verification:**
- Run `ws:fetch-assessments --dry-run` against live API to confirm SQL still returns expected results
- Verify the `workstudio` macro is applied by checking SSL behavior in logs

## Notes

**Known limitations:**
- `WSHelpers::toSqlInClause()` does not escape single quotes — this is a pre-existing issue (adversarial review #4) and out of scope for this refactor
- `updateCircuitJobGuids()` N+1 issue remains — separate concern
- `forUsers()` is a stub — calling it does nothing until implementation

**Future considerations:**
- Once `forUsers()` is implemented, consider whether the `--users` flag should also scope the EDITDATE incremental logic per-user
- `GetQueryService::executeQuery()` timeout(120) may need to be configurable per-caller rather than hardcoded

---

## Context for Development

### Codebase Patterns

**Standalone Query Class Pattern (follow `DailyFootageQuery`):**
- NO `extends AbstractQueryBuilder`, NO `UserQueryContext`
- Static methods: `public static function build(...): string`
- Uses `WSHelpers::toSqlInClause()` and `WSSQLCaster::cast()` directly
- Config values read via `config('workstudio.assessments.*')`

**GetQueryService Delegation Pattern:**
- Command injects `GetQueryService` via `handle(GetQueryService $queryService)`
- Calls `$queryService->executeAndHandle($sql)` which:
  1. Calls `executeQuery($sql)` — HTTP POST to WorkStudio API
  2. Calls `transformArrayResponse()` — `array_combine(Heading, Data)` per row
  3. Returns `Collection` of associative arrays

**Http::workstudio() Macro:**
- `timeout(config('workstudio.timeout', 60))`, `connectTimeout(config('workstudio.connect_timeout', 10))`, `verify => false`
- Callers can chain `.timeout(180)` on top to override for long-running fetches
- Chain: `Http::workstudio()->withBasicAuth(...)->timeout(180)->post(...)`

**Credential Flow:**
- `ApiCredentialManager::getServiceAccountCredentials()` → `{username, password, user_id: null, type: 'service'}`
- `ApiCredentialManager::formatDbParameters($user, $pass)` → `"USER NAME={user}\r\nPASSWORD={pass}\r\n"` for POST body
- Both Basic Auth header AND DBParameters in POST body (DDOProtocol requirement)

### Files to Reference

| File | Purpose |
|------|---------|
| `app/Services/WorkStudio/Assessments/Queries/DailyFootageQuery.php` | Standalone query class pattern to follow |
| `app/Services/WorkStudio/Client/GetQueryService.php` | Service to delegate API calls to (needs macro fix) |
| `app/Services/WorkStudio/Client/ApiCredentialManager.php` | Credential resolution + formatting |
| `app/Providers/WorkStudioServiceProvider.php` | `Http::workstudio()` macro definition |
| `config/workstudio.php` | Config source for job_types, statuses, assessments settings |
| `app/Services/WorkStudio/Shared/Helpers/WSHelpers.php` | `toSqlInClause()` |
| `app/Services/WorkStudio/Shared/Helpers/WSSQLCaster.php` | `cast()` for OLE dates |
| `app/Console/Commands/Fetch/FetchAssessments.php` | Current command (source of SQL to extract) |
| `tests/Feature/Commands/FetchAssessmentsCommandTest.php` | 19 tests, 69 assertions — update for refactor |

### Technical Decisions

- **Query class architecture:** Standalone (like `DailyFootageQuery`), NOT extending `AbstractQueryBuilder` — no `UserQueryContext` needed for system-level command
- **Timeout override:** Command-level concern — callers set their own timeout on top of the macro
- **`--users` flag:** Pass-through only — the flag value is captured and passed to a stub method, implementation deferred
- **JOBGUID method:** When JOBGUIDs provided, they are the ONLY WHERE condition — no status, year, or job type filtering
- **`--full` fix:** `$maxEditDateOle` is only calculated when `!$full`, passed as null otherwise — cleanly eliminates the broken condition

## Review Notes
- Adversarial review completed
- Findings: 10 total, 0 fixed, 10 skipped
- Resolution approach: skip
- Notable deferred items: F1/F2 (GUID/input validation for SQL injection) — pre-existing pattern in `WSHelpers::toSqlInClause()`, F8 (timeout 180→120 regression)

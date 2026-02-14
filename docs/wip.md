# Work In Progress

## Feature: Data Collection Architecture
**Branch:** `feature/data-collection-architecture`
**Tech Spec:** `docs/specs/tech-spec-data-collection-architecture.md`
**Started:** 2026-02-13

### Phase 1: Database Layer — COMPLETE

| Task | Status |
|------|--------|
| 1.1 `planner_career_entries` migration | done |
| 1.2 `assessment_monitors` migration | done |
| 1.3 `ghost_ownership_periods` migration | done |
| 1.4 `ghost_unit_evidence` migration | done |
| 1.5 `config/ws_data_collection.php` | done |
| 1.6 Run & verify migrations | done |

### Phase 2: Models & Factories — COMPLETE

| Task | Status |
|------|--------|
| 2.1 `PlannerCareerEntry` model + factory | done |
| 2.2 `AssessmentMonitor` model + factory | done |
| 2.3 `GhostOwnershipPeriod` model + factory | done |
| 2.4 `GhostUnitEvidence` model + factory | done |
| 2.5 Feature tests (45 tests, 126 assertions) | done |

### Phase 3: Query Builders — COMPLETE

| Task | Status |
|------|--------|
| 3.0 Extract `validateGuid()` to `SqlFragmentHelpers` | done |
| 3.1 `CareerLedgerQueries` (5 methods, refactored from DailyFootageQuery) | done |
| 3.2 `LiveMonitorQueries` (6 methods: permissions, units, notes, edit recency, aging, work types) | done |
| 3.3 `GhostDetectionQueries` (3 methods: ownership changes, UNITGUID snapshot, EXT check) | done |
| 3.4 Unit tests (59 tests, 139 assertions) | done |

### Phase 4: Service Layer & Events — COMPLETE

| Task | Status |
|------|--------|
| 4.1 `CareerLedgerService` (import, export, appendFromMonitor) | done |
| 4.2 `LiveMonitorService` (runDailySnapshot, snapshotAssessment, detectClosedAssessments) | done |
| 4.3 `GhostDetectionService` (checkForOwnershipChanges, createBaseline, runComparison, resolve, cleanup) | done |
| 4.4 `AssessmentClosed` event + `ProcessAssessmentClose` listener | done |
| 4.5 Register services in `WorkStudioServiceProvider` | done |
| 4.6 Fix `GhostOwnershipPeriodFactory` snapshot key (`unit` → `unit_type`) | done |
| 4.7 Feature tests (24 tests, 100 assertions) | done |

### Phase 5: Artisan Commands — COMPLETE

| Task | Status |
|------|--------|
| 5.1 `ImportCareerLedger` command | done |
| 5.2 `ExportCareerLedger` command | done |
| 5.3 `RunLiveMonitor` command | done |
| 5.4 Register scheduler in `routes/console.php` | done |
| 5.5 Feature tests (14 tests, 46 assertions) | done |

### Phase 6: Integration Testing — COMPLETE

| Task | Status |
|------|--------|
| 6.1 `exportToJson` tests (3 tests: happy path, no CLOSE, empty API) | done |
| 6.2 `runDailySnapshot` tests (4 tests: multi-assessment, close detection, empty API, existing monitors) | done |
| 6.3 `checkForOwnershipChanges` tests (5 tests: new takeovers, skip tracked, parent flag, empty, since date) | done |

### Phase 7: Documentation & Cleanup — COMPLETE

| Task | Status |
|------|--------|
| 7.1 Update CHANGELOG.md | done |
| 7.2 Run `vendor/bin/pint --dirty` | done |
| 7.3 Run full test suite | done |
| 7.4 Update MEMORY.md | done |

### ALL PHASES COMPLETE — Ready to commit and merge

---

## Feature: Planner Career Ledger
**Branch:** `feature/data-collection-architecture` (additive, same branch)
**Started:** 2026-02-14

### Phase 1: Migration + Model + Factory — COMPLETE

| Task | Status |
|------|--------|
| 1.1 `planner_job_assignments` migration | done |
| 1.2 `PlannerJobAssignment` model | done |
| 1.3 `PlannerJobAssignmentFactory` | done |

### Phase 2: Query Builder — COMPLETE

| Task | Status |
|------|--------|
| 2.1 `PlannerCareerLedger` (7 methods, ASSDDATE-only) | done |

### Phase 3: Service — COMPLETE

| Task | Status |
|------|--------|
| 3.1 `PlannerCareerLedgerService` (discover, export, batch) | done |

### Phase 4: Artisan Command — COMPLETE

| Task | Status |
|------|--------|
| 4.1 `ExportPlannerCareer` command | done |

### Phase 5: Tests — COMPLETE

| Task | Status |
|------|--------|
| 5.1 Unit tests: query SQL validation (35 tests) | done |
| 5.2 Feature tests: model scopes, factory states (13 tests) | done |
| 5.3 Feature tests: service mocked tests (10 tests) | done |
| 5.4 Feature tests: command tests (3 tests) | done |

### Phase 6: Cleanup — COMPLETE

| Task | Status |
|------|--------|
| 6.1 Update CHANGELOG.md | done |
| 6.2 Run `vendor/bin/pint --dirty` | done |
| 6.3 Run full test suite | done |

### ALL FEATURES COMPLETE — Ready to commit and merge

### Test Suite Totals
- **537 passed**, 3 skipped (pre-existing), 1 failure (pre-existing: bootstrap file exists on disk), 1591 assertions
- **55 new Planner tests**, 131 assertions

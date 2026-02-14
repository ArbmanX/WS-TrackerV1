# Session Handoff — 2026-02-13 (Phase 2)

## Current Task Status

**Feature:** Data Collection Architecture
**Branch:** `feature/data-collection-architecture` (created from main, no commits yet)
**Tech Spec:** `docs/specs/tech-spec-data-collection-architecture.md`

### Phase 1: Database Layer — COMPLETE

All 4 migrations + config file created and migrated. See previous handoff for details.

### Phase 2: Models & Factories — COMPLETE

| File | Status | Key Details |
|------|--------|-------------|
| `app/Models/PlannerCareerEntry.php` | Done | 3 JSONB casts, 3 date casts, boolean cast, decimal casts, 5 scopes (forPlanner, forRegion, forScopeYear, fromBootstrap, fromLiveMonitor) |
| `app/Models/AssessmentMonitor.php` | Done | 2 JSONB casts, 2 date casts, decimal cast, `addSnapshot()` method, 4 scopes (active, inQc, inRework, forRegion) |
| `app/Models/GhostOwnershipPeriod.php` | Done | JSONB cast, boolean cast, 2 date casts, `hasMany(GhostUnitEvidence)`, 3 scopes (active, resolved, parentTakeovers) |
| `app/Models/GhostUnitEvidence.php` | Done | `const UPDATED_AT = null`, `belongsTo(GhostOwnershipPeriod)`, 2 date casts, 2 scopes (forAssessment, detectedBetween), explicit `$table = 'ghost_unit_evidence'` |
| `database/factories/PlannerCareerEntryFactory.php` | Done | States: `withRework()`, `fromBootstrap()`, `fromLiveMonitor()`. Generates realistic daily_metrics + summary_totals JSONB. |
| `database/factories/AssessmentMonitorFactory.php` | Done | States: `withSnapshots($days)`, `inQc()`, `inRework()`. Generates full snapshot structure matching tech spec. |
| `database/factories/GhostOwnershipPeriodFactory.php` | Done | States: `active()`, `resolved()`, `parentTakeover()`. Generates baseline_snapshot with unit arrays. |
| `database/factories/GhostUnitEvidenceFactory.php` | Done | Default creates with `GhostOwnershipPeriod::factory()` FK. Denormalized fields match tech spec. |

### Tests — 45 passed, 126 assertions

| Test File | Tests | Covers |
|-----------|-------|--------|
| `tests/Feature/DataCollection/PlannerCareerEntryTest.php` | 13 | Factory, JSONB casts, date casts, boolean cast, scopes, unique constraint, JSONB structure validation |
| `tests/Feature/DataCollection/AssessmentMonitorTest.php` | 13 | Factory, JSONB casts, date casts, unique constraint, scopes, withSnapshots state, `addSnapshot()` accumulation + denormalization |
| `tests/Feature/DataCollection/GhostOwnershipPeriodTest.php` | 10 | Factory, JSONB cast, date casts, boolean cast, hasMany relationship, scopes, factory states |
| `tests/Feature/DataCollection/GhostUnitEvidenceTest.php` | 9 | Factory, belongsTo relationship, nullable FK, no updated_at, date casts, scopes, ON DELETE SET NULL cascade |

Full suite: **384 passed, 0 failures** (3 pre-existing skips)

### Phase 3: Query Builders — NOT STARTED

Next step per tech spec. Create 3 query builder classes in `app/Services/WorkStudio/DataCollection/Queries/`:

1. **CareerLedgerQueries** — Refactor from existing `DailyFootageQuery::build()` into `AbstractQueryBuilder` pattern. First Unit Wins SQL for daily footage attribution. Also needs V_ASSESSMENT query for summary work type breakdown, and JOBHISTORY query for rework details.
2. **LiveMonitorQueries** — SQL for permission breakdown, unit counts, notes compliance, edit recency, aging units. Uses VEGUNIT + V_ASSESSMENT.
3. **GhostDetectionQueries** — SQL for UNITGUID snapshots (baseline capture), JOBHISTORY ownership change detection, current UNITGUID listing for set-difference comparison.

All should extend `AbstractQueryBuilder` and use `SqlFragmentHelpers` trait.

### Remaining Phases (4-7)

- Phase 4: Service Layer & Events (CareerLedgerService, LiveMonitorService, GhostDetectionService, AssessmentClosed event + listener)
- Phase 5: Artisan Commands (ws:import-career-ledger, ws:export-career-ledger, ws:run-live-monitor)
- Phase 6: Integration Testing
- Phase 7: Documentation & Cleanup

---

## Key Files to Read

| File | Why |
|------|-----|
| `docs/specs/tech-spec-data-collection-architecture.md` | **PRIMARY** — full spec with service architecture, query patterns, event system |
| `app/Services/WorkStudio/Assessments/Queries/DailyFootageQuery.php` | First Unit Wins SQL to refactor into CareerLedgerQueries |
| `app/Services/WorkStudio/Assessments/Queries/AbstractQueryBuilder.php` | Base class for new query builders |
| `app/Services/WorkStudio/Assessments/Queries/SqlFragmentHelpers.php` | Shared SQL fragments trait |
| `app/Models/AssessmentMonitor.php` | `addSnapshot()` method — key write path for Phase 4 |
| `docs/wip.md` | Current WIP status |

---

## Bug Fixed During Session

**CarbonImmutable date loop:** `now()` returns `CarbonImmutable` — calling `$date->addDay()` without reassigning produces the same date each iteration. Fixed in both `AssessmentMonitorFactory` and `PlannerCareerEntryFactory` by using `$date = $date->addDay()`.

## Files Created This Session

- `app/Models/PlannerCareerEntry.php`
- `app/Models/AssessmentMonitor.php`
- `app/Models/GhostOwnershipPeriod.php`
- `app/Models/GhostUnitEvidence.php`
- `database/factories/PlannerCareerEntryFactory.php`
- `database/factories/AssessmentMonitorFactory.php`
- `database/factories/GhostOwnershipPeriodFactory.php`
- `database/factories/GhostUnitEvidenceFactory.php`
- `tests/Feature/DataCollection/PlannerCareerEntryTest.php`
- `tests/Feature/DataCollection/AssessmentMonitorTest.php`
- `tests/Feature/DataCollection/GhostOwnershipPeriodTest.php`
- `tests/Feature/DataCollection/GhostUnitEvidenceTest.php`

## Files Modified This Session

- `docs/wip.md` (updated to reflect Phase 2 completion)

## No Commits Yet

All work is uncommitted on `feature/data-collection-architecture`. Ready to commit when user approves.

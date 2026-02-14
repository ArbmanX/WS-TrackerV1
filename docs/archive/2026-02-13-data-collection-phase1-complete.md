# Session Handoff — 2026-02-13

## Current Task Status

**Feature:** Data Collection Architecture
**Branch:** `feature/data-collection-architecture` (created from main, no commits yet)
**Tech Spec:** `docs/specs/tech-spec-data-collection-architecture.md`

### Phase 1: Database Layer — COMPLETE

All 4 migrations created, run, and verified against PostgreSQL:

| Migration | Table | Status |
|-----------|-------|--------|
| `2026_02_13_171957_create_planner_career_entries_table.php` | `planner_career_entries` | Migrated |
| `2026_02_13_172018_create_assessment_monitors_table.php` | `assessment_monitors` | Migrated |
| `2026_02_13_172018_create_ghost_ownership_periods_table.php` | `ghost_ownership_periods` | Migrated |
| `2026_02_13_172019_create_ghost_unit_evidence_table.php` | `ghost_unit_evidence` | Migrated |

Config file created: `config/ws_data_collection.php`

### Phase 2: Models & Factories — NOT STARTED

Next step is creating 4 models + factories. Use `php artisan make:model <Name> --factory --no-interaction` for each:

1. **PlannerCareerEntry** — JSONB casts (`daily_metrics`, `summary_totals`, `rework_details`), boolean cast (`went_to_rework`), date casts. Scopes: `forPlanner`, `forRegion`, `forScopeYear`, `fromBootstrap`, `fromLiveMonitor`. Factory states: `withRework()`, `fromBootstrap()`, `fromLiveMonitor()`
2. **AssessmentMonitor** — JSONB casts (`daily_snapshots`, `latest_snapshot`), date casts. Scopes: `active()`, `inQc()`, `inRework()`, `forRegion()`. Method: `addSnapshot()`. Factory states: `withSnapshots()`, `inQc()`, `inRework()`
3. **GhostOwnershipPeriod** — JSONB cast (`baseline_snapshot`), boolean (`is_parent_takeover`), date casts. `hasMany(GhostUnitEvidence)`. Scopes: `active()`, `resolved()`, `parentTakeovers()`. Factory states: `active()`, `resolved()`, `parentTakeover()`
4. **GhostUnitEvidence** — `belongsTo(GhostOwnershipPeriod)`, `const UPDATED_AT = null`. Scopes: `forAssessment()`, `detectedBetween()`. Factory with denormalized fields.

### Remaining Phases (3-7)

Per tech spec implementation plan — Phase 3: Query Builders, Phase 4: Services & Events, Phase 5: Artisan Commands, Phase 6: Testing, Phase 7: Documentation & Cleanup.

---

## Key Files to Read

| File | Why |
|------|-----|
| `docs/specs/tech-spec-data-collection-architecture.md` | **PRIMARY** — full spec with table schemas, service architecture, implementation phases |
| `app/Models/SsJob.php` | Model pattern reference (casts method, relationships, scopes) |
| `database/factories/SsJobFactory.php` | Factory pattern reference (states, fake data) |
| `docs/wip.md` | Current WIP status |

---

## No Files Modified (Unstaged)

All work is new files on the `feature/data-collection-architecture` branch. Nothing committed yet.

### New Files Created
- `database/migrations/2026_02_13_171957_create_planner_career_entries_table.php`
- `database/migrations/2026_02_13_172018_create_assessment_monitors_table.php`
- `database/migrations/2026_02_13_172018_create_ghost_ownership_periods_table.php`
- `database/migrations/2026_02_13_172019_create_ghost_unit_evidence_table.php`
- `config/ws_data_collection.php`
- `docs/wip.md` (updated)

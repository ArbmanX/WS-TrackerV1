# Session Handoff — 2026-02-12

## Current Task Status

**Brainstorming session** for data collection and storage architecture using BMAD brainstorming workflow. Session is ~60% complete — two of three brainstorming phases done, architecture decisions largely made, table designs partially complete.

**Session document:** `BMAD_WS/analysis/brainstorming-session-2026-02-12.md` — comprehensive record of all decisions, truths, and open questions.

---

## What Was Accomplished

### Phase 1: First Principles Thinking (COMPLETE)
- Established 7 fundamental truths (FP1-FP7) about the data
- Identified two distinct data lifecycle patterns: **Career Ledger** (closed/historical) and **Live Monitor** (active/QC/REWRK)
- Discovered critical requirement: **Ghost Unit Detection** — tracking when units are deleted during utility ownership (no other system captures this)
- Key insight: historical daily footage is fully reconstructable from existing ASSDDATE timestamps — no preemptive collection needed

### Phase 2: Morphological Analysis (IN PROGRESS)
- Explored full parameter space: WHEN/WHERE/WHAT/HOW LONG for each data domain
- **Decisions made:**
  - **CL-1**: Career Ledger → JSON bootstrap file + artisan import command for historical, then auto-append from live monitor on assessment close. PostgreSQL with JSONB columns. Permanent.
  - **LM-1**: Live Monitor → Daily cron job, PostgreSQL, aggregated metrics, lifecycle-scoped (transitions to career ledger on close)
  - **GU-1**: Ghost Detection → Event-driven (triggered by ONEPPL domain ownership), PostgreSQL UNITGUID snapshots, lifecycle-scoped. On close: delete scaffolding, keep only evidence of deleted units permanently.
- **Career Ledger table designed** (`planner_career_entries`): One row per planner per assessment, with JSONB `daily_metrics` (date-keyed), `summary_totals` (pre-computed aggregates), and `rework_details`

### Phase 3: Constraint Mapping + Chaos Engineering (COMPLETE — 2026-02-13)
- All constraints assessed as Low/Medium risk with mitigations
- API rate limits fine at 10x scale, JSONB growth trivial (max ~180 KB)
- Key finding: CLOSE is terminal — no CLOSE→REWRK→CLOSE cycle, career entries are write-once
- Zero-count sanity check: persist with `"suspicious": true` flag in JSONB
- V_ASSESSMENT view discovered as pre-aggregated shortcut for work type rollups
- JOBVEGETATIONUNITS confirmed as source for polygon area (sq meters) — notes compliance threshold feasible
- Ghost Detection: baseline-only comparison prevents false positives from ONEPPL-added units

---

## Open Questions — ALL RESOLVED (2026-02-13)

1. **Split/child assessments:** ✅ Separate GUIDs, one row per planner-per-JOBGUID works. Parent EXT=`@`, children=`X_a`/`X_ab`.
2. **Planner career summary:** ✅ Compute on-the-fly via `jsonb_each()`. No separate table.
3. **Live Monitor table design:** ✅ `assessment_monitors` — one row per assessment, JSONB time-series with `latest_snapshot` denormalization.
4. **Ghost Detection table design:** ✅ Two tables: `ghost_ownership_periods` (scaffolding) + `ghost_unit_evidence` (permanent). Includes `is_parent_takeover` flag.
5. **Notes compliance polygon size check:** ✅ Use JOBVEGETATIONUNITS.AREA (sq meters), NOT VEG_POLYGONS. Threshold: ≥9.29 sq m (=100 sq ft).
6. **Phase 3 stress-testing:** ✅ Complete. All risks Low/Medium with mitigations identified.

---

## Key Files Modified/Created This Session

| File | Action | Description |
|------|--------|-------------|
| `BMAD_WS/analysis/brainstorming-session-2026-02-12.md` | Created | Full brainstorming session document with all decisions |
| `docs/session-handoffs/2026-02-12-brainstorm-data-architecture.md` | Created | This handoff file |

---

## Key Files to Read for Context

| File | Why |
|------|-----|
| `BMAD_WS/analysis/brainstorming-session-2026-02-12.md` | **PRIMARY** — full brainstorming record with all decisions, table designs, open questions |
| `docs/specs/planner-activity-rules.md` | Business rules for daily footage attribution (first-unit-wins, station counting) |
| `docs/project-context.md` | Project architecture overview |
| `_bmad/ws/data/tables/extracted/02-ss-children/VEGUNIT.md` | Full VEGUNIT schema (227 fields) — key fields for all data domains |
| `_bmad/ws/data/tables/extracted/02-ss-children/JOBHISTORY.md` | JOBHISTORY schema — ghost detection trigger source |
| `config/ws_assessment_query.php` | Config-driven values: statuses, unit groups, permission statuses |
| `app/Services/WorkStudio/Assessments/Queries/ActivityQueries.php` | Existing daily activity query |
| `app/Services/WorkStudio/Shared/Persistence/SnapshotPersistenceService.php` | Existing snapshot pattern to follow |

---

## Next Steps (Updated 2026-02-13)

1. ~~Resume brainstorming~~ ✅ All phases complete, all questions resolved
2. ~~Design remaining tables~~ ✅ Live Monitor + Ghost Detection designed
3. ~~Convert to tech spec~~ ✅ `docs/specs/tech-spec-data-collection-architecture.md` — status: ready
4. **Implementation** — 7 phases per tech spec:
   - Phase 1: Database migrations (4 tables)
   - Phase 2: Models + factories
   - Phase 3: Query builders (extend AbstractQueryBuilder)
   - Phase 4: Services + AssessmentClosed event/listener
   - Phase 5: Artisan commands + scheduler
   - Phase 6: Tests
   - Phase 7: Documentation + cleanup

---

## Architecture Summary (Quick Reference)

```
Assessment Lifecycle Data Pipeline:

[Bootstrap JSON]                [Daily Cron]                    [On Close Event]
ws:import-career-ledger    →    Live Monitor (daily)        →   Append to career ledger
All historical CLOSE data       ~30 ACTIV/QC/REWRK jobs         Delete ghost scaffolding
One-time on app install         Permission breakdowns            Keep deleted unit evidence
                                Unit counts, notes compliance
                                Edit recency
                                + Ghost Detection (on ONEPPL takeover)

Storage: PostgreSQL with JSONB columns
Career Ledger: planner_career_entries (one row per planner per assessment)
Live Monitor: TBD table design
Ghost Detection: TBD table design (UNITGUID snapshots + deleted evidence)
```

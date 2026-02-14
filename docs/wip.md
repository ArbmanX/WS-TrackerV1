# Work In Progress

## Feature: Incremental Planner Career Export
**Branch:** `feature/incremental-planner-export`
**Started:** 2026-02-14

### Implementation — COMPLETE

| Task | Status |
|------|--------|
| Migration: `export_path` column on `planner_job_assignments` | done |
| Model: add `export_path` to `$fillable` | done |
| Factory: `withExportPath()` state | done |
| Query: `getEditDates()` method | done |
| Service: incremental `exportForUser()` with staleness detection | done |
| Command: updated info messages | done |
| Scope year: API-derived via xref subquery, `--all-years` flag | done |
| Unit tests: 9 new query tests (getEditDates, allYears, scope_year) | done |
| Feature tests: 7 new service tests for incremental behavior | done |
| Pint formatting | done |
| CHANGELOG updated | done |
| Full test suite: 552 passed, 3 skipped, 1 pre-existing failure | done |

### ALL TASKS COMPLETE — Ready to commit and merge

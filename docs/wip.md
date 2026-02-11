# Work In Progress

## Branch: `feature/query-config-extraction`

**Phase 1 of 4:** Assessment Queries Refactor & Optimization

### What's Being Done
- Adding `permission_statuses` array to config (PERMSTAT values)
- Adding `unit_groups` array to config (work measurement unit codes)
- Adding `excluded_from_assessments` cycle type array to config
- Writing unit tests for config values
- Applying BUG-001 fix (Refusal -> Refused)

### Files Modified
- `config/ws_assessment_query.php`
- `app/Services/WorkStudio/Assessments/Queries/AssessmentQueries.php` (BUG-001 fix)
- `app/Services/WorkStudio/Assessments/Queries/SqlFragmentHelpers.php` (BUG-001 fix)
- `tests/Unit/AssessmentQueriesTest.php` (new config tests)

### Next Phase
Phase 2: Extract shared SQL fragments to SqlFragmentHelpers

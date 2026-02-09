# Work In Progress

## Feature: Daily Footage — Rearchitected (DATEPOP + Stations + No Sushi)

**Branch:** `feature/daily-footage-command`
**Started:** 2026-02-08
**Related TODO:** FT-001 (Planner Daily Activity System — partial)
**Spec:** `docs/specs/assessment-completion-rules.md`

### Status
- [x] Branch created
- [x] SQL query — `DailyFootageQuery.php` (DATEPOP + STRING_AGG for stations)
- [x] Artisan command — `FetchDailyFootage.php` (date modes: WE/Daily, edit_date filtering, station arrays)
- [x] Removed Sushi model, aggregator, factory, and tests
- [x] Removed `calebporzio/sushi` dependency from composer
- [x] Removed `weekly_quota_miles` and `meters_per_mile` from config
- [x] Updated SsJobFactory — `withJobType()` state, correct default job_type values
- [x] Updated SsJobTest to match new factory defaults
- [x] 23 Pest command tests passing (date modes, filtering, enrichment, SQL, edge cases)
- [x] Pint formatted
- [x] Full test suite: 265 passed, 3 skipped, 1 pre-existing failure (ReferenceDataSeederTest sort order)
- [x] CHANGELOG updated
- [ ] **READY FOR COMMIT** — awaiting user confirmation

### Files Created/Modified
- `app/Console/Commands/FetchDailyFootage.php` — rewritten (date modes, edit_date, stations)
- `app/Services/WorkStudio/Assessments/Queries/DailyFootageQuery.php` — DATEPOP + STRING_AGG
- `config/ws_assessment_query.php` — removed quota/miles settings
- `database/factories/SsJobFactory.php` — withJobType(), config-based defaults
- `tests/Feature/Commands/FetchDailyFootageTest.php` — 23 tests, complete rewrite
- `tests/Feature/Models/SsJobTest.php` — updated for new factory defaults
- `CHANGELOG.md` — updated
- `composer.json` / `composer.lock` — sushi removed

### Files Deleted
- `app/Models/DailyFootage.php`
- `app/Services/WorkStudio/Assessments/DailyFootageAggregator.php`
- `database/factories/DailyFootageFactory.php`
- `tests/Feature/Models/DailyFootageTest.php`
- `tests/Feature/Services/DailyFootageAggregatorTest.php`

### Key Changes from Previous Version
- **DATEPOP** replaces ASSDDATE for completion date tracking
- **STRING_AGG** adds station list to each row (needed for "claim once" rule)
- **Date modes:** WE (Saturday → Sun-Sat week range) and Daily (non-Saturday → single day)
- **Simplified output:** `{job_guid, frstr_user, datepop, distance_planned, stations[]}` — no miles, no week_ending, no ws_user_id
- **No Sushi dependency** — model will be reintroduced when Livewire UI is built
- **Filename convention:** `{we|day}{MM_DD_YYYY}_planning_activities.json`

# Session Handoff — Daily Footage: Domain-Grouped JSON + Sushi Model

**Date:** 2026-02-08
**Branch:** `feature/daily-footage-command`
**Status:** Implementation complete, **NOT YET COMMITTED**

---

## What Was Done This Session

Implemented the second phase of the daily footage feature: domain-grouped JSON storage with a Sushi Eloquent model for querying.

### Phase 1 (previous session, already on branch)
- `ws:fetch-daily-footage` artisan command — fetches from WorkStudio API
- `DailyFootageQuery` SQL builder — T-SQL derived table with ROW_NUMBER()
- 13 Pest tests

### Phase 2 (this session)
1. **Installed `calebporzio/sushi` v2.5** — in-memory Eloquent models backed by JSON
2. **Created `DailyFootage` Sushi model** — reads JSON files from `storage/app/daily-footage/{DOMAIN}/`, provides Eloquent scopes and relationships
3. **Created `DailyFootageAggregator` service** — weekly grouping, quota check, domain summary via Sushi's SQLite backend
4. **Modified `FetchDailyFootage` command** — enriches records with domain/miles/week-ending, writes per-domain JSON + `.manifest`
5. **Created factory + 3 test files** — 34 total tests, all passing
6. **Added config values** — `weekly_quota_miles` (6.5), `meters_per_mile` (1609.344)

---

## Uncommitted Changes

### New Files
| File | Purpose |
|------|---------|
| `app/Models/DailyFootage.php` | Sushi model — JSON-backed Eloquent with scopes + wsUser() relationship |
| `app/Services/WorkStudio/Assessments/DailyFootageAggregator.php` | Weekly aggregation, quota check, domain summary |
| `database/factories/DailyFootageFactory.php` | Test data factory with domain/weekEnding/wsUser states |
| `tests/Feature/Models/DailyFootageTest.php` | 10 tests for Sushi model |
| `tests/Feature/Services/DailyFootageAggregatorTest.php` | 7 tests for aggregator |
| `app/Console/Commands/FetchDailyFootage.php` | Artisan command (new, from phase 1) |
| `app/Services/WorkStudio/Assessments/Queries/DailyFootageQuery.php` | SQL builder (new, from phase 1) |
| `tests/Feature/Commands/FetchDailyFootageTest.php` | 17 command tests (expanded from phase 1's 13) |
| `docs/specs/assessment-completion-rules.md` | Business rules spec (from phase 1) |
| `docs/prompts/` | Query development prompts (from phase 1) |

### Modified Files
| File | Change |
|------|--------|
| `composer.json` / `composer.lock` | Added `calebporzio/sushi` v2.5 |
| `config/ws_assessment_query.php` | Added `weekly_quota_miles`, `meters_per_mile` |
| `CHANGELOG.md` | Updated with all changes |
| `docs/wip.md` | Updated status |

---

## Test Results

```
Full suite: 276 passed, 1 pre-existing failure, 3 skipped
New tests:  34 passed (113 assertions)
Pint:       pass
```

The 1 failure is pre-existing: `ReferenceDataSeederTest > regions have correct sort order` — region sort order mismatch (alphabetical vs expected custom order).

---

## Architecture Decisions

### Storage Structure
```
storage/app/daily-footage/
  ASPLUNDH/2026_ACTIV.json    ← flat array of enriched records
  PPL/2026_ACTIV.json
  .manifest                   ← ISO timestamp, triggers Sushi cache invalidation
```

### Enriched Record Schema
```json
{
  "job_guid": "{abc-111}",
  "completion_date": "2026-01-13",
  "frstr_username": "ASPLUNDH\\tgibson",
  "ws_user_id": 5,
  "domain": "ASPLUNDH",
  "footage_meters": 1523.7,
  "footage_miles": 0.946,
  "week_ending": "2026-01-18"
}
```

### Sushi Cache Invalidation
- `sushiShouldCache()` returns `true` only when `.manifest` file exists on real filesystem
- `sushiCacheReferencePath()` points to `.manifest` — when `filemtime()` changes, Sushi rebuilds its SQLite
- In test environment with `Storage::fake()`, `.manifest` doesn't exist on real FS, so Sushi uses `:memory:` (no caching)

---

## Critical Gotchas Discovered

1. **Sushi test isolation** — Must do ALL THREE in `beforeEach`:
   - Delete `storage/framework/cache/sushi-app-models-daily-footage.sqlite`
   - `(new ReflectionClass(DailyFootage::class))->setStaticPropertyValue('sushiConnection', null)`
   - `DailyFootage::clearBootedModels()`

2. **DDOProtocol date format** — `CONVERT(VARCHAR(10), date, 110)` returns `MM-DD-YYYY`. Must parse with `Carbon::createFromFormat('m-d-Y', ...)` — `Carbon::parse()` throws `InvalidFormatException`.

3. **JSON integer/float edge case** — `round($meters / 1609.344, 3)` can return integer `1` (not float `1.0`). JSON encodes this as `1` not `1.0`. Test assertions should cast: `(float) $json['footage_miles']`.

---

## Next Steps

1. **Commit this branch** — all work is complete and tested, awaiting user confirmation
2. **Merge to main** — standard merge workflow
3. **Live test** — `php artisan ws:fetch-daily-footage` then inspect `storage/app/daily-footage/`
4. **Build Livewire UI** — use `DailyFootageAggregator` to display weekly footage tables grouped by domain
5. **Schedule command** — add to `routes/console.php` for periodic fetching

---

## Key Code Locations

| What | Where |
|------|-------|
| Sushi model | `app/Models/DailyFootage.php` |
| Aggregator service | `app/Services/WorkStudio/Assessments/DailyFootageAggregator.php` |
| Artisan command | `app/Console/Commands/FetchDailyFootage.php` |
| SQL builder | `app/Services/WorkStudio/Assessments/Queries/DailyFootageQuery.php` |
| Config (quota/meters) | `config/ws_assessment_query.php:4-5` |
| Command tests | `tests/Feature/Commands/FetchDailyFootageTest.php` |
| Model tests | `tests/Feature/Models/DailyFootageTest.php` |
| Aggregator tests | `tests/Feature/Services/DailyFootageAggregatorTest.php` |
| Business rules | `docs/specs/assessment-completion-rules.md` |

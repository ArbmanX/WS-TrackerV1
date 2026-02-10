# Plan: Add Unit Count to Daily Footage + Unit Types Reference Table

## Summary

Two changes to the daily footage feature:
1. **Add `unit_count`** to `DailyFootageQuery` — counts working units (excludes NW, SENSI, NOT) per row
2. **Create a local `unit_types` table** synced from the WorkStudio API UNITS table

---

## Part 1: Add `unit_count` to Daily Footage Query

### 1A. Modify `DailyFootageQuery::build()` (`app/Services/WorkStudio/Assessments/Queries/DailyFootageQuery.php`)

Add a conditional count to the outer SELECT:

```sql
SUM(CASE WHEN FU.UNIT NOT IN ('NW', 'SENSI', 'NOT') THEN 1 ELSE 0 END) AS unit_count
```

This counts stations where the first-populated unit is a working unit, per the existing GROUP BY (JOBGUID + completion_date + FRSTR_USER). Footage stays unchanged — all units still contribute to `daily_footage_meters`.

### 1B. Update `FetchDailyFootage` command (`app/Console/Commands/FetchDailyFootage.php`)

- Add `unit_count` to enrichment in `enrichRecords()` (cast to int)
- No other changes needed — the output JSON shape just gains one field

### 1C. Update tests (`tests/Feature/Commands/FetchDailyFootageTest.php`)

- Update `fakeDailyFootageResponse()` Heading array to include `unit_count`
- Update fake Data rows with `unit_count` values
- Add assertion for `unit_count` in the enriched JSON shape test
- Add SQL assertion test for the `CASE WHEN` exclusion logic

---

## Part 2: Create Local `unit_types` Reference Table

### 2A. Create migration: `create_unit_types_table`

```
php artisan make:migration create_unit_types_table --no-interaction
```

Table: `unit_types`
| Column | Type | Notes |
|--------|------|-------|
| id | bigIncrements | PK |
| unit | string | Unique, indexed — the unit code (e.g., NW, SENSI, NOT, 1BRKR) |
| unitssname | string, nullable | Staking sheet display name |
| unitsetid | string, nullable | Unit set ID |
| summarygrp | string, nullable | Summary group |
| entityname | string, nullable | Entity name |
| work_unit | boolean | default true — false for NW, SENSI, NOT |
| last_synced_at | timestamp, nullable | When synced from API |
| timestamps | | created_at, updated_at |

The `work_unit` boolean is derived from `summarygrp` during upsert: if `summarygrp === 'summary-nonwork'` → `work_unit = false`, otherwise `true`. No hardcoded unit code list needed.

### 2B. Create model: `UnitType`

```
php artisan make:model UnitType --factory --no-interaction
```

- `$fillable`: unit, unitssname, unitsetid, summarygrp, entityname, last_synced_at
- Cast `last_synced_at` → datetime

### 2C. Create fetch command: `FetchUnitTypes` (`ws:fetch-unit-types`)

Following the established pattern from `FetchSsJobs` / `FetchCircuits`:

```
php artisan make:command FetchUnitTypes --no-interaction
```

Signature: `ws:fetch-unit-types {--dry-run}`

SQL query against WS API:
```sql
SELECT UNIT, UNITSSNAME, UNITSETID, SUMMARYGRP, ENTITYNAME FROM UNITS ORDER BY UNIT
```

Upsert into `unit_types` table by `unit` (unique key).

### 2D. Create factory: `UnitTypeFactory`

Basic factory + states:
- Default: random unit code, unitssname, `work_unit = true`
- `nonWorking()` state: `summarygrp = 'summary-nonwork'`, `work_unit = false`

### 2E. Write tests: `tests/Feature/Commands/FetchUnitTypesCommandTest.php`

Following the pattern from `FetchSsJobsCommandTest`:
- Test successful sync (creates records)
- Test upsert (updates existing)
- Test dry-run (no DB changes)
- Test API error handling
- Test empty response

---

## File Changes Summary

| File | Action |
|------|--------|
| `app/Services/WorkStudio/Assessments/Queries/DailyFootageQuery.php` | Edit — add `unit_count` column |
| `app/Console/Commands/FetchDailyFootage.php` | Edit — add `unit_count` to enrichment |
| `tests/Feature/Commands/FetchDailyFootageTest.php` | Edit — update fake data & add assertions |
| `database/migrations/xxxx_create_unit_types_table.php` | New — migration |
| `app/Models/UnitType.php` | New — model |
| `database/factories/UnitTypeFactory.php` | New — factory |
| `app/Console/Commands/FetchUnitTypes.php` | New — artisan command |
| `tests/Feature/Commands/FetchUnitTypesCommandTest.php` | New — tests |
| `CHANGELOG.md` | Edit — update unreleased |

## Branch

`feature/unit-count-and-unit-types`

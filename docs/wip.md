# Work In Progress

## Feature: Unit Count + Unit Types Table

**Branch:** `feature/unit-count-and-unit-types`
**Plan:** `docs/plans/unit-count-and-unit-types-table.md`
**Tinker Queries:** `docs/tinker_queries.md`
**Started:** 2026-02-09

### Status: Implementation complete, ready for commit

### What's Done
- Explored UNITS table from WorkStudio API (123 unit types total)
- Mapped SUMMARYGRP distribution: 58 work units, 65 non-work units
- Confirmed `work_unit` derivation: `SUMMARYGRP IS NOT NULL AND != '' AND != 'Summary-NonWork'`
- Validated candidate SQL query with JOIN UNITS for unit_count
- Cross-verified footage unchanged (exact match to baseline)
- Confirmed zero orphan VEGUNIT.UNIT values globally (INNER JOIN is safe)
- Created feature branch
- Updated `DailyFootageQuery::build()` — added `JOIN UNITS U` + `unit_count` CASE WHEN
- Updated `FetchDailyFootage` enrichment — added `unit_count` (int) to output JSON
- Created `unit_types` migration, `UnitType` model, `UnitTypeFactory`
- Created `ws:fetch-unit-types` artisan command with `--dry-run`
- Updated 30 daily footage tests (all pass)
- Wrote 9 FetchUnitTypes command tests (all pass)
- Updated CHANGELOG.md

### All Tasks Complete
1. ~~Update `DailyFootageQuery::build()` — add `JOIN UNITS` + `unit_count` CASE WHEN~~
2. ~~Update `FetchDailyFootage` command — add `unit_count` to enrichment~~
3. ~~Create `unit_types` migration, model (`UnitType`), factory~~
4. ~~Create `ws:fetch-unit-types` artisan command~~
5. ~~Update existing daily footage tests~~
6. ~~Write FetchUnitTypes command tests~~
7. ~~Update CHANGELOG.md + wip.md~~

### Next Steps
- Run `vendor/bin/pint --dirty` and commit
- Merge to main

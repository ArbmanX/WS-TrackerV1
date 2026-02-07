# Work In Progress — Reference Data Seeders

## Current Task
Create database-backed reference tables for **Regions** and **Circuits** with seeders, models, factories, an artisan command, and tests.

## Status: Complete — Ready for Commit

**Branch:** `feature/reference-data-seeders`

## What Was Done

### Migrations
- `create_regions_table` — name (unique), display_name, is_active, sort_order
- `create_circuits_table` — line_name (unique), region_id FK (nullable, nullOnDelete), is_active, last_trim, next_trim, properties (JSON), last_seen_at

### Models + Factories
- `Region` model — active() scope, HasMany circuits
- `Circuit` model — active() scope, BelongsTo region, properties/date casts
- `RegionFactory` — inactive() state
- `CircuitFactory` — withRegion(), inactive() states

### Seeders
- `RegionSeeder` — 6 geographic regions (Harrisburg, Lancaster, Lehigh, Central, Susquehanna, Northeast)
- `CircuitSeeder` — reads database/data/circuits.php, gracefully skips if missing
- `ReferenceDataSeeder` — orchestrator
- `DatabaseSeeder` updated with ReferenceDataSeeder between RolePermissionSeeder and SudoAdminSeeder

### Artisan Command
- `ws:fetch-circuits` — fetches circuits from WS API, --save/--seed/--dry-run/--year options
- New circuits initialized with `properties: {"{year}": []}`, existing circuits preserve year keys on re-run

### Tests (25 tests, 50 assertions)
- `tests/Unit/Models/RegionTest.php` — 5 tests
- `tests/Unit/Models/CircuitTest.php` — 6 tests
- `tests/Feature/Seeders/ReferenceDataSeederTest.php` — 6 tests
- `tests/Feature/Commands/FetchCircuitsCommandTest.php` — 8 tests (1 skipped placeholder)

### Other
- `database/data/.gitkeep` created
- All 159 tests pass, Pint formatted

## Next Steps
- Commit and merge to main
- Run `ws:fetch-circuits --save --seed` against live API to populate circuits

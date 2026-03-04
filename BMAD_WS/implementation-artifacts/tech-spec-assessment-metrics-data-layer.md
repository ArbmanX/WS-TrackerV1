---
title: 'Assessment Metrics Data Layer'
slug: 'assessment-metrics-data-layer'
created: '2026-03-04'
status: 'completed'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Laravel 12', 'PostgreSQL', 'Eloquent ORM', 'Pest 4']
files_to_modify:
  - 'database/migrations/ (new: create_assessment_metrics_table)'
  - 'database/migrations/ (new: create_assessment_contributors_table)'
  - 'database/factories/AssessmentMetricFactory.php (new)'
  - 'database/factories/AssessmentContributorFactory.php (new)'
  - 'app/Models/AssessmentMetric.php (new)'
  - 'app/Models/AssessmentContributor.php (new)'
  - 'app/Models/Assessment.php (add hasOne/hasMany relationships)'
  - 'app/Console/Commands/Traits/GetAdditionalAssessmentMetrics.php (add persistence methods)'
  - 'app/Console/Commands/Fetch/FetchAssessments.php (wire persistence after getAdditionalMetrics)'
  - 'tests/Feature/ (new: AssessmentMetricTest.php, AssessmentContributorTest.php)'
code_patterns:
  - 'firstOrNew + fill + save keyed on job_guid (Assessment upsert pattern)'
  - '$timestamps = false with custom discovered_at/last_synced_at on Assessment'
  - 'assessment_monitors uses $timestamps = true (created_at/updated_at) — follow this for new tables'
  - 'GUID format: {UUID} wrapped in curly braces, string(38)'
  - 'JSONB columns with default({}) for JSON object fields'
  - 'Config-driven thresholds via workstudio.data_collection.*'
  - 'WSHelpers::parseWsDate() strips /Date()/ wrapper, nulls 1899 sentinel dates'
  - 'Factory states: chainable methods like withStatus(), split()'
  - 'WsUser.username is DOMAIN\username format — match FRSTR_USER against this'
test_patterns:
  - 'Pest 4 closure-based: test("description", function() { ... })'
  - 'RefreshDatabase trait (auto in Feature tests via Pest.php)'
  - 'Fluent expect()->toBe()->and()->toX() chains'
  - 'Factory states for test variants'
---

# Tech-Spec: Assessment Metrics Data Layer

**Created:** 2026-03-04

## Overview

### Problem Statement

The `AdditionalMetricsQueries` SQL query returns rich per-assessment metrics (permission breakdown, notes compliance, aging units, timeline dates, station breakdown, work type breakdown, forester contributions) but has no persistence layer. Results currently hit `dd()` in `FetchAssessments` and never reach the database. The frontend has no way to access this data.

### Solution

Create two new tables — `assessment_metrics` (1:1 with assessments) and `assessment_contributors` (many-per-assessment, tracking all contributors with admin-assignable roles). Persist data with conversions already applied: dates cleaned, measurements in imperial units (feet, miles, sqft, acres), counts as integers, percentages rounded. Store enriched `work_type_breakdown` JSON with display names from the `UnitType` model.

### Scope

**In Scope:**

- `assessment_metrics` migration + model (FK to assessments via job_guid)
- `assessment_contributors` migration + model (FK to assessments, nullable FK to ws_users and users)
- Measurement conversions at persistence time (source units verified below)
- Enriched `work_type_breakdown` JSON (integer counts, display names from UnitType)
- Date cleanup at persistence (parseWsDate already handles /Date()/ stripping)
- Wire persistence into `FetchAssessments` command flow
- Pest tests with factories

**Out of Scope:**

- Frontend components / Livewire views
- API resources / endpoints (separate spec)
- Changes to the WS SQL queries
- AssessmentMonitor / time-series snapshot logic

## Context for Development

### Codebase Patterns

- **Assessment model** uses `$timestamps = false` with custom `discovered_at`, `last_synced_at`
- **AssessmentMonitor** uses `$timestamps = true` (created_at/updated_at) — new tables should follow this pattern
- **Upsert pattern:** `Assessment::firstOrNew(['job_guid' => $guid])->fill($attrs)->save()`
- **FetchAssessments flow:** `fetchFromApi()` → `upsertAssessments()` → `getDailyFootageInChunks()` → `getAdditionalMetrics()`. Currently stops at `dd()` on line 97. Metrics persistence wires in right after `getAdditionalMetrics()` returns its Collection.
- **Trait returns Collection:** `getAdditionalMetrics()` returns `Collection<array>` with date fields already parsed. New persistence methods should live in the same trait.
- **Config thresholds:** `config('workstudio.data_collection.thresholds.aging_unit_days')` = 14, `notes_compliance_area_sqm` = 9.29
- **WsUser** has `username` (DOMAIN\user format), `domain`, `display_name`. Match `FRSTR_USER` against this.
- **UnitType** has `unit` (code like REM612), `entityname` (display name), `summarygrp`, `work_unit` (boolean)
- **Factory pattern:** chainable states, `{UUID}` braces around GUIDs, private helpers for complex data generation
- **JSONB pattern:** `->default('{}')` in migration, `'array'` cast in model

### Verified Measurement Units (Source)

| Field | Source Table | Source Unit | Target Unit | Conversion |
| ----- | ----------- | ----------- | ----------- | ---------- |
| UnitQty | V_ASSESSMENT (via SSUNITS.QUANTITY) | Dimensionless count per unit code | Integer | FLOOR() for counts, keep DECIMAL for area-based |
| AREA | JOBVEGETATIONUNITS | Square meters | sqft / acres | x 10.7639 (sqft) or / 4046.86 (acres) |
| LENGTHWRK | JOBVEGETATIONUNITS | Feet | Miles | / 5280 |
| SPANLGTH | STATIONS | Meters | Feet | x 3.28084 |

**Note:** For AdditionalMetrics specifically, AREA and SPANLGTH are used in SQL WHERE/aggregate logic but NOT returned as raw columns. The main persistence conversions are: UnitQty cleanup in work_type_breakdown JSON and date formatting.

### Date Fields (All 7 already parsed before persistence)

**Important:** The `getAdditionalMetrics()` trait method runs `WSHelpers::parseWsDate()` on all 7 date fields *before* returning the Collection. By the time `persistMetrics()` receives each row, date fields are already **Carbon instances** (or `null`) — NOT raw `/Date()/` strings. The persistence method should pass them through directly to Eloquent (which handles Carbon→database conversion natively).

| Column | Source | Type at persistence time |
| ------ | ------ | ------------------------ |
| taken_date | JOBHISTORY.LOGDATE | `Carbon` or `null` |
| sent_to_qc_date | JOBHISTORY.LOGDATE | `Carbon` or `null` |
| sent_to_rework_date | JOBHISTORY.LOGDATE | `Carbon` or `null` |
| closed_date | JOBHISTORY.LOGDATE | `Carbon` or `null` |
| first_unit_date | VEGUNIT.ASSDDATE | `Carbon` or `null` |
| last_unit_date | VEGUNIT.ASSDDATE | `Carbon` or `null` |
| oldest_pending_date | VEGUNIT.ASSDDATE | `Carbon` or `null` |

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `app/Services/WorkStudio/Assessments/Queries/AdditionalMetricsQueries.php` | SQL query returning all metrics |
| `app/Console/Commands/Traits/GetAdditionalAssessmentMetrics.php` | Trait executing query + parsing dates |
| `app/Console/Commands/Fetch/FetchAssessments.php` | Command orchestrating fetch + persist |
| `app/Models/Assessment.php` | Parent model (job_guid FK target) |
| `app/Models/UnitType.php` | Unit code display names |
| `app/Models/WsUser.php` | WS user reference (contributor FK target) |
| `app/Services/WorkStudio/Shared/Helpers/WSHelpers.php` | parseWsDate, toSqlInClause |
| `config/workstudio.php` | Thresholds, permission statuses |

### Technical Decisions

- **Option B chosen:** Separate `assessment_metrics` table, not columns on `assessments`
- **All contributors tracked:** foresters, QC reviewers, planners — not just foresters
- **Admin-assignable role:** `role` column on `assessment_contributors` (nullable, set by admin later)
- **work_type_breakdown as JSON column:** enriched with display names, integer counts — not normalized
- **Conversions at persistence time:** store display-ready values, not raw metric
- **Scope boundary:** Only migrations, models, and persistence wiring. No snapshot service, no API resources, no frontend.
- **Persistence location:** Methods added to `GetsAdditionalAssessmentMetrics` trait, called from `FetchAssessments.handle()` after `getAdditionalMetrics()` returns
- **assessment_metrics keyed on job_guid:** 1:1 with assessments, uses `firstOrNew` + `fill` + `save` pattern (same as Assessment upsert)
- **assessment_contributors uses upsert by composite key:** `job_guid` + `ws_username` to avoid duplicates on re-fetch

## Implementation Plan

### Tasks

- [x] Task 1: Create `assessment_metrics` migration
  - File: `database/migrations/YYYY_MM_DD_HHMMSS_create_assessment_metrics_table.php`
  - Action: Create table with the following columns:
    - `id` (bigIncrements)
    - `job_guid` string(38), unique, foreign key → `assessments.job_guid` with cascadeOnDelete
    - `work_order` string(50)
    - `extension` string(10)
    - Permission breakdown (all `unsignedSmallInteger`, default 0):
      - `total_units`, `approved`, `pending`, `refused`, `no_contact`, `deferred`, `ppl_approved`
    - Notes compliance:
      - `units_requiring_notes` unsignedSmallInteger default 0
      - `units_with_notes` unsignedSmallInteger default 0
      - `units_without_notes` unsignedSmallInteger default 0
      - `notes_compliance_percent` decimal(5,1) nullable
    - Aging:
      - `pending_over_threshold` unsignedSmallInteger default 0
    - Station breakdown:
      - `stations_with_work` unsignedSmallInteger default 0
      - `stations_no_work` unsignedSmallInteger default 0
      - `stations_not_planned` unsignedSmallInteger default 0
    - Split assessment:
      - `split_count` unsignedSmallInteger nullable
      - `split_updated` boolean nullable
    - Timeline dates (all `date` nullable):
      - `taken_date`, `sent_to_qc_date`, `sent_to_rework_date`, `closed_date`
      - `first_unit_date`, `last_unit_date`, `oldest_pending_date`
    - Oldest pending unit:
      - `oldest_pending_statname` string(50) nullable
      - `oldest_pending_unit` string(20) nullable
      - `oldest_pending_sequence` unsignedInteger nullable
    - JSON columns:
      - `work_type_breakdown` jsonb default '[]' — enriched: `[{unit, display_name, quantity}, ...]`
    - Timestamps: `$table->timestamps()` (created_at/updated_at)
    - Index on `job_guid` (already unique)
  - Notes: Follows assessment_monitors migration pattern. FK references `assessments.job_guid` not `assessments.id`.

- [x] Task 2: Create `assessment_contributors` migration
  - File: `database/migrations/YYYY_MM_DD_HHMMSS_create_assessment_contributors_table.php`
  - Action: Create table with:
    - `id` (bigIncrements)
    - `job_guid` string(38), foreign key → `assessments.job_guid` with cascadeOnDelete
    - `ws_username` string(100) — raw `DOMAIN\username` from WS API
    - `ws_user_id` foreignId nullable, constrained → `ws_users.id`, nullOnDelete
    - `user_id` foreignId nullable, constrained → `users.id`, nullOnDelete
    - `unit_count` unsignedSmallInteger default 0
    - `role` string(50) nullable — admin-assignable: "Forester", "QC Reviewer", "Lead Assessor", etc.
    - `$table->timestamps()`
    - Unique constraint on `['job_guid', 'ws_username']` (composite — prevents duplicates on re-fetch)
    - Index on `job_guid`, `ws_user_id`, `user_id`
  - Notes: `ws_username` is always populated from API. `ws_user_id` resolved by matching against `WsUser` table. `user_id` and `role` are null initially, set by admin later.

- [x] Task 3: Create `AssessmentMetric` model
  - File: `app/Models/AssessmentMetric.php`
  - Action: Create Eloquent model with:
    - `use HasFactory`
    - `$fillable` array with all columns from Task 1 (except id, timestamps)
    - `casts()`: dates as `'date'`, `notes_compliance_percent` as `'decimal:1'`, `split_updated` as `'boolean'`, `work_type_breakdown` as `'array'`
    - `assessment()` BelongsTo relationship via `job_guid` → `Assessment.job_guid`
  - Notes: No `$timestamps = false` — use default Laravel timestamps.

- [x] Task 4: Create `AssessmentContributor` model
  - File: `app/Models/AssessmentContributor.php`
  - Action: Create Eloquent model with:
    - `use HasFactory`
    - `$fillable`: `job_guid`, `ws_username`, `ws_user_id`, `user_id`, `unit_count`, `role`
    - `casts()`: `unit_count` as `'integer'`
    - `assessment()` BelongsTo via `job_guid` → `Assessment.job_guid`
    - `wsUser()` BelongsTo → `WsUser`
    - `user()` BelongsTo → `User`
  - Notes: No `$timestamps = false` — use default timestamps.

- [x] Task 5: Add relationships to `Assessment` model
  - File: `app/Models/Assessment.php`
  - Action: Add two relationship methods:
    - `metrics(): HasOne` → `AssessmentMetric::class` with foreign key `job_guid` and local key `job_guid`
    - `contributors(): HasMany` → `AssessmentContributor::class` with foreign key `job_guid` and local key `job_guid`
  - Notes: Import the new model classes. Do not modify existing relationships or fillable.

- [x] Task 6: Add persistence methods to `GetAdditionalAssessmentMetrics` trait
  - File: `app/Console/Commands/Traits/GetAdditionalAssessmentMetrics.php`
  - Action: Add two new methods:
    - `persistMetrics(Collection $metricsRows): void` — iterates the Collection from `getAdditionalMetrics()`, for each row:
      1. Enrich `work_type_breakdown` JSON: decode (null-guard — FOR JSON PATH returns NULL when empty), look up `UnitType::where('unit', $code)->value('entityname')` for display name, cast UnitQty to integer, re-encode
      2. Map API column names to model attributes using the explicit mapping below
      3. `AssessmentMetric::firstOrNew(['job_guid' => $row['JOBGUID']])->fill($mapped)->save()`

    **Column Mapping (SQL alias → model attribute):**

    | SQL Alias | Model Attribute | Notes |
    | --------- | --------------- | ----- |
    | `JOBGUID` | `job_guid` | UPPERCASE — must map |
    | `WO` | `work_order` | UPPERCASE — must map |
    | `EXT` | `extension` | UPPERCASE — must map |
    | `total_units` | `total_units` | Already snake_case |
    | `approved` | `approved` | Already snake_case |
    | `pending` | `pending` | Already snake_case |
    | `refused` | `refused` | Already snake_case |
    | `no_contact` | `no_contact` | Already snake_case |
    | `deferred` | `deferred` | Already snake_case |
    | `ppl_approved` | `ppl_approved` | Already snake_case |
    | `units_requiring_notes` | `units_requiring_notes` | Already snake_case |
    | `units_with_notes` | `units_with_notes` | Already snake_case |
    | `units_without_notes` | `units_without_notes` | Already snake_case |
    | `notes_compliance_percent` | `notes_compliance_percent` | Already snake_case |
    | `pending_over_threshold` | `pending_over_threshold` | Already snake_case |
    | `stations_with_work` | `stations_with_work` | Already snake_case |
    | `stations_no_work` | `stations_no_work` | Already snake_case |
    | `stations_not_planned` | `stations_not_planned` | Already snake_case |
    | `split_count` | `split_count` | Aliased from `SplitAssessmentCount` in SQL |
    | `split_updated` | `split_updated` | Aliased from `SplitAssessmentUpdatedFlag` — may be string, cast to bool |
    | `taken_date` | `taken_date` | Carbon instance from `parseWsDate()` |
    | `sent_to_qc_date` | `sent_to_qc_date` | Carbon instance from `parseWsDate()` |
    | `sent_to_rework_date` | `sent_to_rework_date` | Carbon instance from `parseWsDate()` |
    | `closed_date` | `closed_date` | Carbon instance from `parseWsDate()` |
    | `first_unit_date` | `first_unit_date` | Carbon instance from `parseWsDate()` |
    | `last_unit_date` | `last_unit_date` | Carbon instance from `parseWsDate()` |
    | `oldest_pending_date` | `oldest_pending_date` | Carbon instance from `parseWsDate()` |
    | `oldest_pending_statname` | `oldest_pending_statname` | Already snake_case |
    | `oldest_pending_unit` | `oldest_pending_unit` | Already snake_case |
    | `oldest_pending_sequence` | `oldest_pending_sequence` | Already snake_case |
    | `work_type_breakdown` | `work_type_breakdown` | JSON string — decode, enrich, re-encode |
    | `foresters` | _(used by `persistContributors` only)_ | JSON string — not stored on metrics |
    - `persistContributors(Collection $metricsRows): void` — iterates the Collection, for each row:
      1. Decode `foresters` JSON column (already `[{forester: "DOMAIN\\user", unit_count: 3}, ...]`)
      2. For each forester entry:
         - Parse `DOMAIN\username` into domain + username parts
         - Look up `WsUser::where('username', $rawUsername)->first()` to get `ws_user_id`
         - `AssessmentContributor::updateOrCreate(['job_guid' => $jobGuid, 'ws_username' => $rawUsername], ['ws_user_id' => $wsUserId, 'unit_count' => $unitCount])`
  - Notes: Both methods should use the command's `$this->info()` / `$this->output` for progress logging. UnitType lookup should be cached in a local array to avoid N+1 queries. WsUser lookup should also be pre-loaded.

- [x] Task 7: Wire persistence into `FetchAssessments`
  - File: `app/Console/Commands/Fetch/FetchAssessments.php`
  - Action: Replace the `dd($additionalAssessmentMetrics->take(5))` on line 97 with:

    ```php
    $this->persistMetrics($additionalAssessmentMetrics);
    $this->persistContributors($additionalAssessmentMetrics);
    ```

  - Notes: Keep the existing `$this->info('Time to grab some more details...')` message. Add import for `AssessmentMetric` and `AssessmentContributor` if needed (though they're used via the trait).

- [x] Task 8: Create factories
  - File: `database/factories/AssessmentMetricFactory.php`
  - Action: Create factory with:
    - `definition()`: realistic defaults — permission counts that sum to total_units, compliance percent matching the math, date fields using `fake()->date()`, work_type_breakdown as sample JSON array
    - State: `withHighCompliance()`, `withAgingUnits()`
  - File: `database/factories/AssessmentContributorFactory.php`
  - Action: Create factory with:
    - `definition()`: ws_username as `ASPLUNDH\\` + fake username, unit_count, role nullable
    - States: `forester()`, `qcReviewer()`, `withLocalUser()`

- [x] Task 9: Write Pest feature tests
  - File: `tests/Feature/Assessments/AssessmentMetricTest.php`
  - Action: Tests covering:
    - Factory create + basic attribute verification
    - Relationship: `AssessmentMetric` → `Assessment` via job_guid
    - Relationship: `Assessment` → `metrics` (hasOne) returns correct instance
    - JSON column: `work_type_breakdown` round-trips correctly as array
    - Date columns: stored as date, cast properly
  - File: `tests/Feature/Assessments/AssessmentContributorTest.php`
  - Action: Tests covering:
    - Factory create + attribute verification
    - Relationship: contributor → assessment, contributor → wsUser
    - Composite uniqueness: two contributors with same job_guid + ws_username → only one row (updateOrCreate)
    - Role assignment: initially null, updatable by admin
    - Relationship: `Assessment` → `contributors` (hasMany) returns collection

### Acceptance Criteria

- [x] AC 1: Given a `getAdditionalMetrics()` result Collection, when `persistMetrics()` is called, then one `assessment_metrics` row is created per job_guid with all numeric fields stored as integers, dates as `Y-m-d` format, and `notes_compliance_percent` as decimal(5,1).
- [x] AC 2: Given the same job_guid is fetched twice, when `persistMetrics()` runs again, then the existing `assessment_metrics` row is updated (not duplicated) via `firstOrNew` on `job_guid`.
- [x] AC 3: Given a `work_type_breakdown` JSON from the API like `[{unit: "REM612", UnitQty: 45.00}]`, when persisted, then the stored JSON is `[{unit: "REM612", display_name: "<entityname from UnitType>", quantity: 45}]` with integer quantity and display name populated.
- [x] AC 4: Given a `foresters` JSON with entries like `[{forester: "ASPLUNDH\\jdoe", unit_count: 12}]`, when `persistContributors()` is called, then one `assessment_contributors` row is created with `ws_username = "ASPLUNDH\\jdoe"`, `unit_count = 12`, and `ws_user_id` resolved from `WsUser` table (or null if not found).
- [x] AC 5: Given the same forester appears on a re-fetch with a different unit_count, when `persistContributors()` runs, then the existing row is updated (via `updateOrCreate` on `job_guid` + `ws_username`), not duplicated.
- [x] AC 6: Given an `AssessmentContributor` row exists with `role = null`, when an admin sets `role = "Lead Assessor"`, then the value persists and is retrievable.
- [x] AC 7: Given an `Assessment` with metrics and contributors, when `$assessment->metrics` is accessed, then the related `AssessmentMetric` is returned. When `$assessment->contributors` is accessed, then a Collection of `AssessmentContributor` instances is returned.
- [x] AC 8: Given an `Assessment` is deleted, when cascade fires, then all related `assessment_metrics` and `assessment_contributors` rows are also deleted.
- [x] AC 9: Given the `foresters` JSON is null or empty for a job, when `persistContributors()` runs for that job, then no contributor rows are created and no errors occur.
- [x] AC 10: Given a UnitType code in `work_type_breakdown` that doesn't exist in the `unit_types` table, when persisted, then `display_name` falls back to the raw unit code string (no error).

## Additional Context

### Dependencies

- `assessments` table must exist with `job_guid` string(38) unique column (it does)
- `ws_users` table must exist (it does — for contributor FK resolution)
- `unit_types` table must exist with `unit` and `entityname` columns (it does — for work_type_breakdown enrichment)
- `users` table must exist (it does — for optional local user FK on contributors)

### Testing Strategy

- **Feature tests with RefreshDatabase:** All tests use Pest 4 closure-based syntax
- **Factories:** `AssessmentMetricFactory` with states for compliance/aging variants; `AssessmentContributorFactory` with states for forester/QC/planner roles
- **Relationship tests:** Verify bidirectional traversal Assessment ↔ metrics, Assessment ↔ contributors
- **Upsert tests:** Verify idempotency — running persistence twice with same data doesn't duplicate rows
- **Edge cases:** null foresters JSON, missing UnitType codes, empty date fields
- **No integration tests against live WS API** — mock the Collection that `getAdditionalMetrics()` returns

### Notes

- `FetchAssessments.handle()` currently has `upsertAssessments()` and `syncFromApi()` commented out, with `dd()` on line 97. Only replace the `dd()` line. The commented-out code is a separate concern and should not be modified in this task.
- The `foresters` JSON from the query uses `FRSTR_USER` which is `DOMAIN\username` format. Match against `WsUser.username` which stores the full `DOMAIN\username` string. Use `WsUser::where('username', $rawForester)->first()` for FK resolution.
- `work_type_breakdown` JSON from query: `[{unit: "REM612", UnitQty: 45.00}, ...]`. Enrich to: `[{unit: "REM612", display_name: "Removal 6-12 DBH", quantity: 45}, ...]` using `UnitType::where('unit', $code)->value('entityname')`.
- Pre-load UnitType and WsUser lookups into keyed arrays before iterating to avoid N+1. Example: `$unitTypeMap = UnitType::pluck('entityname', 'unit')->all()`.
- The `oldest_pending_statname`, `oldest_pending_unit`, `oldest_pending_sequence` fields come from the `OldestPending` OUTER APPLY in the SQL — they are single-row values per job, not arrays.
- `split_count` and `split_updated` come from `SSCUSTOM` table — may be null for jobs without split assessment configuration.

## Review Notes

- Adversarial review completed (14 findings)
- Findings: 14 total, 4 fixed (F1-F4), 4 acknowledged low-severity (F5-F8), 6 out-of-scope/noise
- Resolution approach: selective fix (High + Medium severity)
- Fixed: DB::transaction wrapping, factory arithmetic overflow, array_values normalization, persistence trait test coverage
- Also fixed during implementation: PSR-4 filename mismatch (trait file renamed), null-coalesce on nullable fields

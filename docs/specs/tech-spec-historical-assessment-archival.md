---
title: 'Historical Assessment Archival & Planner Performance Analytics'
slug: 'historical-assessment-archival'
created: '2026-02-05'
status: 'ready'
stepsCompleted: [0, 1, 2, 3]
discovery_completed: '2026-02-06'
discovery_results: 'docs/discovery-query-results.md'
tech_stack:
  - PHP 8.4
  - Laravel 12
  - Livewire 4
  - Pest v4
  - DaisyUI v5
  - Tailwind CSS v4
files_to_modify:
  - 'database/migrations/ (5 new migrations)'
  - 'app/Models/ (5 new models)'
  - 'app/Services/WorkStudio/Archival/ (new service directory)'
  - 'app/Services/WorkStudio/Archival/AssessmentArchivalService.php'
  - 'app/Services/WorkStudio/Archival/ArchivalQueryBuilder.php'
  - 'app/Services/WorkStudio/Archival/MetricsCalculator.php'
  - 'app/Services/WorkStudio/Contracts/AssessmentArchivalInterface.php'
  - 'app/Livewire/DataManagement/ImportConfiguration.php'
  - 'app/Livewire/DataManagement/ImportHistory.php'
  - 'resources/views/livewire/data-management/import-configuration.blade.php'
  - 'resources/views/livewire/data-management/import-history.blade.php'
  - 'database/factories/ (5 new factories)'
  - 'tests/Feature/Archival/ (new test directory)'
  - 'tests/Unit/Archival/ (new test directory)'
code_patterns:
  - 'Service with interface contract (per PROJECT_RULES.md)'
  - 'Constructor property promotion (PHP 8.4)'
  - 'Value objects for immutable data (like UserQueryContext)'
  - 'SqlFragmentHelpers trait for date conversion'
  - '#[Computed] for expensive Livewire properties'
  - '#[Url] for persistent Livewire UI state'
  - 'Anonymous migration classes'
  - 'Foreign keys with cascade delete'
  - 'JSON columns for flexible arrays'
test_patterns:
  - 'Pest v4 closure syntax'
  - 'Feature tests with factory usage'
  - 'Mocked HTTP responses for API tests'
  - 'Livewire testing with assertSee/assertSet'
related_docs:
  - 'BMAD_WS/implementation-artifacts/planner-activity-rules.md'
  - 'docs/discovery-query-results.md'
---

# Tech-Spec: Historical Assessment Archival & Planner Performance Analytics

**Created:** 2026-02-05

## Overview

### Problem Statement

The WS-TrackerV1 application fetches ALL WorkStudio data live via external API. For closed assessments, this data is immutable but requires repeated expensive API calls for analysis. There's no way to analyze historical planner performance (planning duration, time-to-QC, footage metrics) across closed circuits because the data isn't persisted locally.

### Solution

Build a local data archival system that imports closed assessment data from the WorkStudio API into a local database, pre-computes planner performance analytics (using the same metrics logic as active monitoring per `planner-activity-rules.md`), and provides an admin interface for configuring and triggering imports. The archived data becomes the source of truth for historical analytics, eliminating redundant API calls and enabling rich performance reporting.

### Scope

**In Scope:**

- 5 new database tables (import configs, archived assessments, units, history, planner metrics)
- Import service with three-phase extraction (circuits → units → history)
- Pre-computed analytics: planning duration, time-to-QC, footage metrics (First Unit Wins rules)
- Filtering by region AND specific planner usernames
- Support for any scope year (captured as data point, not filter boundary)
- Livewire admin page for import configuration and management (Data Management category)
- Idempotent imports — re-running catches newly closed circuits
- ✅ Discovery queries completed — JOBHISTORY schema mapped, QC detection logic confirmed

**Out of Scope:**

- Active (`ACTIV`) circuit monitoring (handled by existing live system)
- Real-time sync or webhooks from WorkStudio
- Modifying the existing live query system (CachedQueryService, GetQueryService)
- Editing or deleting archived records (immutable once imported)

## Context for Development

### Project Rules (from PROJECT_RULES.md)

- **DaisyUI** for all UI components with theme support
- **Services must implement interfaces** — enables testing and flexibility
- **Inject dependencies via constructor** — use Laravel's container
- **New features require tests** — Pest v4 conventions
- **Use migrations for all schema changes** — factories required for new models
- **Run `vendor/bin/pint`** before every commit

### Codebase Patterns

- **Service Layer:** Livewire → CachedQueryService → GetQueryService → AssessmentQueries → External API
- **Value Objects:** `UserQueryContext` scopes all queries by year, context, and dataset
- **Cache Key Pattern:** `ws:{year}:ctx:{hash}:{dataset}`
- **MS Date Conversion:** `SqlFragmentHelpers` trait handles OLE Automation date floats
- **Existing Queries:** `AssessmentQueries.php` contains complex SQL with CROSS APPLY patterns

### Business Rules Reference

The `planner-activity-rules.md` document defines critical computation rules:

- **First Unit Wins:** Footage credited to planner who creates first unit in a station
- **Station Counting:** Each station counted once per planner on date of their first unit
- **Unit Classification:** Work units (SPM, REM612, etc.) vs Non-work (NW, NOT, SENSI)
- **Two-tier metrics:** Circuit owner vs Unit forester (FORESTER)

> **Discovery Finding (2026-02-06):** `SS.TAKENBY` is NULL/empty for CLOSE circuits — ownership is cleared when a circuit closes. To identify who worked on a closed circuit:
> - **Circuit-level:** Query JOBHISTORY.USERNAME for the user who performed the ACTIV→QC transition
> - **Unit-level:** Use VEGUNIT.FORESTER (contains full names like "Paul Longenecker")
> - **Username format:** `DOMAIN\username` (e.g., `ASPLUNDH\jcompton`, `PPL\lehi lci1`)

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `app/Services/WorkStudio/Services/GetQueryService.php` | Existing API execution — reuse for archival queries |
| `app/Services/WorkStudio/Services/CachedQueryService.php` | Singleton pattern, context scoping reference |
| `app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php` | SQL patterns: CROSS APPLY, FOR JSON PATH |
| `app/Services/WorkStudio/AssessmentsDx/Queries/SqlFragmentHelpers.php` | MS date conversion: `parseMsDateToDate()`, `formatToEasternTime()` |
| `app/Services/WorkStudio/ValueObjects/UserQueryContext.php` | Immutable readonly value object pattern |
| `app/Services/WorkStudio/Helpers/WSHelpers.php` | `toSqlInClause()` for safe SQL generation |
| `app/Services/WorkStudio/Contracts/WorkStudioApiInterface.php` | Interface contract pattern |
| `app/Providers/WorkStudioServiceProvider.php` | Service registration, singleton binding |
| `app/Livewire/DataManagement/CacheControls.php` | Admin component patterns: computed, flash, dispatch |
| `app/Models/User.php` | Model pattern: casts, relationships, HasFactory |
| `app/Models/UserWsCredential.php` | Encrypted fields, belongsTo relationship |
| `database/migrations/2026_01_12_100006_create_user_ws_credentials_table.php` | Migration pattern: FK, unique, comments |
| `tests/Feature/Auth/AuthenticationTest.php` | Pest v4 test patterns |
| `config/ws_assessment_query.php` | Configuration pattern for query filters |
| `BMAD_WS/implementation-artifacts/planner-activity-rules.md` | Business rules for metrics computation |
| `_bmad/ws/data/tables/core-jobs.md` | SS, SSCUSTOM, JOBHISTORY schema reference |
| `_bmad/ws/data/tables/vegetation.md` | VEGJOB, VEGUNIT schema reference |
| `_bmad/ws/data/tables/relationships.md` | Table relationship diagrams |

### Technical Decisions

**TD-1: Service Architecture**
- Create new `app/Services/WorkStudio/Archival/` directory for archival-specific code
- `AssessmentArchivalService` implements `AssessmentArchivalInterface` (per PROJECT_RULES.md)
- Service depends on existing `GetQueryService` for API calls — no duplication
- Register as singleton in `WorkStudioServiceProvider` for import state consistency

**TD-2: Query Builder Pattern**
- New `ArchivalQueryBuilder` class extends patterns from `AssessmentQueries`
- Reuse `SqlFragmentHelpers` trait for MS date conversion
- Reuse `WSHelpers::toSqlInClause()` for safe IN clause generation

**TD-3: Metrics Computation**
- Dedicated `MetricsCalculator` class encapsulates analytics logic
- Implements "First Unit Wins" rule per `planner-activity-rules.md`
- Computes metrics at import time, stores as denormalized columns
- Unit classification uses `config('ws_assessment_query.non_work_units')`

**TD-4: Import Idempotency**
- `job_guid` unique constraint prevents duplicate imports
- On re-import: query API for CLOSE circuits WHERE job_guid NOT IN (already archived)
- Track `import_config_id` to associate imports with their configuration

**TD-5: Livewire Admin UI**
- Place in existing `app/Livewire/DataManagement/` alongside `CacheControls`
- Follow `CacheControls` patterns: `#[Computed]`, flash messaging, exception handling
- Two components: `ImportConfiguration` (CRUD configs) + `ImportHistory` (view results)

**TD-6: Date Handling**
- JOBHISTORY.LOGDATE uses format: `/Date(2018-07-31T10:59:46.000Z)/` — extract ISO string and parse with Carbon
- VEGUNIT dates may still use MS OLE Automation floats — use existing `parseMsDateToDate()`
- Store as proper `datetime` columns in local DB for native Carbon/Eloquent support
- All stored dates in UTC; convert to Eastern for display if needed
- Date parsing helper:
  ```php
  preg_match('/Date\((.*?)\)/', $logDate, $matches);
  $carbon = Carbon::parse($matches[1]);
  ```

**TD-7: Large Import Handling**
- If import exceeds 120s API timeout, batch by region or chunk JOBGUIDs
- Consider Laravel Queue jobs for imports >100 circuits
- Track progress via `import_config.total_records_imported`

## Implementation Plan

### Database Schema

#### Table 1: `assessment_import_configs`

Import configuration — who and what to import.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `name` | string(255) | Config name (e.g., "2025 Northeast") |
| `scope_year` | string(10) | Scope year filter (nullable = all years) |
| `regions` | json | Array of region codes |
| `contractors` | json | Array of contractor codes |
| `target_planners` | json | Array of planner usernames (nullable = all) |
| `status_filter` | string(10) | Default 'CLOSE' |
| `last_imported_at` | timestamp | Last successful import |
| `total_records_imported` | integer | Count of circuits imported |
| `created_by` | foreignId | FK → users |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### Table 2: `archived_assessments`

One row per circuit — denormalized from SS + VEGJOB + SSCUSTOM.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `import_config_id` | foreignId | FK → assessment_import_configs |
| `job_guid` | string(38) | Unique WorkStudio JOBGUID |
| `work_order` | string(50) | WO number |
| `extension` | string(10) | Extension |
| `status` | string(10) | CLOSE (at import time) |
| `title` | string(255) | Job title |
| `job_type` | string(30) | Job type |
| `planner` | string(100) | Primary planner — derived from JOBHISTORY (ACTIV→QC transition USERNAME) since SS.TAKENBY is empty for CLOSE |
| `assigned_to` | string(100) | SS.ASSIGNEDTO |
| `taken_date` | datetime | When planner took ownership |
| `create_date` | date | Circuit creation date |
| `edit_date` | datetime | Last edit/sync date |
| `line_name` | string(255) | VEGJOB.LINENAME |
| `region` | string(50) | VEGJOB.REGION |
| `cycle_type` | string(50) | VEGJOB.CYCLETYPE |
| `contractor` | string(100) | VEGJOB.CONTRACTOR |
| `utility` | string(50) | VEGJOB.OPCO |
| `department` | string(100) | VEGJOB.SERVCOMP |
| `total_miles` | decimal(10,2) | VEGJOB.LENGTH |
| `completed_miles` | decimal(10,2) | VEGJOB.LENGTHCOMP |
| `percent_complete` | decimal(5,2) | VEGJOB.PRCENT |
| `completed_date` | date | VEGJOB.COMPLETEDDATE |
| `site_count` | integer | VEGJOB.SITECOUNT |
| `estimated_minutes` | integer | SS.ESTMINS |
| `group_assigned_to` | text | SSCUSTOM.GROUPASSIGNEDTO_USERS |
| `scope_year` | string(10) | Extracted from WPStartDate_Assessment_Xrefs |
| **--- Pre-computed Analytics ---** | | |
| `planning_started_at` | datetime | First unit ASSDDATE or TAKENDATE |
| `planning_completed_at` | datetime | Last unit ASSDDATE |
| `planning_duration_days` | decimal(8,2) | Days from start to completion |
| `qc_submitted_at` | datetime | From JOBHISTORY status→QC event |
| `time_to_qc_days` | decimal(8,2) | TAKENDATE → QC event |
| `total_units_planned` | integer | Count of work units |
| `total_stations_completed` | integer | Count of unique stations |
| `total_footage_meters` | decimal(12,2) | Sum of station SPANLGTH |
| `total_foresters` | integer | COUNT(DISTINCT FORESTER) |
| `imported_at` | timestamp | When archived |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `unique: job_guid` — Prevent duplicate imports
- `index: import_config_id`
- `composite: planner, scope_year`
- `composite: region, scope_year`
- `index: status`

#### Table 3: `archived_assessment_units`

One row per VEGUNIT — detail data for drill-down.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `archived_assessment_id` | foreignId | FK → archived_assessments |
| `job_guid` | string(38) | Indexed for bulk operations |
| `veg_unit_guid` | string(38) | VEGUNIT.VEGUNITGUID |
| `station_name` | string(50) | VEGUNIT.STATNAME |
| `unit_type` | string(20) | VEGUNIT.UNIT |
| `forester` | string(100) | VEGUNIT.FORESTER |
| `assessed_date` | datetime | VEGUNIT.ASSDDATE |
| `permission_status` | string(50) | VEGUNIT.PERMSTAT |
| `acres` | decimal(10,4) | VEGUNIT.ACRES |
| `length_work` | decimal(10,2) | VEGUNIT.LENGTHWRK |
| `is_first_in_station` | boolean | Computed: First Unit Wins |
| `created_at` | timestamp | |

**Indexes:**
- `index: archived_assessment_id`
- `index: job_guid`
- `index: forester`
- `composite: station_name, assessed_date`

#### Table 4: `archived_assessment_history`

JOBHISTORY events for timeline reconstruction.

> **Discovery Update (2026-02-06):** Column names confirmed via API queries. See `docs/discovery-query-results.md`.

| Column | Type | Description | Source Column |
|--------|------|-------------|---------------|
| `id` | bigint (PK) | Auto-increment | — |
| `archived_assessment_id` | foreignId | FK → archived_assessments | — |
| `job_guid` | string(38) | Indexed for bulk operations | JOBHISTORY.JOBGUID |
| `log_date` | datetime | When event occurred (parsed from `/Date(ISO)/` format) | JOBHISTORY.LOGDATE |
| `log_time` | string(20) | Time string for display | JOBHISTORY.LOGTIME |
| `action` | string(100) | Event type (e.g., 'Execute Job Transition', 'Change Job Status') | JOBHISTORY.ACTION |
| `username` | string(100) | User who performed action (format: `DOMAIN\user`) | JOBHISTORY.USERNAME |
| `old_status` | string(20) | Status before change | JOBHISTORY.OLDSTATUS |
| `job_status` | string(20) | Status after change | JOBHISTORY.JOBSTATUS |
| `comments` | text | Event notes | JOBHISTORY.COMMENTS |
| `created_at` | timestamp | | — |

**Indexes:**
- `index: archived_assessment_id`
- `index: job_guid`
- `composite: action, log_date`
- `composite: old_status, job_status` — For QC transition queries

#### Table 5: `planner_performance_metrics`

Pre-computed analytics aggregated per planner per scope year per region.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `planner_username` | string(100) | SS.TAKENBY |
| `scope_year` | string(10) | |
| `region` | string(50) | |
| `total_circuits_completed` | integer | Count of CLOSE circuits |
| `total_miles_planned` | decimal(12,2) | Sum of completed_miles |
| `total_units_planned` | integer | Sum across circuits |
| `total_stations_completed` | integer | Sum across circuits |
| `total_footage_meters` | decimal(14,2) | Sum of footage |
| `avg_planning_duration_days` | decimal(8,2) | AVG(planning_duration_days) |
| `avg_time_to_qc_days` | decimal(8,2) | AVG(time_to_qc_days) |
| `avg_units_per_circuit` | decimal(8,2) | |
| `fastest_circuit_days` | decimal(8,2) | MIN(planning_duration_days) |
| `slowest_circuit_days` | decimal(8,2) | MAX(planning_duration_days) |
| `circuits_reworked_count` | integer | Circuits that hit REWRK |
| `computed_at` | timestamp | When metrics were calculated |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `composite unique: planner_username, scope_year, region`
- `index: scope_year`
- `index: region`

### Tasks

#### Phase 0: Discovery (Pre-Implementation) ✅ COMPLETED

- [x] **Task 0.1: Run ACTION Discovery Query**
  - File: `docs/discovery-query-results.md`
  - Action: Executed discovery queries via tinker
  - Results: JOBHISTORY uses `ACTION` column (not HISTORYTYPE). Top actions: "Save Work Order" (9.2M), "Execute Job Transition" (124K), "Change Job Status" (74K)
  - **QC Detection:** `WHERE JOBSTATUS = 'QC' AND OLDSTATUS IN ('ACTIV', 'REV', 'REWRK') AND ACTION IN ('Execute Job Transition', 'Change Job Status')`

- [x] **Task 0.2: Run Planner Username Discovery Query**
  - File: `docs/discovery-query-results.md`
  - Action: Confirmed username formats
  - Results:
    - JOBHISTORY.USERNAME: `DOMAIN\username` format (e.g., `ASPLUNDH\jcompton`)
    - VEGUNIT.FORESTER: Full names (e.g., "Paul Longenecker")
    - SS.TAKENBY: Empty for CLOSE circuits — must use JOBHISTORY instead

- [x] **Task 0.3: Verify Data Volumes**
  - Results: 24,183 CLOSE circuits, 15.2M JOBHISTORY records, ~14.7M match CLOSE circuits

#### Phase 1: Database Layer

- [ ] **Task 1.1: Create assessment_import_configs Migration**
  - File: `database/migrations/{timestamp}_create_assessment_import_configs_table.php`
  - Action: Create migration with columns per schema; FK to users with cascade; unique name constraint
  - Notes: Use `php artisan make:migration create_assessment_import_configs_table`

- [ ] **Task 1.2: Create archived_assessments Migration**
  - File: `database/migrations/{timestamp}_create_archived_assessments_table.php`
  - Action: Create migration with all 30+ columns; FK to import_configs; unique job_guid; composite indexes
  - Notes: Include all pre-computed analytics columns; add comments on key fields

- [ ] **Task 1.3: Create archived_assessment_units Migration**
  - File: `database/migrations/{timestamp}_create_archived_assessment_units_table.php`
  - Action: Create migration; FK to archived_assessments with cascade; indexes per schema
  - Notes: Include `is_first_in_station` boolean for First Unit Wins computation

- [ ] **Task 1.4: Create archived_assessment_history Migration**
  - File: `database/migrations/{timestamp}_create_archived_assessment_history_table.php`
  - Action: Create migration; FK to archived_assessments with cascade; composite index on type+date
  - Notes: history_notes as TEXT for long notes

- [ ] **Task 1.5: Create planner_performance_metrics Migration**
  - File: `database/migrations/{timestamp}_create_planner_performance_metrics_table.php`
  - Action: Create migration; unique composite on planner+year+region; all metric columns
  - Notes: No FK to other tables — computed independently

- [ ] **Task 1.6: Run Migrations**
  - File: N/A
  - Action: `php artisan migrate`
  - Notes: Verify all 5 tables created successfully

#### Phase 2: Models & Factories

- [ ] **Task 2.1: Create AssessmentImportConfig Model**
  - File: `app/Models/AssessmentImportConfig.php`
  - Action: `php artisan make:model AssessmentImportConfig -f`; add casts (json for arrays, datetime), relationships (belongsTo User, hasMany ArchivedAssessment), fillable
  - Notes: Add scope `scopeForUser($query, $userId)`

- [ ] **Task 2.2: Create ArchivedAssessment Model**
  - File: `app/Models/ArchivedAssessment.php`
  - Action: `php artisan make:model ArchivedAssessment -f`; add all casts (decimals, dates, integers), relationships (belongsTo ImportConfig, hasMany Units/History)
  - Notes: Add scopes: `scopeForPlanner`, `scopeForRegion`, `scopeForYear`

- [ ] **Task 2.3: Create ArchivedAssessmentUnit Model**
  - File: `app/Models/ArchivedAssessmentUnit.php`
  - Action: `php artisan make:model ArchivedAssessmentUnit -f`; belongsTo ArchivedAssessment; casts for decimals/dates/boolean
  - Notes: Add scope `scopeFirstInStation()` for First Unit Wins queries

- [ ] **Task 2.4: Create ArchivedAssessmentHistory Model**
  - File: `app/Models/ArchivedAssessmentHistory.php`
  - Action: `php artisan make:model ArchivedAssessmentHistory -f`; belongsTo ArchivedAssessment; datetime cast for history_date
  - Notes: Add scope `scopeStatusChanges()` for QC detection

- [ ] **Task 2.5: Create PlannerPerformanceMetric Model**
  - File: `app/Models/PlannerPerformanceMetric.php`
  - Action: `php artisan make:model PlannerPerformanceMetric -f`; all decimal casts; scopes for planner/year/region
  - Notes: Standalone model — no FK relationships

- [ ] **Task 2.6: Create Factories for All Models**
  - Files: `database/factories/AssessmentImportConfigFactory.php`, etc. (5 factories)
  - Action: Define realistic fake data for each model; use existing User factory for relationships
  - Notes: ArchivedAssessment factory should generate valid job_guid format `{XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}`

#### Phase 3: Service Layer

- [ ] **Task 3.1: Create AssessmentArchivalInterface**
  - File: `app/Services/WorkStudio/Contracts/AssessmentArchivalInterface.php`
  - Action: Define interface with methods: `runImport()`, `discoverNewCircuits()`, `recomputeMetrics()`
  - Notes: Follow existing `WorkStudioApiInterface` pattern

- [ ] **Task 3.2: Create ArchivalQueryBuilder**
  - File: `app/Services/WorkStudio/Archival/ArchivalQueryBuilder.php`
  - Action: Create query builder class using `SqlFragmentHelpers` trait; methods for Phase 1/2/3 extraction queries
  - Notes: Parameterize by regions, contractors, planners; use `WSHelpers::toSqlInClause()`

- [ ] **Task 3.3: Create MetricsCalculator**
  - File: `app/Services/WorkStudio/Archival/MetricsCalculator.php`
  - Action: Create class with methods: `calculatePlanningDuration()`, `calculateTimeToQc()`, `calculateFootageMetrics()`, `markFirstInStation()`
  - Notes: Implement First Unit Wins logic per planner-activity-rules.md; use `config('ws_assessment_query.non_work_units')`

- [ ] **Task 3.4: Create AssessmentArchivalService**
  - File: `app/Services/WorkStudio/Archival/AssessmentArchivalService.php`
  - Action: Implement `AssessmentArchivalInterface`; inject `GetQueryService`; orchestrate 3-phase import with DB transaction
  - Notes: Return `ImportResult` value object with counts and any errors

- [ ] **Task 3.5: Create ImportResult Value Object**
  - File: `app/Services/WorkStudio/Archival/ImportResult.php`
  - Action: Create readonly class with properties: `circuitsImported`, `unitsImported`, `historyEventsImported`, `errors`, `duration`
  - Notes: Follow `UserQueryContext` pattern

- [ ] **Task 3.6: Register Service in Provider**
  - File: `app/Providers/WorkStudioServiceProvider.php`
  - Action: Add singleton binding for `AssessmentArchivalService` implementing `AssessmentArchivalInterface`
  - Notes: Add to `register()` method after existing bindings

#### Phase 4: Livewire Admin UI

- [ ] **Task 4.1: Create ImportConfiguration Component**
  - File: `app/Livewire/DataManagement/ImportConfiguration.php`
  - Action: Create Livewire component with: list configs, create/edit form, delete confirmation, trigger import action
  - Notes: Follow `CacheControls` patterns; use `#[Computed]` for config list; flash messaging for feedback

- [ ] **Task 4.2: Create ImportConfiguration View**
  - File: `resources/views/livewire/data-management/import-configuration.blade.php`
  - Action: Create DaisyUI view with: config table, modal for create/edit, import button with loading state
  - Notes: Use DaisyUI components: table, modal, form inputs, buttons with loading

- [ ] **Task 4.3: Create ImportHistory Component**
  - File: `app/Livewire/DataManagement/ImportHistory.php`
  - Action: Create component showing: imported assessments list, filter by config/planner/region, pagination
  - Notes: Use `#[Url]` for filter persistence; `#[Computed]` for data

- [ ] **Task 4.4: Create ImportHistory View**
  - File: `resources/views/livewire/data-management/import-history.blade.php`
  - Action: Create DaisyUI view with: filter dropdowns, data table with sorting, pagination
  - Notes: Show key metrics inline (planning_duration, time_to_qc, etc.)

- [ ] **Task 4.5: Add Routes/Navigation**
  - File: `routes/web.php` and navigation component
  - Action: Add routes for import-configuration and import-history; add nav links under Data Management
  - Notes: Apply appropriate middleware (auth, verified)

#### Phase 5: Testing

- [ ] **Task 5.1: Create Unit Tests for MetricsCalculator**
  - File: `tests/Unit/Archival/MetricsCalculatorTest.php`
  - Action: Test planning duration calculation, time-to-QC, First Unit Wins logic, unit classification
  - Notes: Use Pest v4 syntax; test edge cases (no units, single unit, multi-planner station)

- [ ] **Task 5.2: Create Feature Tests for Import Service**
  - File: `tests/Feature/Archival/AssessmentArchivalServiceTest.php`
  - Action: Test full import flow with mocked HTTP responses; test idempotency (no duplicates on re-import)
  - Notes: Use Http::fake() for API mocking; assert database records created correctly

- [ ] **Task 5.3: Create Livewire Tests for Admin Components**
  - File: `tests/Feature/Archival/ImportConfigurationTest.php`
  - Action: Test create config, edit config, trigger import, delete config; test validation
  - Notes: Use Livewire::test() with factory data

- [ ] **Task 5.4: Create Model Tests**
  - File: `tests/Unit/Archival/ModelsTest.php`
  - Action: Test relationships, scopes, casts for all 5 models
  - Notes: Verify factory data generates valid records

#### Phase 6: Documentation & Cleanup

- [ ] **Task 6.1: Update CHANGELOG.md**
  - File: `CHANGELOG.md`
  - Action: Add entries under [Unreleased] for new archival feature
  - Notes: Follow Keep a Changelog format

- [ ] **Task 6.2: Run Pint**
  - File: N/A
  - Action: `vendor/bin/pint`
  - Notes: Fix any formatting issues before commit

- [ ] **Task 6.3: Run Full Test Suite**
  - File: N/A
  - Action: `php artisan test --compact`
  - Notes: All tests must pass before merge

### Acceptance Criteria

#### Core Import Functionality

- [ ] **AC-1:** Given an import config with regions and planners defined, when `runImport()` is called, then closed circuits matching the filters are imported into `archived_assessments`
- [ ] **AC-2:** Given a circuit is already archived (by job_guid), when the same import config is run again, then no duplicate records are created and new closed circuits are added
- [ ] **AC-3:** Given a circuit import, when units are fetched, then all VEGUNIT records are stored in `archived_assessment_units` with correct FK relationship
- [ ] **AC-4:** Given a circuit import, when history is fetched, then all JOBHISTORY records are stored in `archived_assessment_history` with correct FK relationship

#### Metrics Computation

- [ ] **AC-5:** Given archived units for a circuit, when metrics are computed, then `planning_started_at` equals the earliest ASSDDATE and `planning_completed_at` equals the latest ASSDDATE
- [ ] **AC-6:** Given archived history for a circuit, when a status→QC event is found, then `qc_submitted_at` is populated and `time_to_qc_days` is calculated as (qc_date - taken_date)
- [ ] **AC-7:** Given multiple units in a station, when First Unit Wins is applied, then only the first unit (by ASSDDATE, EDITDATE) has `is_first_in_station = true`
- [ ] **AC-8:** Given archived assessments for a planner, when metrics are aggregated, then `planner_performance_metrics` contains correct averages for planning_duration and time_to_qc

#### Admin UI

- [ ] **AC-9:** Given an authenticated admin user, when they navigate to Import Configuration, then they see a list of existing import configs with create/edit/delete actions
- [ ] **AC-10:** Given the import configuration form, when regions/contractors/planners are selected and saved, then a new `assessment_import_configs` record is created
- [ ] **AC-11:** Given an import config, when the user clicks "Run Import", then the import executes and shows success/failure feedback with record counts
- [ ] **AC-12:** Given the Import History page, when a user filters by planner or region, then only matching archived assessments are displayed

#### Error Handling

- [ ] **AC-13:** Given an API timeout during import, when the error occurs, then the partial import is rolled back and an error is logged with details
- [ ] **AC-14:** Given invalid import config data (empty regions), when save is attempted, then validation errors are displayed and no record is created

#### Data Integrity

- [ ] **AC-15:** Given an archived assessment, when it is deleted, then all related units and history records are cascade-deleted
- [ ] **AC-16:** Given the `job_guid` unique constraint, when a duplicate JOBGUID is inserted, then a database constraint violation occurs (handled gracefully)

## Additional Context

### Dependencies

**Internal Dependencies:**
- `GetQueryService` — API query execution (inject via constructor)
- `SqlFragmentHelpers` trait — MS date conversion (reuse via trait)
- `WSHelpers::toSqlInClause()` — Safe SQL IN clause generation
- `User` model — FK for created_by on import configs
- `Auth` facade — Current user for context

**External Dependencies:**
- WorkStudio API — GETQUERY endpoint for data extraction
- Laravel Queue (optional) — For large imports >100 circuits

**Prerequisite:** ✅ COMPLETED
- ~~JOBHISTORY column discovery (Phase 0)~~ — Completed 2026-02-06. Column is `ACTION`, QC detection via `JOBSTATUS = 'QC'`

### Discovery Queries (Phase 0) ✅ COMPLETED

> **Results documented in:** `docs/discovery-query-results.md` (generated 2026-02-06)

**Query 1: ACTION enumeration (corrected column name)**
```sql
SELECT TOP 30
    JH.ACTION,
    COUNT(*) AS event_count,
    COUNT(DISTINCT JH.JOBGUID) AS circuits_affected
FROM JOBHISTORY JH
INNER JOIN SS ON JH.JOBGUID = SS.JOBGUID
WHERE SS.STATUS = 'CLOSE'
GROUP BY JH.ACTION
ORDER BY event_count DESC
```

**Query 2: QC status transitions**
```sql
SELECT TOP 20
    JH.OLDSTATUS,
    JH.JOBSTATUS,
    JH.ACTION,
    COUNT(*) AS transition_count
FROM JOBHISTORY JH
INNER JOIN SS ON JH.JOBGUID = SS.JOBGUID
WHERE SS.STATUS = 'CLOSE'
    AND (JH.JOBSTATUS = 'QC' OR JH.OLDSTATUS = 'QC')
GROUP BY JH.OLDSTATUS, JH.JOBSTATUS, JH.ACTION
ORDER BY transition_count DESC
```

**Query 3: Planners from JOBHISTORY (since TAKENBY is empty for CLOSE)**
```sql
SELECT TOP 30
    JH.USERNAME AS planner_username,
    COUNT(DISTINCT JH.JOBGUID) AS circuits_worked
FROM JOBHISTORY JH
INNER JOIN SS ON JH.JOBGUID = SS.JOBGUID
WHERE SS.STATUS = 'CLOSE'
    AND JH.USERNAME IS NOT NULL
    AND LEN(JH.USERNAME) > 0
GROUP BY JH.USERNAME
ORDER BY circuits_worked DESC
```

**Query 4: Foresters from VEGUNIT**
```sql
SELECT TOP 30
    VU.FORESTER AS planner,
    COUNT(DISTINCT VU.JOBGUID) AS circuits_assessed,
    COUNT(*) AS total_units
FROM VEGUNIT VU
INNER JOIN SS ON VU.JOBGUID = SS.JOBGUID
WHERE SS.STATUS = 'CLOSE'
    AND VU.FORESTER IS NOT NULL
    AND LEN(VU.FORESTER) > 0
GROUP BY VU.FORESTER
ORDER BY circuits_assessed DESC
```

### Key Discovery Findings

| Finding | Value |
|---------|-------|
| CLOSE circuits | 24,183 |
| JOBHISTORY records | 15,184,522 |
| Records matching CLOSE | ~14.7M |
| Top planners (JOBHISTORY) | `PPL\meclayton` (12,110 circuits), `ECI\Scott eci` (6,597) |
| Top foresters (VEGUNIT) | Paul Longenecker (929 circuits), Tyler Azzaro (848) |
| QC transition pattern | `ACTIV→QC` or `REV→QC` via "Execute Job Transition" or "Change Job Status" |
| Date format | `/Date(2018-07-31T10:59:46.000Z)/` — ISO inside wrapper |

### Testing Strategy

**Unit Tests (`tests/Unit/Archival/`):**
- `MetricsCalculatorTest.php` — Planning duration, time-to-QC, First Unit Wins logic
- `ArchivalQueryBuilderTest.php` — SQL generation with various filter combinations
- `ModelsTest.php` — Casts, relationships, scopes for all 5 models

**Feature Tests (`tests/Feature/Archival/`):**
- `AssessmentArchivalServiceTest.php` — Full import flow with mocked HTTP; idempotency
- `ImportConfigurationTest.php` — Livewire CRUD operations, validation
- `ImportHistoryTest.php` — Filtering, pagination, display

**Mocking Strategy:**
- Use `Http::fake()` to mock WorkStudio API responses
- Create fixture JSON files for realistic API response data
- Use factories for all database test data

**Coverage Goals:**
- All public service methods tested
- All acceptance criteria have corresponding tests
- Edge cases: empty results, API errors, duplicate imports

### Notes

**Risk Areas:**
- ~~**HISTORYTYPE discovery**~~ ✅ RESOLVED — Column is `ACTION`, QC detection uses `JOBSTATUS = 'QC'` with `ACTION IN ('Execute Job Transition', 'Change Job Status')`
- **Large imports** — May need chunking if >100 circuits; API timeout is 120s
- **Date format parsing** — LOGDATE uses `/Date(ISO)/` wrapper format, not MS OLE floats

**Known Limitations:**
- Archived data is immutable — no edit/delete through UI (by design)
- Metrics recomputation requires full recalculation from units/history
- Queue jobs not implemented in initial version — synchronous import only
- SS.TAKENBY is empty for CLOSE circuits — planner attribution requires JOBHISTORY analysis

**Future Considerations (Out of Scope):**
- Scheduled automatic re-imports (cron/scheduler)
- Export archived data to CSV/Excel
- Dashboard visualizations for planner metrics
- Comparison between planners (leaderboard)

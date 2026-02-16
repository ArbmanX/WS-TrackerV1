# Changelog

All notable changes to WS-TrackerV1 will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Changed
- **Planner Metrics Redesign — Unified View with Accordion** (2026-02-16)
  - Replaced Quota/Health card toggle with unified single-column layout showing both in one row
  - Replaced side drawer with inline accordion for circuit details (one open at a time)
  - Removed period type selector (Week/Month/Year/Scope) — hardcoded to weekly view
  - Added 4 stat cards at top: On Track, Team Avg, Aging Units, Team Miles
  - Added `getUnifiedMetrics()` to service layer merging quota + health data
  - New partials: `_stat-cards`, `_planner-row`, `_circuit-accordion`
  - Deleted old partials: `_quota-card`, `_health-card`, `_circuit-drawer`
  - Constrained layout to `max-w-5xl mx-auto` for focused reading flow
  - 77 PlannerMetrics tests (165 assertions) — 18 overview + 14 accordion + 45 service

### Added
- **Planner Metrics Circuit Drawer** (2026-02-16)
  - Side drawer panel on planner cards showing circuit-level details (line name, region, miles, completion %)
  - `getCircuitsForPlanner()` on PlannerMetricsService to fetch circuit data from AssessmentMonitor snapshots
  - Drawer accessible from both Quota and Health card views via click-to-open action
  - 16 new drawer tests in `OverviewDrawerTest.php` (74 total PlannerMetrics tests)
  - Card layout refinements for health and quota views

- **Planner Metrics Week Navigation & Boundary Overhaul** (2026-02-15)
  - Sunday–Saturday week boundaries (configurable via `planner_metrics.week_starts_on`)
  - Prev/next offset navigation with chevron arrows and date range label
  - Auto-default flip: shows previous completed week until Tuesday 5 PM ET, then current week
  - Fiscal scope-year (Jul 1 – Jun 30) for scope-year period
  - Period labels with en-dash formatting and cross-year awareness
  - Server-side validation clamps positive offsets to 0 (no future weeks)
  - 18 new service tests + 1 new Livewire test for offset navigation (53 total PlannerMetrics tests)

### Changed
- **Refactored PlannerMetricsService to read from career JSON files** (2026-02-15)
  - `PlannerMetricsService` now reads career data from JSON files produced by `PlannerCareerLedgerService` instead of querying `planner_career_entries` table
  - Removed `CareerLedgerService`, `CareerLedgerQueries`, `PlannerCareerEntry` model, factory, and migration (10 files deleted)
  - Removed `ws:import-career-ledger` and `ws:export-career-ledger` commands
  - Removed `CareerLedgerService` singleton from `WorkStudioServiceProvider`
  - `ProcessAssessmentClose` listener no longer appends to career ledger on assessment close
  - New config key: `planner_metrics.career_json_path`
  - Dropped `planner_career_entries` table
  - Removed `career_ledger` key from `config/ws_data_collection.php`

### Added
- **Planner Metrics Dashboard — Phase 1 Overview Page** (2026-02-15)
  - New `/planner-metrics` route with Livewire page component showing planner card grid
  - Quota view: weekly footage progress vs 6.5 mi/week target, streak counting, coaching messages
  - Health view: assessment staleness, aging units, permission breakdowns per planner
  - Page-level toggles: view (Quota/Health), period (Week/Month/Year/Scope Year), sort (A-Z/Needs Attention)
  - `PlannerMetricsService` aggregates data from career JSON files, `assessment_monitors`, and `planner_job_assignments`
  - `CoachingMessageGenerator` produces contextual tips for behind-quota planners (nudge/recovery/encouraging/celebration)
  - New `config/planner_metrics.php` for quota target and staleness thresholds
  - New `normalized_username` indexed column on `planner_job_assignments` for efficient username bridge
  - Sidebar navigation entry under "Planner Metrics" section
  - 36 new tests (13 service, 7 generator, 16 feature)

### Fixed
- **AssessmentMonitorFactory permission_breakdown values** now match config-driven statuses (Approved, Pending, No Contact, Refused, Deferred, PPL Approved)
- **PlannerJobAssignmentFactory** now generates domain-qualified `frstr_user` and stripped `normalized_username`

### Fixed
- **Daily footage command returning incomplete results** (2026-02-14)
  - Removed `edit_date` filter from `getJobGuids()` in `FetchDailyFootage` — `edit_date` reflects sync freshness, not planner activity
  - All active assessments for the scope year are now sent to the API; the SQL `completion_date BETWEEN` filter handles temporal narrowing
  - Previously only ~5 of 136 assessments were queried, silently dropping most planners

### Changed
- **Refactored fetch commands into Commands/Fetch/ subfolder** (2026-02-14)
  - Moved 6 fetch commands to `app/Console/Commands/Fetch/` with `App\Console\Commands\Fetch` namespace
  - Standardized flags: default is display-only, `--seed` to persist to DB, `--save` to write data files, `--year=` to scope by year
  - Removed `--dry-run` from all commands (default behavior is now display-only)
  - Rolled `--enrich` into `--seed` on `ws:fetch-users` (seed now upserts + enriches in one step)
  - Made year optional on circuits/users/jobs — omitting `--year` fetches all years
  - Added `ws:sync-all-fetchers` orchestrator: runs all 6 fetch commands in dependency order with `--seed`/`--save`
  - Updated `ApiCredentialManagerTest` file paths for new directory structure
- **Simplified PlannerCareerLedger to all-status discovery** (2026-02-14)
  - `getDistinctJobGuids()` no longer filters by `SS.STATUS` — fetches all statuses (ACTIV, QC, REWRK, CLOSE) in a single pass
  - Replaced `$current` + `$allYears` boolean params with `?int $scopeYear` — `null` = all years, integer = filter to that year
  - Removed dead `$current` param from `exportForUser()` and `exportForUsers()` (was never referenced in method body)
  - Artisan command `ws:export-planner-career` replaces `--current` and `--all-years` flags with `--scope-year`
  - Output directory simplified from `{domain}/planners/{current|closed}` to `{domain}/planners/career`
  - Updated 6 test files: removed 5 obsolete tests, rewrote 5 tests, added 2 new tests

### Added
- **Planner Contribution Fields & Metadata Envelope** (2026-02-14)
  - **Contribution tracking:** `computeContributions()` sums `daily_footage_miles` per user — `total_contribution` for queried planner, `others_total_contribution` keyed by stripped username
  - **Metadata envelope:** `wrapWithMetadata()` wraps export JSON with `career_timeframe`, `total_career_miles`, `assessment_count`, `total_career_unit_count` at file level
  - **New fields:** `wo`, `ext` from VEGJOB added to query and export output
  - **Domain-aware output paths:** Export command organizes by domain/current/closed subdirectories
  - **FRSTR_USER scoping fix:** `discoverJobGuids()` now uses FRSTR_USER from API response (not local user list) for correct per-user GUID assignment
  - **Directory-scoped staleness:** Existing exports only matched when `export_path` is within the target directory
  - **Domain stripping:** `stripDomain()` helper removes `ASPLUNDH\` prefix and sanitizes for filenames
  - **Tests:** 4 new tests for contribution computation, domain stripping, WO/EXT fields, stale merge recomputation

- **Incremental Planner Career Export** (2026-02-14)
  - **Migration:** `export_path` column on `planner_job_assignments` — tracks the JSON file path for each exported assignment
  - **Query:** `getEditDates()` method on `PlannerCareerLedger` — fetches OLE-to-ISO8601-converted EDITDATE for staleness detection
  - **Factory:** `withExportPath()` state on `PlannerJobAssignmentFactory`
  - **Incremental export logic:** `exportForUser()` now compares local `updated_at` against remote `EDITDATE` to detect stale data. Stale assessments get metadata refreshed and new daily_metrics appended+deduplicated. Up-to-date assessments are skipped entirely. Missing export files trigger full re-export fallback.
  - **Scope year fix:** `scope_year` in JSON output now derived from `WPStartDate_Assessment_Xrefs` via API (was hardcoded to config). Discovery defaults to config scope year with `--all-years` flag to fetch across all years.
  - **Tests:** 16 new tests (9 query, 7 service) covering staleness detection, metric deduplication, metadata refresh, mixed new+stale runs, missing file fallback, assumed_status re-enrichment, scope year filtering, allYears flag

- **Planner Career Ledger** (2026-02-14)
  - **Database:** `planner_job_assignments` table — tracks discovered JOBGUIDs per FRSTR_USER with status lifecycle (discovered → processed → exported)
  - **Model & Factory:** `PlannerJobAssignment` with `forUser`, `pending`, `processed`, `exported` scopes and `wsUser()` relationship
  - **Query Builder:** `PlannerCareerLedger` in `app/Services/WorkStudio/Planners/Queries/` — consolidated single-query career data extraction using FOR JSON PATH subqueries and OUTER APPLY for daily metrics (ASSDDATE-only attribution)
  - **Service:** `PlannerCareerLedgerService` in `app/Services/WorkStudio/Planners/` — discover job assignments, export per-user JSON career files (1 API call per export via consolidated query)
  - **Artisan Command:** `ws:export-planner-career {users} --output` — discover and export planner career data for specific FRSTR_USERs
  - **Tests:** 55 new tests (Unit: query SQL validation, Feature: model scopes, service mock tests, command tests)

- **Data Collection Architecture** (2026-02-13)
  - **Database Layer:** 4 new PostgreSQL tables — `planner_career_entries`, `assessment_monitors`, `ghost_ownership_periods`, `ghost_unit_evidence` — with JSONB columns for daily snapshots and baseline data
  - **Configuration:** `config/ws_data_collection.php` — thresholds, ghost detection domain, sanity check toggles
  - **Models & Factories:** `PlannerCareerEntry`, `AssessmentMonitor`, `GhostOwnershipPeriod`, `GhostUnitEvidence` with factory states (`active()`, `resolved()`, `parentTakeover()`, `withSnapshots()`)
  - **Query Builders:** 3 domain-specific query builders in `app/Services/WorkStudio/DataCollection/Queries/`:
    - `CareerLedgerQueries` — daily footage attribution (First Unit Wins), assessment timeline, work type breakdown, rework details
    - `LiveMonitorQueries` — permission breakdown, unit counts, notes compliance, edit recency, aging units, work types
    - `GhostDetectionQueries` — ownership changes, UNITGUID snapshots, EXT field check
  - **Services:** 3 service classes in `app/Services/WorkStudio/DataCollection/`:
    - `CareerLedgerService` — bootstrap import/export from JSON, close-event append from live monitor
    - `LiveMonitorService` — daily snapshot orchestration, suspicious flag logic, close detection
    - `GhostDetectionService` — ownership change scanning, baseline creation, ghost comparison, resolution, cleanup
  - **Event System:** `AssessmentClosed` event + `ProcessAssessmentClose` queued listener — triggered when monitored assessments close, creates career entry and cleans up ghost tracking
  - **Artisan Commands:**
    - `ws:import-career-ledger` — bootstrap import from JSON with `--dry-run` preview
    - `ws:export-career-ledger` — export career data to JSON via API with `--scope-year`/`--region` filter hooks
    - `ws:run-live-monitor` — daily snapshot with `--job-guid` single-assessment mode and `--include-ghost` ghost detection
  - **Scheduler:** `ws:run-live-monitor --include-ghost` registered as daily cron in `routes/console.php`
  - **Tests:** 155 new tests (493 total), 1473 assertions across models, query builders, services, events, and commands

- **Historical Metric Snapshot Persistence** (2026-02-11)
  - New `system_wide_snapshots` table — persists system-wide aggregate metrics on each cache miss
  - New `regional_snapshots` table — persists per-region metrics with permission counts and work measurements
  - `SnapshotPersistenceService` — maps API response keys to DB columns with explicit type coercion
  - `SystemWideSnapshot` and `RegionalSnapshot` Eloquent models with `forYear`, `forContext`, `forRegion` query scopes
  - Factories with `capturedAt()` state for trend testing
  - `CachedQueryService` hooks into cache miss flow to trigger snapshot persistence (fail-silent)
  - Config toggle: `ws_cache.snapshot.enabled` (env: `WS_SNAPSHOT_ENABLED`, default: true)
  - Config: `ws_cache.snapshot.datasets` controls which datasets trigger snapshots
  - 37 new tests across 4 test files (persistence service, models, CachedQueryService integration)

### Changed
- **Assessment Query Class Split** (2026-02-11)
  - Split monolithic `AssessmentQueries` (568 lines) into 4 focused domain classes via `AbstractQueryBuilder` base class:
    - `AggregateQueries` — `systemWideDataQuery`, `groupedByRegionDataQuery`
    - `CircuitQueries` — `groupedByCircuitDataQuery`, `getAllByJobGuid`, `getAllJobGUIDsForEntireScopeYear`
    - `ActivityQueries` — `getAllAssessmentsDailyActivities`, `getActiveAssessmentsOrderedByOldest`
    - `LookupQueries` — `getDistinctFieldValues`
  - `AssessmentQueries` is now a thin delegating facade preserving backward compatibility with `GetQueryService`
  - `SqlFragmentHelpers` trait methods changed from `private` to `protected` for subclass access
  - 3 new Pest tests: domain-class/facade SQL equivalence, GUID validation via CircuitQueries, input validation via LookupQueries

### Fixed
- **Remove SS self-join from Q4 & Q5** (2026-02-11) — Removed redundant `INNER JOIN SS AS WSREQSS ON SS.JOBGUID = WSREQSS.JOBGUID` from `getAllAssessmentsDailyActivities()` and `getAllByJobGuid()`. The self-join produced a Cartesian product on the same PK for no benefit.
- **Standardize CYCLETYPE filtering** (2026-02-11) — Replaced hardcoded `NOT IN ('Reactive', 'Storm Follow Up', ...)` with config-driven `excluded_from_assessments` array in `getActiveAssessmentsOrderedByOldest()` and `getDistinctFieldValues()`

### Changed
- **Replace 7x unitCountSubquery with unitCountsCrossApply in getAllByJobGuid** (2026-02-11) — Single CROSS APPLY scans VEGUNIT once instead of 7 correlated subqueries, significantly reducing VEGUNIT reads
- 8 new Pest tests: GUID validation (valid/invalid/empty), no SS self-join verification, CROSS APPLY usage, config-driven CYCLETYPE

### Security
- **SEC-003: GUID validation in getAllByJobGuid** (2026-02-11) — Added regex validation for JOBGUID format before SQL interpolation; throws `InvalidArgumentException` for invalid input

### Changed
- **Shared SQL Fragment Extraction** (2026-02-11)
  - Extracted `baseFromClause()` to `SqlFragmentHelpers` — standard 3-table INNER JOIN used by 6+ queries
  - Extracted `baseWhereClause(array $overrides)` — parameterized WHERE builder with statusSql, cycleTypeSql, includeExcludedUsers options
  - Extracted `permissionCountsCrossApply()` — config-driven VEGUNIT permission counts with validUnitFilter
  - Extracted `permissionCountsWithDatesCrossApply()` — same as above with MIN/MAX ASSDDATE for circuit views
  - Extracted `workMeasurementsCrossApply()` — config-driven JOBVEGETATIONUNITS work measurement aggregation
  - Updated `unitCountsCrossApply()` to use config PERMSTAT values instead of hardcoded strings
  - Refactored all 7 query methods in `AssessmentQueries.php` to use shared fragments
  - 7 new Pest tests: INNER JOIN verification, baseWhereClause overrides, config-driven CROSS APPLY fragments

### Fixed
- **BUG-002: LEFT JOIN → INNER JOIN for xrefs** (2026-02-11) — `baseFromClause()` uses `INNER JOIN` for `WPStartDate_Assessment_Xrefs` since the WHERE clause on `WP_STARTDATE` already forced INNER behavior. Explicit INNER JOIN gives the query optimizer better information.

### Added
- **Assessment Query Config Extraction** (2026-02-11)
  - `permission_statuses` array in `config/ws_assessment_query.php` — single source of truth for VEGUNIT.PERMSTAT values (Approved, Pending, No Contact, Refused, Deferred, PPL Approved)
  - `unit_groups` array — work measurement unit code groups (removal_6_12, removal_over_12, ash_removal, vps, brush, herbicide, bucket_trim, manual_trim)
  - `excluded_from_assessments` cycle type array — standardized exclusion list for assessment queries
  - 7 new Pest tests: config structure validation, BUG-001 regression guards

### Fixed
- **BUG-001: PERMSTAT 'Refusal' → 'Refused'** — corrected incorrect PERMSTAT value in `groupedByCircuitDataQuery`, `getAllByJobGuid` (via `unitCountSubquery`), and `unitCountsCrossApply`. Refusal counts were silently returning 0.

### Security
- **Credential Security Fix (SEC-001)** (2026-02-10)
  - Removed hardcoded credentials from `GetQueryService.php` — now uses `ApiCredentialManager`
  - Removed hardcoded credential defaults from `config/workstudio.php` (empty string fallbacks)
  - Routed all 11 credential-consuming files through `ApiCredentialManager`
  - Added `buildDbParameters()` and `formatDbParameters()` helpers to `ApiCredentialManager`
  - Files updated: `GetQueryService`, `QueryExplorer`, `UserDetailsService`, `HeartbeatService`, 7 Fetch commands
  - 7 new Pest tests (48 assertions): credential manager unit tests + source code audits for hardcoded creds

### Fixed
- `ReferenceDataSeederTest` — corrected expected region order to alphabetical (matches actual `RegionSeeder` sort)

### Added
- **Unit Count in Daily Footage** (2026-02-10)
  - `DailyFootageQuery`: Added `JOIN UNITS` and `SUM(CASE WHEN ...)` to count working units per row (excludes `Summary-NonWork` and empty/null `SUMMARYGRP`)
  - `FetchDailyFootage`: Added `unit_count` (int) to enriched JSON output shape
  - JSON record shape now: `{job_guid, frstr_user, datepop, distance_planned, unit_count, stations[]}`
  - 2 new tests: SQL assertion for `JOIN UNITS` + `CASE WHEN`, enriched output `unit_count` integer cast

- **Unit Types Reference Table** (2026-02-10)
  - `unit_types` migration — local reference table synced from WorkStudio UNITS table
  - `UnitType` model with `work_unit` boolean derived from `SUMMARYGRP` at sync time
  - `UnitTypeFactory` with `nonWorking()` state for test data
  - `ws:fetch-unit-types` artisan command — fetches all unit types from WS API, upserts by `unit` key, derives `work_unit` from `SUMMARYGRP`
  - 9 Pest tests: sync, upsert, dry-run, error handling, `work_unit` derivation (Summary-NonWork, empty, null)

- **Daily Footage by Station Completion — Artisan Command** (2026-02-08)
  - `ws:fetch-daily-footage` artisan command — fetches daily footage metrics from WorkStudio API using first-unit-wins station completion logic
  - `DailyFootageQuery` class — T-SQL derived table with `ROW_NUMBER() OVER PARTITION BY`, `STRING_AGG` for station lists, uses DATEPOP for completion dates
  - Two date modes: **WE** (week-ending, Saturday targets → Sun-Sat range) and **Daily** (non-Saturday → single day filter)
  - Signature: `{date?}`, `{--jobguid=}`, `{--status=ACTIV}`, `{--all-statuses}`, `{--chunk-size=200}`, `{--dry-run}`
  - Pipeline: ss_jobs query (filtered by edit_date, job_type, status) → chunked API calls → enrichment (date parsing, station splitting, domain extraction) → per-domain JSON output
  - JSON record shape: `{job_guid, frstr_user, datepop, distance_planned, stations[]}`
  - Filenames: `storage/app/daily-footage/{DOMAIN}/{we|day}{MM_DD_YYYY}_planning_activities.json`
  - `SsJobFactory`: `withJobType(string)` state, default `job_type` values from config
  - 23 Pest tests covering date modes, job filtering, status flags, enrichment, SQL assertions, edge cases
  - Business rules spec: `docs/specs/assessment-completion-rules.md`

### Removed
- **Sushi model and aggregator** (2026-02-08) — removed before first commit; no UI consumers
  - Deleted: `DailyFootage` model, `DailyFootageAggregator` service, `DailyFootageFactory`, model/aggregator tests
  - Removed `calebporzio/sushi` dependency from `composer.json`
  - Removed `weekly_quota_miles` and `meters_per_mile` config values from `ws_assessment_query.php`
  - Removed `.manifest` file generation from command output

- **WSSQLCaster — Data-Driven SQL Field Casting** (2026-02-08)
  - `WSSQLCaster` class (`app/Services/WorkStudio/Shared/Helpers/WSSQLCaster.php`) — field registry mapping WS field names to cast types (`ole_datetime`, `date`)
  - `cast()` method generates SQL fragments for OLE Automation datetime conversion to Eastern time, supports `TABLE.FIELD` format
  - `oleDateToCarbon()` method for PHP-side OLE date conversion to `CarbonImmutable`
  - 16 unit tests in `tests/Unit/WSSQLCasterTest.php`

### Fixed
- **Incorrect -2 day offset in OLE date conversion** (2026-02-08)
  - Removed erroneous `DATEADD(DAY, -2, ...)` from `SqlFragmentHelpers::formatToEasternTime()` — SQL Server's `CAST(float AS DATETIME)` already handles the OLE epoch correctly
  - Updated `FetchSsJobs` command to use `WSSQLCaster::cast()` instead of inline SQL with the same -2 day bug

### Added
- **SS Jobs & WS Users Data Sync** (2026-02-08)
  - `ws_users` table migration — stores WorkStudio user identities (username, domain, display_name, email, is_enabled, groups JSON)
  - `ss_jobs` table migration — stores SS job records with string PK (`job_guid`), FKs to circuits, ws_users, self-referential parent/child hierarchy
  - `WsUser` model with `takenJobs()`, `modifiedJobs()` HasMany relationships; `WsUserFactory` with `unenriched()`, `disabled()` states
  - `SsJob` model with `circuit()`, `parentJob()`, `childJobs()`, `takenBy()`, `modifiedBy()` relationships; `SsJobFactory` with `withCircuit()`, `withTakenBy()`, `withStatus()` states
  - `ssJobs()` HasMany relationship on `Circuit` model
  - `ws:fetch-users` artisan command — queries SS table for distinct TAKENBY/MODIFIEDBY usernames, upserts to ws_users, optional `--enrich` flag enriches via GETUSERDETAILS endpoint with rate limiting
  - `ws:fetch-jobs` artisan command — queries SS table for jobs scoped by year, groups extensions by JOBGUID, resolves circuit IDs via raw_line_name, resolves user FKs, upserts to ss_jobs, updates circuit properties with jobguids array
  - 41 tests across 4 files: `WsUserTest` (7), `SsJobTest` (15), `FetchWsUsersCommandTest` (7), `FetchSsJobsCommandTest` (12)
  - Migration to drop self-referential FK on `ss_jobs.parent_job_guid` — parent jobs may not exist in filtered dataset

- **Onboarding System Rework — 4-Step Flow** (2026-02-07)
  - Reworked onboarding from 2-step to 4-step flow: Password → Theme → WS Credentials → Confirmation
  - `OnboardingStep` enum (`app/Enums/OnboardingStep.php`) — int-backed enum with `label()`, `route()` methods
  - `onboarding_step` nullable tinyInteger column on `user_settings` table for step tracking
  - `ThemeSelection` Livewire component — theme picker with live preview via Alpine.js store bridge
  - `Confirmation` Livewire component — read-only summary of account, theme, and WS details before finalizing
  - `HeartbeatService` (`app/Services/WorkStudio/Client/HeartbeatService.php`) — standalone server-availability check via HEARTBEAT endpoint, used as pre-flight before credential validation
  - `password-input` Blade component (`resources/views/components/ui/password-input.blade.php`) — reusable password field with Alpine.js eye/eye-slash visibility toggle
  - `onboarding/progress` Blade component — DaisyUI stepper driven by `OnboardingStep` enum
  - `UserWsCredentialFactory` with `invalid()` state
  - `atStep(int)` factory state on `UserSettingFactory` for test setup
  - `wsCredential(): HasOne` relationship on `User` model
  - Back navigation on all onboarding steps (addresses TODO UI-005)
  - 40 tests across 6 files: `OnboardingStepTest` (unit), `ThemeSelectionTest`, `ConfirmationTest`, `ChangePasswordTest`, `WorkStudioSetupTest`, `EnsurePasswordChangedTest`

### Changed
- **Onboarding Flow** (2026-02-07)
  - `ChangePassword` now sets `onboarding_step = 1` and redirects to theme selection (not WS setup)
  - `WorkStudioSetup` now collects WS password in addition to username, tests credentials via `ApiCredentialManager::testCredentials()` and heartbeat pre-check, stores encrypted credentials via `storeCredentials()`, redirects to confirmation (not dashboard)
  - `EnsurePasswordChanged` middleware uses `onboarding_step` for step-based routing with backward compatibility for existing `onboarding_completed_at`-based completion
  - Auth layouts (`simple.blade.php`, `card.blade.php`, `sidebar.blade.php`) — fixed FOUC script to use `ws-theme` localStorage key, `corporate` default, `config('themes.system_mapping')` resolution; added Alpine `x-data`/`x-init` for theme store; widened simple layout to `max-w-md`
  - Theme picker (`theme-picker.blade.php`) — added `x-on:change` to all radio inputs for instant client-side theme switching
  - `ApiCredentialManager` — added `testCredentials()` method for lightweight credential validation via `SELECT TOP 1 1 AS test` query

- **Reference Data Seeders — Regions & Circuits** (2026-02-07)
  - `regions` table migration — `name` (unique), `display_name`, `is_active`, `sort_order`
  - `circuits` table migration — `line_name` (unique), `region_id` FK (nullable, nullOnDelete), `is_active`, `last_trim`, `next_trim`, `properties` (JSON), `last_seen_at`
  - `Region` model with `active()` scope, `HasMany` circuits relationship
  - `Circuit` model with `active()` scope, `BelongsTo` region, `properties` cast to array, `last_trim`/`next_trim` cast to date
  - `RegionFactory` with `inactive()` state; `CircuitFactory` with `withRegion()` and `inactive()` states
  - `RegionSeeder` — 6 geographic regions (Harrisburg, Lancaster, Lehigh, Central, Susquehanna, Northeast) via idempotent `updateOrCreate`
  - `CircuitSeeder` — reads `database/data/circuits.php`, maps region names to IDs, skips gracefully if file missing
  - `ReferenceDataSeeder` — orchestrator calling RegionSeeder then CircuitSeeder
  - `ws:fetch-circuits` artisan command — fetches distinct circuits from WorkStudio API with `--save`, `--seed`, `--dry-run`, `--year` options; tracks scope years in `properties` JSON
  - `database/data/` directory with `.gitkeep` for generated circuit data files
  - 25 Pest tests across 4 files: `RegionTest` (unit), `CircuitTest` (unit), `ReferenceDataSeederTest` (feature), `FetchCircuitsCommandTest` (feature)

- **User Management — Create User** (2026-02-06)
  - `CreateUser` Livewire component (`app/Livewire/UserManagement/CreateUser.php`) for admin user creation
  - Form with name, email, and role selection; auto-generates 16-char temporary password
  - Success state displays temp password with `select-all` for easy copy
  - "Create Another User" button resets form for batch creation
  - New users get `email_verified_at` set (pre-verified) and `UserSetting` with `first_login: true` (triggers onboarding flow)
  - Route file `routes/user-management.php` at `/user-management/create`, gated by `permission:manage-users`
  - Sidebar "User Management" section (visible to `manage-users` holders) with Create User active link + 3 placeholder items (Edit Users, User Activity, User Settings)
  - 15 Pest feature tests (`tests/Feature/UserManagement/CreateUserTest.php`): auth guard, permission gating, validation (name, email format, email uniqueness, role), successful creation with role + settings, temp password verification, form reset, role dropdown
  - 3 route-level permission tests added to `tests/Feature/PermissionTest.php`

- **Spatie Laravel Permission v6 — Role-Based Access Control** (2026-02-06)
  - Published `config/permission.php` and Spatie permission migration (5 tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`)
  - 5 roles: `sudo-admin`, `manager`, `planner`, `general-foreman`, `user`
  - 7 permissions: `view-dashboard`, `access-data-management`, `manage-cache`, `execute-queries`, `access-pulse`, `access-health-dashboard`, `manage-users`
  - `RolePermissionSeeder` (idempotent) — defines all roles and permissions, registered in `DatabaseSeeder`
  - `HasRoles` trait on `User` model — enables `assignRole()`, `hasPermissionTo()`, `can()` etc.
  - `role`, `permission`, `role_or_permission` middleware aliases in `bootstrap/app.php`
  - `withRole()` factory state on `UserFactory` for test setup
  - Permission-gated sidebar navigation — sections and items hidden when user lacks permission
  - Permission middleware on data management routes (`permission:access-data-management`, `permission:execute-queries`)
  - Permission middleware on health dashboard route (`permission:access-health-dashboard`)
  - 10 permission tests (`tests/Feature/PermissionTest.php`): 403 for unauthorized users, role-based access verification, seeder idempotency

### Fixed
- **SEC-005: `hasRole()` missing method** (2026-02-06) — Added `HasRoles` trait to User model; replaced broken `$user->hasRole('admin')` in Pulse gate with `$user->hasPermissionTo('access-pulse')`

### Changed
- **Consolidated project documentation into `docs/`** (2026-02-06)
  - Moved `TODO.md`, `CODE-REVIEW.md` to `docs/` (already tracked there)
  - Created `docs/specs/` — planner-activity-rules.md, tech-spec-historical-assessment-archival.md
  - Created `docs/queries/` — planner-activity-query-reference.md, discovery-query-results.md (renamed from QueryInfo/)
  - Created `docs/diagrams/` — planner-activity-dataflow.excalidraw
  - Created `docs/archive/` — WS-Tracker-Rebuild-Plan.md, prompt-planner-daily-activity-query.md, tech-spec-workstudio-api-layer-refactoring-archived-2026-02-01.md, testing-monitoring-setup.md
  - Created `docs/session-handoffs/` for AI context workflow
  - Updated 30+ internal links in TODO.md, CLAUDE.md, query reference docs
  - Added redirect READMEs in old `BMAD_WS/` locations
  - Eliminated confusing `../../BMAD_WS/` cross-directory paths

- **Domain-driven folder restructure for WorkStudio services** (2026-02-06)
  - Reorganized `app/Services/WorkStudio/` from flat technical layers into domain namespaces:
    - `Client/` — HTTP infrastructure: `GetQueryService`, `ApiCredentialManager`, `WorkStudioApiService`, `WorkStudioApiInterface`
    - `Shared/` — Cross-domain: `UserQueryContext`, `CachedQueryService`, `ResourceGroupAccessService`, `UserDetailsService`, exceptions, helpers
    - `Assessments/` — Assessment domain: `AssessmentQueries`, `SqlFieldBuilder`, `SqlFragmentHelpers`
  - Removed old directories: `Services/`, `Managers/`, `Contracts/`, `ValueObjects/`, `Helpers/`, `Exceptions/`, `AssessmentsDx/`
  - Updated 53 import statements across 24 files (app, tests, routes, providers)
  - Prepares structure for future `WorkJobs/` and `Planner/` domain modules

### Added
- **Query Explorer** (2026-02-06)
  - `QueryExplorer` Livewire component for raw SQL SELECT queries against WorkStudio API (`app/Livewire/DataManagement/QueryExplorer.php`)
  - Query builder UI with table dropdown (VEGJOB, VEGUNIT, STATIONS + custom), fields input, TOP limit (1-500), optional WHERE clause
  - Results display with row count badge, query timing, executed SQL in monospace, raw JSON output
  - Uses `config('workstudio.service_account.*')` credentials — bypasses `GetQueryService` hardcoded creds
  - Blade view (`resources/views/livewire/data-management/query-explorer.blade.php`) with DaisyUI cards, loading states
  - Route at `/data-management/query-explorer` (`routes/data-management.php`)
  - Sidebar navigation item under "Data Management" with `code-bracket` icon
  - Feature tests: auth guard, page render, validation, query execution with mocked HTTP, error handling, clear state (`tests/Feature/DataManagement/QueryExplorerTest.php`)

### Removed
- **Dead Code Cleanup** (2026-02-05)
  - Deleted `app/Livewire/_backup/` directory (deprecated TwoFactor components)
  - Deleted `resources/views/welcome.blade.php` (never served — `/` redirects to login)
  - Deleted `resources/views/dashboard.blade.php` ("Coming Soon" stub — Livewire Overview renders instead)
  - Deleted `resources/views/livewire/auth/register.blade.php` (registration disabled)
  - Deleted `app/Services/WorkStudio/Helpers/ExecutionTimer.php` (debug helper with `echo` output)
  - Deleted `app/Actions/Fortify/CreateNewUser.php` and `app/Concerns/ProfileValidationRules.php` (registration scaffolding)
  - Deleted `routes/settings.php` (all routes were just redirects to dashboard)
  - Removed `Fortify::createUsersUsing()` and `Fortify::registerView()` from `FortifyServiceProvider`
  - Removed `getAll()` debug method from `GetQueryService` (hardcoded GUID)
  - Removed `queryAll()` debug method from `GetQueryService` (dump calls, ExecutionTimer usage)
  - Removed `public $sqlState` property and assignment from `GetQueryService` (set but never read)
  - Removed `$currentUserId` unused property from `WorkStudioApiService`
  - Removed disabled Search and Notifications placeholder buttons from header
  - Removed `/dashboard/test` unauthenticated test route
  - Removed `/allByJobGUID` debug route (used deleted `getAll()` method)

### Fixed
- **App Logo Branding** (2026-02-05) — Changed "Laravel Starter Kit" to "WS-Tracker" in `app-logo.blade.php`

### Security
- **Protected API Routes** (2026-02-05) — Wrapped WorkStudio API routes (`/assessment-jobguids`, `/system-wide-metrics`, `/regional-metrics`, `/daily-activities/all-assessments`, `/field-lookup`) in `auth` middleware group; added `UserQueryContext::fromUser()` to fix broken closures missing required context parameter

### Added
- **User-Scoped Assessment Queries** (2026-02-05)
  - `UserQueryContext` value object (`app/Services/WorkStudio/ValueObjects/UserQueryContext.php`) — immutable readonly class encapsulating user-specific query parameters (resource groups, contractors, domain, username)
  - `fromUser(User)` builds context from authenticated user's WS fields; `fromConfig()` provides backward-compatible fallback
  - `cacheHash()` produces deterministic MD5 — users with identical access share cache entries
  - Migration: `ws_resource_groups` JSON column on `users` table for pre-computed REGION values
  - `resolveRegionsFromGroups()` on `ResourceGroupAccessService` — three-tier resolution: explicit map → direct region match → planner defaults
  - `group_to_region_map` config in `workstudio_resource_groups.php` for WS group-to-region mapping
  - `withWorkStudio()` factory state on `UserFactory` for test setup
  - `ws:backfill-resource-groups` Artisan command with `--dry-run` flag for backfilling existing onboarded users
  - Unit tests: `UserQueryContextTest` (11 tests), `ResourceGroupResolutionTest` (8 tests), `AssessmentQueriesTest` (10 tests)

### Changed
- **AssessmentQueries refactored from static to instance** (2026-02-05)
  - Constructor accepts `UserQueryContext`; pre-computes SQL fragments from user's resource groups and contractors
  - System-level values (excludedUsers, job_types, scope_year) remain config-driven
- **GetQueryService methods accept UserQueryContext** (2026-02-05)
  - All data methods (`getSystemWideMetrics`, `getRegionalMetrics`, `getJobGuids`, `getActiveAssessmentsOrderedByOldest`, etc.) now require `UserQueryContext` parameter
- **CachedQueryService context-scoped caching** (2026-02-05)
  - Cache key pattern changed from `ws:{year}:{dataset}` to `ws:{year}:ctx:{hash}:{dataset}`
  - Context hash tracking for admin invalidation across all user contexts
  - Updated methods: `invalidateAll()`, `invalidateDataset()`, `warmAllForContext()`, `getCacheStatus()`
- **WorkStudioApiService and interface updated** (2026-02-05)
  - `WorkStudioApiInterface` and `WorkStudioApiService` delegation methods accept `UserQueryContext`
- **Dashboard Livewire components pass user context** (2026-02-05)
  - `Overview`, `ActiveAssessments`, `CacheControls` all build `UserQueryContext::fromUser(Auth::user())`
- **Onboarding resolves and stores ws_resource_groups** (2026-02-05)
  - `WorkStudioSetup` calls `resolveRegionsFromGroups()` during WS validation, stores result on User model
- **All tests updated for UserQueryContext** (2026-02-05)
  - `CachedQueryServiceTest`, `WorkStudioApiServiceTest`, `ActiveAssessmentsTest`, `CacheControlsTest`, `WorkStudioSetupTest` all pass context-aware mocks

### Removed
- **Dead code cleanup** (2026-02-05)
  - Removed `getRegionsForUser()` method from `ResourceGroupAccessService` (replaced by `resolveRegionsFromGroups()` + User model)
  - Removed `users` config key from `workstudio_resource_groups.php` (per-user regions now on User model)

- **Driver-Agnostic Cache Layer** (2026-02-04)
  - `CachedQueryService` decorator wrapping `GetQueryService` with per-dataset TTL caching (`app/Services/WorkStudio/Services/CachedQueryService.php`)
  - Cache config file (`config/ws_cache.php`) with per-dataset TTLs, key prefix, dataset definitions, registry key
  - Driver-agnostic metadata tracking via registry pattern (hit/miss counts, cached_at timestamps)
  - Methods: `getSystemWideMetrics()`, `getRegionalMetrics()`, `getDailyActivitiesForAllAssessments()`, `getActiveAssessmentsOrderedByOldest()`, `getJobGuids()` — all with `forceRefresh` parameter
  - Admin methods: `invalidateAll()`, `invalidateDataset()`, `warmAll()`, `getCacheStatus()`, `getDriverName()`
  - Cache key pattern: `ws:{scope_year}:{dataset_name}`
  - Unit tests for caching, force refresh, invalidation, warm, status, hit tracking (`tests/Unit/CachedQueryServiceTest.php`)

- **Cache Admin Dashboard** (2026-02-04)
  - `CacheControls` Livewire component under Data Management section (`app/Livewire/DataManagement/CacheControls.php`)
  - Dashboard view with stats row, dataset table with status badges, TTL progress bars, hit/miss counts (`resources/views/livewire/data-management/cache-controls.blade.php`)
  - Actions: per-dataset refresh, clear all (with confirmation), warm cache
  - Flash message system with auto-dismiss
  - Loading overlays for bulk operations
  - Data Management routes (`routes/data-management.php`) at `/data-management/cache`
  - Feature tests for auth guard, page display, all actions (`tests/Feature/DataManagement/CacheControlsTest.php`)

### Changed
- **Dashboard components now use CachedQueryService** (2026-02-04)
  - `Overview.php` switched from `WorkStudioApiService` to `CachedQueryService` for `systemMetrics` and `regionalMetrics`
  - `ActiveAssessments.php` switched from `WorkStudioApiService` to `CachedQueryService` for assessment data
  - Existing `ActiveAssessmentsTest.php` updated to mock `CachedQueryService`

- **Sidebar Navigation** (2026-02-04)
  - Added "Data Management" section with "Cache Controls" sub-item (icon: server-stack)

### Fixed
- **WorkStudioServiceProvider registration** (2026-02-04)
  - Added `WorkStudioServiceProvider::class` to `bootstrap/providers.php` — was previously missing, meaning interface bindings and `Http::macro('workstudio')` were never loaded

- **Active Assessments Component** (2026-02-04)
  - `ActiveAssessments` Livewire component (`app/Livewire/Dashboard/ActiveAssessments.php`) with error-resilient API calls
  - Active assessments card view with scrollable list, empty state, loading overlay (`resources/views/livewire/dashboard/active-assessments.blade.php`)
  - Assessment row Blade component — card-style list items with avatar initials, progress bar, miles remaining (`resources/views/components/dashboard/assessment-row.blade.php`)
  - `getActiveAssessmentsOrderedByOldest()` added to `WorkStudioApiInterface` and `WorkStudioApiService`
  - SQL query in `AssessmentQueries` filtered by resource groups, scope year 2026, contractors, job types, and cycle type exclusions
  - Feature tests for empty state, data display, count badge, and refresh (`tests/Feature/Dashboard/ActiveAssessmentsTest.php`)

### Changed
- **Overview Dashboard Layout** (2026-02-04)
  - Stats grid restored to standalone `grid-cols-2 md:grid-cols-4` row
  - Content section restructured: region cards (2x2 left) + active assessments card (right) in `xl:grid-cols-2` layout
  - Active assessments appears alongside regions in both cards and table view modes

### Fixed
- **Active Assessments SQL** (2026-02-04)
  - Fixed `ASSDDATE` casting: uses `parseMsDateToDate()` (DATETIME) instead of incorrect BIGINT epoch conversion
  - Added missing resource group, scope year, contractor, job type, and cycle type filters to match other queries

### Added (Previous)
- **App Shell Layout** (2026-02-04)
  - `config/themes.php` — Theme configuration with 16 DaisyUI themes organized by category
  - `resources/js/alpine/stores.js` — Alpine.js stores for theme and sidebar state management
  - Updated `resources/js/app.js` to import Alpine stores
  - UI components: `icon`, `tooltip`, `breadcrumbs`, `theme-toggle`, `theme-picker`
  - Layout components: `app-shell`, `sidebar`, `header`, `user-menu`
  - Full theme picker with categorized themes (Recommended, Light, Dark)
  - FOUC prevention script for instant theme application
  - Responsive sidebar: drawer on mobile, collapsible on tablet/desktop
  - localStorage persistence for theme and sidebar preferences
  - Updated Overview component to use new app-shell layout

- **Overview Dashboard** (2026-02-04)
  - `Overview` Livewire component (`app/Livewire/Dashboard/Overview.php`)
  - Dashboard view with cards/table toggle (`resources/views/livewire/dashboard/overview.blade.php`)
  - UI components: `stat-card`, `metric-pill`, `view-toggle` (`resources/views/components/ui/`)
  - Dashboard components: `region-card`, `region-table` (`resources/views/components/dashboard/`)
  - Integrated with `WorkStudioApiService` for real-time API data
  - URL-persisted state for view mode and sorting (`#[Url]` attributes)
  - Responsive grid layouts for all screen sizes
- **Blade Heroicons** (2026-02-04)
  - Added `blade-ui-kit/blade-heroicons` package for icon components

- **WS Module Integration** (2026-02-04)
  - Integrated WorkStudio Database Intelligence module (`_bmad/ws/`)
  - Schema Architect, Query Specialist, Laravel Generator agents available
  - Query optimization workflows for WorkStudio API queries
  - Updated `CLAUDE.md` with WS module documentation

### Removed
- **UI Cleanup for Dashboard Rebuild** (2026-02-04)
  - Removed Settings Livewire components (`Profile`, `Password`, `Appearance`, `TwoFactor`, `DeleteUserForm`)
  - Removed Settings views (`resources/views/livewire/settings/`)
  - Removed UI components: `desktop-user-menu`, `placeholder-pattern`, `settings/layout`, `settings-heading`, `app/header`
  - Removed Settings tests (`tests/Feature/Settings/`)
  - Backed up TwoFactor components to `app/Livewire/_backup/` for future reference

### Changed
- **UI Simplification** (2026-02-04)
  - Dashboard view replaced with minimal "Coming Soon" stub
  - App layout simplified from drawer/sidebar to minimal header with logout
  - Settings routes now redirect to dashboard (temporarily disabled)

### Added (Previous)
- **User Onboarding & Authentication System** (2026-02-03)
  - `user_settings` table for preferences, onboarding status, and theme settings
  - WorkStudio columns on `users` table (`ws_username`, `ws_full_name`, `ws_domain`, `ws_groups`, `ws_validated_at`)
  - `UserSetting` model with factory states (`firstLogin()`, `onboarded()`)
  - `UserDetailsService` for GETUSERDETAILS protocol integration
  - `UserDetailsServiceInterface` contract with custom exceptions
  - `EnsurePasswordChanged` middleware for onboarding flow enforcement
  - `ChangePassword` Livewire component for first-login password change
  - `WorkStudioSetup` Livewire component for WorkStudio validation
  - `SudoAdminSeeder` for initial system administrator creation
  - Onboarding routes (`/onboarding/password`, `/onboarding/workstudio`)
  - Comprehensive test coverage for onboarding flow and middleware
- `PROJECT_RULES.md` — Development standards and guidelines
- AI Context Management rules in `PROJECT_RULES.md` (context file tracking, warnings at 60%/70%)
- AI session management rules in `CLAUDE.md`
- **Testing & Monitoring Infrastructure** (2026-02-01)
  - Laravel Dusk for browser testing (`tests/Browser/`)
  - Spatie Laravel Health with custom WorkStudio API check (`/health`, `/health/dashboard`)
  - Spatie Laravel Activitylog on User model (365-day retention)
  - Laravel Pulse performance monitoring (`/pulse`)
  - Health check tests (`tests/Feature/HealthCheckTest.php`)
  - WorkStudioApiService unit tests (`tests/Unit/WorkStudioApiServiceTest.php`)
  - Scheduled cleanup commands in `routes/console.php`

### Changed
- **User Onboarding Flow** (2026-02-03)
  - Home route (`/`) now redirects to login
  - Dashboard requires completed onboarding (`onboarding` middleware)
  - Login page updated to show "Contact administrator" instead of registration link
  - Registration disabled in Fortify configuration (admin creates users)
  - `User` model updated with `settings()` relationship and WS field casts
- `AssessmentQueries::getAllJobGUIDsForEntireScopeYear()` — Added Total_Miles, Completed_Miles, Percent_Complete fields
- **WorkStudio API Layer Refactoring** (2026-02-01)
  - `WorkStudioApiService` now acts as facade, delegating to `GetQueryService`
  - `WorkStudioApiInterface` updated to 6-method contract
  - `WorkStudioServiceProvider` cleaned up (dead imports removed, interface binding added)
  - `ws_assessment_query.php` consolidated (duplicate resourceGroups removed)
  - Route `dd()` calls replaced with conditional `dump()`

### Fixed
- Dead imports in `WorkStudioServiceProvider` referencing non-existent classes
- Blocking `dd()` calls in `workstudioAPI.php` routes

### Security
- Added TODO markers for hardcoded credentials in `GetQueryService.php` (fix pending)

---

## [0.1.0] - 2026-02-01

### Added
- Initial WS-TrackerV1 project structure
- WorkStudio API integration layer
- Livewire 4 components
- Laravel 12 foundation

---

<!-- Links -->
[Unreleased]: https://github.com/your-org/ws-tracker-v1/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/your-org/ws-tracker-v1/releases/tag/v0.1.0

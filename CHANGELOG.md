# Changelog

All notable changes to WS-TrackerV1 will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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

# Changelog

All notable changes to WS-TrackerV1 will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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

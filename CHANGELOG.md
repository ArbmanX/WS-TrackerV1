# Changelog

All notable changes to WS-TrackerV1 will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
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

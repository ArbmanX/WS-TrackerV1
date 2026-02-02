# Changelog

All notable changes to WS-TrackerV1 will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
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

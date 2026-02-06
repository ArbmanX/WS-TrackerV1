---
title: 'WorkStudio API Layer Refactoring'
slug: 'workstudio-api-layer-refactoring'
created: '2026-02-01'
completed: '2026-02-01'
status: 'completed'
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8, 9]
tech_stack:
  - Laravel 12
  - PHP 8.4
  - Livewire 4
  - Pest 4
  - Guzzle HTTP Client (via Laravel HTTP facade)
files_to_modify:
  - app/Services/WorkStudio/WorkStudioApiService.php
  - app/Services/WorkStudio/Services/GetQueryService.php
  - app/Services/WorkStudio/Contracts/WorkStudioApiInterface.php
  - app/Services/WorkStudio/ResourceGroupAccessService.php
  - app/Providers/WorkStudioServiceProvider.php
  - config/workstudio.php
  - config/workstudio_resource_groups.php
  - config/ws_assessment_query.php
  - routes/workstudioAPI.php
code_patterns:
  - Service-oriented architecture with DI
  - HTTP macro pattern for API client configuration
  - Static query builder methods
  - Trait-based SQL fragment composition
  - Config-driven field selection
test_patterns:
  - No existing tests (Pest 4 framework available)
---

# Tech-Spec: WorkStudio API Layer Refactoring

**Created:** 2026-02-01

## Overview

### Problem Statement

The WorkStudio API integration layer in WS-TrackerV1 has structural issues inherited from the legacy WS-Tracker codebase:

- **Hardcoded credentials** bypassing the credential manager in `GetQueryService.php`
- **Duplicate configuration** across `ws_assessment_query.php` and `workstudio_resource_groups.php`
- **Misaligned interface** (`WorkStudioApiInterface`) that doesn't reflect actual implementation
- **Inconsistent folder structure** with services scattered across different locations
- **Debug code** (`dd()`) blocking route responses
- **Dead imports** in `WorkStudioServiceProvider` referencing non-existent classes
- **Missing DI bindings** - interface not bound to implementation

### Solution

Refactor the API layer to establish `WorkStudioApiService` as the single entry point that delegates to specialized services. Consolidate duplicate configuration into a single source of truth. Enforce consistent folder structure to reduce complexity and improve maintainability.

### Scope

**In Scope:**

- Mark credential hardcoding with TODO for future fix
- Consolidate `ws_assessment_query.php` and `workstudio_resource_groups.php` into single config
- Refactor `WorkStudioApiService` to be the true entry point (facade pattern)
- Update `WorkStudioApiInterface` to reflect actual API surface
- Establish consistent folder structure under `/app/Services/WorkStudio/`
- Clean up `WorkStudioServiceProvider` (remove dead imports, add proper bindings)
- Combine files where it reduces complexity
- Document all route endpoints for validation
- Remove or toggle debug `dd()` calls in routes

**Out of Scope:**

- Actually fixing the credential hardcoding (TODO only)
- Writing tests (deferred to later phase)
- UI/UX changes
- Database schema changes
- New feature implementation
- Creating the missing Aggregation/ or Transformers/ folders

## Context for Development

### Codebase Patterns

**Architecture:**
- Service-oriented with dependency injection via Laravel container
- HTTP macro pattern: `Http::macro('workstudio', ...)` in ServiceProvider for consistent API client config
- Static query builder methods in `AssessmentQueries` class
- Trait-based composition for SQL fragments (`SqlFragmentHelpers`)
- Config-driven field selection via `SqlFieldBuilder`

**Naming Conventions:**
- Services: `{Purpose}Service.php` (e.g., `GetQueryService`, `ResourceGroupAccessService`)
- Managers: `{Purpose}Manager.php` (e.g., `ApiCredentialManager`)
- Queries: `{Domain}Queries.php` (e.g., `AssessmentQueries`)
- Helpers: `{Domain}Helpers.php` or traits

**Folder Structure (Final - Consistent):**
```
app/Services/WorkStudio/
├── AssessmentsDx/
│   └── Queries/
│       ├── AssessmentQueries.php
│       ├── SqlFieldBuilder.php
│       └── SqlFragmentHelpers.php
├── Contracts/
│   └── WorkStudioApiInterface.php  ← Updated (6 methods)
├── Helpers/
│   ├── ExecutionTimer.php
│   └── WSHelpers.php
├── Managers/
│   └── ApiCredentialManager.php
├── Services/
│   ├── GetQueryService.php         ← TODO markers added
│   └── ResourceGroupAccessService.php
├── WorkJobsDx/                      (empty - future use)
└── WorkStudioApiService.php         ← Facade with delegation
```

### Files Modified

| File | Purpose | Final Status |
| ---- | ------- | ------------ |
| `WorkStudioApiService.php` | Single entry point facade (185 lines) | ✅ Fixed imports, added GetQueryService delegation |
| `GetQueryService.php` | Query execution (237 lines) | ✅ Security TODOs added at hardcoded credentials |
| `ApiCredentialManager.php` | Credential handling (184 lines) | No changes needed |
| `WorkStudioApiInterface.php` | Service contract (56 lines) | ✅ Updated to 6-method contract |
| `ResourceGroupAccessService.php` | Region access (39 lines) | Already in correct location (Services/) |
| `AssessmentQueries.php` | SQL builders (509 lines) | No changes needed |
| `SqlFragmentHelpers.php` | SQL fragments trait (244 lines) | No changes needed |
| `SqlFieldBuilder.php` | Field selection (62 lines) | No changes needed |
| `WorkStudioServiceProvider.php` | Service registration (34 lines) | ✅ Dead imports removed, binding added |
| `workstudio.php` | Base API config | No changes needed |
| `workstudio_resource_groups.php` | Region config (single source of truth) | No changes needed |
| `ws_assessment_query.php` | Query config (60 lines) | ✅ Duplicate resourceGroups removed |
| `workstudioAPI.php` | Routes (28 lines) | ✅ dd() replaced with conditional dump |

### Technical Decisions

1. **Single Entry Point**: `WorkStudioApiService` will act as facade, delegating to `GetQueryService` for query execution
2. **Config Consolidation**: Remove `resourceGroups` from `ws_assessment_query.php`, reference `workstudio_resource_groups.php` instead
3. **Folder Structure**: Enforce pattern - all services in `Services/`, delete duplicate `ResourceGroupAccessService.php` from root
4. **Interface Alignment**: `WorkStudioApiInterface` will define: `healthCheck()`, `getCurrentCredentialsInfo()`, `executeQuery()`, `getJobGuids()`, `getSystemWideMetrics()`, `getRegionalMetrics()`
5. **DI Bindings**: Add `$this->app->bind(WorkStudioApiInterface::class, WorkStudioApiService::class)` to ServiceProvider
6. **Debug Toggle**: Replace `dd()` with conditional `if (config('app.debug')) { dump($data); }`

### Investigation Findings

**Dead Code in ServiceProvider:**
```php
// These imports reference non-existent files:
use App\Services\WorkStudio\Aggregation\AggregateCalculationService;
use App\Services\WorkStudio\Aggregation\AggregateDiffService;
use App\Services\WorkStudio\Aggregation\AggregateQueryService;
use App\Services\WorkStudio\Aggregation\AggregateStorageService;
use App\Services\WorkStudio\Transformers\CircuitTransformer;
use App\Services\WorkStudio\Transformers\DDOTableTransformer;
use App\Services\WorkStudio\Transformers\PlannedUnitAggregateTransformer;
```

**Hardcoded Credentials Location:**
- `GetQueryService.php:37-38` - DBParameters with hardcoded user/pass
- `GetQueryService.php:47` - HTTP basic auth with hardcoded user/pass

**Config Duplication:**
- `ws_assessment_query.php` lines 22-76 = `workstudio_resource_groups.php` lines 10-68

**Route Registration:**
- `workstudioAPI.php` included from `web.php:13`
- Uses web middleware (sessions, CSRF)

## Implementation Plan

### Tasks

| Priority | Task | File(s) | Status | Notes |
|----------|------|---------|--------|-------|
| 1 | Add TODO comment for hardcoded credentials | `GetQueryService.php` | ✅ | Security TODOs at lines 36-37, 40, 49-50 |
| 2 | Remove dead imports from ServiceProvider | `WorkStudioServiceProvider.php` | ✅ | Removed 8 dead imports |
| 3 | Add interface binding to ServiceProvider | `WorkStudioServiceProvider.php` | ✅ | Added in register() method |
| 4 | Remove duplicate resourceGroups from config | `ws_assessment_query.php` | ✅ | Reduced 113→60 lines |
| 5 | Update references to use consolidated config | `AssessmentQueries.php` | ✅ | No changes needed - already using correct config |
| 6 | Delete duplicate ResourceGroupAccessService from root | `app/Services/WorkStudio/` | ✅ | Already correct in WS-TrackerV1 |
| 7 | Update WorkStudioApiInterface with actual methods | `WorkStudioApiInterface.php` | ✅ | 6-method contract defined |
| 8 | Expand WorkStudioApiService as facade | `WorkStudioApiService.php` | ✅ | Delegation to GetQueryService |
| 9 | Replace dd() with debug-conditional dump | `workstudioAPI.php` | ✅ | 3 routes fixed |

### Acceptance Criteria

- [x] Hardcoded credentials marked with searchable `// TODO: SECURITY` comments
- [x] `WorkStudioServiceProvider` has no dead imports and binds interface to implementation
- [x] `ws_assessment_query.php` no longer duplicates `workstudio_resource_groups.php` data
- [x] `WorkStudioApiInterface` defines 6 methods: `healthCheck()`, `getCurrentCredentialsInfo()`, `executeQuery()`, `getJobGuids()`, `getSystemWideMetrics()`, `getRegionalMetrics()`
- [x] `WorkStudioApiService` implements all interface methods, delegating query methods to `GetQueryService`
- [x] All routes in `workstudioAPI.php` return JSON responses (no blocking `dd()` calls)
- [x] Folder structure is consistent (no duplicate files at root level)

## Additional Context

### Dependencies

- `ApiCredentialManager` for credential handling
- `ResourceGroupAccessService` for region/role access
- External WorkStudio API (DDOProtocol endpoint)
- Laravel HTTP facade with `workstudio` macro

### Testing Strategy

*Deferred to later phase per scope agreement*

### Notes

- Debug `dd()` calls in routes are intentional for development, need toggle mechanism
- Credential hardcoding is known issue, will be marked with TODO only
- ServiceProvider has significant cleanup needed (dead imports)
- No existing tests to preserve - clean slate for future test phase

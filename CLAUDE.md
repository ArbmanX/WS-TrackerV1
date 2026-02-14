# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# AI Session Management Rules

These rules govern AI agent behavior during development sessions on this project.
Review the docs directory

## Context Management

- **At 60% context usage:** Ask user if they would like to create a context/handoff file summarizing current work
- **At 65% context usage:** Create a markdown summary of current activities and offer to clear context with user confirmation
- **Context file location:** Save to `docs/session-handoffs/` with timestamp

## UI & UX

- **Daisy UI exclusive** - all ui and ux design must use daisy ui, skills are available

## Git Workflow Enforcement

- **Before starting any new phase:**
  1. ensure the TODO tracker is up to date.
  2. Check if wip file is clear. If not clear prompt user to decide what to do next.
  3. create a wip file and update it after every phase. Clear wip file once branch is commited and merged.
  4. Confirm previous phase was merged to `main`
  5. Confirm current branch is `main`
  6. Create new branch for the phase

- **Always get user confirmation before:**
  - Pushing to origin (`git push`)
  - Force operations
  - Merging to main

## Documentation

- **Read the project context file** - This will provide the necessary context for this project
- **Read PROJECT_RULES.md** — Follow all project development rules

### CHANGELOG.md Maintenance

- **Update CHANGELOG.md before every commit** with meaningful changes
- Follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format
- Use these categories: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`
- Add entries under `## [Unreleased]` section
- Be specific: include file names, feature names, and brief descriptions
- Group related changes together

### Update Memory 

- **Update the project context file** - add any necessary details to the context file for the start of a new session

## Session Handoff Template

When creating context/handoff files, include:

- Current task status
- Files modified this session
- Next steps / TODO items
- Any blockers or decisions needed
- Relevant code locations
- Files NOT to read.
- keep everything to bare minimum to reduce token usage as start of new sessions 

## Build & Development Commands

```bash
# Full dev server (Laravel + Queue + Pail logs + Vite — concurrently)
composer run dev

# Run tests (Pest 4)
php artisan test --compact                          # all tests
php artisan test --compact --filter=testName         # single test by name
php artisan test --compact tests/Feature/Dashboard/  # by directory
php artisan test --compact tests/Unit/CachedQueryServiceTest.php  # single file

# Browser tests (Laravel Dusk)
php artisan dusk
php artisan dusk --filter=SmokeTest

# Code formatting (Pint — Laravel preset)
vendor/bin/pint --dirty    # format changed files only
vendor/bin/pint            # format entire project

# Frontend
npm run dev    # Vite dev server (HMR)
npm run build  # production build
```

## Architecture Overview

### External API Integration (not a local database)

WS-Tracker does **not** query a local database for business data. It sends raw SQL strings to a remote WorkStudio DDOProtocol HTTP API (`config/workstudio.php` → `base_url`), which returns JSON result sets. The local SQLite database stores only Laravel auth/session data (users, settings, activity logs).

### Service Layer — Domain-Driven Structure

`app/Services/WorkStudio/` is organized by domain:

```
WorkStudio/
├── Client/                          # HTTP infrastructure (shared)
│   ├── GetQueryService.php          # Executes SQL via HTTP POST, transforms response
│   ├── ApiCredentialManager.php     # Resolves user/service-account credentials
│   ├── WorkStudioApiService.php     # Facade delegating to GetQueryService
│   └── Contracts/WorkStudioApiInterface.php
├── Shared/                          # Cross-domain utilities
│   ├── Cache/CachedQueryService.php # TTL caching decorator over GetQueryService
│   ├── ValueObjects/UserQueryContext.php  # Immutable query scope (regions, contractors)
│   ├── Services/ResourceGroupAccessService.php, UserDetailsService.php
│   ├── Contracts/UserDetailsServiceInterface.php
│   ├── Exceptions/                  # UserNotFoundException, WorkStudioApiException
│   └── Helpers/WSHelpers.php
└── Assessments/                     # Assessment domain queries
    └── Queries/AssessmentQueries.php, SqlFieldBuilder.php, SqlFragmentHelpers.php
```

**Request flow:** Livewire Component → `CachedQueryService` → `GetQueryService` → `AssessmentQueries` (SQL builder) → HTTP POST to external API

### User Query Scoping

Every data query is scoped by `UserQueryContext` — a readonly value object built from the authenticated user's WorkStudio fields (regions, contractors, domain). Users with identical access produce the same `cacheHash()` and share cached results. Cache key pattern: `ws:{year}:ctx:{hash}:{dataset}`.

### Livewire Components

Organized into functional groups under `app/Livewire/`:
- **Dashboard/** — `Overview` (main dashboard), `ActiveAssessments`
- **DataManagement/** — `CacheControls` (admin cache dashboard), `QueryExplorer` (raw SQL tool)
- **Onboarding/** — `ChangePassword`, `WorkStudioSetup` (first-login flow)

### Route Files

- `routes/web.php` — Home redirect, onboarding, health checks
- `routes/workstudioAPI.php` — Dashboard route + API data endpoints (auth-protected)
- `routes/data-management.php` — Cache controls + query explorer (auth + onboarding middleware)

### Middleware

- `onboarding` alias → `EnsurePasswordChanged` — Enforces password change + WorkStudio validation before accessing protected routes

### Service Providers

Four providers in `bootstrap/providers.php`:
- `AppServiceProvider` — General app bindings
- `FortifyServiceProvider` — Auth config (registration disabled, admin creates users)
- `HealthCheckServiceProvider` — Spatie Health checks
- `WorkStudioServiceProvider` — Binds `WorkStudioApiInterface`, `UserDetailsServiceInterface`, registers `CachedQueryService` singleton, defines `Http::workstudio()` macro

### Config Files (project-specific)

- `config/workstudio.php` — API base URL, timeouts, view GUIDs, status mappings, service account
- `config/ws_assessment_query.php` — Scope year, excluded users, job types, contractors
- `config/ws_cache.php` — Per-dataset TTLs, cache key prefix, dataset definitions
- `config/workstudio_resource_groups.php` — Group-to-region mapping, role defaults
- `config/workstudio_fields.php` — Field definitions for SQL builder
- `config/themes.php` — DaisyUI theme configuration (16 themes)

### Frontend Stack

Tailwind CSS v4 + DaisyUI v5. Theme switching via Alpine.js store (`resources/js/alpine/stores.js`) with localStorage persistence. All UI components must use DaisyUI theme variables, never hardcoded colors.

### Testing

Pest 4 with `RefreshDatabase` trait for Feature tests. Browser tests extend `DuskTestCase`. Factory state `withWorkStudio()` on `UserFactory` creates onboarded users with WS fields.

## Workflow Rules

- **Branch workflow required** — Create `phase/` or `feature/` branches; never commit directly to main
- **Update CHANGELOG.md** before every commit (Keep a Changelog format, entries under `[Unreleased]`)
- **Run `vendor/bin/pint --dirty`** before committing
- **Run tests** before merging: `php artisan test --compact`
- **Get user confirmation** before `git push`, force operations, or merging to main
- **Check `docs/` folder** for WIP files and TODO tracker before starting new work
- **DaisyUI theming** — Use theme variables, not hardcoded colors; components must support theme switching

## Known Issues (P0)

- SSL verification disabled in `WorkStudioServiceProvider` (`'verify' => false`)

Ask user if they would like you to read the Boost Guidelines at the start of every new session. 
boost guidelines are in the .claude folder and called boost.md 
---

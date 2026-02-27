# Project Context for AI Agents

_This file contains critical rules and patterns that AI agents must follow when implementing code in this project. Focus on unobvious details that agents might otherwise miss._

---

## Technology Stack & Versions

### Core
- **PHP** ^8.2 + **Laravel** ^12.0 (Livewire Starter Kit base)
- **Livewire** ^4.0 — reactive server-rendered components
- **PostgreSQL** — application database (`pgsql` driver). Use `STRING_AGG` not `GROUP_CONCAT`
- **Tailwind CSS** ^4.0.7 + **DaisyUI** ^5.5.14 — all 35 themes enabled
- **Alpine.js** — bundled with Livewire; NOT available on authless pages
- **Vite** ^7.0.4 with `@tailwindcss/vite` plugin

### Key Dependencies
- **Spatie Permission** v6 — RBAC with 5 roles, 7 permissions
- **Spatie Health** ^1.34, **Activity Log**, **Response Cache** ^7.7
- **Laravel Pulse** ^1.5 — performance monitoring
- **Blade Heroicons** ^2.6

### External Integration
- **WorkStudio DDOProtocol** — raw SQL via HTTP POST (NOT a local DB)
- SSL verification disabled in `WorkStudioServiceProvider` (P0 known issue)

### Dev Tools
- **Pest** ^4.3 + Pest Laravel Plugin ^4.0
- **Laravel Pint** ^1.24 (formatting) · **Dusk** ^8.3 (browser) · **Pail** ^1.2.2 (logs)

## Critical Implementation Rules

### PHP/Laravel Language Rules

**Configuration & Environment:**
- Always `config()` — never `env()` outside config files
- Business-critical values are config-driven: permission statuses, unit groups, excluded users in `config/ws_assessment_query.php`
- Artisan boolean options return string `'true'`/`'false'` — use `filter_var($val, FILTER_VALIDATE_BOOLEAN)` for PostgreSQL boolean columns

**Service Architecture:**
- All services expose an interface contract (e.g., `WorkStudioApiInterface`, `PlannerMetricsServiceInterface`)
- Constructor injection only — no `app()` or `resolve()` in business logic
- No business logic in controllers — thin controllers delegate to services
- Commands are thin wrappers: inject services via `handle()`, delegate all logic
- Service providers register interface → implementation bindings

**DDOProtocol SQL & Data Patterns:**
- DDOProtocol does NOT support CTEs (`WITH...AS`) — silently returns empty results. Use derived tables (subqueries) instead
- OLE Automation dates: integer = days since Dec 30, 1899, decimal = time fraction. Parse with `CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC')` — never `createFromDate()` (inherits wall-clock time)
- MS JSON date wrapper `/Date(.../` — `REPLACE` wrapper then `CAST` to `DATETIME`
- `CONVERT(VARCHAR(10), date, 110)` returns `MM-DD-YYYY` — parse with `Carbon::createFromFormat('m-d-Y', ...)`
- GUID validation via regex before SQL interpolation (prevents injection)
- Config-driven SQL fragments via `SqlFragmentHelpers` trait — shared `baseFromClause()`, `baseWhereClause($overrides)`

**Error Handling:**
- No `dd()` anywhere — use `Log` facade or structured exceptions
- Custom exceptions: `UserNotFoundException`, `WorkStudioApiException`
- Failed fetches log via `Log::build()` on-the-fly logger — no `config/logging.php` changes

### Framework-Specific Rules

**Livewire Components:**
- Use `#[Layout('components.layout.app-shell')]` attribute for page layout with `title` and `breadcrumbs` props
- Use `#[Computed]` for derived data (auto-cached per request) — invalidate with `unset($this->propertyName)`
- Use `#[Url]` on public properties for query string sync (e.g., `viewMode`, `sortBy`, `sortDir`)
- Dispatch browser events via `$this->dispatch('event-name')` for Alpine.js interop
- Livewire views live in `resources/views/livewire/{domain}/` matching `App\Livewire\{Domain}\` namespace

**Blade Components:**
- Reusable UI components in `resources/views/components/ui/` (stat-card, metric-pill, tooltip, breadcrumbs, etc.)
- Domain components in `resources/views/components/{domain}/` (e.g., `planner/card.blade.php` → `<x-planner.card>`)
- Layout components in `resources/views/components/layout/`

**Routing:**
- Route files split by domain: `web.php`, `workstudioAPI.php`, `data-management.php`, `planner-metrics.php`, `user-management.php`
- `web.php` requires domain route files at the bottom
- `routes/dev.php` loaded conditionally: `if (app()->environment('local'))` this is used for UI design
    it provides a place to display, test and refine UI components, it is no auth required at this time
- Middleware stack: `auth` → `verified` → `onboarding` for protected routes
- Spatie permission middleware: `permission:permission-name` in route definitions

**Onboarding Flow:**
- `EnsurePasswordChanged` middleware redirects to onboarding steps progressively
- `UserSetting` model tracks `first_login`, `onboarding_step`, `onboarding_completed_at`
- Factory states: `firstLogin()`, `onboarded()` for test scenarios

**User Query Context:**
- All WorkStudio queries scoped via `UserQueryContext::fromUser(Auth::user())`
- Contains scope year, resource groups, contractors, job types — resolved from user's config
- Passed to all query/service methods; never build queries without it

### Testing Rules

**Structure & Organization:**
- Pest 4 with `RefreshDatabase` trait auto-applied to all Feature tests (configured in `Pest.php`)
- Feature tests in `tests/Feature/{Domain}/` — Unit tests in `tests/Unit/{Domain}/`
- Test helpers as file-scoped functions (e.g., `fakeAssessmentsHeading()`, `fakeAssessmentRow()`)
- Fake API responses mimic DDOProtocol format: `['Heading' => [...], 'Rows' => [[...]]]`

**Factory Patterns:**
- `User::factory()->withRole('admin')` — Spatie roles need persisted user (uses `afterCreating`)
- `UserSetting::factory()->onboarded()` / `->firstLogin()` for onboarding state
- `Assessment::factory()->withWorkStudio()` for WS-specific fields
- `UnitType::factory()->nonWorking()` for non-work unit types
- Factory defaults pull from config where applicable (e.g., `config('ws_assessment_query.job_types.assessments')`)

**Permission Tests:**
- Must seed `RolePermissionSeeder` first: `$this->seed(RolePermissionSeeder::class)`
- Create user with `withRole()`, then assert 403/200 on gated routes
- Spatie caches permissions aggressively — call `forgetCachedPermissions()` in seeders

**Service Tests:**
- Mock `GetQueryService` and instantiate service directly: `new Service($mockQS)`
- Test GUIDs must pass `validateGuid()` regex — use `{11111111-1111-1111-1111-111111111111}` format
- Pest `toContain()` is variadic — second arg is another needle, NOT a custom message

**Commands:**
- Run all: `php artisan test --compact`
- Single: `php artisan test --filter=TestName`
- Format before commit: `vendor/bin/pint --dirty`

### Code Quality & Style Rules

**Formatting:**
- Laravel Pint (PSR-12 based) — run `vendor/bin/pint --dirty` before every commit
- No custom Pint overrides — uses default Laravel preset

**DaisyUI v5 Theming (CRITICAL):**
- DaisyUI-exclusive theming — NEVER hardcode colors (no `text-blue-500`, `bg-gray-200`, etc.)
- Use DaisyUI semantic classes: `btn-primary`, `bg-base-200`, `text-base-content`, `badge-warning`
- CSS variables use full `oklch(...)` values: `var(--color-warning)` NOT `oklch(var(--wa))`
- Old v4 shorthand vars (`--wa`, `--su`, `--b3`, `--bc`) do NOT exist in v5
- For opacity: `color-mix(in oklch, var(--color-base-content) 20%, transparent)`
- Custom CSS classes in `app.css` for reusable patterns: `.nav-hub-item`, `.hub-card`, `.progress-bar-track`

**Naming Conventions:**
- Models: PascalCase singular (`Assessment`, `UnitType`, `GhostOwnershipPeriod`)
- Services: PascalCase with `Service` suffix, organized by domain (`WorkStudio/Client/`, `WorkStudio/Assessments/`, `PlannerMetrics/`)
- Query classes: PascalCase with `Queries` suffix (`AggregateQueries`, `CircuitQueries`)
- Commands: `ws:` prefix for WorkStudio commands (`ws:fetch-assessments`, `ws:run-live-monitor`)
- Config: snake_case (`ws_assessment_query`, `ws_data_collection`, `ws_cache`)
- Migrations: Laravel default timestamp prefix

**File Organization:**
- `app/Services/WorkStudio/` — domain-split: `Client/`, `Assessments/`, `Planners/`, `DataCollection/`, `Shared/`
- `app/Services/WorkStudio/Shared/` — cross-cutting: `Cache/`, `Contracts/`, `Helpers/`, `ValueObjects/`, `Services/`, `Exceptions/`
- Contracts live alongside their implementations in `Contracts/` subdirectories

### Development Workflow Rules

**Git:**
- Branch naming: `feature/` or `phase/` prefix — never commit to `main` directly
- Update `CHANGELOG.md` `[Unreleased]` section before every commit
- Track work in `docs/wip.md` during development, clear after merge
- Full commit cycle: Pint → test → changelog → commit → merge to main → push → delete branch

**Session Discipline:**
- Planning and implementation are SEPARATE sessions — never both in one
- Planning produces a doc in `docs/plans/` — implementation reads it
- Check `docs/session-handoffs/` for context from prior sessions
- Warn at 60% context; at 65% save handoff and offer to clear

### Critical Don't-Miss Rules

**Anti-Patterns:**
- NEVER use CTEs with DDOProtocol — they silently fail with no error
- NEVER use `createFromDate()` for OLE dates — it inherits current wall-clock time
- NEVER hardcode colors — always DaisyUI semantic classes or CSS variables
- NEVER use `env()` in application code — always `config()`
- NEVER put business logic in controllers — delegate to services
- NEVER use `dd()` — use `Log` or exceptions
- NEVER use old DaisyUI v4 variable names (`--wa`, `--su`, `--b3`, etc.)

**Edge Cases:**
- `STRING_AGG(...) WITHIN GROUP (...)` T-SQL contains "WITH" substring — regex tests for CTEs must use `\bWITH\b(?!IN)` to avoid false positives
- EXT=`@` rule: Parent assessments always get `parent_job_guid = null` regardless of API `PJOBGUID` value
- PostgreSQL self-referential FK requires splitting into separate `Schema::table()` call
- FK ordering: rows sorted by `strlen(EXT)` before upsert — parents insert before splits
- `Assessment` model uses `$timestamps = false` — uses `discovered_at`, `last_synced_at`, `last_edited` instead
- VEGUNIT→UNITS has zero orphans globally — safe to INNER JOIN without data loss

**Security:**
- All API credentials route through `ApiCredentialManager` — no hardcoded creds
- GUID validation via regex before any SQL interpolation (SEC-003)
- `config/workstudio.php` defaults are empty strings — creds only in `.env`
- `/health` is unauthenticated (monitoring); `/health/dashboard` requires `auth` + `permission:access-health-dashboard`

---

## Usage Guidelines

**For AI Agents:**
- Read this file before implementing any code
- Follow ALL rules exactly as documented
- When in doubt, prefer the more restrictive option
- Cross-reference with `docs/specs/` for domain-specific query logic

**For Humans:**
- Keep this file lean and focused on agent needs
- Update when technology stack or patterns change
- Review quarterly for outdated rules
- Remove rules that become obvious over time

Last Updated: 2026-02-25

---
project_name: 'WorkStudioDev'
user_name: 'Arbman'
date: '2026-02-08'
sections_completed: ['technology_stack', 'language_rules', 'framework_rules', 'testing_rules', 'code_quality', 'workflow_rules', 'critical_rules']
status: 'complete'
rule_count: 85
optimized_for_llm: true
---

# Project Context for AI Agents

_This file contains critical rules and patterns that AI agents must follow when implementing code in this project. Focus on unobvious details that agents might otherwise miss._

---

## Technology Stack & Versions

### Core Stack
- PHP 8.4 / Laravel 12 / Livewire 4 / Fortify v1
- PostgreSQL (local app data) + WorkStudio DDOProtocol API (external business data)
- Tailwind CSS v4 + DaisyUI v5 + Alpine.js (via Livewire)
- Vite 7 with @tailwindcss/vite plugin (NOT PostCSS)
- Spatie: Permission v6, Health v1, Activity Log, Response Cache v7

### Version-Critical Agent Rules
- **Laravel 12**: No `Kernel.php` — middleware in `bootstrap/app.php`. Model casts use `casts()` method.
- **Livewire 4**: `wire:model` is deferred by default. Use `wire:model.live` for real-time. No `.defer` modifier.
- **Tailwind v4**: CSS-first config via `@theme` directives. No `tailwind.config.js`. DaisyUI v5 classes.
- **Pest 4**: All tests in Pest syntax, never PHPUnit class syntax. `uses(RefreshDatabase::class)` required.
- **Spatie Permission v6**: `HasRoles` trait on User model. Aggressive caching — `forgetCachedPermissions()` in seeders.
- **Dual data sources**: PostgreSQL for auth/settings/reference data. HTTP API for business data (raw SQL over POST).

## Critical Implementation Rules

### PHP Language Rules

**Code Style:**
- Constructor property promotion required (`public function __construct(public Type $prop) {}`)
- Explicit return types on all methods. Type hints on all parameters.
- Curly braces always required on control structures, even single-line bodies.
- Enum keys use TitleCase. PHPDoc blocks over inline comments. No empty zero-param constructors.

**Configuration:**
- `config()` only — `env()` FORBIDDEN outside `config/*.php` files.
- Artisan boolean options return strings `'true'`/`'false'` — use `filter_var($val, FILTER_VALIDATE_BOOLEAN)` for PostgreSQL boolean columns.

**Eloquent:**
- `Model::query()` over `DB::` facade. Eager loading required (prevent N+1).
- Relationship methods must have return type hints.
- Factory states for tests: `withWorkStudio()`, `withRole('role-name')`, `onboarded()`.

**DDOProtocol SQL (External API):**
- NO CTEs (`WITH...AS`) — API silently returns empty. Use derived tables (subqueries).
- `CONVERT(VARCHAR(10), date, 110)` → `MM-DD-YYYY` — parse with `Carbon::createFromFormat('m-d-Y', ...)`.
- MS JSON date wrapper `/Date(...)/` — must `REPLACE` wrapper then `CAST` to `DATETIME`.
- `STRING_AGG(...) WITHIN GROUP (...)` contains "WITH" substring — CTE regex tests need `\bWITH\b(?!IN)`.

### Framework Rules (Laravel + Livewire + Alpine)

**Laravel Architecture:**
- Services implement interfaces → bound in `WorkStudioServiceProvider`. `CachedQueryService` is a singleton.
- Route files split by domain: `web.php`, `workstudioAPI.php`, `data-management.php`, `user-management.php`.
- Form Request classes for validation (not inline). Check sibling requests for array vs string format.
- `php artisan make:*` for all file creation. Pass `--no-interaction`. Named routes + `route()` helper always.

**Livewire 4:**
- `#[Computed]` for cached derived properties. `#[Url]` syncs properties with URL query params.
- Server-side state — validate/authorize in actions (they're like HTTP requests).
- Components organized by domain: `Dashboard/`, `DataManagement/`, `Onboarding/`, `UserManagement/`.

**Alpine.js:**
- `$store.sidebar` for sidebar state. `$store.theme` for theme persistence (localStorage → `<html data-theme="">`).
- Alpine for client-side interactivity only (toggling, animations, stores) — NOT data fetching.

**Middleware Chain:**
- Standard protected: `auth, verified, onboarding`. Permission-gated adds `permission:name` after.
- `onboarding` alias → `EnsurePasswordChanged` — redirects to correct onboarding step if incomplete.

**Service Layer:**
- Domain-driven: `Services/WorkStudio/{Client,Shared,Assessments}/`.
- Flow: Livewire → `CachedQueryService` → `GetQueryService` → `AssessmentQueries` → HTTP POST.
- Cache key: `ws:{scope_year}:ctx:{hash8}:{dataset}`. Users with identical WS access share cache.
- `UserQueryContext`: immutable value object scoping all queries by user's regions/contractors.

### Testing Rules

**Structure & Execution:**
- `tests/Feature/` for routes, middleware, Livewire, commands. `tests/Unit/` for services, models, value objects.
- `tests/Browser/` for Dusk browser tests. Most tests should be Feature tests.
- Create: `php artisan make:test --pest {Name}` or `--pest --unit`. Run: `php artisan test --compact --filter=Name`.
- Every code change must be tested. Run minimum tests needed via `--filter` or file path.

**Factory & Seeder Patterns:**
- `User::factory()->withWorkStudio()->create()` — onboarded user + settings + WS credential.
- `User::factory()->withRole('role-name')->create()` — REQUIRES `$this->seed(RolePermissionSeeder::class)` first.
- `withRole()` uses `afterCreating` — Spatie needs persisted user ID for pivot table.
- `SsJob` factory defaults `job_type` from config — tests must use `config()` values, not hardcoded strings.

**Permission Test Pattern:**
- Seed `RolePermissionSeeder` → create user `withRole('user')` → assert 403 → create `withRole('sudo-admin')` → assert 200.

**Conventions:**
- Use `fake()` not `$this->faker`. Prefer factory states + `Http::fake()` over Mockery.
- Never delete tests without approval. Pest syntax only — no PHPUnit class-based tests.

### Code Quality & Style Rules

**Formatting:**
- Laravel Pint (Laravel preset) — run `vendor/bin/pint --dirty` before every commit.

**Code Organization:**
- Stick to existing directory structure — no new base folders without approval.
- Don't change dependencies without approval. Reuse existing components before creating new ones.
- Prefer editing existing files over creating new ones.

**Naming:**
- Descriptive names: `isRegisteredForDiscounts`, not `discount()`.
- Livewire: PascalCase in domain folders (`Dashboard/Overview`). Config: snake_case (`ws_cache.php`).
- Routes: dot-notation (`data-management.cache`). Tests: PascalCase matching feature (`CreateUserTest.php`).

**Documentation:**
- PHPDoc blocks preferred. No inline comments unless exceptionally complex.
- No documentation files unless explicitly requested. CHANGELOG.md updated before every commit.

**Frontend Style:**
- DaisyUI theme variables only — never hardcoded colors. Must support theme switching.
- Blade Heroicons for icons. Alpine.js stores for client state.

### Development Workflow Rules

**Branching:**
- `feature/` or `phase/` branches only — never commit directly to main.

**Pre-Work Checklist:**
1. Ensure `docs/TODO.md` is up to date.
2. Check `docs/wip.md` — if not clear, prompt user.
3. Create/update `docs/wip.md` during work. Clear after merge.
4. Confirm previous phase merged to main. Create new branch from main.

**Commit Protocol:**
- Update `CHANGELOG.md` before every commit (`[Unreleased]`, Keep a Changelog format).
- Run `vendor/bin/pint --dirty` then `php artisan test --compact` before merging.

**User Confirmation Required:**
- `git push`, force operations, merging to main — always ask first.

**Dev Commands:**
- `composer run dev` — full stack (Laravel + Queue + Logs + Vite).
- Frontend not updating? → `npm run build` or `npm run dev`.

**Session Management:**
- Read `docs/project-context.md` at session start. At 60% context, offer handoff file. At 65%, create one in `docs/session-handoffs/`.

### Critical Don't-Miss Rules

**Anti-Patterns (NEVER do):**
- `DB::` facade for local data → use `Model::query()`.
- `env()` outside config files → use `config()`.
- PHPUnit class syntax → Pest only.
- `wire:model.defer` → v4 is deferred by default; use `wire:model.live` for real-time.
- Hardcoded colors in Blade → DaisyUI theme variables only.
- CTEs in DDOProtocol SQL → silently returns empty. Use derived tables.
- Assume one database → local PostgreSQL and external HTTP API are separate paths.

**Edge Cases:**
- `Carbon::parse()` fails on `MM-DD-YYYY` → use `Carbon::createFromFormat('m-d-Y', ...)`.
- Spatie caches permissions → call `forgetCachedPermissions()` in seeders and after role changes.
- `withRole()` needs persisted user (`afterCreating`) → cannot use with `make()`, only `create()`.
- `/health` is unauthenticated (monitoring). `/health/dashboard` requires auth + `access-health-dashboard`.
- Response cache is active → route/middleware changes may need cache invalidation.

**Known Security Issues (P0 — do NOT replicate these patterns):**
- SEC-001: Hardcoded creds in `GetQueryService.php` — should use `ApiCredentialManager`.
- SEC-003: SQL injection in `getAllByJobGuid` — needs GUID regex validation.
- SEC-004: SSL verification disabled — `verify => false` in `WorkStudioServiceProvider`.

**Performance:**
- `AssessmentQueries` is 650+ lines (planned split REF-003) — don't add more to it.
- `CachedQueryService` is singleton — beware state in tests.
- Cache TTLs per-dataset in `ws_cache.php` — don't hardcode override values.

---

## Usage Guidelines

**For AI Agents:**
- Read this file before implementing any code.
- Follow ALL rules exactly as documented.
- When in doubt, prefer the more restrictive option.
- Update this file if new patterns emerge during implementation.

**For Humans:**
- Keep this file lean and focused on agent needs.
- Update when technology stack or patterns change.
- Review periodically — remove rules that become obvious over time.

Last Updated: 2026-02-08

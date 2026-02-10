# WS-TrackerV1 — Project Context (Session Quick-Load)

> **Purpose:** Load this file at the start of each new AI session to avoid re-exploring the codebase.
> **Last Updated:** 2026-02-10
> **Complements:** CLAUDE.md (auto-loaded) — this file adds architecture detail, current state, and patterns NOT covered there.

---

## 1. Stack & Infrastructure

| Layer | Tech | Version | Notes |
|-------|------|---------|-------|
| Framework | Laravel | 12 | Streamlined structure (no Kernel.php) |
| PHP | | 8.4.16 | Constructor promotion, enums, readonly |
| Frontend | Tailwind + DaisyUI | v4 + v5 | Theme variables only, no hardcoded colors |
| Reactivity | Livewire + Alpine.js | v4 | Server-side state, Alpine for client interactivity |
| Auth | Fortify | v1 | Headless, registration disabled, admin creates users |
| Permissions | Spatie Permission | v6 | 5 roles, 7 permissions |
| Database | PostgreSQL | pgsql | Use `STRING_AGG` not `GROUP_CONCAT` |
| Testing | Pest | v4 | `RefreshDatabase` trait, factory states |
| Monitoring | Pulse + Spatie Health | v1 | `/health` (public), `/health/dashboard` (gated) |

**External API:** WorkStudio DDOProtocol — raw SQL via HTTP POST to `https://ppl02.geodigital.com:8372/DDOProtocol/`. NOT a local database. All business data comes from this API.

---

## 2. Directory Map (Key Files Only)

```
app/
├── Enums/OnboardingStep.php              # Step enum for 4-step onboarding
├── Http/Middleware/EnsurePasswordChanged  # 'onboarding' alias — enforces onboarding flow
├── Livewire/
│   ├── Actions/Logout                    # Logout action
│   ├── Dashboard/Overview                # Main dashboard (cards/table views, sorting)
│   ├── Dashboard/ActiveAssessments       # Checked-out assessments table
│   ├── DataManagement/CacheControls      # Cache admin (refresh, warm, clear)
│   ├── DataManagement/QueryExplorer      # Raw SQL tool (permission-gated)
│   ├── Onboarding/ChangePassword         # Step 1: password change
│   ├── Onboarding/ThemeSelection         # Step 2: theme picker
│   ├── Onboarding/WorkStudioSetup        # Step 3: WS credential validation
│   ├── Onboarding/Confirmation           # Step 4: summary + finalize
│   └── UserManagement/CreateUser         # Admin user creation
├── Models/
│   ├── User.php                          # HasRoles, LogsActivity, HasOne: settings, wsCredential
│   ├── UserSetting.php                   # Theme, onboarding state, preferences
│   ├── UserWsCredential.php              # Encrypted WS creds (Laravel encryption)
│   ├── Region.php                        # HasMany: circuits, scope: active()
│   └── Circuit.php                       # BelongsTo: region, scope: active()
├── Providers/
│   ├── AppServiceProvider                # Bindings, Pulse gate, password rules
│   ├── FortifyServiceProvider            # Auth views, rate limiting (5/min)
│   ├── HealthCheckServiceProvider        # DB, cache, disk, WS API, env checks
│   └── WorkStudioServiceProvider         # API bindings, CachedQueryService singleton, Http macro
└── Services/WorkStudio/
    ├── Client/
    │   ├── GetQueryService               # Core: executes SQL via HTTP POST ⚠️ hardcoded creds
    │   ├── ApiCredentialManager           # Credential CRUD, test, encrypt/decrypt
    │   ├── WorkStudioApiService           # Facade with retry logic (5 retries, exponential backoff)
    │   ├── HeartbeatService               # Lightweight server availability check
    │   └── Contracts/WorkStudioApiInterface
    ├── Shared/
    │   ├── Cache/CachedQueryService       # TTL caching decorator with registry
    │   ├── ValueObjects/UserQueryContext   # Immutable query scope (regions, contractors, domain)
    │   ├── Services/ResourceGroupAccessService  # WS groups → regions mapping
    │   ├── Services/UserDetailsService    # Enriches user data from /GETUSERDETAILS
    │   ├── Helpers/WSHelpers              # SQL IN clause builder
    │   └── Exceptions/                    # UserNotFoundException, WorkStudioApiException
    └── Assessments/Queries/
        ├── AssessmentQueries              # 650+ line SQL builder (TODO: split)
        ├── SqlFieldBuilder                # Dynamic field selection from config
        └── SqlFragmentHelpers             # Reusable SQL fragments (trait)

config/
├── workstudio.php                        # API URL, timeouts, view GUIDs, status mappings
├── ws_cache.php                          # Per-dataset TTLs, prefix "ws", registry
├── ws_assessment_query.php               # Scope year, excluded users, job types, contractors
├── workstudio_resource_groups.php        # Group-to-region mapping, role defaults
├── workstudio_fields.php                 # SQL field definitions
├── themes.php                            # 16 DaisyUI themes (default: corporate)
└── permission.php                        # Spatie config (guard: web, 24h cache)

routes/
├── web.php                               # Home redirect, onboarding, health endpoints
├── workstudioAPI.php                     # Dashboard + API JSON endpoints (auth+onboarding)
├── data-management.php                   # Cache + Query Explorer (permission-gated)
└── user-management.php                   # Create user (permission: manage-users)
```

---

## 3. Request Flow

```
Livewire Component
  → CachedQueryService (TTL cache with registry)
    → GetQueryService (HTTP POST to /GETQUERY)
      → AssessmentQueries (SQL string builder)
        → WorkStudio DDOProtocol API
          → JSON response → Collection
```

**Cache key pattern:** `ws:{scope_year}:ctx:{hash8}:{dataset}`
- `hash8` = first 8 chars of MD5(sorted resourceGroups + sorted contractors)
- Users with identical WS access share cached results
- TTL per dataset: 600-900s (configurable in `ws_cache.php`)

---

## 4. Models Quick Reference

### User
- **Relations:** `settings` (HasOne→UserSetting), `wsCredential` (HasOne→UserWsCredential)
- **Traits:** HasFactory, HasRoles (Spatie), LogsActivity, Notifiable, TwoFactorAuthenticatable
- **WS Fields:** ws_username, ws_full_name, ws_domain, ws_groups (JSON), ws_resource_groups (JSON), ws_validated_at
- **Key Methods:** `isOnboardingComplete()`, `isFirstLogin()`, `queryContext()`, `initials()`
- **Casts:** password→hashed, ws_groups→array, ws_resource_groups→array

### UserSetting
- **Fields:** user_id (unique FK), theme, layout_preference, notifications_enabled, sidebar_collapsed, first_login, onboarding_step, onboarding_completed_at
- **Casts:** notifications_enabled→bool, sidebar_collapsed→bool, first_login→bool, onboarding_step→int, onboarding_completed_at→datetime

### UserWsCredential
- **Fields:** user_id (unique FK), encrypted_username, encrypted_password, is_valid, validated_at, last_used_at
- **Casts:** encrypted_username→encrypted, encrypted_password→encrypted, is_valid→bool
- **Methods:** `markAsUsed()`, `markAsValid()`, `markAsInvalid()`

### Region
- **Fields:** name (unique), display_name, is_active, sort_order
- **Relations:** `circuits` (HasMany→Circuit)
- **6 regions:** HARRISBURG, LANCASTER, LEHIGH, CENTRAL, SUSQUEHANNA, NORTHEAST

### Circuit
- **Fields:** line_name (unique), region_id (FK→regions, SET NULL), is_active, last_trim, next_trim, properties (JSON), last_seen_at
- **Relations:** `region` (BelongsTo→Region), `ssJobs` (HasMany→SsJob)

### WsUser
- **Fields:** username (unique), domain, display_name, email, is_enabled, groups (JSON), last_synced_at
- **Relations:** `takenJobs` (HasMany→SsJob, FK: taken_by_id), `modifiedJobs` (HasMany→SsJob, FK: modified_by_id)
- **Casts:** is_enabled→bool, groups→array, last_synced_at→datetime

### SsJob
- **PK:** job_guid (string, non-incrementing)
- **Fields:** circuit_id (FK→circuits), parent_job_guid (string, no FK constraint), taken_by_id (FK→ws_users), modified_by_id (FK→ws_users), work_order, extensions (JSON), job_type, status, scope_year, edit_date, taken, version, sync_version, assigned_to, raw_title, last_synced_at
- **Relations:** `circuit` (BelongsTo→Circuit), `parentJob` (BelongsTo→self), `childJobs` (HasMany→self), `takenBy` (BelongsTo→WsUser), `modifiedBy` (BelongsTo→WsUser)
- **Casts:** extensions→array, edit_date→datetime, taken→bool, last_synced_at→datetime

### UnitType
- **Fields:** unit (unique), unitssname, unitsetid, summarygrp, entityname, work_unit (bool), last_synced_at
- **Casts:** work_unit→boolean, last_synced_at→datetime
- **Synced by:** `ws:fetch-unit-types` artisan command (upserts from WorkStudio UNITS table)
- **`work_unit` derivation:** true when `SUMMARYGRP` is not null, not empty, and not `Summary-NonWork`

---

## 5. Routes & Permissions

### Permission Matrix

| Role | view-dashboard | access-data-management | manage-cache | execute-queries | access-pulse | access-health-dashboard | manage-users |
|------|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| sudo-admin | x | x | x | x | x | x | x |
| manager | x | x | | | | | x |
| planner | x | | | | | | |
| general-foreman | x | | | | | | |
| user | x | | | | | | |

### Route Groups

| Route | Middleware Stack | Component |
|-------|-----------------|-----------|
| `/` | web | Redirect to login |
| `/onboarding/*` | auth | Onboarding steps (4 Livewire components) |
| `/dashboard` | auth, verified, onboarding | Dashboard/Overview |
| `/system-wide-metrics` | auth, verified, onboarding | JSON API |
| `/regional-metrics` | auth, verified, onboarding | JSON API |
| `/data-management/cache` | auth, verified, onboarding, permission:access-data-management | CacheControls |
| `/data-management/query-explorer` | + permission:execute-queries | QueryExplorer |
| `/user-management/create` | auth, verified, onboarding, permission:manage-users | CreateUser |
| `/health` | none | JSON health check |
| `/health/dashboard` | auth, permission:access-health-dashboard | Spatie Health UI |

### Middleware Aliases (bootstrap/app.php)
- `onboarding` → EnsurePasswordChanged (routes to correct onboarding step)
- `role` → Spatie RoleMiddleware
- `permission` → Spatie PermissionMiddleware
- `role_or_permission` → Spatie RoleOrPermissionMiddleware

---

## 6. Factory States & Testing Patterns

### Factory Quick Reference

```php
// Fully onboarded user with WS fields
User::factory()->withWorkStudio()->create();

// User with specific role (needs RolePermissionSeeder)
User::factory()->withRole('sudo-admin')->create();

// Combined: onboarded + role
User::factory()->withWorkStudio()->withRole('manager')->create();

// UserSetting states
UserSetting::factory()->onboarded()->create();     // completed onboarding
UserSetting::factory()->atStep(2)->create();        // at specific step
UserSetting::factory()->firstLogin()->create();     // first login state

// UserWsCredential states
UserWsCredential::factory()->invalid()->create();   // is_valid=false

// Region/Circuit
Region::factory()->inactive()->create();
Circuit::factory()->withRegion()->create();
Circuit::factory()->inactive()->create();

// WsUser states
WsUser::factory()->unenriched()->create();       // null display_name/email
WsUser::factory()->disabled()->create();          // is_enabled=false

// SsJob states
SsJob::factory()->withCircuit()->create();        // creates + links Circuit
SsJob::factory()->withTakenBy()->create();        // creates + links WsUser
SsJob::factory()->withStatus('QC')->create();     // specific status
```

### Permission Test Pattern

```php
uses(RefreshDatabase::class);

it('requires manage-users permission', function () {
    $this->seed(RolePermissionSeeder::class);

    // Unauthorized user
    $user = User::factory()->withRole('user')->create();
    $this->actingAs($user)->get('/user-management/create')->assertForbidden();

    // Authorized user
    $admin = User::factory()->withRole('sudo-admin')->create();
    $this->actingAs($admin)->get('/user-management/create')->assertOk();
});
```

### Key Testing Notes
- Always seed `RolePermissionSeeder` for permission-gated route tests
- `withRole()` uses `afterCreating` (Spatie needs persisted user for pivot)
- `withWorkStudio()` auto-creates onboarded UserSetting + valid UserWsCredential
- Use `fake()` not `$this->faker` (project convention)
- Run: `php artisan test --compact --filter=TestName`

---

## 7. Sidebar Navigation Structure

```php
// Sections with permission gating
'Dashboard'        → permission: null (visible to all auth users)
  'Overview'       → route: dashboard, permission: view-dashboard
'Data Management'  → permission: access-data-management (section-level gate)
  'Cache Controls' → route: data-management.cache, permission: access-data-management
  'Query Explorer' → route: data-management.query-explorer, permission: execute-queries
'User Management'  → permission: manage-users (section-level gate)
  'Create User'    → route: user-management.create, permission: manage-users
  'Edit Users'     → route: user-management.edit (not yet implemented)
  'User Activity'  → route: user-management.activity (not yet implemented)
  'User Settings'  → route: user-management.settings (not yet implemented)
```

Permission check: `auth()->user()?->can($permission)` in Blade.
Sidebar is responsive: mobile drawer, tablet icons-only, desktop expanded.

---

## 8. Current State & Priorities

### What's Built (as of 2026-02-08)
- 4-step onboarding flow (password → theme → WS creds → confirmation)
- Dashboard with system-wide & regional metrics, active assessments
- Cache management admin tool
- Query Explorer (raw SQL against WS API)
- User creation with role assignment
- Spatie Permission (5 roles, 7 permissions)
- Reference data (6 regions, circuits from API)
- SS Jobs & WS Users data sync (`ws:fetch-users`, `ws:fetch-jobs` artisan commands)
- Unit types reference table with `ws:fetch-unit-types` sync command
- Daily footage query with working unit count (`unit_count`)
- Domain-driven service architecture
- ~41+ test files, good coverage of recent features

### P0 — Must Fix Before Production
| ID | Issue | File |
|----|-------|------|
| SEC-001 | Hardcoded credentials | `Client/GetQueryService.php` lines 36, 45 |
| SEC-003 | SQL injection in getAllByJobGuid | `Client/GetQueryService.php` — needs GUID regex |
| SEC-004 | SSL verification disabled | `WorkStudioServiceProvider.php` — `verify => false` |

### P1 — High Priority
| ID | Issue |
|----|-------|
| CLN-009 | Fix broken route closures (missing UserQueryContext) |
| REF-001 | Extract view logic to #[Computed] properties |
| REF-008 | Fix wrong return types (getRegionsForRole, executeAndHandle) |
| UI-001 | Add error states to dashboard components |
| UI-002 | Add loading skeletons |

### P2 — Next Sprint
| ID | Issue |
|----|-------|
| REF-003 | Split AssessmentQueries (650+ lines) into focused classes |
| REF-005 | Extract magic strings to enums (ACTIV, QC, REWRK, etc.) |
| FT-001 | Planner Daily Activity System (depends on REF-003) |
| FT-006 | Unified toast/notification system |
| FT-007 | Historical Assessment Archival & Analytics |

### Stats: 64 TODOs total — 49 pending, 14 completed, 10 need plans

---

## 9. Gotchas & Patterns

### Architecture Gotchas
- **PostgreSQL** — Use `STRING_AGG` not `GROUP_CONCAT`, use `pgsql` dialect
- **External API** — Business data is NOT in local DB. All queries go through HTTP POST to WorkStudio
- **Spatie caches aggressively** — Call `app()[PermissionRegistrar::class]->forgetCachedPermissions()` in seeders
- **`withRole()` timing** — Uses `afterCreating` because Spatie needs the user ID persisted first
- **Cache scoping** — Users with identical WS access (same regions/contractors) share cache via `cacheHash()`
- **SQL builder** — AssessmentQueries builds raw SQL strings, not Eloquent. This is intentional (external DB)

### Code Patterns
- Services implement interfaces → enables testing and swapping
- Use `config()` never `env()` in app code
- Constructor property promotion everywhere
- Livewire components use `#[Computed]` for cached properties
- `#[Url]` attribute syncs Livewire properties with URL params
- Health check at `/health` is unauthenticated (monitoring); `/health/dashboard` requires auth + permission

### UI Patterns
- DaisyUI components with theme variables (never hardcoded colors)
- Alpine.js `$store.sidebar` for sidebar state
- Alpine.js `$store.theme` for theme persistence (localStorage)
- Dynamic route validation in sidebar (disables items if route doesn't exist)
- Theme set on `<html data-theme="">` attribute

### Development Commands
```bash
composer run dev              # Full dev (Laravel + Queue + Logs + Vite)
php artisan test --compact    # All tests
vendor/bin/pint --dirty       # Format changed files
npm run build                 # Production frontend build
```

---

## 10. Key Config Values (for SQL Builders)

**Scope Year:** Configured in `ws_assessment_query.scope_year`
**Excluded Cycle Types:** 'Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP'
**Valid Unit Filter:** `UNIT IS NOT NULL AND UNIT != '' AND UNIT != 'NW'`
**Status Codes:** SA (Saved), ACTIV (Active), QC (Quality Check), REWRK (Rework), DEF (Deferred), CLOSE (Closed)
**WS API Tables:** VEGJOB (jobs), VEGUNIT (units), STATIONS, WSREQUEST (work requests), SS (assessment jobs — synced to local ss_jobs table)

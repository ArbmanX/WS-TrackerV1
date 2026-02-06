# WS-TrackerV1 Code Review & UI/UX Audit

> **Reviewer:** BMad Master (Automated Deep Analysis)
> **Date:** 2026-02-05
> **Scope:** Full codebase, UI/UX, architecture, security
> **Stack:** Laravel 12 / Livewire 4 / Fortify / Tailwind CSS v4 / DaisyUI v5 / Alpine.js

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Critical Security Issues](#2-critical-security-issues)
3. [Unused Files & Dead Code](#3-unused-files--dead-code)
4. [Business Logic Extraction Guide](#4-business-logic-extraction-guide)
5. [Code Quality Fixes](#5-code-quality-fixes)
6. [Architecture Improvements](#6-architecture-improvements)
7. [UI/UX Design Improvements](#7-uiux-design-improvements)
8. [Prioritized Action Plan](#8-prioritized-action-plan)

---

## 1. Executive Summary

WS-TrackerV1 is a Laravel 12 + Livewire 4 application for tracking WorkStudio assessment data. The project demonstrates strong foundational architecture — services with interfaces, proper value objects, context-scoped caching, and clean Livewire components. However, several critical issues must be addressed before production deployment.

### Metrics at a Glance

| Category | Count |
|---|---|
| Critical Security Issues | 5 |
| Unused Files/Dead Code | 11 items |
| Business Logic to Extract | 7 areas |
| Code Quality Fixes | 14 items |
| UI/UX Improvements | 12 recommendations |

---

## 2. Critical Security Issues

### 2.1 HARDCODED CREDENTIALS IN SOURCE CODE

**Severity:** CRITICAL
**File:** `app/Services/WorkStudio/Services/GetQueryService.php` (lines 39, 49)

```php
// Line 39 — Plaintext credentials in payload
'DBParameters' => "USER NAME=ASPLUNDH\\cnewcombe\r\nPASSWORD=chrism\r\n",

// Line 49 — Plaintext credentials in HTTP auth
Http::withBasicAuth('ASPLUNDH\cnewcombe', 'chrism')
```

**Fix:**
Replace hardcoded credentials with the already-existing `$credentials` variable from `ApiCredentialManager`:

```php
$credentials = $this->getCredentials($userId);

$payload = [
    'Protocol' => 'GETQUERY',
    'DBParameters' => "USER NAME={$credentials['username']}\r\nPASSWORD={$credentials['password']}\r\n",
    'SQL' => $sql,
];

$response = Http::withBasicAuth($credentials['username'], $credentials['password'])
    ->timeout(120)
    // ...
```

> The credential manager and `$credentials` variable already exist (line 33) but are not used. The TODO comments on lines 35, 39, and 48 acknowledge this.

---

### 2.2 SQL INJECTION VULNERABILITY

**Severity:** CRITICAL
**File:** `app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php`

The `getAllByJobGuid()` method directly interpolates a string into SQL:

```php
"WHERE WSREQSS.JOBGUID = '{$jobGuid}'"
```

**Fix:**
Since this API uses raw SQL strings (not parameterized queries), validate the GUID format strictly:

```php
public function getAllByJobGuid(string $jobGuid): string
{
    // Validate GUID format: {XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}
    if (!preg_match('/^\{[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}\}$/', $jobGuid)) {
        throw new \InvalidArgumentException("Invalid GUID format: {$jobGuid}");
    }
    // ... rest of query
}
```

Also apply the same validation to `WSHelpers::toSqlInClause()` for any user-facing inputs.

---

### 2.3 UNPROTECTED API/TEST ROUTES

> **COMPLETED** (2026-02-05) — Branch `cleanup/dead-code-removal`. Test route removed, API routes wrapped in `auth` middleware, `UserQueryContext` added, `dump()` calls removed.

**Severity:** HIGH
**File:** `routes/workstudioAPI.php`

Multiple routes expose raw WorkStudio data without authentication:

| Route | Issue |
|---|---|
| `GET /dashboard/test` (line 17) | Dashboard accessible without auth |
| `GET /assessment-jobguids` (line 25) | No auth middleware |
| `GET /system-wide-metrics` (line 34) | No auth middleware |
| `GET /regional-metrics` (line 43) | No auth middleware |
| `GET /daily-activities/all-assessments` (line 52) | No auth middleware |
| `GET /allByJobGUID` (line 61) | No auth middleware |
| `GET /field-lookup/{table}/{field}` (line 74) | No auth, accepts table/field params |

**Fix:**
- Remove `dashboard/test` route entirely
- Wrap all API routes in auth middleware group
- Add rate limiting to API routes
- Move debug `dump()` calls behind a config flag or remove entirely

```php
Route::middleware(['auth', 'verified', 'onboarding'])
    ->prefix('api/ws')
    ->name('api.ws.')
    ->group(function () {
        // ... API routes here
    });
```

---

### 2.4 SSL VERIFICATION DISABLED

**Severity:** HIGH
**File:** `app/Providers/WorkStudioServiceProvider.php` (line ~41)

```php
Http::macro('workstudio', function () {
    return Http::timeout(config('workstudio.timeout', 60))
        ->connectTimeout(config('workstudio.connect_timeout', 10))
        ->withOptions(['verify' => false]); // <-- Disables SSL
});
```

**Fix:**
Enable SSL verification in production. Use a config toggle for development:

```php
->withOptions(['verify' => config('workstudio.verify_ssl', true)])
```

---

### 2.5 PUBLIC SQL STATE EXPOSURE

> **COMPLETED** (2026-02-05) — Branch `cleanup/dead-code-removal`. Property and assignment removed entirely (was set but never read).

**Severity:** MEDIUM
**File:** `app/Services/WorkStudio/Services/GetQueryService.php` (line 16)

```php
public $sqlState; // Stores the last SQL query executed — publicly accessible
```

**Fix:**
Change visibility to `private` or `protected` and add a controlled getter if needed:

```php
private string $lastSql = '';
```

---

## 3. Unused Files & Dead Code

### 3.1 Unused Files (Safe to Delete)

> **COMPLETED** (2026-02-05) — Branch `cleanup/dead-code-removal`. All listed files deleted, plus `ExecutionTimer.php`, `CreateNewUser.php`, `ProfileValidationRules.php`, `settings.php`, `dashboard.blade.php`.

| File | Reason |
|---|---|
| `app/Livewire/_backup/TwoFactor.php` | Deprecated — Fortify handles 2FA natively. Entire `_backup/` directory. |
| `app/Livewire/_backup/TwoFactor/RecoveryCodes.php` | Same as above — deprecated backup code. |
| `resources/views/livewire/auth/register.blade.php` | Registration is disabled in `config/fortify.php` (`Features::registration()` is commented out). View still exists but is unreachable. |
| `app/Services/WorkStudio/Helpers/ExecutionTimer.php` | Debug-only utility. Not used in any production code path. Only referenced in `queryAll()` debug method. |
| `resources/views/welcome.blade.php` | Root `/` route immediately redirects to login. Welcome page is never served. |

### 3.2 Unused Methods & Properties

| Location | Item | Reason |
|---|---|---|
| `WorkStudioApiService.php:25` | `private ?int $currentUserId = null` | Declared but never set or read anywhere in the class. |
| `GetQueryService.php:179` | `getAll()` method | Hardcodes test GUID `{9C2BFF24-...}`. Only callable via unprotected `/allByJobGUID` route. |
| `GetQueryService.php:222` | `queryAll()` method | Debug method with `dump()` calls. Not referenced by any route or service. |
| `GetQueryService.php:16` | `public $sqlState` | Set but never read by any consumer. |
| `WorkStudioApiService.php` | Delegation methods (`executeQuery`, `getJobGuids`, etc.) | `WorkStudioApiService` delegates to `GetQueryService` but the routes in `workstudioAPI.php` inject `GetQueryService` directly, bypassing the facade entirely. |

### 3.3 Dead Route Groups

| File | Routes | Reason |
|---|---|---|
| `routes/settings.php` | All settings routes | Entire file is redirects to dashboard. Comment says "Temporarily Disabled for UI Rebuild." No corresponding Livewire components exist (Settings\Profile, Settings\Password, etc. were removed). |
| `routes/workstudioAPI.php:17` | `dashboard/test` | Testing route with comment "remove in production." |
| `routes/workstudioAPI.php:25-81` | All `/assessment-*`, `/system-wide-*`, `/regional-*`, `/daily-activities/*`, `/allByJobGUID`, `/field-lookup/*` routes | These are testing/debug routes using closures. They bypass the service facade and inject `GetQueryService` directly. They also call methods that now require `UserQueryContext` but the routes don't pass one — these routes will throw errors if called. |

---

## 4. Business Logic Extraction Guide

The project rules state: **"No business logic in controllers"** and **"Services must implement interfaces."** Several areas violate these principles.

### 4.1 Overview Blade Template — Progress Calculation

**File:** `resources/views/livewire/dashboard/overview.blade.php` (lines 3-8)

```php
@php
    $totalStats = $this->systemMetrics->first() ?? [];
    $totalMiles = $totalStats['total_miles'] ?? 0;
    $completedMiles = $totalStats['completed_miles'] ?? 0;
    $overallPercent = $totalMiles > 0 ? ($completedMiles / $totalMiles) * 100 : 0;
@endphp
```

**Extract to:** Computed property in `app/Livewire/Dashboard/Overview.php`

```php
#[Computed]
public function overallProgress(): array
{
    $stats = $this->systemMetrics->first() ?? [];
    $total = $stats['total_miles'] ?? 0;
    $completed = $stats['completed_miles'] ?? 0;

    return [
        'total_miles' => $total,
        'completed_miles' => $completed,
        'percent' => $total > 0 ? ($completed / $total) * 100 : 0,
        'total_assessments' => $stats['total_assessments'] ?? 0,
        'active_planners' => $stats['active_planners'] ?? 0,
    ];
}
```

---

### 4.2 Region Card — Progress Calculation

**File:** `resources/views/components/dashboard/region-card.blade.php` (lines 5-10)

```php
@php
    $totalMiles = $region['Total_Miles'] ?? 0;
    $completedMiles = $region['Completed_Miles'] ?? 0;
    $percentComplete = $totalMiles > 0 ? ($completedMiles / $totalMiles) * 100 : 0;
    $milesRemaining = $totalMiles - $completedMiles;
@endphp
```

**Extract to:** Either a `RegionMetric` value object or helper method in a view model. The same pattern repeats for region-table.

---

### 4.3 CacheControls — Dynamic Method Invocation

**File:** `app/Livewire/DataManagement/CacheControls.php`

The `refreshDataset()` method calls service methods dynamically:

```php
$service->{$method}($context);
```

**Extract to:** A dedicated method map or strategy pattern in `CachedQueryService`:

```php
// In CachedQueryService
public function refreshDataset(string $dataset, UserQueryContext $context): void
{
    $this->invalidateDataset($dataset, $context);
    match ($dataset) {
        'system_metrics' => $this->getSystemWideMetrics($context, forceRefresh: true),
        'regional_metrics' => $this->getRegionalMetrics($context, forceRefresh: true),
        'active_assessments' => $this->getActiveAssessmentsOrderedByOldest($context, forceRefresh: true),
        // ... other datasets
        default => throw new \InvalidArgumentException("Unknown dataset: {$dataset}"),
    };
}
```

---

### 4.4 AssessmentQueries — Monolithic 650+ Line Class

**File:** `app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php`

This single class contains ALL SQL generation logic. Break it into focused query builders:

| New Class | Methods to Extract |
|---|---|
| `SystemMetricsQuery` | `systemWideDataQuery()` |
| `RegionalMetricsQuery` | `groupedByRegionDataQuery()` |
| `CircuitDetailsQuery` | `groupedByCircuitDataQuery()`, `getAllByJobGuid()` |
| `DailyActivitiesQuery` | `getAllAssessmentsDailyActivities()` |
| `ActiveAssessmentsQuery` | `getActiveAssessmentsOrderedByOldest()` |
| `FieldLookupQuery` | `getDistinctFieldValues()`, `getAllJobGUIDsForEntireScopeYear()` |

All classes should share the base context setup (resource groups, contractors, etc.) via a parent class or composition.

---

### 4.5 Magic Strings — Status Codes & Filter Constants

**Scattered across:** `AssessmentQueries.php`, `SqlFragmentHelpers.php`

Hardcoded strings like `'ACTIV'`, `'QC'`, `'REWRK'`, `'CLOSE'`, `'Reactive'`, `'Storm Follow Up'` appear throughout SQL generation.

**Extract to:** An enum or config constants:

```php
// app/Enums/AssessmentStatus.php
enum AssessmentStatus: string
{
    case Active = 'ACTIV';
    case QualityCheck = 'QC';
    case Rework = 'REWRK';
    case Closed = 'CLOSE';
}

// config/ws_assessment_query.php — already partially exists, expand it
'excluded_cycle_types' => ['Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP'],
```

---

### 4.6 Duplicate SQL Filters

The same cycle type exclusion filter appears in at least 4 query methods:

```sql
AND VEGJOB.CYCLETYPE NOT IN ('Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP')
```

**Extract to:** A shared method in `SqlFragmentHelpers`:

```php
protected function excludedCycleTypesClause(): string
{
    $types = WSHelpers::toSqlInClause(config('ws_assessment_query.excluded_cycle_types'));
    return "AND VEGJOB.CYCLETYPE NOT IN ({$types})";
}
```

---

### 4.7 Onboarding Settings — Assumption Without Null Check

**Files:** `ChangePassword.php`, `WorkStudioSetup.php`

Both onboarding components assume `$user->settings` relation exists:

```php
auth()->user()->settings->updateOrCreate(...)
```

**Extract to:** A method on the User model or a dedicated `OnboardingService`:

```php
// app/Models/User.php
public function ensureSettings(): UserSetting
{
    return $this->settings ?? $this->settings()->create([
        'first_login' => true,
    ]);
}

// Or better: app/Services/OnboardingService.php
class OnboardingService
{
    public function markPasswordChanged(User $user): void { ... }
    public function markWorkStudioSetup(User $user, array $wsData): void { ... }
    public function isComplete(User $user): bool { ... }
}
```

---

## 5. Code Quality Fixes

### 5.1 Exception Handling Inconsistencies

| Component | Current Behavior | Fix |
|---|---|---|
| `ActiveAssessments.php` | Catches exception, returns empty collection silently | Show error state in UI; log the exception |
| `Overview.php` (`systemMetrics`, `regionalMetrics`) | No try-catch at all — will throw to user | Wrap in try-catch, set an `$error` property for UI |
| `CacheControls.php` | Catches `\Throwable` (too broad) | Catch specific exceptions; log unexpected ones |
| `TwoFactor.php` (backup) | Generic catch-all, shows generic message | N/A (deprecated — delete) |

**Recommended pattern for Livewire components:**

```php
#[Computed]
public function systemMetrics(): Collection
{
    try {
        $context = UserQueryContext::fromUser(auth()->user());
        return app(CachedQueryService::class)->getSystemWideMetrics($context);
    } catch (\Throwable $e) {
        Log::error('Failed to load system metrics', ['error' => $e->getMessage()]);
        $this->dispatch('notify', type: 'error', message: 'Failed to load metrics. Please try refreshing.');
        return collect([]);
    }
}
```

---

### 5.2 Sort Flag Issue

**File:** `app/Livewire/Dashboard/Overview.php`

```php
$sorted = $metrics->sortBy($this->sortBy, SORT_REGULAR, $descending);
```

`SORT_REGULAR` may produce unexpected results when comparing mixed numeric/string data. Use explicit sort:

```php
$sorted = $metrics->sortBy(function ($item) {
    return $item[$this->sortBy] ?? '';
}, SORT_NATURAL, $descending);
```

---

### 5.3 Wrong Return Types

| Location | Declared | Actual | Fix |
|---|---|---|---|
| `ResourceGroupAccessService::getRegionsForRole()` | `array\|string` | Always `array` | Change to `array` |
| `GetQueryService::executeAndHandle()` | `Collection\|array` | Always `Collection` | Change to `Collection` |

---

### 5.4 Missing Interface for CachedQueryService

`CachedQueryService` is bound as a singleton in the container but has no interface. Per project rules: **"Services must implement interfaces."**

Create `Contracts/CachedQueryServiceInterface.php` and bind it.

---

### 5.5 ApiCredentialManager — Non-Transactional Double Updates

**File:** `app/Services/WorkStudio/Managers/ApiCredentialManager.php`

`markSuccess()` and `markFailed()` update both `UserWsCredential` and `User` models separately:

```php
public function markSuccess(?int $userId): void
{
    DB::transaction(function () use ($userId) {
        $credential = UserWsCredential::where('user_id', $userId)->first();
        $credential?->update([...]);
        User::where('id', $userId)->update([...]);
    });
}
```

---

### 5.6 AppServiceProvider — Missing `hasRole` Method

**File:** `app/Providers/AppServiceProvider.php` (line ~40)

```php
$user->hasRole('admin')  // User model doesn't define hasRole()
```

The User model uses `Spatie\Permission` trait imports but `HasRoles` trait is **not** included in the User model's `use` statement. This will throw a `BadMethodCallException` when any user tries to access the Pulse dashboard.

**Fix:** Either add the `HasRoles` trait to User or implement a simple role check:

```php
// Option A: Add Spatie permission trait
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable
{
    use HasFactory, HasRoles, LogsActivity, Notifiable, TwoFactorAuthenticatable;
}

// Option B: Simple admin check (if not using Spatie roles)
Gate::define('viewPulse', fn (User $user) => $user->email === config('app.admin_email'));
```

---

### 5.7 Debug Code in Production

> **COMPLETED** (2026-02-05) — Branch `cleanup/dead-code-removal`. `queryAll()`, `getAll()` methods deleted. `ExecutionTimer.php` deleted. Route `dump()` calls removed.

| File | Line | Issue |
|---|---|---|
| `GetQueryService.php` | 243-245 | `dump()` calls in `queryAll()` method |
| `GetQueryService.php` | 54 | `logger()->info("Transfer time: ...")` on every single API call |
| `workstudioAPI.php` | Various | `dump($data)` behind `config('app.debug')` but still in routes |
| `ExecutionTimer.php` | 19 | `echo` output directly in class |

**Fix:** Remove all `dump()` calls. Replace verbose logging with conditional debug logging.

---

### 5.8 Broken Route Closures

**File:** `routes/workstudioAPI.php`

All closure routes inject `GetQueryService` and call methods like `$queryService->getJobGuids()` without passing a `UserQueryContext`. These methods now require context as the first parameter. These routes will throw `TypeError` exceptions if called.

**Fix:** Either delete these test routes or fix them to work with the current API:

```php
// If keeping for admin debugging:
Route::middleware(['auth'])->prefix('api/debug')->group(function () {
    Route::get('/job-guids', function () {
        $context = UserQueryContext::fromUser(auth()->user());
        return response()->json(app(GetQueryService::class)->getJobGuids($context));
    });
});
```

---

## 6. Architecture Improvements

### 6.1 Service Layer Gaps

The codebase has a good start on clean architecture but has gaps:

```
Current:
  Livewire Component → CachedQueryService → GetQueryService → AssessmentQueries
                                                             → External API

Missing:
  - Interface for CachedQueryService
  - Interface for GetQueryService
  - WorkStudioApiService facade is bypassed by routes
  - No OnboardingService (logic lives in Livewire components)
```

**Recommended:**
1. Add `CachedQueryServiceInterface`
2. Add `GetQueryServiceInterface`
3. Route all API calls through `WorkStudioApiService` (remove direct `GetQueryService` injection in routes)
4. Create `OnboardingService` for password/WS setup logic

---

### 6.2 CachedQueryService — Hardcoded Cache Invalidation

```php
private function clearComputedCache(): void
{
    unset($this->cacheStatus, $this->cacheDriver, ...);
}
```

This list must be manually updated whenever new computed properties are added. Consider using reflection or a simpler reset pattern.

---

### 6.3 N+1 Query Risks

| Location | Issue | Fix |
|---|---|---|
| `User::isOnboardingComplete()` | Lazy-loads `settings` relation | Use `$user->loadMissing('settings')` or eager load |
| `User::isFirstLogin()` | Same | Same |
| `UserQueryContext::fromUser()` | Calls `ResourceGroupAccessService` which reads config each time | Cache within request lifecycle |

---

## 7. UI/UX Design Improvements

### 7.1 Error State UI

**Current:** When the WorkStudio API fails, components silently show empty states (no regions, no assessments). Users cannot distinguish "no data" from "API error."

**Fix:** Add explicit error state UI to dashboard components:

```blade
@if($this->hasError)
    <div class="alert alert-error shadow-lg">
        <x-heroicon-o-exclamation-triangle class="size-6" />
        <div>
            <h3 class="font-bold">Unable to load data</h3>
            <p class="text-sm">{{ $this->errorMessage }}</p>
        </div>
        <button class="btn btn-sm btn-ghost" wire:click="$refresh">Retry</button>
    </div>
@endif
```

---

### 7.2 Loading Skeleton States

**Current:** Initial page load shows nothing until data arrives. The loading overlay only appears on refresh actions.

**Fix:** Add skeleton placeholders for initial render:

```blade
@if($this->systemMetrics === null)
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @for($i = 0; $i < 4; $i++)
            <div class="stat bg-base-100 rounded-box shadow p-3 animate-pulse">
                <div class="h-4 bg-base-300 rounded w-20 mb-2"></div>
                <div class="h-8 bg-base-300 rounded w-16"></div>
            </div>
        @endfor
    </div>
@else
    {{-- actual content --}}
@endif
```

---

### 7.3 Region Cards — Mobile Responsiveness

**Current:** Region cards use `grid-cols-2` even on small mobile screens, making cards cramped.

**Fix:**

```blade
{{-- Before --}}
<div class="grid grid-cols-2 gap-4">

{{-- After --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
```

---

### 7.4 Loading Overlay — Too Aggressive

**Current:** Sort and panel operations show a fixed full-viewport overlay with spinner:

```blade
<div wire:loading.flex wire:target="openPanel, closePanel, sort"
     class="fixed inset-0 z-40 items-center justify-center bg-base-100/50">
```

**Fix:** Use targeted loading indicators instead of full-page overlays:
- Sort: Show spinner in the column header being sorted
- Panel: Show skeleton in the panel area only
- Reserve full-page overlays for truly long operations (cache warming)

---

### 7.5 Disabled Header Features

**Current:** Search and Notifications buttons in the header are permanently disabled with "Coming Soon" tooltips.

**Fix:** Either:
- Remove them entirely until implemented (cleaner UX)
- Or move them to a less prominent position to avoid suggesting broken functionality

---

### 7.6 Inconsistent Flash/Notification Pattern

| Component | Pattern |
|---|---|
| CacheControls | Custom flash message with auto-dismiss (Alpine + 5s timeout) |
| WorkStudioSetup | Inline alert boxes |
| ActiveAssessments | Silent failure (no notification) |
| Overview | No error handling |

**Fix:** Implement a unified notification/toast system using Alpine.js store:

```js
// Alpine store for toasts
Alpine.store('toasts', {
    items: [],
    add(message, type = 'info', duration = 5000) { ... },
    remove(id) { ... }
});
```

Then dispatch from any Livewire component:

```php
$this->dispatch('toast', type: 'success', message: 'Cache cleared');
```

---

### 7.7 Accessibility Issues

| Issue | Location | Fix |
|---|---|---|
| Missing `aria-label` on refresh buttons | `overview.blade.php`, `active-assessments.blade.php` | Add `aria-label="Refresh data"` |
| No `role="status"` on loading spinners | Multiple components | Add `role="status" aria-label="Loading"` |
| Region cards use `wire:click` on `<div>` | `region-card.blade.php` | Add `role="button" tabindex="0"` and keyboard handler |
| Progress bars missing `aria-valuenow` | `region-card.blade.php` | DaisyUI `<progress>` already handles this, but verify |
| Color-only status indicators | `stat-card.blade.php`, `cache-controls.blade.php` | Add text labels alongside color indicators |
| Header page title truncated at 200px | `header.blade.php` line 63 | Use responsive text sizing instead of hard truncation |

---

### 7.8 Theme System — Dark Theme List Hardcoded in JS

**File:** `resources/js/alpine/stores.js` (line 85)

```js
const darkThemes = ['dark', 'synthwave', 'cyberpunk', 'dracula', 'night', 'forest', 'coffee'];
```

This list is hardcoded and must be manually kept in sync with the themes in `app.css` and `config/themes.php`.

**Fix:** Pass theme metadata from server-side config to the Alpine store via a Blade data attribute.

---

### 7.9 Sidebar Navigation — Missing Active State Depth

**Current:** The sidebar highlights the active route but doesn't support nested sub-navigation or grouping by feature area.

**Fix:** As more features are added, consider implementing expandable menu sections with sub-items. The current flat structure works for 2 items but won't scale.

---

### 7.10 View Toggle — No URL Persistence on Page Reload

**Current:** View mode (`cards` vs `table`) uses `#[Url]` which is good, but the toggle component itself doesn't indicate which mode is active with sufficient visual weight.

**Fix:** Use DaisyUI `btn-group` with active state:

```blade
<div class="join">
    <button class="btn btn-sm join-item {{ $current === 'cards' ? 'btn-active' : '' }}"
            wire:click="$set('viewMode', 'cards')">
        <x-heroicon-o-squares-2x2 class="size-4" />
    </button>
    <button class="btn btn-sm join-item {{ $current === 'table' ? 'btn-active' : '' }}"
            wire:click="$set('viewMode', 'table')">
        <x-heroicon-o-table-cells class="size-4" />
    </button>
</div>
```

---

### 7.11 Onboarding Flow — No Back Navigation

**Current:** The 2-step onboarding flow (Change Password → WorkStudio Setup) has no way to go back to step 1 from step 2.

**Fix:** Add a "Back" link on the WorkStudio Setup page:

```blade
<div class="flex items-center justify-between text-sm text-base-content/60">
    <a href="{{ route('onboarding.password') }}" wire:navigate class="link">Back to Step 1</a>
    <p>Step 2 of 2</p>
</div>
```

---

### 7.12 Cache Controls — Missing Operation Feedback

**Current:** Individual dataset refresh buttons show a spinner but don't confirm success/failure per-row. Bulk operations use a global flash message.

**Fix:** Add per-row status indicators that briefly flash green/red after an operation completes:

```blade
<td>
    <button wire:click="refreshDataset('{{ $dataset['key'] }}')" class="btn btn-xs btn-ghost">
        <x-heroicon-o-arrow-path class="size-4"
            wire:loading.class="animate-spin"
            wire:target="refreshDataset('{{ $dataset['key'] }}')" />
    </button>
</td>
```

---

## 8. Prioritized Action Plan

### P0 — Must Fix Before Production

| # | Item | Files | Effort |
|---|---|---|---|
| 1 | Remove hardcoded credentials | `GetQueryService.php` | 15 min |
| 2 | Protect/remove unauthed routes | `workstudioAPI.php` | 30 min |
| 3 | Fix SQL injection in `getAllByJobGuid()` | `AssessmentQueries.php` | 15 min |
| 4 | Enable SSL verification | `WorkStudioServiceProvider.php` | 5 min |
| 5 | Remove debug code (`dump()`, `queryAll()`, `ExecutionTimer` usage) | Multiple | 20 min |
| 6 | Fix `hasRole()` missing method | `AppServiceProvider.php` + `User.php` | 15 min |

### P1 — High Priority (Next Sprint)

| # | Item | Files | Effort |
|---|---|---|---|
| 7 | Delete `_backup/` directory | `app/Livewire/_backup/` | 5 min |
| 8 | Add error states to dashboard components | `Overview.php`, `ActiveAssessments.php`, views | 2 hr |
| 9 | Extract view business logic to computed properties | `overview.blade.php`, `region-card.blade.php` | 1 hr |
| 10 | Fix broken route closures (missing UserQueryContext) | `workstudioAPI.php` | 30 min |
| 11 | Add loading skeletons | Dashboard views | 1 hr |
| 12 | Make `$sqlState` private | `GetQueryService.php` | 5 min |
| 13 | Fix wrong return types | `ResourceGroupAccessService.php`, `GetQueryService.php` | 10 min |

### P2 — Medium Priority (Planned Work)

| # | Item | Files | Effort |
|---|---|---|---|
| 14 | Create `CachedQueryServiceInterface` | New file + provider | 30 min |
| 15 | Extract AssessmentQueries into focused classes | `AssessmentsDx/Queries/` | 4 hr |
| 16 | Create OnboardingService | New file + refactor components | 2 hr |
| 17 | Implement unified toast/notification system | Alpine store + Blade component | 2 hr |
| 18 | Extract magic strings to enums/config | `AssessmentQueries.php`, config | 1 hr |
| 19 | Add accessibility attributes | Multiple blade files | 1 hr |
| 20 | Improve mobile responsiveness (region cards, overlays) | Multiple blade files | 1 hr |
| 21 | Replace dynamic method invocation in CacheControls | `CacheControls.php`, `CachedQueryService.php` | 1 hr |
| 22 | Make ApiCredentialManager updates transactional | `ApiCredentialManager.php` | 30 min |

### P3 — Low Priority (Backlog)

| # | Item | Files | Effort |
|---|---|---|---|
| 23 | Remove disabled Search/Notifications from header | `header.blade.php` | 5 min |
| 24 | Add back navigation to onboarding | `work-studio-setup.blade.php` | 10 min |
| 25 | Delete unused `register.blade.php` | View file | 5 min |
| 26 | Clean up `settings.php` route file | `routes/settings.php` | 5 min |
| 27 | Delete `welcome.blade.php` | View file | 5 min |
| 28 | Sync dark theme list from config to JS | `stores.js` + `config/themes.php` | 30 min |
| 29 | Remove `$currentUserId` from `WorkStudioApiService` | Service file | 5 min |
| 30 | Add eager loading for User->settings in middleware | `EnsurePasswordChanged.php` | 10 min |
| 31 | Fix app-logo branding inconsistency | `app-logo.blade.php` | 5 min |
| 32 | Delete dead `dashboard.blade.php` placeholder | View file | 5 min |
| 33 | Fix dynamic Tailwind color classes | `stat-card.blade.php`, `metric-pill.blade.php` | 30 min |
| 34 | Add aria-labels to 2FA code inputs | `two-factor-challenge.blade.php` | 10 min |
| 35 | Consolidate duplicate auth layouts | `auth/card.blade.php`, `auth/simple.blade.php` | 20 min |
| 36 | Add i18n `__()` wrappers to remaining views | Multiple dashboard/sidebar views | 1 hr |

---

## 9. Additional Findings — UI/UX Deep Dive

These findings surfaced during the full Blade view and frontend asset analysis.

### 9.1 App Logo Branding Inconsistency

> **COMPLETED** (2026-02-05) — Branch `cleanup/dead-code-removal`. Changed "Laravel Starter Kit" to "WS-Tracker".

**File:** `resources/views/components/app-logo.blade.php`

The `app-logo` component still displays **"Laravel Starter Kit"** from the original scaffold, while the sidebar correctly shows **"WS-Tracker"**. This component is used in auth layouts (login, register, etc.), so users see mismatched branding.

**Fix:** Update the text in `app-logo.blade.php` to "WS-Tracker" to match the sidebar branding.

---

### 9.2 Dead Dashboard Placeholder View

**File:** `resources/views/dashboard.blade.php`

This file contains a "Dashboard Coming Soon" placeholder with a centered icon and message. However, the actual `/dashboard` route maps directly to the `Livewire\Dashboard\Overview` component, which uses its own layout attribute (`#[Layout('components.layout.app-shell')]`). This view file is never rendered.

**Fix:** Delete `dashboard.blade.php` — it serves no purpose and could cause confusion.

---

### 9.3 Dynamic Tailwind Color Classes May Not Compile

**Files:** `resources/views/components/ui/stat-card.blade.php`, `resources/views/components/ui/metric-pill.blade.php`

These components use dynamic class interpolation:

```blade
<div class="stat-figure text-{{ $color }}">
<div class="stat-value {{ $valueSize[$size] ?? $valueSize['md'] }} text-{{ $color }}">
```

Tailwind CSS v4's JIT compiler only detects classes that appear as complete strings in source files. Dynamic interpolation like `text-{{ $color }}` will produce classes like `text-primary` or `text-success` at runtime, but the compiler may not include them in the output CSS if they don't appear as literal strings elsewhere.

**Fix:** Use a class map with complete Tailwind class strings:

```php
@php
    $colorClasses = [
        'primary' => 'text-primary',
        'secondary' => 'text-secondary',
        'success' => 'text-success',
        'warning' => 'text-warning',
        'error' => 'text-error',
        'info' => 'text-info',
    ];
    $textColor = $colorClasses[$color] ?? 'text-primary';
@endphp
```

> **Note:** This may already work because DaisyUI's `@source` directive scans Blade views and these color names appear in other contexts. However, it's fragile — a safelist approach is more reliable.

---

### 9.4 Two-Factor Authentication — Missing Accessibility Labels

**File:** `resources/views/livewire/auth/two-factor-challenge.blade.php`

The 2FA code entry UI uses 6 individual `<input>` fields with auto-advancing focus (excellent UX), but none have `aria-label` attributes. Screen readers cannot identify which digit position each input represents.

**Fix:**

```blade
@for ($i = 0; $i < 6; $i++)
    <input
        type="text"
        maxlength="1"
        inputmode="numeric"
        aria-label="Digit {{ $i + 1 }} of 6"
        ...
    />
@endfor
```

---

### 9.5 Duplicate Auth Layouts

**Files:**
- `resources/views/layouts/auth/card.blade.php`
- `resources/views/layouts/auth/simple.blade.php`

These two auth layout variants are nearly identical — both center a card in the viewport with minimal differences in spacing. Check which views reference each, and consider consolidating into a single layout with a `compact` prop for the spacing variation.

---

### 9.6 Internationalization (i18n) Gaps

Many user-facing strings in the application views are **not** wrapped in Laravel's `__()` translation helper. While auth views generally use `__()` correctly, the following areas have hardcoded English strings:

**Dashboard views:**
- "Regional Overview", "Circuit assessment progress across all regions"
- "Total Assessments", "Total Miles", "Overall Progress", "Active Planners"
- "No Regions Found", "There are no active regions to display."

**Sidebar navigation:**
- "Overview", "Dashboard", "Data Management", "Cache Controls", "Collapse"

**Cache controls:**
- "Cache Controls", "Manage WorkStudio API data cache"
- All stat card labels, table headers, and button text

**Active assessments:**
- "Active Assessments", "Oldest checked-out circuits", "No Active Assessments"

**Fix:** Wrap all user-facing strings in `__()` helper for future localization support:

```blade
{{-- Before --}}
<h1 class="text-2xl font-bold">Regional Overview</h1>

{{-- After --}}
<h1 class="text-2xl font-bold">{{ __('Regional Overview') }}</h1>
```

---

*Generated by BMad Master — WS-TrackerV1 Code Review & UI/UX Audit*

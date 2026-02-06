# WS-TrackerV1 — Master TODO Tracker

> **Project:** WS-TrackerV1
> **Created:** 2026-02-05
> **Last Updated:** 2026-02-06

---

## How to Use This File

- **Status:** `pending` | `in-progress` | `completed` | `blocked`
- **Priority:** `P0` (critical/blocking) | `P1` (high) | `P2` (medium) | `P3` (low/backlog)
- **Plan column:** Links to plan file if one exists, or `NEEDS PLAN` flag
- Items with plans are listed first within each category, followed by items needing plans
- When a TODO is completed, fill in the **Completion Record** fields in its detail section
- Dependencies are listed by TODO ID — a task cannot start until its dependencies are `completed`

---

## Quick Stats

| Category | Total | Pending | In Progress | Completed | Needs Plan |
|----------|-------|---------|-------------|-----------|------------|
| Security | 6 | 3 | 0 | 3 | 0 |
| Cleanup | 9 | 1 | 0 | 8 | 0 |
| Refactor | 11 | 10 | 0 | 1 | 2 |
| Feature | 8 | 6 | 0 | 1 | 4 |
| UI/UX | 12 | 11 | 0 | 1 | 1 |
| Testing | 10 | 10 | 0 | 0 | 3 |
| Performance | 7 | 7 | 0 | 0 | 0 |
| **Totals** | **63** | **48** | **0** | **14** | **10** |

---

## Overview — All TODOs by Priority

### P0 — Must Fix Before Production

| ID | Title | Category | Status | Depends On | Plan |
|----|-------|----------|--------|------------|------|
| SEC-001 | Remove hardcoded credentials | Security | pending | — | [CODE-REVIEW.md #2.1](CODE-REVIEW.md#21-hardcoded-credentials-in-source-code) |
| SEC-002 | Protect/remove unauthed API routes | Security | **completed** | — | [CODE-REVIEW.md #2.3](CODE-REVIEW.md#23-unprotected-apitest-routes) |
| SEC-003 | Fix SQL injection in getAllByJobGuid | Security | pending | — | [CODE-REVIEW.md #2.2](CODE-REVIEW.md#22-sql-injection-vulnerability) |
| SEC-004 | Enable SSL verification | Security | pending | — | [CODE-REVIEW.md #2.4](CODE-REVIEW.md#24-ssl-verification-disabled) |
| SEC-005 | Fix hasRole() missing method | Security | **completed** | — | [CODE-REVIEW.md #5.6](CODE-REVIEW.md#56-appserviceprovider--missing-hasrole-method) |
| CLN-001 | Remove debug code (dump, queryAll, etc.) | Cleanup | **completed** | — | [CODE-REVIEW.md #5.7](CODE-REVIEW.md#57-debug-code-in-production) |

### P1 — High Priority (Next Sprint)

| ID | Title | Category | Status | Depends On | Plan |
|----|-------|----------|--------|------------|------|
| CLN-002 | Delete `_backup/` directory | Cleanup | **completed** | — | [CODE-REVIEW.md #3.1](CODE-REVIEW.md#31-unused-files-safe-to-delete) |
| CLN-009 | Fix broken route closures (missing UserQueryContext) | Cleanup | pending | SEC-002 | [CODE-REVIEW.md #5.8](CODE-REVIEW.md#58-broken-route-closures) |
| SEC-006 | ~~Make $sqlState private~~ Removed entirely | Security | **completed** | — | [CODE-REVIEW.md #2.5](CODE-REVIEW.md#25-public-sql-state-exposure) |
| REF-001 | Extract view business logic to computed properties | Refactor | pending | — | [CODE-REVIEW.md #4.1](CODE-REVIEW.md#41-overview-blade-template--progress-calculation) |
| REF-008 | Fix wrong return types | Refactor | pending | — | [CODE-REVIEW.md #5.3](CODE-REVIEW.md#53-wrong-return-types) |
| UI-001 | Add error states to dashboard components | UI/UX | pending | — | [CODE-REVIEW.md #7.1](CODE-REVIEW.md#71-error-state-ui) |
| UI-002 | Add loading skeletons | UI/UX | pending | — | [CODE-REVIEW.md #7.2](CODE-REVIEW.md#72-loading-skeleton-states) |

### P2 — Medium Priority (Planned Work)

| ID | Title | Category | Status | Depends On | Plan |
|----|-------|----------|--------|------------|------|
| REF-002 | Create CachedQueryServiceInterface | Refactor | pending | — | [CODE-REVIEW.md #5.4](CODE-REVIEW.md#54-missing-interface-for-cachedqueryservice) |
| REF-003 | Extract AssessmentQueries into focused classes | Refactor | pending | — | [CODE-REVIEW.md #4.4](CODE-REVIEW.md#44-assessmentqueries--monolithic-650-line-class) |
| REF-004 | Create OnboardingService | Refactor | pending | — | [CODE-REVIEW.md #4.7](CODE-REVIEW.md#47-onboarding-settings--assumption-without-null-check) |
| REF-005 | Extract magic strings to enums/config | Refactor | pending | — | [CODE-REVIEW.md #4.5](CODE-REVIEW.md#45-magic-strings--status-codes--filter-constants) |
| REF-006 | Replace dynamic method invocation in CacheControls | Refactor | pending | — | [CODE-REVIEW.md #4.3](CODE-REVIEW.md#43-cachecontrols--dynamic-method-invocation) |
| REF-007 | Make ApiCredentialManager updates transactional | Refactor | pending | — | [CODE-REVIEW.md #5.5](CODE-REVIEW.md#55-apicredentialmanager--non-transactional-double-updates) |
| REF-010 | Extract duplicate SQL filters to shared helpers | Refactor | pending | REF-003 | [CODE-REVIEW.md #4.6](CODE-REVIEW.md#46-duplicate-sql-filters) |
| REF-011 | Domain-driven folder restructure | Refactor | **completed** | — | — |
| FT-001 | Planner Daily Activity System | Feature | pending | REF-003 | [plans/](plans/) — see detail |
| FT-006 | Unified toast/notification system | Feature | pending | — | NEEDS PLAN |
| FT-007 | Historical Assessment Archival & Analytics | Feature | pending | — | [tech-spec](specs/tech-spec-historical-assessment-archival.md) |
| FT-008 | Query Explorer admin tool | Feature | **completed** | — | — |
| UI-003 | Improve mobile responsiveness | UI/UX | pending | — | [CODE-REVIEW.md #7.3](CODE-REVIEW.md#73-region-cards--mobile-responsiveness) |
| UI-004 | Add accessibility attributes | UI/UX | pending | — | [CODE-REVIEW.md #7.7](CODE-REVIEW.md#77-accessibility-issues) |
| UI-011 | Add eager loading for User->settings in middleware | UI/UX | pending | — | [CODE-REVIEW.md #6.3](CODE-REVIEW.md#63-n1-query-risks) |

### P3 — Backlog

| ID | Title | Category | Status | Depends On | Plan |
|----|-------|----------|--------|------------|------|
| CLN-003 | Delete unused register.blade.php | Cleanup | **completed** | — | — |
| CLN-004 | Delete unused welcome.blade.php | Cleanup | **completed** | — | — |
| CLN-005 | Delete dead dashboard.blade.php placeholder | Cleanup | **completed** | — | — |
| CLN-006 | Clean up settings.php route file | Cleanup | **completed** | — | — |
| CLN-007 | Remove disabled Search/Notifications from header | Cleanup | **completed** | — | — |
| CLN-008 | Remove $currentUserId from WorkStudioApiService | Cleanup | **completed** | — | — |
| REF-009 | Consolidate duplicate auth layouts | Refactor | pending | — | NEEDS PLAN |
| FT-002 | Kanban Board | Feature | pending | FT-001, UI-001 | NEEDS PLAN |
| FT-003 | Analytics Dashboard enhancement | Feature | pending | FT-001 | NEEDS PLAN |
| FT-004 | Admin Tools | Feature | pending | FT-002, FT-003 | NEEDS PLAN |
| FT-005 | Sync System | Feature | pending | FT-001 | NEEDS PLAN |
| UI-005 | Add back navigation to onboarding | UI/UX | pending | — | — |
| UI-006 | Sync dark theme list from config to JS | UI/UX | pending | — | — |
| UI-007 | Fix app-logo branding inconsistency | UI/UX | **completed** | — | — |
| UI-008 | Fix dynamic Tailwind color classes | UI/UX | pending | — | — |
| UI-009 | Add aria-labels to 2FA code inputs | UI/UX | pending | — | — |
| UI-010 | Add i18n __() wrappers to remaining views | UI/UX | pending | — | — |
| TST-001 | GetQueryService unit tests (mocked HTTP) | Testing | pending | — | — |
| TST-002 | Feature test: API routes return JSON | Testing | pending | SEC-002, CLN-009 | — |
| TST-003 | Livewire component tests | Testing | pending | — | NEEDS PLAN |
| TST-004 | Dusk: Login flow end-to-end | Testing | pending | — | — |
| TST-005 | Dusk: Dashboard loads correctly | Testing | pending | UI-001, UI-002 | — |
| TST-006 | Dusk: Key user workflows | Testing | pending | TST-004, TST-005 | NEEDS PLAN |
| TST-007 | Code coverage report setup | Testing | pending | TST-001, TST-003 | — |
| TST-008 | Add Dusk to CI pipeline | Testing | pending | TST-004 | NEEDS PLAN |
| TST-009 | PlannerActivityService tests | Testing | pending | FT-001 | [task-4](plans/task-4-sql-integration-plan.md#part-3-testing-plan) |
| TST-010 | PlannerActivity API integration tests | Testing | pending | FT-001, TST-009 | [task-4](plans/task-4-sql-integration-plan.md#part-3-testing-plan) |
| PERF-001 | Optimize Q1: systemWideDataQuery | Performance | pending | — | [task-5](plans/task-5-query-optimization-plan.md#q1-systemwidedataquery) |
| PERF-002 | Optimize Q2: groupedByRegionDataQuery | Performance | pending | PERF-001 | [task-5](plans/task-5-query-optimization-plan.md#q2q3-regional-and-circuit-data-queries) |
| PERF-003 | Optimize Q3: groupedByCircuitDataQuery | Performance | pending | PERF-002 | [task-5](plans/task-5-query-optimization-plan.md#q2q3-regional-and-circuit-data-queries) |
| PERF-004 | Optimize Q4: getAllAssessmentsDailyActivities | Performance | pending | — | [task-5](plans/task-5-query-optimization-plan.md#q4-getallassessmentsdailyactivities) |
| PERF-005 | Optimize Q5: getAllByJobGuid (CTE refactor) | Performance | pending | — | [task-5](plans/task-5-query-optimization-plan.md#q5-getallbyjobguid) |
| PERF-006 | Optimize Q7: dailyRecordsQuery fragment | Performance | pending | — | [task-5](plans/task-5-query-optimization-plan.md#q7-dailyrecordsquery-fragment) |
| PERF-007 | Database index recommendations | Performance | pending | PERF-001 | [task-5](plans/task-5-query-optimization-plan.md#43-index-recommendations) |

---

## Detailed TODO Entries

---

### Security

---

#### SEC-001: Remove Hardcoded Credentials from GetQueryService

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Status** | pending |
| **Category** | Security |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #2.1](CODE-REVIEW.md#21-hardcoded-credentials-in-source-code) |
| **Source** | Code Review #1 |
| **Est. Effort** | 15 min |
| **Files** | `app/Services/WorkStudio/Services/GetQueryService.php` |

**Description:**
Lines 39 and 49 contain plaintext credentials (`ASPLUNDH\cnewcombe` / `chrism`) for both the payload `DBParameters` and `Http::withBasicAuth()`. The `$credentials` variable from `ApiCredentialManager` is already fetched on line 33 but never used. Replace hardcoded values with `$credentials['username']` and `$credentials['password']`.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### SEC-002: Protect/Remove Unauthed API Routes

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Status** | **completed** |
| **Category** | Security |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #2.3](CODE-REVIEW.md#23-unprotected-apitest-routes) |
| **Source** | Code Review #2 |
| **Est. Effort** | 30 min |
| **Files** | `routes/workstudioAPI.php` |

**Description:**
7 routes expose raw WorkStudio data without authentication: `GET /dashboard/test`, `/assessment-jobguids`, `/system-wide-metrics`, `/regional-metrics`, `/daily-activities/all-assessments`, `/allByJobGUID`, `/field-lookup/{table}/{field}`. Remove the test route entirely and wrap all API routes in `auth` + `verified` + `onboarding` middleware group.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `routes/workstudioAPI.php` |
| **Notes** | Removed `/dashboard/test` and `/allByJobGUID` routes entirely. Wrapped remaining 5 routes in `auth` middleware group. Added `UserQueryContext::fromUser()` to fix missing context parameter. Left `TODO: CLN-009` for future full refactor to controllers. |

</details>

---

#### SEC-003: Fix SQL Injection in getAllByJobGuid

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Status** | pending |
| **Category** | Security |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #2.2](CODE-REVIEW.md#22-sql-injection-vulnerability) |
| **Source** | Code Review #3 |
| **Est. Effort** | 15 min |
| **Files** | `app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php` |

**Description:**
`getAllByJobGuid()` directly interpolates `$jobGuid` into SQL without validation. Add strict GUID regex validation (`/^\{[0-9A-Fa-f-]+\}$/`) before interpolation. Also audit `WSHelpers::toSqlInClause()` for similar risks.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### SEC-004: Enable SSL Verification

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Status** | pending |
| **Category** | Security |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #2.4](CODE-REVIEW.md#24-ssl-verification-disabled) |
| **Source** | Code Review #4 |
| **Est. Effort** | 5 min |
| **Files** | `app/Providers/WorkStudioServiceProvider.php` |

**Description:**
The `Http::macro('workstudio')` sets `['verify' => false]` which disables SSL certificate verification. Change to `['verify' => config('workstudio.verify_ssl', true)]` and add the config key.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### SEC-005: Fix hasRole() Missing Method

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Status** | **completed** |
| **Category** | Security |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #5.6](CODE-REVIEW.md#56-appserviceprovider--missing-hasrole-method) |
| **Source** | Code Review #6 |
| **Est. Effort** | 15 min |
| **Files** | `app/Providers/AppServiceProvider.php`, `app/Models/User.php` |

**Description:**
`AppServiceProvider` calls `$user->hasRole('admin')` for Pulse dashboard access, but the User model doesn't include the `HasRoles` trait from Spatie Permission. This will throw `BadMethodCallException`. Either add the trait or use a simpler Gate check.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-06 |
| **Time Elapsed** | Part of feature/permissions-system branch |
| **Files Changed** | `app/Models/User.php`, `app/Providers/AppServiceProvider.php` |
| **Notes** | Added `HasRoles` trait to User model. Replaced `$user->hasRole('admin')` with `$user->hasPermissionTo('access-pulse')` in Pulse gate. Full Spatie Permission v6 integration with 5 roles, 7 permissions, route guards, and sidebar gating. |

</details>

---

#### SEC-006: Make $sqlState Private (REMOVED ENTIRELY)

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Status** | **completed** |
| **Category** | Security |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #2.5](CODE-REVIEW.md#25-public-sql-state-exposure) |
| **Source** | Code Review #12 |
| **Est. Effort** | 5 min |
| **Files** | `app/Services/WorkStudio/Services/GetQueryService.php` |

**Description:**
`public $sqlState` stores the last SQL query executed and is publicly accessible. Change to `private string $lastSql = ''`.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `app/Services/WorkStudio/Services/GetQueryService.php` |
| **Notes** | Property and assignment removed entirely rather than made private — it was set but never read anywhere. |

</details>

---

### Cleanup

---

#### CLN-001: Remove Debug Code

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Status** | **completed** |
| **Category** | Cleanup |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #5.7](CODE-REVIEW.md#57-debug-code-in-production) |
| **Source** | Code Review #5 |
| **Est. Effort** | 20 min |
| **Files** | `GetQueryService.php`, `workstudioAPI.php`, `ExecutionTimer.php` |

**Description:**
Remove `dump()` calls from `queryAll()` (lines 243-245), `logger()->info("Transfer time")` on every API call (line 54), conditional `dump($data)` in route closures, and `echo` in `ExecutionTimer.php`. Either delete the `queryAll()` method entirely or convert to proper logging.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `GetQueryService.php`, `workstudioAPI.php`, `ExecutionTimer.php` (deleted) |
| **Notes** | Deleted `queryAll()` and `getAll()` methods entirely. Deleted `ExecutionTimer.php`. Removed all `dump()` calls from route closures. Transfer time logger left in `executeQuery()` (useful for monitoring). |

</details>

---

#### CLN-002: Delete _backup/ Directory

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Status** | **completed** |
| **Category** | Cleanup |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Code Review #7 |
| **Est. Effort** | 5 min |
| **Files** | `app/Livewire/_backup/` (entire directory) |

**Description:**
Contains deprecated TwoFactor components. Fortify handles 2FA natively. Safe to delete.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `app/Livewire/_backup/` (entire directory deleted) |
| **Notes** | Deleted TwoFactor.php, TwoFactor/RecoveryCodes.php, and parent directory. |

</details>

---

#### CLN-003: Delete Unused register.blade.php

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | **completed** |
| **Category** | Cleanup |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Code Review #25 |
| **Est. Effort** | 5 min |
| **Files** | `resources/views/livewire/auth/register.blade.php` |

**Description:**
Registration is disabled in `config/fortify.php`. This view is unreachable. Safe to delete.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `resources/views/livewire/auth/register.blade.php` (deleted), `FortifyServiceProvider.php` (removed registerView) |
| **Notes** | Also removed CreateNewUser.php and ProfileValidationRules.php as part of registration scaffolding removal. |

</details>

---

#### CLN-004: Delete Unused welcome.blade.php

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | **completed** |
| **Category** | Cleanup |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Code Review #27 |
| **Est. Effort** | 5 min |
| **Files** | `resources/views/welcome.blade.php` |

**Description:**
Root `/` immediately redirects to login. Welcome page is never served.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `resources/views/welcome.blade.php` (deleted) |
| **Notes** | 79KB Laravel default welcome page removed. |

</details>

---

#### CLN-005: Delete Dead dashboard.blade.php Placeholder

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | **completed** |
| **Category** | Cleanup |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Code Review #32 |
| **Est. Effort** | 5 min |
| **Files** | `resources/views/dashboard.blade.php` |

**Description:**
Contains "Coming Soon" stub, but `/dashboard` maps to the Livewire `Overview` component which uses its own layout. Never rendered.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `resources/views/dashboard.blade.php` (deleted) |
| **Notes** | — |

</details>

---

#### CLN-006: Clean Up settings.php Route File

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | **completed** |
| **Category** | Cleanup |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Code Review #26 |
| **Est. Effort** | 5 min |
| **Files** | `routes/settings.php` |

**Description:**
All settings routes redirect to dashboard. Either delete the file and remove the `require` in `web.php`, or leave as placeholder with a clear comment for future rebuild.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `routes/settings.php` (deleted), `routes/web.php` (removed require) |
| **Notes** | File deleted entirely. Settings will be rebuilt as part of future feature work. |

</details>

---

#### CLN-007: Remove Disabled Search/Notifications from Header

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | **completed** |
| **Category** | Cleanup |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Code Review #23 |
| **Est. Effort** | 5 min |
| **Files** | `resources/views/components/layout/header.blade.php` |

**Description:**
Search and Notifications buttons are permanently disabled with "Coming Soon" tooltips. Remove until implemented.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `resources/views/components/layout/header.blade.php` |
| **Notes** | Removed Search and Notifications placeholder buttons with their tooltip wrappers. |

</details>

---

#### CLN-008: Remove $currentUserId from WorkStudioApiService

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | **completed** |
| **Category** | Cleanup |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Code Review #29 |
| **Est. Effort** | 5 min |
| **Files** | `app/Services/WorkStudio/WorkStudioApiService.php` |

**Description:**
`private ?int $currentUserId = null` is declared but never set or read.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `app/Services/WorkStudio/WorkStudioApiService.php` |
| **Notes** | Removed property. Updated `getCurrentCredentialsInfo()` to pass `null` explicitly (was always null). |

</details>

---

#### CLN-009: Fix Broken Route Closures

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Status** | pending |
| **Category** | Cleanup |
| **Depends On** | SEC-002 |
| **Plan** | [CODE-REVIEW.md #5.8](CODE-REVIEW.md#58-broken-route-closures) |
| **Source** | Code Review #10 |
| **Est. Effort** | 30 min |
| **Files** | `routes/workstudioAPI.php` |

**Description:**
All closure routes inject `GetQueryService` directly and call methods without passing a `UserQueryContext` — these will throw `TypeError`. Either delete the debug routes (preferred) or fix them to build `UserQueryContext::fromUser(auth()->user())`.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

### Refactor

---

#### REF-001: Extract View Business Logic to Computed Properties

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #4.1-4.2](CODE-REVIEW.md#41-overview-blade-template--progress-calculation) |
| **Source** | Code Review #9 |
| **Est. Effort** | 1 hr |
| **Files** | `overview.blade.php`, `region-card.blade.php`, `Overview.php` |

**Description:**
Progress calculations (`$completedMiles / $totalMiles * 100`) are done in `@php` blocks inside Blade templates. Extract to `#[Computed]` properties in `Overview.php` and/or a `RegionMetric` value object.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-002: Create CachedQueryServiceInterface

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #5.4](CODE-REVIEW.md#54-missing-interface-for-cachedqueryservice) |
| **Source** | Code Review #14 |
| **Est. Effort** | 30 min |
| **Files** | New: `app/Services/WorkStudio/Shared/Contracts/CachedQueryServiceInterface.php`, `WorkStudioServiceProvider.php` |

**Description:**
Per project rules: "Services must implement interfaces." `CachedQueryService` is bound as singleton but has no interface. Create one and update the provider binding.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-003: Extract AssessmentQueries into Focused Classes

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #4.4](CODE-REVIEW.md#44-assessmentqueries--monolithic-650-line-class) |
| **Source** | Code Review #15 |
| **Est. Effort** | 4 hr |
| **Files** | `app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php` → split into `SystemMetricsQuery`, `RegionalMetricsQuery`, `CircuitDetailsQuery`, `DailyActivitiesQuery`, `ActiveAssessmentsQuery`, `FieldLookupQuery` |

**Description:**
650+ line monolithic class. Break into focused query builders sharing context via a parent class or composition.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-004: Create OnboardingService

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #4.7](CODE-REVIEW.md#47-onboarding-settings--assumption-without-null-check) |
| **Source** | Code Review #16 |
| **Est. Effort** | 2 hr |
| **Files** | New: `app/Services/OnboardingService.php`, refactor `ChangePassword.php`, `WorkStudioSetup.php` |

**Description:**
Both onboarding components assume `$user->settings` exists and contain business logic. Extract to a dedicated service with `markPasswordChanged()`, `markWorkStudioSetup()`, `isComplete()`.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-005: Extract Magic Strings to Enums/Config

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #4.5](CODE-REVIEW.md#45-magic-strings--status-codes--filter-constants) |
| **Source** | Code Review #18 |
| **Est. Effort** | 1 hr |
| **Files** | New: `app/Enums/AssessmentStatus.php`, update `config/ws_assessment_query.php`, `AssessmentQueries.php`, `SqlFragmentHelpers.php` |

**Description:**
Strings like `'ACTIV'`, `'QC'`, `'REWRK'`, `'CLOSE'`, `'Reactive'`, `'Storm Follow Up'` scattered across SQL generation. Create enum and expand config.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-006: Replace Dynamic Method Invocation in CacheControls

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #4.3](CODE-REVIEW.md#43-cachecontrols--dynamic-method-invocation) |
| **Source** | Code Review #21 |
| **Est. Effort** | 1 hr |
| **Files** | `app/Livewire/DataManagement/CacheControls.php`, `app/Services/WorkStudio/Services/CachedQueryService.php` |

**Description:**
`refreshDataset()` calls `$service->{$method}($context)` dynamically. Replace with a `match()` expression or strategy pattern in `CachedQueryService`.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-007: Make ApiCredentialManager Updates Transactional

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #5.5](CODE-REVIEW.md#55-apicredentialmanager--non-transactional-double-updates) |
| **Source** | Code Review #22 |
| **Est. Effort** | 30 min |
| **Files** | `app/Services/WorkStudio/Managers/ApiCredentialManager.php` |

**Description:**
`markSuccess()` and `markFailed()` update `UserWsCredential` and `User` models separately. Wrap in `DB::transaction()`.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-008: Fix Wrong Return Types

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #5.3](CODE-REVIEW.md#53-wrong-return-types) |
| **Source** | Code Review #13 |
| **Est. Effort** | 10 min |
| **Files** | `ResourceGroupAccessService.php`, `GetQueryService.php` |

**Description:**
`getRegionsForRole()` declares `array|string` but always returns `array`. `executeAndHandle()` declares `Collection|array` but always returns `Collection`. Fix to accurate types.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-009: Consolidate Duplicate Auth Layouts

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | NEEDS PLAN |
| **Source** | Code Review #35 |
| **Est. Effort** | 20 min |
| **Files** | `resources/views/layouts/auth/card.blade.php`, `resources/views/layouts/auth/simple.blade.php` |

**Description:**
Two nearly identical auth layout variants. Investigate which views use which, consider consolidating into one with a `compact` prop.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-010: Extract Duplicate SQL Filters to Shared Helpers

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Refactor |
| **Depends On** | REF-003 |
| **Plan** | [CODE-REVIEW.md #4.6](CODE-REVIEW.md#46-duplicate-sql-filters) |
| **Source** | Code Review |
| **Est. Effort** | 30 min |
| **Files** | `SqlFragmentHelpers.php`, `AssessmentQueries.php` |

**Description:**
The same cycle type exclusion filter (`VEGJOB.CYCLETYPE NOT IN (...)`) appears in 4+ query methods. Extract to a shared `excludedCycleTypesClause()` method.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### REF-011: Domain-Driven Folder Restructure

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | **completed** |
| **Category** | Refactor |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Architecture discussion |
| **Est. Effort** | 1 hr |
| **Files** | 32 files changed — 15 moved via `git mv`, 24 files updated (imports) |

**Description:**
Reorganized `app/Services/WorkStudio/` from flat technical layers into domain-driven namespaces: `Client/` (HTTP infrastructure), `Shared/` (cross-domain utilities, cache, value objects, exceptions), `Assessments/` (assessment-specific queries). Removed old directories: `Services/`, `Managers/`, `Contracts/`, `ValueObjects/`, `Helpers/`, `Exceptions/`, `AssessmentsDx/`. Updated 53 import statements across 24 files. Prepares for future `WorkJobs/` and `Planner/` domain modules.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-06 |
| **Time Elapsed** | ~45 min |
| **Files Changed** | 32 files (15 renamed, 17 import updates) |
| **Notes** | Branch `refactor/domain-folder-structure`, merged to main (c547a65). Git preserved full rename history (84-99% similarity). All 107 tests pass. |

</details>

---

### Features

---

#### FT-001: Planner Daily Activity System

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Feature |
| **Depends On** | REF-003 |
| **Plan** | Multiple — see below |
| **Source** | Rebuild plan, planner-activity-rules, task-4 |
| **Est. Effort** | 20+ hr |

**Plan Documents:**
- Business Rules: [`docs/specs/planner-activity-rules.md`](specs/planner-activity-rules.md)
- Implementation Prompt: [`docs/archive/prompt-planner-daily-activity-query.md`](archive/prompt-planner-daily-activity-query.md)
- SQL Integration Plan: [`docs/plans/task-4-sql-integration-plan.md`](plans/task-4-sql-integration-plan.md)
- Data Flow Diagram: [`docs/diagrams/planner-activity-dataflow.excalidraw`](diagrams/planner-activity-dataflow.excalidraw)

**Description:**
Full planner daily activity tracking system with "First Unit Wins" footage attribution logic. Includes:

| Sub-task | Files | Status |
|----------|-------|--------|
| Create `PlannerActivityQueries.php` | `app/Services/WorkStudio/AssessmentsDx/Queries/` | pending |
| Create `PlannerActivityService.php` | `app/Services/WorkStudio/Services/` | pending |
| Create `PlannerActivityController.php` | `app/Http/Controllers/Api/WorkStudio/` | pending |
| Add config entries (non_work_units, etc.) | `config/ws_assessment_query.php` | pending |
| Create queue job for chunked requests | `app/Jobs/` | pending |
| Add API routes | `routes/` | pending |
| Livewire UI component | `app/Livewire/`, `resources/views/` | pending |

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### FT-002: Kanban Board

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Feature |
| **Depends On** | FT-001, UI-001 |
| **Plan** | NEEDS PLAN |
| **Source** | [Rebuild Plan (archived)](archive/WS-Tracker-Rebuild-Plan.md) |
| **Est. Effort** | TBD |

**Description:**
Circuit Kanban board for visual workflow management. Adapt patterns from old WS-Tracker `CircuitKanban` component. Requires Livewire 4 + DaisyUI implementation.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### FT-003: Analytics Dashboard Enhancement

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Feature |
| **Depends On** | FT-001 |
| **Plan** | NEEDS PLAN |
| **Source** | [Rebuild Plan (archived)](archive/WS-Tracker-Rebuild-Plan.md) |
| **Est. Effort** | TBD |

**Description:**
Enhanced analytics dashboard with planner performance charts, trend analysis, and drill-down capabilities. Build on the existing Overview dashboard and planner activity data.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### FT-004: Admin Tools

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Feature |
| **Depends On** | FT-002, FT-003 |
| **Plan** | NEEDS PLAN |
| **Source** | [Rebuild Plan (archived)](archive/WS-Tracker-Rebuild-Plan.md) |
| **Est. Effort** | TBD |

**Description:**
Admin dashboard for user management, system configuration, and monitoring. Adapt from old WS-Tracker admin components.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### FT-005: Sync System

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Feature |
| **Depends On** | FT-001 |
| **Plan** | NEEDS PLAN |
| **Source** | [Rebuild Plan (archived)](archive/WS-Tracker-Rebuild-Plan.md) |
| **Est. Effort** | TBD |

**Description:**
New decoupled sync architecture replacing the legacy WS-Tracker sync system. Should be event-driven, interface-based, and not tied to specific models.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### FT-006: Unified Toast/Notification System

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Feature |
| **Depends On** | — |
| **Plan** | NEEDS PLAN |
| **Source** | Code Review #17 |
| **Est. Effort** | 2 hr |

**Description:**
Replace inconsistent flash/notification patterns (custom flash in CacheControls, inline alert in WorkStudioSetup, silent failure in ActiveAssessments, nothing in Overview) with a unified Alpine.js toast store that any Livewire component can dispatch to.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### FT-007: Historical Assessment Archival & Planner Performance Analytics

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | Feature |
| **Depends On** | — |
| **Plan** | [tech-spec-historical-assessment-archival.md](specs/tech-spec-historical-assessment-archival.md) |
| **Source** | Quick Spec Workflow Session |
| **Est. Effort** | 25+ hr |

**Description:**
Archive closed assessment data from WorkStudio API into local database for historical analysis and planner performance tracking. Immutable records for completed circuits with pre-computed analytics.

**Key Components:**

| Component | Description |
|-----------|-------------|
| **5-Table Schema** | `assessment_import_configs`, `archived_assessments`, `archived_assessment_units`, `archived_assessment_history`, `planner_performance_metrics` |
| **Import Service** | `AssessmentArchivalService` with 3-phase extraction (circuits → units → history) |
| **Admin UI** | Livewire component in Data Management for configuring imports |
| **Planner Metrics** | Two-tier analytics: circuit owner (TAKENBY) + unit forester (FORESTER) |

**Implementation Phases:**

| Phase | Tasks | Status |
|-------|-------|--------|
| 1. Database Schema | 5 migrations, models, relationships | pending |
| 2. Query Infrastructure | `ArchivalQueries.php`, 3-phase extraction | pending |
| 3. Service Layer | `AssessmentArchivalService`, idempotent imports | pending |
| 4. Admin UI | Import config Livewire component | pending |
| 5. Analytics | Pre-computed metrics, dashboard integration | pending |
| 6. Testing | Unit tests, feature tests, integration | pending |

**Metrics Tracked:**
- Planning duration (ownership → completion)
- Time-to-QC (completion → QC status)
- Ownership velocity (circuits per period)
- Footage attribution ("First Unit Wins" rule from planner-activity-rules.md)

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### FT-008: Query Explorer Admin Tool

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | **completed** |
| **Category** | Feature |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Admin tooling need |
| **Est. Effort** | 1 hr |
| **Files** | `app/Livewire/DataManagement/QueryExplorer.php`, `resources/views/livewire/data-management/query-explorer.blade.php`, `routes/data-management.php`, `resources/views/components/layout/sidebar.blade.php`, `tests/Feature/DataManagement/QueryExplorerTest.php` |

**Description:**
Livewire component under Data Management that builds and executes raw SQL SELECT queries against the WorkStudio API. Table dropdown (VEGJOB, VEGUNIT, STATIONS + custom), fields input, TOP limit (1-500), optional WHERE clause. Displays raw JSON results with row count, query timing, and executed SQL. Uses `config('workstudio.service_account.*')` credentials, bypassing GetQueryService hardcoded creds. 9 Pest feature tests.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-06 |
| **Time Elapsed** | ~30 min |
| **Files Changed** | `QueryExplorer.php`, `query-explorer.blade.php`, `data-management.php`, `sidebar.blade.php`, `QueryExplorerTest.php`, `CHANGELOG.md` |
| **Notes** | Committed directly on main (f5630da). Future features will use branch workflow. |

</details>

---

### UI/UX

---

#### UI-001: Add Error States to Dashboard Components

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #7.1](CODE-REVIEW.md#71-error-state-ui) |
| **Source** | Code Review #8 |
| **Est. Effort** | 2 hr |
| **Files** | `Overview.php`, `ActiveAssessments.php`, `overview.blade.php`, `active-assessments.blade.php` |

**Description:**
When WorkStudio API fails, components show empty states silently. Add try-catch to computed properties, set error flags, and render alert-error UI with retry buttons.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-002: Add Loading Skeletons

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #7.2](CODE-REVIEW.md#72-loading-skeleton-states) |
| **Source** | Code Review #11 |
| **Est. Effort** | 1 hr |
| **Files** | Dashboard blade views |

**Description:**
Add skeleton placeholders for initial render (stats grid, region cards) using `animate-pulse` with `bg-base-300` shapes.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-003: Improve Mobile Responsiveness

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #7.3-7.4](CODE-REVIEW.md#73-region-cards--mobile-responsiveness) |
| **Source** | Code Review #20 |
| **Est. Effort** | 1 hr |
| **Files** | Multiple blade files |

**Description:**
Region cards use `grid-cols-2` on mobile (cramped). Change to `grid-cols-1 sm:grid-cols-2`. Also replace full-viewport loading overlay with targeted spinners.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-004: Add Accessibility Attributes

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #7.7](CODE-REVIEW.md#77-accessibility-issues) |
| **Source** | Code Review #19 |
| **Est. Effort** | 1 hr |
| **Files** | `overview.blade.php`, `active-assessments.blade.php`, `region-card.blade.php`, `stat-card.blade.php`, `cache-controls.blade.php`, `header.blade.php` |

**Description:**
Missing `aria-label` on refresh buttons, no `role="status"` on loading spinners, region cards use `wire:click` on `<div>` without keyboard handler, color-only status indicators, truncated title.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-005: Add Back Navigation to Onboarding

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #7.11](CODE-REVIEW.md#711-onboarding-flow--no-back-navigation) |
| **Source** | Code Review #24 |
| **Est. Effort** | 10 min |
| **Files** | WorkStudio Setup blade view |

**Description:**
The 2-step onboarding flow has no way to go back from step 2 to step 1. Add a "Back to Step 1" link.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-006: Sync Dark Theme List from Config to JS

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #7.8](CODE-REVIEW.md#78-theme-system--dark-theme-list-hardcoded-in-js) |
| **Source** | Code Review #28 |
| **Est. Effort** | 30 min |
| **Files** | `resources/js/alpine/stores.js`, `config/themes.php` |

**Description:**
`darkThemes` array is hardcoded in JS and must be manually synced with config. Pass theme metadata via Blade data attribute.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-007: Fix App-Logo Branding Inconsistency

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | **completed** |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Code Review #31 |
| **Est. Effort** | 5 min |
| **Files** | `resources/views/components/app-logo.blade.php` |

**Description:**
Shows "Laravel Starter Kit" but sidebar shows "WS-Tracker". Update to match.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | 2026-02-05 |
| **Time Elapsed** | Part of cleanup/dead-code-removal branch |
| **Files Changed** | `resources/views/components/app-logo.blade.php` |
| **Notes** | Changed "Laravel Starter Kit" to "WS-Tracker". |

</details>

---

#### UI-008: Fix Dynamic Tailwind Color Classes

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #9.3](CODE-REVIEW.md#93-dynamic-tailwind-color-classes-may-not-compile) |
| **Source** | Code Review #33 |
| **Est. Effort** | 30 min |
| **Files** | `resources/views/components/ui/stat-card.blade.php`, `resources/views/components/ui/metric-pill.blade.php` |

**Description:**
`text-{{ $color }}` may not be picked up by Tailwind v4 JIT. Replace with class map of complete literal strings.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-009: Add Aria-Labels to 2FA Code Inputs

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Code Review #34 |
| **Est. Effort** | 10 min |
| **Files** | `resources/views/livewire/auth/two-factor-challenge.blade.php` |

**Description:**
Six individual code inputs lack `aria-label` attributes. Add `aria-label="Digit X of 6"` to each.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-010: Add i18n __() Wrappers to Remaining Views

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #9.6](CODE-REVIEW.md#96-internationalization-i18n-gaps) |
| **Source** | Code Review #36 |
| **Est. Effort** | 1 hr |
| **Files** | Dashboard, sidebar, cache controls, active assessments blade views |

**Description:**
Many user-facing strings in dashboard/sidebar/cache views are hardcoded English without `__()` translation helper.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-011: Add Eager Loading for User->Settings

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #6.3](CODE-REVIEW.md#63-n1-query-risks) |
| **Source** | Code Review #30 |
| **Est. Effort** | 10 min |
| **Files** | `app/Http/Middleware/EnsurePasswordChanged.php` or auth service provider |

**Description:**
`User::isOnboardingComplete()` and `isFirstLogin()` lazy-load the `settings` relation. Use `$user->loadMissing('settings')` or eager load in the middleware.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### UI-012: Loading Overlay — Replace Full-Page with Targeted

| Field | Value |
|-------|-------|
| **Priority** | P2 |
| **Status** | pending |
| **Category** | UI/UX |
| **Depends On** | — |
| **Plan** | [CODE-REVIEW.md #7.4](CODE-REVIEW.md#74-loading-overlay--too-aggressive) |
| **Source** | Code Review |
| **Est. Effort** | 30 min |
| **Files** | `overview.blade.php` |

**Description:**
Sort and panel operations trigger a fixed full-viewport overlay. Use targeted loading indicators (spinner in column header for sort, skeleton in panel area).

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

### Testing

---

#### TST-001: GetQueryService Unit Tests (Mocked HTTP)

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | — |
| **Plan** | — |
| **Source** | [Testing Monitoring Setup](archive/testing-monitoring-setup.md) §5a |
| **Est. Effort** | 2 hr |
| **Files** | New: `tests/Unit/GetQueryServiceTest.php` |

**Description:**
Unit test `GetQueryService` query execution with mocked HTTP responses. Test `executeQuery()`, `executeAndHandle()`, `transformArrayResponse()`, `transformJsonResponse()`.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### TST-002: Feature Test — API Routes Return JSON

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | SEC-002, CLN-009 |
| **Plan** | — |
| **Source** | Testing Monitoring Setup §5a |
| **Est. Effort** | 1 hr |
| **Files** | New: `tests/Feature/Api/WorkStudioApiRouteTest.php` |

**Description:**
Verify all API routes require auth and return proper JSON responses.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### TST-003: Livewire Component Tests

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | — |
| **Plan** | NEEDS PLAN |
| **Source** | Testing Monitoring Setup §5b |
| **Est. Effort** | 4 hr |
| **Files** | New tests in `tests/Feature/Livewire/` |

**Description:**
Identify key Livewire components and write component tests using Livewire testing utilities. Priority: Overview, CacheControls, ActiveAssessments.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### TST-004: Dusk — Login Flow End-to-End

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | — |
| **Plan** | — |
| **Source** | Testing Monitoring Setup §5c |
| **Est. Effort** | 1 hr |
| **Files** | New: `tests/Browser/LoginFlowTest.php` |

**Description:**
Full login flow: visit login page, enter credentials, submit, verify redirect to onboarding or dashboard.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### TST-005: Dusk — Dashboard Loads Correctly

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | UI-001, UI-002 |
| **Plan** | — |
| **Source** | Testing Monitoring Setup §5c |
| **Est. Effort** | 1 hr |
| **Files** | New: `tests/Browser/DashboardTest.php` |

**Description:**
Verify dashboard loads with stats, region cards, and proper layout. Check loading states render before data.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### TST-006: Dusk — Key User Workflows

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | TST-004, TST-005 |
| **Plan** | NEEDS PLAN |
| **Source** | Testing Monitoring Setup §5c |
| **Est. Effort** | 3 hr |

**Description:**
End-to-end tests for key workflows: theme switching, view toggle, sorting, cache management, onboarding flow.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### TST-007: Code Coverage Report Setup

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | TST-001, TST-003 |
| **Plan** | — |
| **Source** | Testing Monitoring Setup §5 |
| **Est. Effort** | 30 min |

**Description:**
Configure Pest/PHPUnit code coverage reports. Add coverage threshold to CI pipeline.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### TST-008: Add Dusk to CI Pipeline

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | TST-004 |
| **Plan** | NEEDS PLAN |
| **Source** | Testing Monitoring Setup §1 |
| **Est. Effort** | 2 hr |

**Description:**
Configure CI to run `php artisan dusk` with Chrome/Chromium. Handle headless browser setup, screenshot artifacts on failure.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### TST-009: PlannerActivityService Tests

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | FT-001 |
| **Plan** | [task-4 §3.1](plans/task-4-sql-integration-plan.md#part-3-testing-plan) |
| **Source** | Task-4 SQL Integration Plan |
| **Est. Effort** | 3 hr |
| **Files** | New: `tests/Feature/Services/PlannerActivityServiceTest.php` |

**Description:**
Test daily activity returns expected structure, date/planner filtering, non-work unit exclusion, first-unit-wins footage attribution, multi-planner station detection, assessment progress calculation.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

#### TST-010: PlannerActivity API Integration Tests

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Testing |
| **Depends On** | FT-001, TST-009 |
| **Plan** | [task-4 §3.2](plans/task-4-sql-integration-plan.md#part-3-testing-plan) |
| **Source** | Task-4 SQL Integration Plan |
| **Est. Effort** | 2 hr |
| **Files** | New: `tests/Feature/Api/PlannerActivityApiTest.php` |

**Description:**
API endpoint accessibility, JSON response structure validation, query parameter validation, error handling for invalid jobGuid.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

### Performance

---

#### PERF-001: Optimize Q1 — systemWideDataQuery

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Performance |
| **Depends On** | — |
| **Plan** | [Query Optimization Plan](plans/task-5-query-optimization-plan.md) |
| **Source** | Task-5 (Priority P6) |
| **Est. Effort** | 2 hr |
| **Files** | `AssessmentQueries.php`, `SqlFragmentHelpers.php` |

**Description:**
Remove inefficient `TOP 1` subquery for CONTRACTOR. Use window function. Good baseline for optimization workflow. Follow Phase A-E workflow from plan.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Benchmark** | Original: —, Optimized: —, Improvement: — |
| **Notes** | — |

</details>

---

#### PERF-002: Optimize Q2 — groupedByRegionDataQuery

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Performance |
| **Depends On** | PERF-001 |
| **Plan** | [Query Optimization Plan](plans/task-5-query-optimization-plan.md) |
| **Source** | Task-5 (Priority P4) |
| **Est. Effort** | 3 hr |
| **Files** | `AssessmentQueries.php`, `SqlFragmentHelpers.php` |

**Description:**
2 CROSS APPLYs executed for every row, repeated VEGUNIT scans. Pre-aggregate VEGUNIT counts in CTE, use conditional aggregation.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Benchmark** | Original: —, Optimized: —, Improvement: — |
| **Notes** | — |

</details>

---

#### PERF-003: Optimize Q3 — groupedByCircuitDataQuery

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Performance |
| **Depends On** | PERF-002 |
| **Plan** | [Query Optimization Plan](plans/task-5-query-optimization-plan.md) |
| **Source** | Task-5 (Priority P3) |
| **Est. Effort** | 3 hr |
| **Files** | `AssessmentQueries.php`, `SqlFragmentHelpers.php` |

**Description:**
Large result set, 2 CROSS APPLYs + STRING_AGG subquery. Apply same CTE approach as Q2.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Benchmark** | Original: —, Optimized: —, Improvement: — |
| **Notes** | — |

</details>

---

#### PERF-004: Optimize Q4 — getAllAssessmentsDailyActivities

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Performance |
| **Depends On** | — |
| **Plan** | [Query Optimization Plan](plans/task-5-query-optimization-plan.md) |
| **Source** | Task-5 (Priority P1 — highest query complexity) |
| **Est. Effort** | 4 hr |
| **Files** | `AssessmentQueries.php`, `SqlFragmentHelpers.php` |

**Description:**
Most complex query — FOR JSON PATH, nested subqueries, highest execution time. Split into multiple simpler queries, use pagination, cache intermediate results.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Benchmark** | Original: —, Optimized: —, Improvement: — |
| **Notes** | — |

</details>

---

#### PERF-005: Optimize Q5 — getAllByJobGuid (CTE Refactor)

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Performance |
| **Depends On** | — |
| **Plan** | [SQL Integration Plan](plans/task-4-sql-integration-plan.md) + [Query Optimization Plan](plans/task-5-query-optimization-plan.md) |
| **Source** | Task-4 Part 1, Task-5 (Priority P2) |
| **Est. Effort** | 3 hr |
| **Files** | `AssessmentQueries.php`, `SqlFragmentHelpers.php`, `config/ws_assessment_query.php` |

**Description:**
8+ separate subqueries per job, repeated table scans. Convert to CTE-based approach with `UnitStats` and `StationFootage` CTEs. Add column selection config (summary vs. detail). Expected 60-80% execution time reduction.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Benchmark** | Original: —, Optimized: —, Improvement: — |
| **Notes** | — |

</details>

---

#### PERF-006: Optimize Q7 — dailyRecordsQuery Fragment

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Performance |
| **Depends On** | — |
| **Plan** | [Query Optimization Plan](plans/task-5-query-optimization-plan.md) |
| **Source** | Task-5 (Priority P5) |
| **Est. Effort** | 2 hr |
| **Files** | `SqlFragmentHelpers.php` |

**Description:**
Triple-nested derived tables, ROW_NUMBER with complex partitioning, multiple VEGUNIT scans. Simplify nesting with CTEs, consider temp table for first assessment dates.

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Benchmark** | Original: —, Optimized: —, Improvement: — |
| **Notes** | — |

</details>

---

#### PERF-007: Database Index Recommendations

| Field | Value |
|-------|-------|
| **Priority** | P3 |
| **Status** | pending |
| **Category** | Performance |
| **Depends On** | PERF-001 |
| **Plan** | [SQL Integration Plan §4.3](plans/task-4-sql-integration-plan.md#43-index-recommendations) |
| **Source** | Task-4 Part 4, Task-5 |
| **Est. Effort** | 1 hr (documentation) |

**Description:**
Compile recommended indexes from optimization work and submit to DBA:
- `IX_VEGUNIT_JobStats` on `(JOBGUID, UNIT, PERMSTAT)` INCLUDE `(ASSDDATE, FORESTER, FRSTR_USER)`
- `IX_VEGUNIT_PlannerActivity` on `(JOBGUID, STATNAME, UNIT)` INCLUDE `(FORESTER, FRSTR_USER, ASSDDATE, EDITDATE)`
- `IX_STATIONS_Footage` on `(JOBGUID, STATNAME)` INCLUDE `(SPANLGTH)`

<details>
<summary>Completion Record</summary>

| Field | Value |
|-------|-------|
| **Completed** | — |
| **Time Elapsed** | — |
| **Files Changed** | — |
| **Notes** | — |

</details>

---

## Items Needing Plans

The following TODOs require a plan/spec before implementation can begin. Use `/bmad:bmm:workflows:quick-spec` or `/bmad:bmm:workflows:create-story` to generate them.

| ID | Title | Category | Priority | Notes |
|----|-------|----------|----------|-------|
| FT-002 | Kanban Board | Feature | P3 | Adapt from old WS-Tracker CircuitKanban |
| FT-003 | Analytics Dashboard Enhancement | Feature | P3 | Planner performance charts, trends |
| FT-004 | Admin Tools | Feature | P3 | User management, system config |
| FT-005 | Sync System | Feature | P3 | Event-driven, interface-based architecture |
| FT-006 | Unified Toast/Notification System | Feature | P2 | Alpine.js store + Livewire dispatch |
| REF-009 | Consolidate Duplicate Auth Layouts | Refactor | P3 | Investigate which views use which |
| TST-003 | Livewire Component Tests | Testing | P3 | Identify components, write test strategy |
| TST-006 | Dusk Key User Workflows | Testing | P3 | Define critical user paths |
| TST-008 | Add Dusk to CI Pipeline | Testing | P3 | Browser setup, artifact handling |

---

## Completion Log

> When a TODO is completed, add a row here for a chronological audit trail.

| Date | ID | Title | Time Elapsed | Files Changed |
|------|----|-------|--------------|---------------|
| 2026-02-05 | CLN-001 | Remove debug code (dump, queryAll, etc.) | cleanup/dead-code-removal | GetQueryService.php, workstudioAPI.php, ExecutionTimer.php |
| 2026-02-05 | CLN-002 | Delete _backup/ directory | cleanup/dead-code-removal | app/Livewire/_backup/ |
| 2026-02-05 | CLN-003 | Delete unused register.blade.php | cleanup/dead-code-removal | register.blade.php, FortifyServiceProvider.php |
| 2026-02-05 | CLN-004 | Delete unused welcome.blade.php | cleanup/dead-code-removal | welcome.blade.php |
| 2026-02-05 | CLN-005 | Delete dead dashboard.blade.php placeholder | cleanup/dead-code-removal | dashboard.blade.php |
| 2026-02-05 | CLN-006 | Clean up settings.php route file | cleanup/dead-code-removal | settings.php, web.php |
| 2026-02-05 | CLN-007 | Remove disabled Search/Notifications from header | cleanup/dead-code-removal | header.blade.php |
| 2026-02-05 | CLN-008 | Remove $currentUserId from WorkStudioApiService | cleanup/dead-code-removal | WorkStudioApiService.php |
| 2026-02-05 | SEC-002 | Protect/remove unauthed API routes | cleanup/dead-code-removal | workstudioAPI.php |
| 2026-02-05 | SEC-006 | Remove $sqlState property entirely | cleanup/dead-code-removal | GetQueryService.php |
| 2026-02-05 | UI-007 | Fix app-logo branding inconsistency | cleanup/dead-code-removal | app-logo.blade.php |
| 2026-02-06 | FT-008 | Query Explorer admin tool | ~30 min | QueryExplorer.php, query-explorer.blade.php, data-management.php, sidebar.blade.php, QueryExplorerTest.php |
| 2026-02-06 | REF-011 | Domain-driven folder restructure | ~45 min | 32 files (15 renamed, 17 import updates across app/, tests/, routes/) |
| 2026-02-06 | SEC-005 | Fix hasRole() missing method + full Spatie Permission integration | feature/permissions-system | User.php, AppServiceProvider.php, bootstrap/app.php, permission.php, RolePermissionSeeder.php, DatabaseSeeder.php, data-management.php, web.php, sidebar.blade.php, UserFactory.php, PermissionTest.php, CacheControlsTest.php, QueryExplorerTest.php, HealthCheckTest.php |

---

## Reference Documents

| Document | Location | Purpose |
|----------|----------|---------|
| Code Review | [`docs/CODE-REVIEW.md`](CODE-REVIEW.md) | Full code review with fixes and guidance |
| Rebuild Plan (archived) | [`docs/archive/WS-Tracker-Rebuild-Plan.md`](archive/WS-Tracker-Rebuild-Plan.md) | Original rebuild roadmap — tasks extracted |
| Planner Activity Rules | [`docs/specs/planner-activity-rules.md`](specs/planner-activity-rules.md) | Business rules for FT-001 |
| Planner Activity Prompt | [`docs/archive/prompt-planner-daily-activity-query.md`](archive/prompt-planner-daily-activity-query.md) | Implementation prompt for FT-001 |
| API Refactoring (completed) | [`docs/archive/tech-spec-workstudio-api-layer-refactoring-archived-2026-02-01.md`](archive/tech-spec-workstudio-api-layer-refactoring-archived-2026-02-01.md) | Historical reference |
| Testing & Monitoring Setup | [`docs/archive/testing-monitoring-setup.md`](archive/testing-monitoring-setup.md) | Incomplete testing items extracted |
| Dashboard Plan | [`docs/plans/task-3-dashboard-plan.md`](plans/task-3-dashboard-plan.md) | Dashboard implementation (completed) |
| SQL Integration Plan | [`docs/plans/task-4-sql-integration-plan.md`](plans/task-4-sql-integration-plan.md) | Planner Activity + query optimization |
| Query Optimization Plan | [`docs/plans/task-5-query-optimization-plan.md`](plans/task-5-query-optimization-plan.md) | Systematic query optimization workflow |
| Data Flow Diagram | [`docs/diagrams/planner-activity-dataflow.excalidraw`](diagrams/planner-activity-dataflow.excalidraw) | Visual data flow for FT-001 |
| Historical Assessment Archival Spec | [`docs/specs/tech-spec-historical-assessment-archival.md`](specs/tech-spec-historical-assessment-archival.md) | Full spec for FT-007 |

---

*Generated by BMad Master — WS-TrackerV1 TODO Tracker*

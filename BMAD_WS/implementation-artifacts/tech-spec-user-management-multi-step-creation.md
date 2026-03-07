---
title: 'User Management — Multi-Step Creation & WS Credential Assignment'
slug: 'user-management-multi-step-creation'
created: '2026-03-06'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - Laravel 12
  - Livewire 4
  - Spatie Permission v6
  - DaisyUI v5 / Tailwind CSS v4
  - Alpine.js v3 (stores, transitions, interactivity)
  - Pest 4 (testing)
files_to_modify:
  - app/Models/User.php (add relationships)
  - app/Models/WsUser.php (add relationships)
  - app/Models/Assessment.php (add relationships)
  - app/Models/Region.php (add relationships)
  - routes/user-management.php (add wizard route)
files_to_create:
  - database/migrations/XXXX_create_user_ws_identities_table.php
  - database/migrations/XXXX_create_user_assessments_table.php
  - database/migrations/XXXX_create_user_regions_table.php
  - app/Models/UserWsIdentity.php
  - app/Models/UserAssessment.php
  - app/Models/UserRegion.php
  - database/factories/UserWsIdentityFactory.php
  - database/factories/UserAssessmentFactory.php
  - database/factories/UserRegionFactory.php
  - app/Livewire/UserManagement/UserWizard.php
  - resources/views/livewire/user-management/user-wizard.blade.php
  - resources/views/livewire/user-management/steps/select-credentials.blade.php
  - resources/views/livewire/user-management/steps/verify-information.blade.php
  - resources/views/livewire/user-management/steps/assign-roles.blade.php
  - resources/views/livewire/user-management/steps/assign-regions-assessments.blade.php
  - resources/views/livewire/user-management/steps/review-save.blade.php
  - resources/views/livewire/user-management/partials/summary-panel.blade.php
  - resources/views/components/user-management/wizard-progress.blade.php
  - tests/Feature/UserManagement/UserWizardTest.php
  - tests/Unit/Models/UserWsIdentityTest.php
  - tests/Unit/Models/UserAssessmentTest.php
  - tests/Unit/Models/UserRegionTest.php
code_patterns:
  - 'Single parent Livewire component with step partials (not separate pages)'
  - 'DaisyUI steps-horizontal for progress indicator'
  - 'Alpine.js for client-side interactivity (search, filter, checkbox state)'
  - 'wire:model, wire:click, wire:loading patterns throughout'
  - '#[Layout("components.layout.app-shell")] with breadcrumbs array'
  - 'Factories with state methods for test data'
  - 'Spatie: Role::firstOrCreate + syncPermissions for idempotent role management'
  - 'Middleware-first auth: permission:manage-users on route group'
  - 'Role via Spatie — no role column on pivot tables, query via join'
test_patterns:
  - 'Pest 4 with RefreshDatabase on Feature tests'
  - 'beforeEach seeds RolePermissionSeeder'
  - 'createOnboardedUser() helper for test setup'
  - 'Livewire::actingAs($user)->test(Component::class) pattern'
  - 'Authorization tests: assertForbidden() for wrong roles, assertOk() for correct'
  - 'Factory states for test data variation'
---

# User Management — Multi-Step Creation & WS Credential Assignment

## Overview

### Problem Statement

Admins have no integrated workflow for creating users with full WorkStudio context. The current `CreateUser` component is a simple name/email/role form with no WS credential integration, no assessment assignment, no region mapping, and no way to associate historical WS usernames with a user for data continuity. Admins cannot assign roles, regions, or assessments to WS credentials outside of user creation either.

### Solution

A multi-step wizard (5 steps) with a persistent two-panel layout that lets admins select WS credentials, verify user info, assign roles, assign regions and assessments, then review and save. The wizard supports single-user creation in Phase 1, with batch creation and dynamic role creation added in Phase 2.

### In Scope

**Phase 1 (Core):**
- 5-step user creation wizard with two-panel layout
- Select WS credentials from `ws_users` table (checkbox, search, filter)
- Group multiple WS credentials under one user (primary + historical)
- Verify/edit user information pre-populated from WS data
- Assign role from existing Spatie roles
- Assign regions from `regions` table
- Auto-detect assessments via `assessment_contributors`, admin verifies/corrects
- Persistent right-side summary panel, editable at any step
- New database tables: `user_ws_identities`, `user_assessments`, `user_regions`

**Phase 2 (Enhancement):**
- Batch/multi-user creation in one session
- Dynamic role creation (create role + assign permissions on the fly)
- CSV export of created users with temp passwords
- Standalone WS credential assignment (roles/regions/assessments without new user)

### Out of Scope

- Editing existing users after creation (future feature)
- User listing/management page
- WS credential sync from API (uses existing `ws_users` data)
- Onboarding flow changes
- Bulk user import from external sources

---

## Context for Development

### Codebase Patterns

**Architecture:**
- Livewire 4 components with `#[Layout('components.layout.app-shell')]` and breadcrumbs
- DaisyUI v5 theming with `@plugin` syntax, theme variables only (no hardcoded colors)
- Alpine.js v3 stores for client-side state (`$store.theme`, `$store.sidebar`)
- Blade components in `resources/views/components/` (cards, badges, panels, hub layout)
- Slide-out panels (`x-ui.slide-out-panel`) for detail views
- No drag-and-drop library — checkbox + button patterns used

**Multi-Step Pattern (from onboarding):**
- Separate Livewire component per step with redirect navigation
- `<x-onboarding.progress :currentStep="N" />` uses DaisyUI `steps-horizontal`
- Loading states via `wire:loading.attr="disabled"` + spinner
- This wizard differs: single parent component with step partials (no page reloads)

**Auth & Permissions:**
- Spatie Permission v6 with 5 roles, 7 permissions (seeded via `RolePermissionSeeder`)
- Middleware-first: `permission:manage-users` on route groups
- No policies — all auth via middleware and permission checks

**Data Sync:**
- `ws_users` populated by `ws:fetch-users --seed` (from SS/VEGJOB tables + GETUSERDETAILS enrichment)
- `assessment_contributors` populated by `ws:fetch-assessments` (from VEGUNIT foresters JSON)
- `ws_users.username` format: `DOMAIN\username` (e.g., `ASPLUNDH\jsmith`)
- No FK between `ws_users` and `users` — independent tables

### Files to Reference

| File | Purpose | Relevance |
|---|---|---|
| `app/Livewire/UserManagement/CreateUser.php` | Current simple user creation | **Replace** with wizard route |
| `app/Livewire/Onboarding/*.php` | 4-step onboarding flow | **Pattern reference** |
| `app/Models/User.php` | User model with HasRoles | **Extend** with new relationships |
| `app/Models/WsUser.php` | WS user cache model | **Source** for credential selection |
| `app/Models/AssessmentContributor.php` | WS username to assessment mapping | **Source** for auto-detection |
| `app/Models/Region.php` | Region model with active scope | **Source** for region assignment |
| `app/Models/Assessment.php` | Assessment model (48 fields) | **Target** for user assignment |
| `database/seeders/RolePermissionSeeder.php` | Role/permission seeding | **Reference** for existing roles |
| `resources/views/components/onboarding/progress.blade.php` | Step progress indicator | **Adapt** for wizard progress |
| `tests/Feature/UserManagement/CreateUserTest.php` | Existing user creation tests | **Replace** with wizard tests |

### Technical Decisions

1. **Single Livewire component with step partials** — Unlike onboarding (separate pages per step), the wizard is a single parent `UserWizard` component managing all state, with step views as Blade partials. This keeps the two-panel layout persistent and avoids page reloads between steps.

2. **Role via Spatie, not on pivot tables** — The user's role is stored via Spatie's `model_has_roles` table (single source of truth). `user_assessments` and `user_regions` are lean pivots with no role column. To query "all assessments for planners," join `user_assessments` through `model_has_roles`. This prevents data drift when roles change.

3. **`user_ws_identities` unique on `ws_user_id`** — Each WS credential can belong to exactly ONE app user. The unique constraint is on `ws_user_id` alone (not composite). This prevents two admins from assigning the same credential to different users.

4. **Step 2 pre-populates from WS data** — `display_name` → user name, `email` → user email. Admin confirms or overrides. Don't make them re-type what the system knows.

5. **Assessment auto-detection** — Query `assessment_contributors` by ALL WS usernames associated with the user being created (primary + historical). Present results grouped by assessment with work order, circuit, and contributor info. Admin can add/remove.

6. **No drag-and-drop** — Checkbox selection with "Add/Remove" buttons. Matches existing codebase patterns, avoids new dependency.

7. **Two implementation phases** — Phase 1 is single-user creation with the full wizard. Phase 2 adds batch, dynamic roles, CSV export, and standalone assignment.

### Key Constraints

- Field role = app role = permissions (unified). Spatie role is the single source of truth.
- One user can have multiple WS identities (one primary, rest historical).
- Assessment assignment is semi-automated: system detects via `assessment_contributors`, admin verifies.
- Users can be created without WS credentials (but won't see anything).
- The right-side summary panel persists across all steps and is editable inline.
- `assessment_contributors.role` is API-sourced (Forester, QC Reviewer) — distinct from the Spatie app role.

---

## Implementation Plan — Phase 1

### Task 1: Database Migrations

- [ ] **Task 1.1: Create `user_ws_identities` migration**
  - File: `database/migrations/2026_03_06_000001_create_user_ws_identities_table.php`
  - Action: Create table with columns:
    - `id` (primary key)
    - `user_id` (FK to `users.id`, CASCADE on delete)
    - `ws_user_id` (FK to `ws_users.id`, SET NULL on delete, **unique** — each WS credential maps to exactly one user)
    - `is_primary` (boolean, default `false`)
    - `timestamps`
  - Index: `user_id` for lookups

- [ ] **Task 1.2: Create `user_assessments` migration**
  - File: `database/migrations/2026_03_06_000002_create_user_assessments_table.php`
  - Action: Create lean pivot table:
    - `id` (primary key)
    - `user_id` (FK to `users.id`, CASCADE on delete)
    - `assessment_id` (FK to `assessments.id`, CASCADE on delete)
    - `timestamps`
  - Unique constraint: `['user_id', 'assessment_id']`

- [ ] **Task 1.3: Create `user_regions` migration**
  - File: `database/migrations/2026_03_06_000003_create_user_regions_table.php`
  - Action: Create lean pivot table:
    - `id` (primary key)
    - `user_id` (FK to `users.id`, CASCADE on delete)
    - `region_id` (FK to `regions.id`, CASCADE on delete)
    - `timestamps`
  - Unique constraint: `['user_id', 'region_id']`

### Task 2: Eloquent Models

- [ ] **Task 2.1: Create `UserWsIdentity` model**
  - File: `app/Models/UserWsIdentity.php`
  - Action: Fillable: `user_id`, `ws_user_id`, `is_primary`. Casts: `is_primary` → boolean. Relationships: `user()` BelongsTo User, `wsUser()` BelongsTo WsUser.

- [ ] **Task 2.2: Create `UserAssessment` model**
  - File: `app/Models/UserAssessment.php`
  - Action: Fillable: `user_id`, `assessment_id`. Relationships: `user()` BelongsTo User, `assessment()` BelongsTo Assessment.

- [ ] **Task 2.3: Create `UserRegion` model**
  - File: `app/Models/UserRegion.php`
  - Action: Fillable: `user_id`, `region_id`. Relationships: `user()` BelongsTo User, `region()` BelongsTo Region.

- [ ] **Task 2.4: Add relationships to existing models**
  - File: `app/Models/User.php`
  - Action: Add `wsIdentities()` HasMany UserWsIdentity, `assessments()` BelongsToMany Assessment (via `user_assessments`), `regions()` BelongsToMany Region (via `user_regions`), `primaryWsIdentity()` HasOne UserWsIdentity where `is_primary = true`.
  - File: `app/Models/WsUser.php`
  - Action: Add `identity()` HasOne UserWsIdentity, `user()` HasOneThrough User via UserWsIdentity.
  - File: `app/Models/Assessment.php`
  - Action: Add `assignedUsers()` BelongsToMany User (via `user_assessments`).
  - File: `app/Models/Region.php`
  - Action: Add `assignedUsers()` BelongsToMany User (via `user_regions`).

### Task 3: Factories

- [ ] **Task 3.1: Create `UserWsIdentityFactory`**
  - File: `database/factories/UserWsIdentityFactory.php`
  - Action: Default definition with `user_id` → User::factory(), `ws_user_id` → WsUser::factory(), `is_primary` → false. States: `primary()` sets `is_primary = true`.

- [ ] **Task 3.2: Create `UserAssessmentFactory`**
  - File: `database/factories/UserAssessmentFactory.php`
  - Action: Default with `user_id` → User::factory(), `assessment_id` → Assessment::factory().

- [ ] **Task 3.3: Create `UserRegionFactory`**
  - File: `database/factories/UserRegionFactory.php`
  - Action: Default with `user_id` → User::factory(), `region_id` → Region::factory().

### Task 4: Wizard Livewire Component

- [ ] **Task 4.1: Create `UserWizard` parent component**
  - File: `app/Livewire/UserManagement/UserWizard.php`
  - Action: Single Livewire component managing wizard state:
    - Properties:
      - `int $currentStep = 1` (1-5)
      - `array $selectedWsUserIds = []` (Step 1 selections)
      - `?int $primaryWsUserId = null` (which credential is primary)
      - `string $userName = ''` (Step 2 — pre-populated from primary WS credential)
      - `string $userEmail = ''` (Step 2 — pre-populated)
      - `string $selectedRole = ''` (Step 3)
      - `array $selectedRegionIds = []` (Step 4)
      - `array $selectedAssessmentIds = []` (Step 4)
      - `array $detectedAssessmentIds = []` (auto-detected, read-only reference)
      - `bool $userCreated = false` (success state)
      - `string $temporaryPassword = ''` (shown after creation)
    - Methods:
      - `goToStep(int $step)` — validates current step before advancing, allows going back freely
      - `nextStep()` — validates + advances
      - `previousStep()` — goes back without validation
      - `selectWsCredentials(array $ids)` — updates selection
      - `setPrimary(int $wsUserId)` — marks one credential as primary
      - `detectAssessments()` — queries `assessment_contributors` for all selected WS usernames, populates `detectedAssessmentIds`
      - `saveUser()` — wraps creation in DB transaction: create User, assign role, link WS identities, link regions, link assessments, create UserSetting
      - `createAnother()` — resets state for next user
    - Computed properties:
      - `selectedWsUsers` — WsUser models for selected IDs
      - `availableRoles` — Role::orderBy('name')->pluck('name')
      - `availableRegions` — Region::active()->orderBy('display_name')->get()
      - `detectedAssessments` — Assessment models for detected IDs with contributor info
    - Validation per step:
      - Step 1: At least one WS credential selected (optional — user can skip)
      - Step 2: `userName` required, `userEmail` required + unique + valid email
      - Step 3: `selectedRole` required, must exist in roles table
      - Step 4: No required validation (regions/assessments are optional)
      - Step 5: Review only, no additional validation
    - Layout: `#[Layout('components.layout.app-shell', ['title' => 'Create User', 'breadcrumbs' => [...]])]`

- [ ] **Task 4.2: Create wizard progress component**
  - File: `resources/views/components/user-management/wizard-progress.blade.php`
  - Action: Adapt from `onboarding/progress.blade.php`. DaisyUI `steps-horizontal` with 5 steps: Select Credentials, Verify Info, Assign Role, Regions & Assessments, Review & Save. Accepts `$currentStep` prop. Steps before current are clickable (go back).

- [ ] **Task 4.3: Create wizard layout view**
  - File: `resources/views/livewire/user-management/user-wizard.blade.php`
  - Action: Two-panel layout:
    - Top: `<x-user-management.wizard-progress :currentStep="$currentStep" />`
    - Left panel (~60% width, `xl:col-span-3` in 5-col grid): Dynamic step content via `@include("livewire.user-management.steps.{$stepView}")`
    - Right panel (~40% width, `xl:col-span-2`): `@include('livewire.user-management.partials.summary-panel')`
    - Responsive: stack panels vertically on mobile (summary below steps)
    - Success state: show created user info + temp password (same pattern as existing CreateUser)

- [ ] **Task 4.4: Create Step 1 — Select Credentials**
  - File: `resources/views/livewire/user-management/steps/select-credentials.blade.php`
  - Action:
    - Search input (wire:model.live for filtering by username, display_name, email, domain)
    - Filter by domain dropdown
    - Scrollable list of `ws_users` with checkboxes
    - Each row shows: display_name, username (domain\user format), email, enabled badge, groups tags
    - Already-assigned WS credentials (linked to other users) shown as disabled with tooltip "Assigned to {user}"
    - "Skip — create without credentials" link at bottom
    - Selected count badge
    - Next/Skip buttons

- [ ] **Task 4.5: Create Step 2 — Verify Information**
  - File: `resources/views/livewire/user-management/steps/verify-information.blade.php`
  - Action:
    - Pre-populated from primary WS credential: `display_name` → name field, `email` → email field
    - If no primary selected yet, prompt admin to select one from their Step 1 choices (radio buttons)
    - Editable fields: Full Name, Email (validated unique)
    - Display selected WS credentials in a mini-list showing which is primary (radio to change)
    - Info alert: "A temporary password will be generated automatically"
    - Back/Next buttons

- [ ] **Task 4.6: Create Step 3 — Assign Role**
  - File: `resources/views/livewire/user-management/steps/assign-roles.blade.php`
  - Action:
    - List of existing Spatie roles as selectable cards (radio selection, not dropdown)
    - Each card shows: role name, list of permissions assigned to that role
    - Selected role highlighted with primary border
    - Info text explaining role determines app permissions
    - Back/Next buttons

- [ ] **Task 4.7: Create Step 4 — Assign Regions & Assessments**
  - File: `resources/views/livewire/user-management/steps/assign-regions-assessments.blade.php`
  - Action:
    - **Regions section:**
      - Checkbox list of active regions (display_name)
      - Select all / deselect all
    - **Assessments section:**
      - Auto-detected assessments shown with "Detected" badge (from `assessment_contributors` query on all selected WS usernames)
      - Each assessment row: work_order, extension, circuit line_name, status, scope_year
      - Checkboxes pre-checked for detected assessments
      - Admin can uncheck detected ones or search/add others
      - Search input to find assessments not auto-detected (by work_order, line_name)
    - Back/Next buttons

- [ ] **Task 4.8: Create Step 5 — Review & Save**
  - File: `resources/views/livewire/user-management/steps/review-save.blade.php`
  - Action:
    - Full summary of all data:
      - User: name, email
      - WS Credentials: list with primary marked
      - Role: name + permissions
      - Regions: list
      - Assessments: count + expandable list
    - "Edit" links on each section that jump back to the relevant step
    - Save button with loading state
    - Back button

- [ ] **Task 4.9: Create Summary Panel partial**
  - File: `resources/views/livewire/user-management/partials/summary-panel.blade.php`
  - Action:
    - Sticky card (DaisyUI card, `sticky top-4`)
    - Sections for each data category (credentials, user info, role, regions, assessments)
    - Each section shows current selections with counts
    - Inline edit: click on a value to jump to that step
    - Empty state for sections not yet completed (dimmed, step number reference)
    - Collapses to a compact badge bar on mobile

### Task 5: Route Registration

- [ ] **Task 5.1: Update user management routes**
  - File: `routes/user-management.php`
  - Action: Add wizard route. Keep existing create route for now (can remove later):
    ```php
    Route::get('/wizard', UserWizard::class)->name('wizard');
    ```
    Middleware remains: `auth`, `verified`, `onboarding`, `permission:manage-users`

### Task 6: Tests

- [ ] **Task 6.1: Unit tests for new models**
  - File: `tests/Unit/Models/UserWsIdentityTest.php`
  - Action: Test factory creates valid model, test relationships (user, wsUser), test unique constraint on ws_user_id, test is_primary cast.
  - File: `tests/Unit/Models/UserAssessmentTest.php`
  - Action: Test factory, relationships, unique constraint on (user_id, assessment_id).
  - File: `tests/Unit/Models/UserRegionTest.php`
  - Action: Test factory, relationships, unique constraint on (user_id, region_id).

- [ ] **Task 6.2: Feature tests for UserWizard**
  - File: `tests/Feature/UserManagement/UserWizardTest.php`
  - Action:
    - `beforeEach`: seed `RolePermissionSeeder`, create test WsUsers, Assessments, AssessmentContributors, Regions
    - Authorization tests: `manage-users` permission required, others get 403
    - Step navigation: can advance forward with valid data, can go back freely
    - Step 1: select WS credentials, verify selected count updates
    - Step 2: pre-populates from primary credential, validates required fields, validates unique email
    - Step 3: select role from available roles
    - Step 4: auto-detects assessments from contributors, can add/remove regions and assessments
    - Step 5: review shows all data correctly
    - Save: creates User, assigns role, links WS identities with correct primary, links regions, links assessments, creates UserSetting with first_login=true, generates temp password
    - Save: wraps in transaction — if any step fails, nothing is persisted
    - Edge case: WS credential already assigned to another user shows as disabled in Step 1
    - Edge case: user created without WS credentials (skip Step 1)
    - Edge case: user created without regions or assessments (skip Step 4 selections)

- [ ] **Task 6.3: Test relationship additions on existing models**
  - File: `tests/Unit/Models/UserWsIdentityTest.php` (extend)
  - Action: Test User->wsIdentities(), User->primaryWsIdentity(), User->assessments(), User->regions(), WsUser->identity(), Assessment->assignedUsers(), Region->assignedUsers()

---

## Acceptance Criteria — Phase 1

### Wizard Navigation
- [ ] AC1: Given an admin with `manage-users` permission, when they navigate to `/user-management/wizard`, then they see the 5-step wizard with progress indicator and two-panel layout.
- [ ] AC2: Given a user without `manage-users` permission, when they navigate to `/user-management/wizard`, then they receive a 403 Forbidden response.
- [ ] AC3: Given the admin is on Step 3, when they click a previous step in the progress bar, then they navigate back without losing any entered data.
- [ ] AC4: Given the admin is on Step 2 with invalid data (empty name), when they click Next, then validation errors display and they cannot advance.

### Step 1 — Select Credentials
- [ ] AC5: Given the admin is on Step 1, when they type in the search field, then the WS credentials list filters by username, display_name, or email in real-time.
- [ ] AC6: Given a WS credential is already linked to another user, when the admin views Step 1, then that credential appears disabled with a tooltip showing the assigned user.
- [ ] AC7: Given the admin selects 3 WS credentials, when they view the summary panel, then it shows 3 credentials with the ability to designate one as primary.

### Step 2 — Verify Information
- [ ] AC8: Given the admin selected WS credentials and marked one as primary, when they advance to Step 2, then the name and email fields are pre-populated from the primary credential's `display_name` and `email`.
- [ ] AC9: Given the admin enters an email that already exists in the `users` table, when they click Next, then a validation error displays "This email is already taken."

### Step 3 — Assign Role
- [ ] AC10: Given the admin is on Step 3, when they view the role list, then each role displays as a card showing the role name and its assigned permissions.
- [ ] AC11: Given the admin selects the "planner" role, when they view the summary panel, then it shows "Role: Planner" with the associated permissions listed.

### Step 4 — Regions & Assessments
- [ ] AC12: Given the user has WS credentials with matching `assessment_contributors` records, when the admin reaches Step 4, then assessments are auto-detected and pre-checked with a "Detected" badge.
- [ ] AC13: Given the admin unchecks a detected assessment, when they proceed to review, then that assessment is excluded from the final assignment.
- [ ] AC14: Given the admin searches for an assessment by work order, when results appear, then they can check it to add it to the assignment.

### Step 5 — Review & Save
- [ ] AC15: Given the admin has completed all steps, when they view Step 5, then a full summary displays: user name, email, WS credentials (with primary marked), role with permissions, regions, and assessments.
- [ ] AC16: Given the admin clicks Save, when the operation succeeds, then: a User record is created, email_verified_at is set, the Spatie role is assigned, WS identities are linked (one primary), regions are linked, assessments are linked, a UserSetting is created with `first_login = true`, and a temporary password is displayed.
- [ ] AC17: Given the admin clicks Save, when any part of the creation fails, then the entire operation rolls back (no partial data) and an error message displays.

### Summary Panel
- [ ] AC18: Given the admin is on any step, when they view the right-side summary panel, then it shows the current state of all selections made so far, with empty sections showing step references.
- [ ] AC19: Given the admin clicks an "Edit" link in the summary panel or review step, when clicked, then the wizard navigates to the relevant step.

### Edge Cases
- [ ] AC20: Given the admin skips Step 1 (no WS credentials), when they complete the wizard, then a user is created with no WS identity links.
- [ ] AC21: Given the admin skips regions and assessments in Step 4, when they save, then the user is created with no region or assessment assignments.

---

## Dependencies

- **Existing data required:** `ws_users` table must be populated (via `ws:fetch-users --seed`), `assessment_contributors` must be populated (via `ws:fetch-assessments`), `regions` must be seeded, `roles/permissions` must be seeded.
- **No new external libraries** — uses existing stack (Livewire, Alpine, DaisyUI).
- **No API calls** — all data is read from local database tables.

## Testing Strategy

**Unit Tests:**
- New model factories, relationships, and constraints
- Relationship additions on existing models (User, WsUser, Assessment, Region)

**Feature Tests:**
- Full wizard flow: authorization, step navigation, validation per step, save transaction
- Edge cases: skip credentials, skip regions/assessments, duplicate email, already-assigned credential
- Livewire component testing via `Livewire::actingAs()->test(UserWizard::class)` pattern

**Manual Testing:**
- Verify two-panel layout responsiveness (mobile, tablet, desktop)
- Verify search/filter performance with real WS data
- Verify summary panel updates in real-time across step changes
- Verify assessment auto-detection accuracy against real `assessment_contributors` data

## Notes

### High-Risk Items
- **Transaction integrity on save** — 5+ table writes per user. Must be wrapped in `DB::transaction()`. Test that partial failures roll back completely.
- **WS credential uniqueness** — The unique constraint on `user_ws_identities.ws_user_id` means a credential can't be assigned to two users. The UI must reflect this by disabling already-assigned credentials. Concurrent admin sessions could race — the DB constraint is the safety net.
- **Assessment auto-detection query performance** — Querying `assessment_contributors` for multiple WS usernames could be slow with large datasets. Consider eager loading and chunking if needed.

### Phase 2 Preview (Out of Scope for Phase 1)
- **Batch creation:** Wizard maintains array of users-to-create. Admin configures one user, adds to queue, configures next. Save creates all in one transaction.
- **Dynamic role creation:** Inline modal on Step 3 to create a new Spatie role with permission checkboxes. Persists immediately via `Role::create()` + `syncPermissions()`.
- **CSV export:** After batch save, download CSV with columns: name, email, temporary_password, role, regions, ws_credentials.
- **Standalone assignment:** Entry point that skips Steps 1-2, lets admin select an existing user, then assign role/regions/assessments.

### Known Limitations
- WS credentials must be pre-synced via `ws:fetch-users --seed` before they appear in the wizard.
- Assessment auto-detection only works for WS usernames that exist in `assessment_contributors` — if the username format doesn't match, detection will miss them.
- The existing `CreateUser` component remains at its current route during Phase 1 — can be deprecated after Phase 2.

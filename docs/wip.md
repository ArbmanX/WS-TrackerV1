# Work In Progress — Onboarding System Rework

## Current Task
Rework onboarding from 2-step to 4-step flow: Password → Theme → WS Credentials → Confirmation

## Status: Complete — Ready for Commit

**Branch:** `feature/onboarding-rework`

## Phases
- [x] Phase 1: Database & Foundation (migration, enum, model updates)
- [x] Phase 2: Auth Layout Fixes (theme binding, FOUC, wider container)
- [x] Phase 3: Reusable UI Components (password-input, progress, theme-picker)
- [x] Phase 4: Step 1 — Change Password (modify)
- [x] Phase 5: Step 2 — Theme Selection (new)
- [x] Phase 6: Step 3 — WorkStudio Credentials (modify + heartbeat)
- [x] Phase 7: Step 4 — Confirmation (new)
- [x] Phase 8: Middleware & Routes
- [x] Phase 9: Tests

## What Was Done

### New Files
- `app/Enums/OnboardingStep.php` — int-backed enum (Password=1, Theme=2, Credentials=3, Confirmation=4)
- `app/Services/WorkStudio/Client/HeartbeatService.php` — standalone HEARTBEAT endpoint check
- `app/Livewire/Onboarding/ThemeSelection.php` — theme picker with live preview
- `app/Livewire/Onboarding/Confirmation.php` — read-only summary + finalize
- `resources/views/livewire/onboarding/theme-selection.blade.php`
- `resources/views/livewire/onboarding/confirmation.blade.php`
- `resources/views/components/ui/password-input.blade.php` — reusable with eye toggle
- `resources/views/components/onboarding/progress.blade.php` — DaisyUI stepper
- `database/factories/UserWsCredentialFactory.php`
- `database/migrations/*_add_onboarding_step_to_user_settings_table.php`
- `tests/Unit/Enums/OnboardingStepTest.php` — 5 tests
- `tests/Feature/Onboarding/ThemeSelectionTest.php` — 7 tests
- `tests/Feature/Onboarding/ConfirmationTest.php` — 5 tests

### Modified Files
- `app/Models/UserSetting.php` — added `onboarding_step` to fillable + casts
- `app/Models/User.php` — added `wsCredential()` relationship
- `database/factories/UserSettingFactory.php` — added `onboarding_step`, `atStep()` state, updated `onboarded()`
- `app/Http/Middleware/EnsurePasswordChanged.php` — step-based routing with backward compat
- `app/Livewire/Onboarding/ChangePassword.php` — sets step=1, redirects to theme
- `app/Livewire/Onboarding/WorkStudioSetup.php` — adds ws_password, heartbeat check, credential test+store, redirects to confirmation
- `app/Services/WorkStudio/Client/ApiCredentialManager.php` — added `testCredentials()`
- `routes/web.php` — added theme + confirmation routes
- Auth layouts (`simple.blade.php`, `card.blade.php`, `sidebar.blade.php`) — fixed FOUC, theme key, Alpine init
- `resources/views/components/ui/theme-picker.blade.php` — added `x-on:change`
- Views updated with password-input + progress components
- Tests updated: `ChangePasswordTest`, `WorkStudioSetupTest`, `EnsurePasswordChangedTest`

### Test Results
- 40 onboarding-related tests pass (119 assertions)
- 65 broader feature tests pass (198 assertions)
- 5 pre-existing failures unrelated to this feature (FetchCircuits + Permission tests)

## Next Steps
- Commit and merge to main
- Manual testing: create user → login → 4-step flow → dashboard

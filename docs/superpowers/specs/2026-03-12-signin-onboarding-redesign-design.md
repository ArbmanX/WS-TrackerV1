# Sign-In & Onboarding Redesign

## Overview
Redesign login page with PPL branding, domain-based theme switching post-login, and expanded onboarding with team selection (GF/Manager) and home page preference.

## Login Changes

### PPL Default Theme
- Login page forces PPL theme: `ppl-light` (system light) / `ppl-dark` (system dark)
- FOUC script in auth layout uses PPL mapping instead of corporate/dark
- After first successful login, username-only field (existing feature — no change needed)

### New Logo
- Replace cube SVG with tree + electrical utility pole design
- Keep `currentColor` for theme-aware coloring
- Same component: `app-logo-icon.blade.php`

### Domain Theme Switch Post-Login
- Add `domain_theme_map` to `config/themes.php`
- Map email domains: `ppl` → ppl-light/dark, `asplundh` → asplundh-light/dark, `pennline` → pennline-light/dark
- In `FortifyServiceProvider::configureAuthentication()`, after successful auth, set user's theme based on domain
- Theme persists to `user_settings.theme` and localStorage via redirect

## Onboarding Changes

### New Step Flow (6 steps)
1. **Password** (existing) — step 1
2. **Theme** (existing) — step 2, pre-seeded with domain theme
3. **WS Credentials** (existing) — step 3
4. **Team Selection** (NEW) — step 4, **GF/Manager only** (skip for other roles)
5. **Home Page** (NEW) — step 5, all users
6. **Confirmation** (existing, updated) — step 6

### Team Selection (Step 4)
- Only shown to users with `general-foreman` or `manager` role
- Default team names: `{LastName}_A_Team`, `{LastName}_B_Team`
- User can edit names before saving
- Creates `Team` records linked to user
- Full team management deferred to future scope

### Home Page Selection (Step 5)
- All users select their default landing page
- Options: Dashboard, Planner Overview, Planner Production, Assessments
- Saves to `user_settings.home_page`
- Confirmation step redirects to chosen page instead of hardcoded `/dashboard`

## New Files (4)
1. `database/migrations/XXXX_create_teams_and_add_home_page.php`
2. `app/Models/Team.php`
3. `app/Livewire/Onboarding/TeamSelection.php` + view
4. `app/Livewire/Onboarding/HomePageSelection.php` + view

## Modified Files
- `app/Enums/OnboardingStep.php` — add TeamSelection, HomePage steps
- `resources/views/components/app-logo-icon.blade.php` — new SVG
- `config/themes.php` — PPL defaults, domain map
- `resources/views/layouts/auth/simple.blade.php` — PPL FOUC script
- `app/Providers/FortifyServiceProvider.php` — domain theme on login
- `app/Http/Middleware/EnsurePasswordChanged.php` — handle 6 steps with role-based skip
- `app/Models/UserSetting.php` — add home_page to fillable
- `app/Models/User.php` — add teams() relationship
- `app/Livewire/Onboarding/WorkStudioSetup.php` — redirect to team-selection or home-page
- `app/Livewire/Onboarding/Confirmation.php` — updated summary, step 6, redirect to home_page
- `routes/web.php` — new onboarding routes
- `resources/views/components/onboarding/progress.blade.php` — handle conditional steps

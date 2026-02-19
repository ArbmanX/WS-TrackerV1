# M13: Onboarding Dashboard Preference

> Depends on: M1, M12
> Unlocks: none

## Goal

Add a "Dashboard Preference" step to the onboarding flow. User picks their default landing hub. Post-login redirect uses this preference.

## Micro Tasks

- [ ] New onboarding step between Theme Selection and WorkStudio Setup
- [ ] Show available hubs for the user's role (admin sees different options than planner)
- [ ] Store selection in `user_settings.default_landing`
- [ ] Update post-login redirect to read `default_landing` and route accordingly
- [ ] Fallback to `/dashboard` if no preference set
- [ ] Update onboarding progress tracking to include new step

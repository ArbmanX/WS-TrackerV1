# M11: Admin Hub Refactor

> Depends on: M2
> Unlocks: none

## Goal

Reorganize existing admin tools into hub layout at `/admin`. Existing Livewire components are embedded, not rewritten.

## Micro Tasks

- [ ] Admin hub page with card grid
- [ ] User Management card → links to `/admin/users` (existing CreateUser + future edit/list)
- [ ] Cache Controls card → embeds existing CacheControls component at `/admin/cache`
- [ ] Query Explorer card → embeds existing QueryExplorer component at `/admin/queries`
- [ ] System Health card → links to health dashboard and Pulse
- [ ] Update routes to new `/admin/*` URL structure
- [ ] Preserve existing permission gates (`manage-users`, `access-data-management`, `execute-queries`)

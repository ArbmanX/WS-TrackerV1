# M1: Sidebar & Navigation System

> Depends on: nothing
> Unlocks: M2, M12

## Goal

Replace the current flat sidebar with a role-keyed navigation config. Each role gets a distinct set of hub links.

## Micro Tasks

- [ ] Define nav config structure (PHP array keyed by role with hub name, route, icon, permission)
- [ ] Admin/manager nav: Dashboard, Planners, Assessments, Monitoring, Admin, Settings
- [ ] Planner nav: My Dashboard, My Circuits, My Progress, Settings
- [ ] General foreman nav: Dashboard, Team, Assessments, Settings
- [ ] Base user nav: Dashboard, Settings
- [ ] Rewrite sidebar component to read config for the authenticated user's role
- [ ] Settings link pinned to bottom of sidebar (separate from hub list)
- [ ] Graceful handling of routes that don't exist yet (disabled state)
- [ ] Mobile/tablet responsive behavior preserved

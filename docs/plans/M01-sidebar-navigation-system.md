# M1: Sidebar & Navigation System

> Depends on: nothing
> Unlocks: M2, M12

## Goal

Replace the current flat sidebar with a role-keyed navigation config. Each role gets a distinct set of hub links.

## Micro Tasks

- [X] Define nav config structure (PHP array keyed by role with hub name, route, icon, permission)
- [X] Admin/manager nav: Dashboard, Planners, Assessments, Monitoring, Admin, Settings
- [X] Planner nav: My Dashboard, My Circuits, My Progress, Settings
- [X] General foreman nav: Dashboard, Team, Assessments, Settings
- [X] Base user nav: Dashboard, Settings
- [X] Rewrite sidebar component to read config for the authenticated user's role
- [X] Settings link pinned to bottom of sidebar (separate from hub list)
- [X] Graceful handling of routes that don't exist yet (disabled state)
- [X] Mobile/tablet responsive behavior preserved

# Navigation & Hub Architecture — Task Index

> Design doc: [2026-02-18-navigation-and-hub-design.md](./2026-02-18-navigation-and-hub-design.md)

## Macro Tasks

### Foundation (build first)

| # | Task | File | Status |
|---|---|---|---|
| M1 | Sidebar & Navigation System | [M01](./M01-sidebar-navigation-system.md) | planned |
| M2 | Hub Page Framework | [M02](./M02-hub-page-framework.md) | planned |

### Hub Pages (parallel after M2)

| # | Task | File | Status |
|---|---|---|---|
| M3 | Dashboard Hub | [M03](./M03-dashboard-hub.md) | planned |
| M4 | Planners Hub | [M04](./M04-planners-hub.md) | planned |
| M6 | Assessments Hub | [M06](./M06-assessments-hub.md) | planned |
| M10 | Monitoring Hub + Ghost Detail | [M10](./M10-monitoring-hub.md) | planned |
| M11 | Admin Hub Refactor | [M11](./M11-admin-hub-refactor.md) | planned |

### Detail Views (follow their parent hub)

| # | Task | File | Depends On | Status |
|---|---|---|---|---|
| M5 | Planner Detail View | [M05](./M05-planner-detail-view.md) | M4 | planned |
| M7 | Region Detail View | [M07](./M07-region-detail-view.md) | M6 | planned |
| M8 | Circuit Detail View | [M08](./M08-circuit-detail-view.md) | M6 | planned |
| M9 | Assessment Detail View | [M09](./M09-assessment-detail-view.md) | M8 | planned |

### Settings & Onboarding

| # | Task | File | Depends On | Status |
|---|---|---|---|---|
| M12 | Settings Page | [M12](./M12-settings-page.md) | M1 | planned |
| M13 | Onboarding Dashboard Preference | [M13](./M13-onboarding-dashboard-preference.md) | M1, M12 | planned |

### Role-Specific & Polish (build last)

| # | Task | File | Depends On | Status |
|---|---|---|---|---|
| M14 | Planner Role Views | [M14](./M14-planner-role-views.md) | M1, M5 | planned |
| M15 | General Foreman Role Views | [M15](./M15-general-foreman-role-views.md) | M1, M4, M6 | planned |
| M16 | Cross-Linking & Breadcrumbs | [M16](./M16-cross-linking-breadcrumbs.md) | M5, M7, M8, M9 | planned |

## Dependency Graph

```
M1 (Sidebar)
├── M2 (Hub Framework)
│   ├── M3  (Dashboard Hub)
│   ├── M4  (Planners Hub) ──► M5 (Planner Detail)
│   ├── M6  (Assessments Hub) ──► M7 (Region Detail)
│   │                         ──► M8 (Circuit Detail) ──► M9 (Assessment Detail)
│   ├── M10 (Monitoring Hub)
│   └── M11 (Admin Hub)
├── M12 (Settings) ──► M13 (Onboarding Preference)
│
M5 ──────────────────► M14 (Planner Role Views)
M4 + M6 ────────────► M15 (Foreman Role Views)
M5 + M7 + M8 + M9 ─► M16 (Cross-Linking)
```

## Suggested Build Order

1. **M1** → **M2** (foundation)
2. **M3, M4, M6, M10, M11, M12** (parallel hub build)
3. **M5, M7, M8, M13** (detail views + onboarding)
4. **M9** (assessment detail, needs circuit detail)
5. **M14, M15, M16** (role views + polish)

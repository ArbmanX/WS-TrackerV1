# Navigation & Hub Page Architecture Design

> Approved: 2026-02-18
> Approach: Role-Based Hub Grid (Approach A)

## Design Principles

1. **Hub pages over deep nav** — sidebar has 3-5 items per role, each leading to a card-grid landing page
2. **Role-tailored sidebars** — each role gets a purpose-built nav, not a universal nav with hidden items
3. **Shared detail views** — entity detail pages (planner, circuit, assessment, region, ghost) are role-agnostic and reachable from any hub
4. **Cross-linking everywhere** — entity names are always clickable links to their detail view
5. **User-configurable landing** — default hub preference set during onboarding, changeable in settings

---

## Sidebar Structure Per Role

### sudo-admin / manager

| Hub | Icon | Permission |
|---|---|---|
| Dashboard | grid/overview | `view-dashboard` |
| Planners | people | `view-dashboard` |
| Assessments | clipboard | `view-dashboard` |
| Monitoring | shield/radar | `access-data-management` |
| Admin | gear | `manage-users` (manager sees subset of cards) |
| Settings | cog (bottom-pinned) | none |

### planner

| Hub | Icon | Permission |
|---|---|---|
| My Dashboard | grid/overview | `view-dashboard` |
| My Circuits | map-pin | `view-dashboard` |
| My Progress | chart-trending | `view-dashboard` |
| Settings | cog (bottom-pinned) | none |

### general-foreman

| Hub | Icon | Permission |
|---|---|---|
| Dashboard | grid/overview | `view-dashboard` |
| Team | people | `view-dashboard` |
| Assessments | clipboard | `view-dashboard` |
| Settings | cog (bottom-pinned) | none |

### user (base role)

| Hub | Icon | Permission |
|---|---|---|
| Dashboard | grid/overview | `view-dashboard` |
| Settings | cog (bottom-pinned) | none |

---

## Hub Page Content

### Dashboard Hub (Admin/Manager)

Two-column focus: planner health (left) and assessment progress (right).

**Cards:**
- **Planner Snapshot** — active planners count, behind-quota count, link to Planners Hub
- **Assessment Pipeline** — active/QC/rework/deferred counts, link to Assessments Hub
- **Regional Summary** — 5 regions with status bars, link to Region Detail
- **Recent Activity** — last 24h sync events, new assessments
- **Alerts** — active ghost detections, stale planners, link to Monitoring Hub
- **Scope Year Progress** — completion % by year with mini bars

**Data sources:** `SystemWideSnapshot`, `RegionalSnapshot`, `AssessmentMonitor`, `PlannerMetricsService`, `GhostOwnershipPeriod`

### Planners Hub (Admin/Manager)

**Top:** Stat cards — Active planners, Behind Quota, Avg miles/week

**Cards:**
- **Planner Roster** — full list with status, quota, region → Planner Detail
- **Coaching Signals** — planners needing attention ranked by urgency → Planner Detail
- **Career Trends** — team-wide career trajectory chart
- **Regional Comparison** — planners grouped by region, avg metrics

**Data sources:** `PlannerMetricsService`, `PlannerCareerLedgerService`, `CoachingMessageGenerator`

### Assessments Hub (Admin/Manager)

**Top:** Stat cards — Total, Active, QC, Rework, Deferred, Closed

**Cards:**
- **By Region** — region cards with circuit count, completion % → Region Detail
- **By Status** — pipeline/kanban view: Active→QC→Complete → filtered list
- **Scope Years** — progress by year → filtered list
- **Split Tracking** — parent/child split status

**Data sources:** `Assessment` model, `AggregateQueries`, `CircuitQueries`, `RegionalSnapshot`

### Monitoring Hub (Admin only)

**Cards:**
- **Ghost Detection** — active/resolved counts → Ghost Detail
- **Sync Status** — last sync time, failed rows → Sync History
- **Data Freshness** — per-table last update, staleness flags
- **Assessment Monitors** — active/QC/rework per circuit, anomaly alerts

**Data sources:** `GhostOwnershipPeriod`, `GhostUnitEvidence`, `AssessmentMonitor`, `Assessment.last_synced_at`

### Admin Hub (Admin only)

**Cards:**
- **User Management** — create, edit, roles, activity (existing + new views)
- **Cache Controls** — existing `CacheControls` component
- **Query Explorer** — existing `QueryExplorer` component
- **System Health** — health dashboard, Pulse, logs

**Data sources:** `User` model, existing Livewire components, Laravel Pulse

### Planner Role Hubs

| Hub | Content |
|---|---|
| My Dashboard | Personal quota gauge, coaching message, this-week stats, mini activity feed |
| My Circuits | Card per assigned circuit with assessment counts, completion %, status badges |
| My Progress | Career trend chart, weekly miles, historical performance |

### General Foreman Hubs

| Hub | Content |
|---|---|
| Dashboard | Regional KPIs, team summary stats |
| Team | Planner cards (using `<x-planner.card>`), sorted by performance |
| Assessments | Regional assessments filtered to their region |

---

## Detail Views (Shared, Role-Agnostic)

| View | Route | Key Content |
|---|---|---|
| Planner Detail | `/planners/{username}` | Quota progress, career chart, assigned circuits, active assessments, coaching, recent activity |
| Circuit Detail | `/assessments/circuit/{id}` | Assessment list, completion %, unit breakdown (work/non-work), permissioning status |
| Assessment Detail | `/assessments/{job_guid}` | Full assessment data: status, extension, scope year, parent/child splits, VEGJOB fields, permission status, edit history |
| Region Detail | `/assessments/region/{id}` | Circuits in region, aggregate stats, planner distribution, snapshot trend |
| Ghost Detail | `/monitoring/ghosts/{id}` | Ownership periods, unit evidence, resolution status, affected circuits |

---

## Cross-Linking Pattern

Entity names are always clickable links to their detail view, regardless of where they appear. Navigation forms a directed graph, not a tree.

**Examples:**
- Dashboard "3 behind quota" → Planners Hub (filtered)
- Dashboard "Central region" → Region Detail
- Planner Detail "CKT-4412" → Circuit Detail
- Circuit Detail "{GUID}" → Assessment Detail
- Assessment Detail "tgibson" → Planner Detail

### Breadcrumbs

Breadcrumbs show entry-path context:
```
Planners → tgibson → CKT-4412 → {Assessment GUID}
Assessments → Central → CKT-4412 → {Assessment GUID}
```

First segment is always the hub you came from.

---

## URL Structure

```
/dashboard                          Dashboard Hub
/planners                           Planners Hub
/planners/{username}                Planner Detail
/assessments                        Assessments Hub
/assessments/region/{id}            Region Detail
/assessments/circuit/{id}           Circuit Detail
/assessments/{job_guid}             Assessment Detail
/monitoring                         Monitoring Hub
/monitoring/ghosts                  Ghost list
/monitoring/ghosts/{id}             Ghost Detail
/admin                              Admin Hub
/admin/users                        User Management
/admin/cache                        Cache Controls
/admin/queries                      Query Explorer
/settings                           User Settings
/settings/profile                   Profile
/settings/preferences               Theme, default view
```

---

## Onboarding Addition: Dashboard Preference

### Updated Flow

```
Change Password → Theme Selection → Dashboard Preference → WorkStudio Setup → Confirmation
```

### Dashboard Preference Step

Shows available hubs for the user's role. User picks their landing page.

- **Admin:** Dashboard | Planners | Assessments
- **Planner:** My Dashboard | My Circuits | My Progress
- **General Foreman:** Dashboard | Team | Assessments

### Storage

New column on `user_settings`: `default_landing` (string, nullable, e.g. `planners`, `assessments`, `my-dashboard`).

### Post-Login Redirect

1. User logs in
2. Onboarding incomplete → `/onboarding/password`
3. Onboarding complete → read `user_settings.default_landing`
4. Redirect to hub route (fallback: `/dashboard`)

Users can change this anytime from Settings → Preferences.

---

## Data Flow

```
WS API (DDOProtocol)          Local DB (PostgreSQL)        JSON Files (Career Data)
        │                              │                            │
   CircuitQueries              Assessment Model             PlannerCareerLedgerService
   AggregateQueries            Circuit / Region             PlannerMetricsService
   ActivityQueries             AssessmentMonitor
   LookupQueries              RegionalSnapshot
                               SystemWideSnapshot
                               GhostOwnershipPeriod
                               GhostUnitEvidence
        │                              │                            │
        └──────────────────────────────┴────────────────────────────┘
                                       │
                    ┌──────────────────┼──────────────────┐
                    │                  │                  │
              Hub Pages          Detail Views        Settings
              (card grids)       (entity pages)      (user prefs)
```

No new backend services needed for hub pages — they orchestrate existing data sources. Detail views may need thin Livewire components that compose existing service calls.

---

## Implementation Notes

- Sidebar config should be a PHP array per role (similar to current approach but role-keyed)
- Hub pages are Livewire components that query services and render card grids
- Card components should be reusable Blade components (`<x-hub.card>`)
- Detail views are Livewire components with entity-specific layouts
- Breadcrumbs can use a simple session/query-param approach to track entry path
- Existing Livewire components (CacheControls, QueryExplorer, CreateUser) are embedded within hub/admin pages, not rewritten

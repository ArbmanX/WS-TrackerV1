# Planner Analytics Page — Data Points, Filters & Selectors Inventory

> **Purpose:** Editable inventory of every data element for the Planner Analytics page.
> **Instructions:** Add (+), remove (-), or adjust items. Once approved, this becomes the design contract.
> **Created:** 2026-02-10
> **Status:** DRAFT — Awaiting user review

---

## Table of Contents

1. [Page Overview](#1-page-overview)
2. [Global Filters & Selectors](#2-global-filters--selectors)
3. [All-Planners Overview Section](#3-all-planners-overview-section)
4. [Individual Planner Detail Section](#4-individual-planner-detail-section)
5. [Permissioning Tracker Section](#5-permissioning-tracker-section)
6. [Charts & Visualizations](#6-charts--visualizations)
7. [Component Swap Behavior](#7-component-swap-behavior)
8. [Data Source Mapping](#8-data-source-mapping)

---

## 1. Page Overview

**Page Type:** Single-page application (SPA-like via Livewire component swapping)
**URL:** `/planner-analytics` (proposed)
**Permission:** `view-dashboard` (same as main dashboard, visible to planner+ roles)
**Layout:** Uses `layouts.app.sidebar` — appears in sidebar under new "Planner Analytics" section

**Core Concept:** The page has two modes:
- **Overview Mode** — aggregate metrics for ALL planners (default view)
- **Planner Detail Mode** — drill into a specific planner's historical data

Switching between modes swaps the content area without a page reload.

---

## 2. Global Filters & Selectors

These persist across both modes (overview and detail).

### 2.1 Time Period Selector

| ID | Control | Type | Default | Options / Behavior |
|----|---------|------|---------|-------------------|
| F-01 | **Date Range Picker** | Dual date input | Current week (Sun–Sat) | Custom from/to dates, max range TBD |
| F-02 | **Quick Period Buttons** | Button group | "This Week" active | `Today`, `This Week`, `Last Week`, `This Month`, `Last Month`, `Custom` |
| F-03 | **Granularity Toggle** | Segmented control | "Daily" | `Daily`, `Weekly`, `Monthly` — controls how charts aggregate |

### 2.2 Scope Filters

| ID | Control | Type | Default | Options / Behavior |
|----|---------|------|---------|-------------------|
| F-04 | **Region Filter** | Multi-select dropdown | All regions | CENTRAL, HARRISBURG, LANCASTER, LEHIGH, NORTHEAST, SUSQUEHANNA |
| F-05 | **Planner Selector** | Searchable dropdown | "All Planners" (overview) | List of active planners from WS API. Selecting one switches to detail mode |
| F-06 | **Scope Year** | Read-only badge | From config | Shows current scope year (e.g., "2026 Cycle") |

### 2.3 Display Controls

| ID | Control | Type | Default | Options / Behavior |
|----|---------|------|---------|-------------------|
| F-07 | **Unit of Measure Toggle** | Toggle | Miles | `Miles` / `Feet` / `Meters` |
| F-08 | **Compare Mode** | Toggle switch | Off | When on, shows comparison columns (vs. previous period) |

---

## 3. All-Planners Overview Section

Shown when no specific planner is selected (default view).

### 3.1 Summary Stat Cards (Top Row)

| ID | Metric | Calculation | Format | Icon Suggestion |
|----|--------|-------------|--------|-----------------|
| M-01 | **Total Miles Planned** | Sum of all planners' footage for selected period | `XX.X mi` | map-pin |
| M-02 | **Total Units Planned** | Count of all work units placed in period | `X,XXX` | cube |
| M-03 | **Active Planners** | Distinct planner count with activity in period | `XX` | users |
| M-04 | **Avg Miles/Planner/Day** | M-01 / (active work days * M-03) | `X.XX mi` | chart-bar |
| M-05 | **Units Needing Permission** | Count of units where PERMSTAT is NULL or pending | `XXX` | shield-exclamation |
| M-06 | **Stations Completed** | Distinct stations with at least one unit | `X,XXX` | check-circle |

### 3.2 Planner Comparison Table

| ID | Column | Source | Sortable | Notes |
|----|--------|--------|----------|-------|
| T-01 | **Planner Name** | VEGUNIT.FORESTER | Yes | Click → enters detail mode |
| T-02 | **Miles Planned** | Sum of attributed footage (First Unit Wins) | Yes | For selected period |
| T-03 | **Units Placed** | Count of work units | Yes | Excludes NW/NOT/SENSI |
| T-04 | **Stations Completed** | Distinct stations with first unit by this planner | Yes | |
| T-05 | **Avg Miles/Day** | T-02 / active work days in period | Yes | |
| T-06 | **Assessments Worked** | Distinct JOBGUID count | Yes | |
| T-07 | **Units Needing Permission** | Units where PERMSTAT pending for this planner | Yes | |
| T-08 | **Quota Status** | Miles vs 6.5 mi/week target | Yes | Badge: on-track / behind / ahead |
| T-09 | **Trend Sparkline** | Mini line chart of daily miles | No | Last 7 days inline |

### 3.3 Daily Breakdown Table (Expandable)

| ID | Column | Source | Notes |
|----|--------|--------|-------|
| D-01 | **Date** | Activity date (ASSDDATE) | Row per day in selected period |
| D-02 | **Total Miles (All Planners)** | Sum of all planners' footage that day | |
| D-03 | **Total Units (All Planners)** | Sum of all work units that day | |
| D-04 | **Planners Active** | Distinct planner count that day | |
| D-05 | **Top Planner** | Planner with most miles that day | Name + miles |
| D-06 | **Stations Completed** | Total new stations assessed that day | |

### 3.4 Monthly Summary Row

| ID | Metric | Calculation | Notes |
|----|--------|-------------|-------|
| MS-01 | **Month Total Miles** | Sum of daily miles for month | Bold summary row |
| MS-02 | **Month Total Units** | Sum of daily units for month | |
| MS-03 | **Month Avg Miles/Day** | MS-01 / work days in month | |
| MS-04 | **Month vs Target** | MS-01 vs (6.5 * weeks in month * planner count) | Percentage |

---

## 4. Individual Planner Detail Section

Shown when a specific planner is selected via F-05 or clicking a planner name.

### 4.1 Planner Header

| ID | Data Point | Source | Notes |
|----|------------|--------|-------|
| PH-01 | **Planner Name** | VEGUNIT.FORESTER | Large heading |
| PH-02 | **Username** | VEGUNIT.FRSTR_USER | Subtitle |
| PH-03 | **Region(s) Active** | Derived from assessment regions | Badge list |
| PH-04 | **Period Summary** | Selected date range | "Jan 6 – Jan 12, 2026" |
| PH-05 | **Back to Overview** | Navigation | Button/link to return to all-planners |

### 4.2 Planner KPI Cards

| ID | Metric | Calculation | Format |
|----|--------|-------------|--------|
| PK-01 | **Miles This Period** | Sum of attributed footage | `XX.X mi` |
| PK-02 | **Units This Period** | Count of work units placed | `XXX` |
| PK-03 | **Stations Completed** | Distinct stations assessed | `XXX` |
| PK-04 | **Avg Miles/Day** | PK-01 / work days | `X.XX mi` |
| PK-05 | **Quota Status** | Miles vs 6.5 mi/week | On-track / Behind badge |
| PK-06 | **Units Needing Permission** | Pending PERMSTAT count | `XX` |
| PK-07 | **Assessments Active** | Distinct active JOBGUIDs | `X` |
| PK-08 | **Rank Among Planners** | Position by miles in period | `#X of Y` |

### 4.3 Planner Daily Activity Table

| ID | Column | Source | Notes |
|----|--------|--------|-------|
| PA-01 | **Date** | ASSDDATE | Row per day |
| PA-02 | **Miles** | Footage attributed that day | |
| PA-03 | **Units** | Work units placed that day | |
| PA-04 | **Stations** | Stations assessed that day (first unit) | |
| PA-05 | **Assessment(s)** | Which assessment(s) worked on | Name/WO/circuit |
| PA-06 | **Non-Work Units** | NW/NOT/SENSI count that day | |
| PA-07 | **Cumulative Miles (Week)** | Running total for the week | Progress toward 6.5 mi |

### 4.4 Planner Assessment Breakdown

| ID | Column | Source | Notes |
|----|--------|--------|-------|
| AB-01 | **Assessment / Circuit** | VEGJOB + circuit name | |
| AB-02 | **Region** | Circuit region | |
| AB-03 | **Miles Planned** | Footage attributed on this assessment | |
| AB-04 | **Units Placed** | Work units on this assessment | |
| AB-05 | **Stations Completed** | Stations assessed by this planner | |
| AB-06 | **% Complete** | Stations assessed / total stations | Progress bar |
| AB-07 | **Status** | Assessment status (ACTIV, QC, etc.) | Badge |

---

## 5. Permissioning Tracker Section

Available in both overview and detail modes.

### 5.1 Permission Overview Cards

| ID | Metric | Source | Notes |
|----|--------|--------|-------|
| PM-01 | **Total Unpermissioned Units** | VEGUNIT.PERMSTAT NULL or pending | All planners |
| PM-02 | **Oldest Unpermissioned Unit** | MIN(ASSDDATE) where PERMSTAT pending | Date + age in days |
| PM-03 | **Avg Days to Permission** | AVG(PERMDATE - ASSDDATE) for permitted units | Trend metric |
| PM-04 | **Units Permissioned This Period** | Count of newly permitted in date range | |

### 5.2 Unpermissioned Units Table (Sortable)

| ID | Column | Source | Sortable | Notes |
|----|--------|--------|----------|-------|
| UP-01 | **Unit** | VEGUNIT.UNIT | Yes | Unit type code |
| UP-02 | **Station** | VEGUNIT.STATNAME | Yes | Station name |
| UP-03 | **Assessment / Circuit** | VEGJOB / circuit name | Yes | |
| UP-04 | **Planner** | VEGUNIT.FORESTER | Yes | Who placed it |
| UP-05 | **Date Placed** | VEGUNIT.ASSDDATE | Yes | When placed |
| UP-06 | **Days Waiting** | NOW - ASSDDATE | Yes | Age in days, color-coded |
| UP-07 | **Region** | Circuit region | Yes | |

---

## 6. Charts & Visualizations

### 6.1 Overview Mode Charts

| ID | Chart Type | Data | Axes | Notes |
|----|-----------|------|------|-------|
| C-01 | **Line Chart: Daily Miles (All Planners)** | Aggregate daily footage | X: date, Y: miles | Stacked or single line. F-03 granularity applies |
| C-02 | **Bar Chart: Miles by Planner** | Period total per planner | X: planner, Y: miles | Horizontal bars, sorted desc. Quota line at 6.5/wk |
| C-03 | **Stacked Area: Daily Activity by Planner** | Each planner's daily footage stacked | X: date, Y: miles | Shows contribution |
| C-04 | **Donut: Region Distribution** | Miles planned by region | Segments: region | Period total |

### 6.2 Planner Detail Charts

| ID | Chart Type | Data | Axes | Notes |
|----|-----------|------|------|-------|
| C-05 | **Line Chart: Planner's Daily Miles** | Selected planner's daily footage | X: date, Y: miles | With 6.5/wk pace line |
| C-06 | **Bar Chart: Units by Type** | Work unit type breakdown | X: unit code, Y: count | SPM, SPB, REM612, etc. |
| C-07 | **Line Chart: Historical Trend** | Weekly miles over months | X: week, Y: miles | Longer historical view |
| C-08 | **Heatmap: Activity Calendar** | Daily activity intensity | Calendar grid | Green intensity = more miles |
| C-09 | **Stacked Bar: Assessment Breakdown** | Miles per assessment per day | X: date, Y: miles, color: assessment | |

### 6.3 Chart Library

| Option | Notes |
|--------|-------|
| **Chart.js** | Lightweight, works well with Alpine.js/Livewire. Good for all chart types above |
| **ApexCharts** | Richer interactivity, built-in tooltips, responsive. Slightly heavier |

---

## 7. Component Swap Behavior

### 7.1 State Transitions

```
[Page Load] → Overview Mode (all planners)
    │
    ├── [Select planner from F-05 dropdown] → Planner Detail Mode
    ├── [Click planner name in T-01 table] → Planner Detail Mode
    │
    └── Planner Detail Mode
        │
        ├── [Click "Back to Overview" PH-05] → Overview Mode
        ├── [Clear planner in F-05] → Overview Mode
        └── [Change date range F-01/F-02] → Refresh same mode with new dates
```

### 7.2 URL State Sync

| Parameter | Maps To | Default |
|-----------|---------|---------|
| `?planner=` | F-05 Planner Selector | empty (overview) |
| `?from=` | F-01 Start Date | Current week start |
| `?to=` | F-01 End Date | Current week end |
| `?granularity=` | F-03 Granularity | daily |
| `?region=` | F-04 Region Filter | all |
| `?tab=` | Active sub-tab (activity/permissions/charts) | activity |

Using Livewire `#[Url]` attribute for all — supports deep linking and back/forward.

### 7.3 Tabs Within Each Mode

**Overview Mode Tabs:**
1. **Summary** — Stat cards + comparison table (default)
2. **Daily Breakdown** — Day-by-day table with all planners
3. **Charts** — C-01 through C-04
4. **Permissions** — PM section + UP table

**Planner Detail Mode Tabs:**
1. **Activity** — KPIs + daily table (default)
2. **Assessments** — Assessment breakdown table
3. **Charts** — C-05 through C-09
4. **Permissions** — Planner-specific permission view

---

## 8. Data Source Mapping

### 8.1 WorkStudio API Tables Used

| Table | Key Fields Used | Purpose |
|-------|----------------|---------|
| **VEGUNIT** | JOBGUID, STATNAME, UNIT, FORESTER, FRSTR_USER, ASSDDATE, EDITDATE, PERMSTAT, PERMDATE | Core unit data |
| **STATIONS** | JOBGUID, STATNAME, SPANLGTH | Station footage (meters) |
| **VEGJOB** | JOBGUID, CONTRACTOR, CYCLETYPE, TITLE, WO | Assessment metadata |
| **SS** | JOBGUID, JOBTYPE, STATUS, TAKENBY, SCOPE_YEAR | Assessment status/type |

### 8.2 Local Database Tables

| Table | Purpose |
|-------|---------|
| **circuits** | Circuit name → region mapping |
| **regions** | Region display names |
| **unit_types** | Unit classification (work vs non-work) via `work_unit` boolean |
| **ws_users** | Planner display names, enriched user data |

### 8.3 Computed / Derived Metrics

| Metric | Derivation |
|--------|-----------|
| Footage per planner per day | First Unit Wins rule → SUM(SPANLGTH) for stations where planner has rank=1 |
| Quota status | Footage / (6.5 * weeks) |
| Days waiting (permissions) | CURRENT_DATE - ASSDDATE where PERMSTAT is pending |
| Rank among planners | RANK() over period footage |

---

## Review Checklist

> **Arbman — please review each section and mark:**
> - [x] Keep as-is
> - [ ] Modify (describe change)
> - [ ] Remove
> - [ ] Add new item

### Sections to Review:
- [ ] 2. Global Filters & Selectors
- [ ] 3. All-Planners Overview
- [ ] 4. Individual Planner Detail
- [ ] 5. Permissioning Tracker
- [ ] 6. Charts & Visualizations
- [ ] 7. Component Swap Behavior

---

*Generated by BMad Master — Planner Analytics Design Planning*

# Planner Analytics Page — User Flow Chart

> **Created:** 2026-02-10
> **Status:** DRAFT

---

## Primary User Flow

```mermaid
flowchart TD
    subgraph ENTRY["Page Entry"]
        A[User clicks 'Planner Analytics'<br/>in sidebar] --> B[Page loads at<br/>/planner-analytics]
        B --> C{URL has ?planner= ?}
        C -->|No| D[Overview Mode<br/>All Planners]
        C -->|Yes| E[Planner Detail Mode<br/>Specific Planner]
    end

    subgraph OVERVIEW["Overview Mode"]
        D --> D1[Show: Summary stat cards<br/>M-01 through M-06]
        D1 --> D2[Show: Planner comparison table<br/>T-01 through T-09]
        D2 --> D3[Show: Daily breakdown table<br/>D-01 through D-06]
        D3 --> D4[Show: Overview charts<br/>C-01 through C-04]
    end

    subgraph DETAIL["Planner Detail Mode"]
        E --> E1[Show: Planner header<br/>PH-01 through PH-05]
        E1 --> E2[Show: KPI cards<br/>PK-01 through PK-08]
        E2 --> E3[Show: Daily activity table<br/>PA-01 through PA-07]
        E3 --> E4[Show: Assessment breakdown<br/>AB-01 through AB-07]
        E4 --> E5[Show: Planner charts<br/>C-05 through C-09]
    end

    subgraph TRANSITIONS["Mode Transitions"]
        D2 -->|Click planner name<br/>in table row| T1[Set ?planner=name]
        T1 --> E

        E1 -->|Click 'Back to Overview'<br/>or clear planner filter| T2[Remove ?planner=]
        T2 --> D
    end

    subgraph FILTERS["Filter Interactions"]
        F1[Change date range<br/>F-01 / F-02] -->|Livewire round-trip| F2[Refresh current mode<br/>with new dates]
        F3[Change granularity<br/>F-03] -->|Toggle daily/weekly/monthly| F4[Re-aggregate<br/>charts & tables]
        F5[Filter region<br/>F-04] -->|Multi-select| F6[Filter table rows<br/>& recalc aggregates]
        F7[Select planner<br/>F-05 dropdown] -->|Sets ?planner=| E
        F8[Toggle unit of measure<br/>F-07] -->|Client-side Alpine.js| F9[Convert all<br/>displayed values]
    end

    subgraph PERMISSIONS["Permissions Tab"]
        P1[Click 'Permissions' tab] --> P2{In which mode?}
        P2 -->|Overview| P3[Show all unpermissioned<br/>PM-01..PM-04 + UP table]
        P2 -->|Detail| P4[Show planner-specific<br/>unpermissioned units]
    end
```

---

## Tab Navigation Flow

```mermaid
flowchart LR
    subgraph OVERVIEW_TABS["Overview Mode Tabs"]
        OT1[Summary<br/>default] -->|click| OT2[Daily Breakdown]
        OT2 -->|click| OT3[Charts]
        OT3 -->|click| OT4[Permissions]
        OT4 -->|click| OT1
    end

    subgraph DETAIL_TABS["Planner Detail Mode Tabs"]
        DT1[Activity<br/>default] -->|click| DT2[Assessments]
        DT2 -->|click| DT3[Charts]
        DT3 -->|click| DT4[Permissions]
        DT4 -->|click| DT1
    end
```

---

## Filter Cascade Flow

```mermaid
flowchart TD
    subgraph GLOBAL_FILTERS["Global Filters (Persist Across Modes)"]
        GF1[Date Range<br/>F-01] --> QUERY
        GF2[Quick Period<br/>F-02] --> GF1
        GF3[Granularity<br/>F-03] --> CHARTS
        GF4[Region<br/>F-04] --> QUERY
        GF5[Planner<br/>F-05] --> MODE
        GF6[Unit of Measure<br/>F-07] --> DISPLAY
    end

    MODE{Mode Decision}
    MODE -->|planner selected| DETAIL_QUERY[Query: single planner data]
    MODE -->|no planner| OVERVIEW_QUERY[Query: all planners data]

    QUERY[API Query Params] --> MODE
    DETAIL_QUERY --> RENDER[Render Components]
    OVERVIEW_QUERY --> RENDER

    CHARTS[Chart Aggregation] --> RENDER
    DISPLAY[Display Transform] --> RENDER
```

---

## URL State Machine

```mermaid
stateDiagram-v2
    [*] --> Overview: /planner-analytics
    Overview --> DetailView: ?planner=Alice
    DetailView --> Overview: clear planner

    Overview --> Overview: change dates
    DetailView --> DetailView: change dates

    state Overview {
        [*] --> SummaryTab
        SummaryTab --> DailyTab: ?tab=daily
        DailyTab --> ChartsTab: ?tab=charts
        ChartsTab --> PermissionsTab: ?tab=permissions
        PermissionsTab --> SummaryTab: ?tab=summary
    }

    state DetailView {
        [*] --> ActivityTab
        ActivityTab --> AssessmentsTab: ?tab=assessments
        AssessmentsTab --> PlannerChartsTab: ?tab=charts
        PlannerChartsTab --> PlannerPermissionsTab: ?tab=permissions
        PlannerPermissionsTab --> ActivityTab: ?tab=activity
    }
```

---

## Component Architecture

```mermaid
flowchart TD
    subgraph PAGE["PlannerAnalytics (Livewire Full-Page Component)"]
        FILTERS_BAR[FilterBar<br/>Date, Period, Region, Planner, Granularity]

        subgraph CONTENT["Content Area (swapped by mode)"]
            subgraph OV["Overview Components"]
                OV_STATS[OverviewStats<br/>M-01..M-06]
                OV_TABLE[PlannerComparisonTable<br/>T-01..T-09]
                OV_DAILY[DailyBreakdownTable<br/>D-01..D-06]
                OV_CHARTS[OverviewCharts<br/>C-01..C-04]
                OV_PERMS[PermissionsOverview<br/>PM + UP table]
            end

            subgraph DT["Detail Components"]
                DT_HEADER[PlannerHeader<br/>PH-01..PH-05]
                DT_KPIS[PlannerKPIs<br/>PK-01..PK-08]
                DT_ACTIVITY[DailyActivityTable<br/>PA-01..PA-07]
                DT_ASSESS[AssessmentBreakdown<br/>AB-01..AB-07]
                DT_CHARTS[PlannerCharts<br/>C-05..C-09]
                DT_PERMS[PlannerPermissions]
            end
        end
    end

    FILTERS_BAR --> CONTENT
```

---

## Interaction Patterns

### Click Planner Name (Overview -> Detail)
```
1. User clicks "Alice Johnson" in comparison table
2. Livewire sets $selectedPlanner = "Alice Johnson"
3. URL updates: ?planner=Alice+Johnson
4. Content area transitions (optional: Alpine.js fade)
5. Planner detail components mount with Alice's data
6. Charts render with Alice's historical data
```

### Change Date Range
```
1. User picks new dates in date picker
2. Livewire property updates ($dateFrom, $dateTo)
3. URL updates: ?from=2026-01-06&to=2026-01-12
4. All visible components re-query with new dates
5. Charts re-render with new data range
6. Loading states shown during fetch
```

### Switch Tab
```
1. User clicks "Charts" tab
2. Alpine.js tab component shows charts pane
3. URL updates: ?tab=charts
4. If chart data not yet loaded, Livewire fetches it
5. Chart.js renders in the visible container
```

---

*Generated by BMad Master — Planner Analytics User Flow*

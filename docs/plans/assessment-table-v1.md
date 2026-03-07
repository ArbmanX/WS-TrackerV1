# Assessment Table v1

## Overview

A simple two-panel layout for viewing assessments. Right side displays a clickable table of active assessments. Left side displays details for the selected assessment. This is the first iteration — data first, design later.

## Layout

- **Left 1/3** — Assessment detail viewport (selected row)
- **Right 2/3** — Assessment table (list)

## Data Sources (local Postgres only)

- `assessments`
- `assessment_metrics`
- `assessment_contributors`

## Table Columns

| # | Column | Source |
|---|--------|--------|
| 1 | Work Order + Extension | `assessments.work_order` + `assessments.extension` |
| 2 | Status | `assessments.status` |
| 3 | Percent Complete | `assessments.percent_complete` |
| 4 | Region | `assessments.region` |
| 5 | Assigned To | `assessments.assigned_to` |
| 6 | Oldest Pending Date | `assessment_metrics.oldest_pending_date` |
| 7 | Total Units | `assessment_metrics.total_units` |
| 8 | Approved / Pending / Refused | `assessment_metrics.approved`, `.pending`, `.refused` |
| 9 | Stations With Work | `assessment_metrics.stations_with_work` |
| 10 | Last Edited | `assessments.last_edited` |

## Default Filter & Sort

- **Filter:** `assessments.status = 'ACTIV'`
- **Sort:** `assessment_metrics.oldest_pending_date` ASC (oldest first)

## Behavior

- Rows are clickable — selecting a row populates the left detail panel
- Detail panel will eventually display individual unit data, stations, etc.
- Current table data represents roughly 1/3 of what the detail card will eventually show

## Out of Scope (future iterations)

- Detail panel content beyond basic assessment info
- Individual unit data (WO+EXT+STATNAME+SEQUENCE)
- Design/styling decisions
- assessment_monitors table
- Filtering/search UI

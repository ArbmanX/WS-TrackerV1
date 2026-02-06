---
title: 'Planner Daily Activity Query - BMAD Prompt'
created: '2026-02-02'
optimized_by: 'Lyra'
target_agent: 'BMAD Master / Architect'
status: 'ready-for-use'
---

# Planner Daily Activity Query — Implementation Prompt

> **Usage:** Feed this prompt to BMAD Master or use with `/bmad:bmm:workflows:quick-spec` to generate a complete tech-spec.

---

## The Prompt

You are a senior software architect specializing in Laravel applications with complex SQL query optimization. I need you to design and implement a **Planner Daily Activity Query System** for tracking vegetation management planner productivity.

### Domain Context

**Business Domain:** Utility vegetation management planning for powerline rights-of-way (ROW).

**Workflow Lifecycle:**
```
Circuit → Assessment → Planning → QC → Work Jobs
           ↓
    (1+ planners work on stations/units)
           ↓
    (QC audits: pass → close, fail → rework)
```

**Key Entities:**
- **Circuit:** A powerline route belonging to a region (1 mile to 120+ miles)
- **Assessment:** A planning job for a circuit's vegetation maintenance (identified by `JOBGUID` + `WO` + `EXT`)
- **Station:** A span between poles (measured in meters via `SPANLGTH`)
- **Unit:** A work item placed at a station by a planner (tracked via `VEGUNIT` table)

**Tables:** `STATIONS` and `VEGUNIT` (external WorkStudio API)
- Reference: Database dictionary at the WorkStudio documentation endpoint
- Existing query patterns: `app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php`

---

### Business Rules (CRITICAL)

#### Station Completion Rules

| Rule | Description |
|------|-------------|
| **First Unit Wins** | The planner who creates the FIRST unit in a station gets footage credit for that station |
| **One-Time Credit** | A station's footage is credited ONLY on the date of its first assessed unit |
| **Multi-Planner Stations** | If Planner B adds units to a station Planner A started, only unit counts go to B (not footage) |
| **Excluded Stations** | Stations where `STATNAME` contains 'EX' prefix AND has non-work units only |

#### Unit Classification

| Type | Examples | Counts as Unit? | Counts for Footage? |
|------|----------|-----------------|---------------------|
| **Work Units** | `SPM`, `SPB`, `MPM`, `MPB`, `REM612`, `BRUSH`, etc. | ✅ Yes | ✅ Yes (if first) |
| **Non-Work Units** | `NW`, `NOT`, `SENSI` | ❌ No | ✅ Yes (if first) |
| **Empty/Null** | `NULL`, `''` | ❌ Excluded | ❌ Excluded |

#### Data Quality Flags

| Condition | Action |
|-----------|--------|
| Station has BOTH `NW` AND a work unit | Flag as data quality issue (invalid combination) |
| Station has `NW` + `NOT` or `NW` + `SENSI` | Valid (NOT=notification, SENSI=sensitive customer) |

#### Measurement Conversions

- Station `SPANLGTH` is in **meters**
- Convert to **feet** for `planned_stations_with_length` arrays
- Convert to **miles** for totals (1 mile = 1609.34 meters)

#### Filtering Constraints (from config)

- `cycle_types`: Exclude reactive, storm follow-up, etc.
- `job_types`: Include only Assessment, Assessment Dx, Split_Assessment, Tandem_Assessment
- `contractors`: Filter by configured contractor(s)
- `non_work_units`: `['NW', 'NOT', 'SENSI']` (configurable)

---

### Function Specification

```php
/**
 * Get daily activity metrics for planner(s) within date range.
 *
 * @param string|array|null $planner  Username (ASPLUNDH\tgibson) or full name ('Toni Gibson'),
 *                                     or array of mixed formats, or null for all planners
 * @param string|array|null $dates    Single date 'MM-DD-YYYY', range ['MM-DD-YYYY', 'MM-DD-YYYY'],
 *                                     or null for current date (with retry -1 day if no results)
 * @param string $groupBy             'planner-assessment-date' (default) or 'planner-date-assessments'
 * @return array
 */
public function getDailyActivity(
    string|array|null $planner = null,
    string|array|null $dates = null,
    string $groupBy = 'planner-assessment-date'
): array
```

**Planner Matching:**
- Compare against `VEGUNIT.FORESTER` (username format: `DOMAIN\username`)
- Compare against `VEGUNIT.FRSTR_USER` (may contain full name)
- Support mixed input: `['ASPLUNDH\tgibson', 'John Smith']`

**Date Handling:**
- Compare against `VEGUNIT.ASSDDATE` (assessment date)
- Track `VEGUNIT.EDITDATE` for change monitoring
- If `$dates = null` and `$planner = null`: use current date, retry -1 day until results found
- Week ending date = Saturday of that week

---

### Response Structure

**Option A: `groupBy = 'planner-assessment-date'`** (drill-down by assessment)
```php
[
    'tgibson' => [
        'username' => 'ASPLUNDH\tgibson',
        'display_name' => 'Toni Gibson',
        'date_range' => '01-21-2026 : 01-31-2026',
        'total_miles' => 45.7,
        'weekly_quota' => 6.5,
        'assessments' => [
            '2025-1092' => [
                'JOBGUID' => 'abc-123-def',
                'line_name' => 'CIRCUIT_NAME',
                'region' => 'REGION_1',
                'daily_activity' => [
                    '01-21-2026' => [
                        'week_ending_date' => '01-25-2026',
                        'total_footage' => 6000,
                        'total_miles' => 1.14,
                        'total_units' => 78,
                        'total_completed_stations' => 25,
                        'total_nw_stations' => 10,
                        'stations_by_outside_planners' => 3,
                        'planned_stations_with_length' => [
                            'nw_stations' => ['EX12345' => 345.34],
                            'work_stations' => ['10' => 252.7, '20' => 333.3]
                        ],
                        'unit_counts' => ['SPM' => 5, 'SPB' => 10, 'REM612' => 3],
                        'data_quality_flags' => []
                    ]
                ]
            ]
        ]
    ]
]
```

**Option B: `groupBy = 'planner-date-assessments'`** (timeline view)
```php
[
    'tgibson' => [
        'username' => 'ASPLUNDH\tgibson',
        'display_name' => 'Toni Gibson',
        'date_range' => '01-21-2026 : 01-31-2026',
        'total_miles' => 45.7,
        'weekly_quota' => 6.5,
        'daily_activity' => [
            '01-21-2026' => [
                'week_ending_date' => '01-25-2026',
                'day_total_miles' => 3.2,
                'day_total_units' => 156,
                'assessments' => [
                    '2025-1092' => [
                        'JOBGUID' => 'abc-123-def',
                        'total_footage' => 6000,
                        'total_miles' => 1.14,
                        'total_units' => 78,
                        // ... same detail structure
                    ]
                ]
            ]
        ]
    ]
]
```

---

### Performance & Architecture Requirements

#### Query Chunking Strategy

| Condition | Chunk Size | Reason |
|-----------|------------|--------|
| Planner set, no date | 7-day chunks (Sun-Sat) | Full history = large dataset |
| No planner, date range > 7 days | 7-day chunks (Sun-Sat) | All planners = large dataset |
| Single planner, single date | No chunking | Small dataset |
| Single planner, ≤7 day range | No chunking | Manageable dataset |

**Implementation:**
- Use Laravel queue workers for chunked requests
- Merge chunk results into final response
- Week boundaries: Sunday start → Saturday end (week_ending_date)

#### SQL Optimization Goals

1. **Do heavy lifting in SQL:** Aggregations, groupings, first-unit detection via `ROW_NUMBER()`
2. **Minimize round trips:** Use `CROSS APPLY` or subqueries for related data
3. **Return JSON:** Use `FOR JSON PATH` for structured responses from API

#### Station First-Unit Detection Pattern

```sql
-- Identify first unit per station per assessment
ROW_NUMBER() OVER (
    PARTITION BY VEGUNIT.JOBGUID, VEGUNIT.STATNAME
    ORDER BY VEGUNIT.ASSDDATE ASC, VEGUNIT.EDITDATE ASC
) AS unit_sequence

-- Only credit footage where unit_sequence = 1
```

---

### Deliverables Requested

1. **Business Rules Document** — Formalized rules for station/unit attribution with edge cases
2. **Data Flow Diagram** — Visual lifecycle of station completion and footage attribution
3. **Query Class** — `PlannerActivityQueries.php` with SQL builders following existing patterns
4. **Service Class** — `PlannerActivityService.php` with `getDailyActivity()` method
5. **Config Updates** — Add `non_work_units` array to `ws_assessment_query.php`
6. **Queue Job** — For chunked large-dataset requests

---

### Reference Files

- Existing query patterns: `app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php`
- SQL fragment helpers: `app/Services/WorkStudio/AssessmentsDx/Queries/SqlFragmentHelpers.php`
- Config: `config/ws_assessment_query.php`
- Field definitions: `config/workstudio_fields.php`

---

### Quota Context

- **Weekly quota:** 6.5 miles per planner
- Planners can work multiple assessments per day/week to meet quota
- Response should include quota tracking fields for UI display

---

## End of Prompt

---

## Lyra's Optimization Notes

### Techniques Applied

| Technique | Application |
|-----------|-------------|
| **Role Assignment** | Senior software architect with SQL optimization expertise |
| **Constraint Optimization** | Explicit business rules table, edge cases documented |
| **Task Decomposition** | Clear deliverables list with specific file names |
| **Context Layering** | Domain context → Business rules → Technical spec → Architecture |
| **Few-Shot Structure** | Response structure examples for both groupBy options |

### Key Improvements Over Original

1. **Formalized business rules** into scannable tables
2. **Clarified edge cases** (NW + work unit = flag, NOT/SENSI coexistence)
3. **Added quota context** (6.5 miles/week requirement)
4. **Specified chunking strategy** with clear conditions
5. **Included SQL optimization patterns** (ROW_NUMBER for first-unit detection)
6. **Defined both response structures** for `groupBy` parameter
7. **Referenced existing codebase patterns** for consistency

### Pro Tips for Usage

- Run with `/bmad:bmm:workflows:quick-spec` for full tech-spec generation
- Or use `/bmad:bmm:agents:architect` for architecture-focused discussion
- The data flow diagram request pairs well with `/bmad:bmm:workflows:create-excalidraw-flowchart`

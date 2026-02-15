# Planner Activity Query — Complete Reference

> **Query ID:** `WQ-001` — Active Assessments Planner Activity
> **Author:** Query Specialist Agent
> **Created:** 2026-02-05
> **Source Session:** `export2-5-26SQL.txt`
> **Status:** Design Complete — Ready for PHP Integration

---

## Table of Contents

1. [Requirements Summary](#1-requirements-summary)
2. [Database Tables Involved](#2-database-tables-involved)
3. [JOIN Chain Explained](#3-join-chain-explained)
4. [Business Rules Encoded in SQL](#4-business-rules-encoded-in-sql)
5. [CTE Breakdown (Step-by-Step)](#5-cte-breakdown-step-by-step)
6. [Full SQL — Version A: Auto-Detect Last Active Date](#6-full-sql--version-a-auto-detect-last-active-date)
7. [Full SQL — Version B: With Date Range](#7-full-sql--version-b-with-date-range)
8. [PHP Integration Pattern](#8-php-integration-pattern)
9. [Output Columns Reference](#9-output-columns-reference)
10. [Performance Considerations](#10-performance-considerations)
11. [Helper Methods Referenced](#11-helper-methods-referenced)
12. [Configuration Dependencies](#12-configuration-dependencies)
13. [Related Files](#13-related-files)

---

## 1. Requirements Summary

| # | Requirement | SQL Implementation |
|---|-------------|-------------------|
| 1 | ACTIV assessments only | `SS.STATUS = 'ACTIV'` |
| 2 | Taken by ASPLUNDH user | `SS.TAKEN = 1 AND SS.TAKENBY LIKE 'ASPLUNDH%'` |
| 3 | Optional date range | PHP conditionally interpolates dates into WHERE clause |
| 4 | Auto-detect last active day | `LastActiveDay` CTE finds MAX across `VEGUNIT.ASSDDATE` and `VEGUNIT.EDITDATE` |
| 5 | First Unit Wins (footage) | `ROW_NUMBER() OVER (PARTITION BY JOBGUID, STATNAME ORDER BY ASSDDATE ASC, EDITDATE ASC)` |
| 6 | Work vs Non-Work classification | `NW`, `NOT`, `SENSI` = non-work; everything else = work |
| 7 | Multi-planner tracking | `StationOwners` CTE compares `unit_rank = 1` forester vs current row forester |
| 8 | Scope year filtering | `WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{SCOPE_YEAR}%'` |

---

## 2. Database Tables Involved

### SS (Work Orders / Assessments)
- **Role in this query:** Root table — filters ACTIV assessments taken by ASPLUNDH users
- **Key columns used:** `JOBGUID` (PK), `WO`, `EXT`, `STATUS`, `TAKEN`, `TAKENBY`, `JOBTYPE`
- **Filter logic:** `STATUS = 'ACTIV'`, `TAKEN = 1`, `TAKENBY LIKE 'ASPLUNDH%'`

### VEGJOB (Vegetation Job Details)
- **Role in this query:** Assessment metadata — region, line name, total/completed miles
- **Key columns used:** `JOBGUID` (FK→SS), `LINENAME`, `REGION`, `CYCLETYPE`, `LENGTH`, `LENGTHCOMP`, `PRCENT`, `CONTRACTOR`
- **Filter logic:** Exclude reactive/storm cycle types; filter by contractor

### WPStartDate_Assessment_Xrefs (Work Program Cross-Reference)
- **Role in this query:** Scope year filtering — links assessments to work program year
- **Key columns used:** `Assess_JOBGUID` (FK→SS.JOBGUID), `WP_STARTDATE`
- **Filter logic:** `WP_STARTDATE LIKE '%2026%'` (scope year from config)

### VEGUNIT (Vegetation Units — Property/Customer Level)
- **Role in this query:** Core data — unit records with planner, dates, permission status
- **Key columns used:** `JOBGUID` (FK→SS), `STATNAME`, `UNIT`, `FORESTER`, `FRSTR_USER`, `ASSDDATE`, `EDITDATE`, `PERMSTAT`
- **Why it's critical:** Every row = one unit placed by a planner at a station. The entire "First Unit Wins" logic and planner attribution is built on this table.

### STATIONS (Pole Spans)
- **Role in this query:** Footage data — each station has a span length in meters
- **Key columns used:** `JOBGUID` (FK→SS), `STATNAME`, `SPANLGTH`
- **Why needed:** `SPANLGTH` (meters) is converted to miles (`÷ 1609.34`) for footage credit

---

## 3. JOIN Chain Explained

```
SS ──(JOBGUID)──→ VEGJOB
│                    Assessment metadata: region, miles, cycle type
│
├──(JOBGUID)──→ WPStartDate_Assessment_Xrefs
│                    Scope year filter (LEFT JOIN — some assessments may lack xref)
│
├──(JOBGUID)──→ VEGUNIT (via RankedUnits CTE)
│                    Unit data: planner, dates, permission status
│                    ROW_NUMBER() ranks units per station
│
└──(JOBGUID + STATNAME)──→ STATIONS
                             Span length (footage) for each station
```

**Why this chain works:**
- `SS` is the anchor — it holds the assessment status, ownership, and job type
- `VEGJOB` extends SS with vegetation-specific metadata (1:1 relationship via JOBGUID)
- `WPStartDate_Assessment_Xrefs` is a LEFT JOIN because not all assessments have a work program xref row — we don't want to lose valid assessments
- `VEGUNIT` is the many-to-one child — each assessment has hundreds of units across dozens of stations
- `STATIONS` provides the physical span length; joined on both `JOBGUID` AND `STATNAME` to get the correct station within the correct assessment

---

## 4. Business Rules Encoded in SQL

### 4.1 First Unit Wins (Footage Attribution)

```
The planner who creates the FIRST unit in a station gets 100% of that
station's footage credit.
```

**SQL Implementation:**
```sql
ROW_NUMBER() OVER (
    PARTITION BY V.JOBGUID, V.STATNAME
    ORDER BY V.ASSDDATE ASC, V.EDITDATE ASC
) AS unit_rank
```

- `PARTITION BY JOBGUID, STATNAME` — rank resets for each station within each assessment
- `ORDER BY ASSDDATE ASC, EDITDATE ASC` — earliest assessed date wins; ties broken by edit date
- Only rows where `unit_rank = 1` get footage credit in the main SELECT

**Why this is deterministic:** The dual-column ORDER BY ensures that even if two units share the same `ASSDDATE`, the one with the earlier `EDITDATE` wins. This eliminates non-deterministic ranking.

### 4.2 One-Time Credit

```
A station's footage is credited ONLY ONCE — on the date its first unit
was created.
```

**SQL Implementation:**
```sql
SUM(CASE WHEN R.unit_rank = 1
    THEN ISNULL(S.SPANLGTH, 0) ELSE 0 END
) / 1609.34
```

- `unit_rank = 1` guard ensures footage only counted for the first unit
- `ISNULL(S.SPANLGTH, 0)` handles stations with NULL span length gracefully
- Division by `1609.34` converts meters to miles

### 4.3 Work vs Non-Work Units

| Type | Unit Codes | Gets Footage? | Gets Unit Count? |
|------|-----------|---------------|------------------|
| **Work** | SPM, SPB, MPM, MPB, REM*, ASH*, VPS, BRUSH, HCB, BRUSHTRIM, HERBA, HERBNA | Yes (if first) | Yes |
| **Non-Work** | NW, NOT, SENSI | Yes (if first) | No |
| **Invalid** | NULL, empty string | No | No |

**SQL Implementation:**
```sql
-- Work units (excludes NW, NOT, SENSI)
COUNT(CASE WHEN R.UNIT NOT IN ('NW', 'NOT', 'SENSI') THEN 1 END) AS Work_Unit_Count

-- Non-work units
COUNT(CASE WHEN R.UNIT IN ('NW', 'NOT', 'SENSI') THEN 1 END) AS Non_Work_Unit_Count
```

**Important nuance:** Non-work units like NW *can* still earn footage if they're the first unit at a station (unit_rank = 1). They just don't count toward work unit totals. This is intentional — a planner who marks a station as "No Work" still walked the span.

### 4.4 Multi-Planner Stations

```
If Planner B adds units to a station that Planner A started:
  - Planner A gets: footage credit (on their date)
  - Planner B gets: unit counts only (on their dates)
```

**SQL Implementation (StationOwners CTE + main SELECT):**
```sql
-- CTE: capture who was first at each station
StationOwners AS (
    SELECT JOBGUID, STATNAME, FORESTER AS Station_Owner
    FROM RankedUnits
    WHERE unit_rank = 1
)

-- Main SELECT: count stations where someone else was first
COUNT(DISTINCT
    CASE WHEN SO.Station_Owner IS NOT NULL
         AND SO.Station_Owner != R.FORESTER
        THEN R.STATNAME END
) AS Stations_By_Outside_Planners
```

**Why a separate CTE instead of a correlated subquery:**
The original design draft used a `SELECT TOP 1` correlated subquery inside CASE WHEN, which would execute once per row — an N+1 pattern at the SQL level. The `StationOwners` CTE pre-computes the answer once and joins it, making it O(1) per row lookup.

---

## 5. CTE Breakdown (Step-by-Step)

This query uses **4 CTEs** (Common Table Expressions) that build on each other in a pipeline:

### CTE 1: `ActiveAssessments`

**Purpose:** Narrow the scope to only the assessments we care about.

**What it does:**
1. Joins `SS` → `VEGJOB` → `WPStartDate_Assessment_Xrefs`
2. Applies ALL filters: ACTIV status, TAKEN by ASPLUNDH%, scope year, valid job types, excluded cycle types, contractor filter, excluded users
3. Returns assessment-level metadata (WO, region, miles, etc.)

**Why it's first:** By filtering at the assessment level before touching VEGUNIT (which has 100x more rows), we dramatically reduce the data volume for all downstream CTEs.

```sql
WITH ActiveAssessments AS (
    SELECT
        SS.JOBGUID, SS.WO, SS.EXT, SS.TAKENBY,
        VEGJOB.LINENAME, VEGJOB.REGION, VEGJOB.CYCLETYPE,
        CAST(VEGJOB.LENGTH AS DECIMAL(10,2)) AS Total_Miles,
        CAST(VEGJOB.LENGTHCOMP AS DECIMAL(10,2)) AS Completed_Miles,
        VEGJOB.PRCENT AS Percent_Complete
    FROM SS
    INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
    LEFT JOIN WPStartDate_Assessment_Xrefs
        ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID
    WHERE SS.STATUS = 'ACTIV'
      AND SS.TAKEN = 1
      AND SS.TAKENBY LIKE 'ASPLUNDH%'
      AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{SCOPE_YEAR}%'
      AND SS.JOBTYPE IN ({JOB_TYPES})
      AND VEGJOB.CYCLETYPE NOT IN ({CYCLE_TYPES})
      AND VEGJOB.CONTRACTOR IN ({CONTRACTORS})
      AND SS.TAKENBY NOT IN ({EXCLUDED_USERS})
)
```

### CTE 2: `LastActiveDay` (conditional — only when no date range provided)

**Purpose:** Auto-detect the most recent day with any unit activity.

**What it does:**
1. Scans all VEGUNIT rows joined to our ActiveAssessments
2. Parses the Microsoft JSON date format (`/Date(1234567890000)/`) to SQL DATE
3. Takes the MAX across both `ASSDDATE` (when unit was assessed) and `EDITDATE` (when unit was last edited)
4. Uses `UNION ALL` to combine both date sources, then takes the overall MAX

**Why UNION ALL instead of UNION:** We don't need deduplication here — we're just finding MAX. `UNION ALL` avoids the sort/distinct step.

```sql
LastActiveDay AS (
    SELECT MAX(ActivityDate) AS Target_Date
    FROM (
        SELECT CAST(CAST(REPLACE(REPLACE(V.ASSDDATE, '/Date(', ''), ')/', '')
            AS DATETIME) AS DATE) AS ActivityDate
        FROM VEGUNIT V
        INNER JOIN ActiveAssessments A ON V.JOBGUID = A.JOBGUID
        WHERE V.ASSDDATE IS NOT NULL AND V.ASSDDATE != ''
          AND V.UNIT IS NOT NULL AND V.UNIT != ''
        UNION ALL
        SELECT CAST(CAST(REPLACE(REPLACE(V.EDITDATE, '/Date(', ''), ')/', '')
            AS DATETIME) AS DATE)
        FROM VEGUNIT V
        INNER JOIN ActiveAssessments A ON V.JOBGUID = A.JOBGUID
        WHERE V.EDITDATE IS NOT NULL
          AND V.UNIT IS NOT NULL AND V.UNIT != ''
    ) AS AllDates
)
```

### CTE 3: `RankedUnits`

**Purpose:** Assign a rank to every unit within its station — first unit gets rank 1.

**What it does:**
1. Joins VEGUNIT to ActiveAssessments (filters down to only our target assessments)
2. Excludes invalid units (NULL, empty, missing ASSDDATE)
3. Parses both ASSDDATE and EDITDATE from Microsoft JSON format to SQL DATE
4. Applies `ROW_NUMBER() OVER (PARTITION BY JOBGUID, STATNAME ORDER BY ASSDDATE ASC, EDITDATE ASC)` — this is the core of the "First Unit Wins" rule

**Key columns produced:**
- `Activity_Date` — parsed ASSDDATE as a clean SQL DATE
- `Edit_Date` — parsed EDITDATE as a clean SQL DATE
- `unit_rank` — 1 = first unit at this station (gets footage), 2+ = subsequent units (unit count only)

```sql
RankedUnits AS (
    SELECT
        V.JOBGUID, V.STATNAME, V.UNIT, V.FORESTER, V.FRSTR_USER,
        V.ASSDDATE, V.EDITDATE, V.PERMSTAT,
        CAST(CAST(REPLACE(REPLACE(V.ASSDDATE, '/Date(', ''), ')/', '')
            AS DATETIME) AS DATE) AS Activity_Date,
        CAST(CAST(REPLACE(REPLACE(V.EDITDATE, '/Date(', ''), ')/', '')
            AS DATETIME) AS DATE) AS Edit_Date,
        ROW_NUMBER() OVER (
            PARTITION BY V.JOBGUID, V.STATNAME
            ORDER BY V.ASSDDATE ASC, V.EDITDATE ASC
        ) AS unit_rank
    FROM VEGUNIT V
    INNER JOIN ActiveAssessments A ON V.JOBGUID = A.JOBGUID
    WHERE V.UNIT IS NOT NULL
      AND V.UNIT != ''
      AND V.ASSDDATE IS NOT NULL
      AND V.ASSDDATE != ''
)
```

### CTE 4: `StationOwners`

**Purpose:** Pre-compute who "owns" each station (the first planner) for multi-planner detection.

**What it does:**
1. Filters RankedUnits to only `unit_rank = 1` rows
2. Captures the `FORESTER` (planner name) as `Station_Owner`
3. Creates a lookup table: for any (JOBGUID, STATNAME) pair, who was first?

**Why it's lightweight:** It only reads from the already-computed RankedUnits CTE and applies a simple WHERE filter — no additional table scans.

```sql
StationOwners AS (
    SELECT JOBGUID, STATNAME, FORESTER AS Station_Owner
    FROM RankedUnits
    WHERE unit_rank = 1
)
```

---

## 6. Full SQL — Version A: Auto-Detect Last Active Date

This version is used when **no date range is provided**. It automatically finds the most recent day with any assessed or edited units and returns activity for that day only.

```sql
WITH ActiveAssessments AS (
    SELECT
        SS.JOBGUID,
        SS.WO,
        SS.EXT,
        SS.TAKENBY,
        VEGJOB.LINENAME,
        VEGJOB.REGION,
        VEGJOB.CYCLETYPE,
        CAST(VEGJOB.LENGTH AS DECIMAL(10,2)) AS Total_Miles,
        CAST(VEGJOB.LENGTHCOMP AS DECIMAL(10,2)) AS Completed_Miles,
        VEGJOB.PRCENT AS Percent_Complete
    FROM SS
    INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
    LEFT JOIN WPStartDate_Assessment_Xrefs
        ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID
    WHERE SS.STATUS = 'ACTIV'
      AND SS.TAKEN = 1
      AND SS.TAKENBY LIKE 'ASPLUNDH%'
      AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{SCOPE_YEAR}%'
      AND SS.JOBTYPE IN ({JOB_TYPES})
      AND VEGJOB.CYCLETYPE NOT IN ({CYCLE_TYPES})
      AND VEGJOB.CONTRACTOR IN ({CONTRACTORS})
      AND SS.TAKENBY NOT IN ({EXCLUDED_USERS})
),

LastActiveDay AS (
    SELECT MAX(ActivityDate) AS Target_Date
    FROM (
        SELECT CAST(CAST(REPLACE(REPLACE(V.ASSDDATE, '/Date(', ''), ')/', '')
            AS DATETIME) AS DATE) AS ActivityDate
        FROM VEGUNIT V
        INNER JOIN ActiveAssessments A ON V.JOBGUID = A.JOBGUID
        WHERE V.ASSDDATE IS NOT NULL AND V.ASSDDATE != ''
          AND V.UNIT IS NOT NULL AND V.UNIT != ''
        UNION ALL
        SELECT CAST(CAST(REPLACE(REPLACE(V.EDITDATE, '/Date(', ''), ')/', '')
            AS DATETIME) AS DATE)
        FROM VEGUNIT V
        INNER JOIN ActiveAssessments A ON V.JOBGUID = A.JOBGUID
        WHERE V.EDITDATE IS NOT NULL
          AND V.UNIT IS NOT NULL AND V.UNIT != ''
    ) AS AllDates
),

RankedUnits AS (
    SELECT
        V.JOBGUID,
        V.STATNAME,
        V.UNIT,
        V.FORESTER,
        V.FRSTR_USER,
        V.ASSDDATE,
        V.EDITDATE,
        V.PERMSTAT,
        CAST(CAST(REPLACE(REPLACE(V.ASSDDATE, '/Date(', ''), ')/', '')
            AS DATETIME) AS DATE) AS Activity_Date,
        CAST(CAST(REPLACE(REPLACE(V.EDITDATE, '/Date(', ''), ')/', '')
            AS DATETIME) AS DATE) AS Edit_Date,
        ROW_NUMBER() OVER (
            PARTITION BY V.JOBGUID, V.STATNAME
            ORDER BY V.ASSDDATE ASC, V.EDITDATE ASC
        ) AS unit_rank
    FROM VEGUNIT V
    INNER JOIN ActiveAssessments A ON V.JOBGUID = A.JOBGUID
    WHERE V.UNIT IS NOT NULL
      AND V.UNIT != ''
      AND V.ASSDDATE IS NOT NULL
      AND V.ASSDDATE != ''
),

StationOwners AS (
    SELECT JOBGUID, STATNAME, FORESTER AS Station_Owner
    FROM RankedUnits
    WHERE unit_rank = 1
)

SELECT
    -- Assessment identifiers
    A.JOBGUID                   AS Job_GUID,
    A.WO                        AS Work_Order,
    A.EXT                       AS Extension,
    A.LINENAME                  AS Line_Name,
    A.REGION                    AS Region,
    A.TAKENBY                   AS Current_Owner,
    A.Total_Miles,
    A.Completed_Miles,
    A.Percent_Complete,

    -- Planner & date
    R.FORESTER                  AS Planner,
    R.Activity_Date,

    -- Footage: only from stations where THIS unit was the FIRST
    CAST(
        SUM(CASE WHEN R.unit_rank = 1
            THEN ISNULL(S.SPANLGTH, 0) ELSE 0 END
        ) / 1609.34
        AS DECIMAL(10,4)
    )                           AS Footage_Miles,

    -- Station count: distinct stations where this planner was first
    COUNT(DISTINCT
        CASE WHEN R.unit_rank = 1
            THEN R.STATNAME END
    )                           AS Stations_Completed,

    -- Work units (excludes NW, NOT, SENSI)
    COUNT(
        CASE WHEN R.UNIT NOT IN ('NW', 'NOT', 'SENSI')
            THEN 1 END
    )                           AS Work_Unit_Count,

    -- Non-work units
    COUNT(
        CASE WHEN R.UNIT IN ('NW', 'NOT', 'SENSI')
            THEN 1 END
    )                           AS Non_Work_Unit_Count,

    COUNT(*)                    AS Total_Unit_Count,

    -- Stations where a DIFFERENT planner was first
    COUNT(DISTINCT
        CASE WHEN SO.Station_Owner IS NOT NULL
             AND SO.Station_Owner != R.FORESTER
            THEN R.STATNAME END
    )                           AS Stations_By_Outside_Planners

FROM ActiveAssessments A
INNER JOIN RankedUnits R
    ON A.JOBGUID = R.JOBGUID
LEFT JOIN STATIONS S
    ON R.JOBGUID = S.JOBGUID
    AND R.STATNAME = S.STATNAME
LEFT JOIN StationOwners SO
    ON R.JOBGUID = SO.JOBGUID
    AND R.STATNAME = SO.STATNAME

-- Auto-detect: use the most recent day with any activity
WHERE R.Activity_Date = (SELECT Target_Date FROM LastActiveDay)

GROUP BY
    A.JOBGUID, A.WO, A.EXT, A.LINENAME, A.REGION, A.TAKENBY,
    A.Total_Miles, A.Completed_Miles, A.Percent_Complete,
    R.FORESTER, R.Activity_Date

ORDER BY R.Activity_Date DESC, A.REGION, A.WO
```

---

## 7. Full SQL — Version B: With Date Range

When the user provides a start and end date, the `LastActiveDay` CTE is dropped entirely and the WHERE clause changes:

**Changes from Version A:**
1. Remove the `LastActiveDay` CTE
2. Replace the WHERE clause:

```sql
-- Replace:
WHERE R.Activity_Date = (SELECT Target_Date FROM LastActiveDay)

-- With:
WHERE R.Activity_Date >= '{START_DATE}'
  AND R.Activity_Date <= '{END_DATE}'
```

Everything else remains identical. The PHP method handles this conditional SQL generation (see Section 8).

---

## 8. PHP Integration Pattern

This follows the existing pattern in `AssessmentQueries.php` where methods return raw SQL strings with PHP-interpolated values.

```php
/**
 * Planner daily activity for ACTIV assessments taken by ASPLUNDH users.
 *
 * Implements "First Unit Wins" footage attribution:
 * - ROW_NUMBER() partitioned by (JOBGUID, STATNAME), ordered by ASSDDATE ASC
 * - Only unit_rank=1 gets footage credit
 * - Other planners get unit counts only
 *
 * @param string|null $startDate  'YYYY-MM-DD' or null for auto-detect
 * @param string|null $endDate    'YYYY-MM-DD' or null for auto-detect
 */
public function getActivAssessmentsPlannerActivity(
    ?string $startDate = null,
    ?string $endDate = null
): string {
    $cycleTypes = WSHelpers::toSqlInClause(
        config('ws_assessment_query.cycle_types')
    );

    // Only include LastActiveDay CTE when no dates provided
    $lastActiveDayCte = '';
    $dateFilter = '';

    if ($startDate !== null && $endDate !== null) {
        $dateFilter = "WHERE R.Activity_Date >= '{$startDate}'
                         AND R.Activity_Date <= '{$endDate}'";
    } else {
        $lastActiveDayCte = ",
        LastActiveDay AS (
            SELECT MAX(ActivityDate) AS Target_Date
            FROM (
                SELECT " . self::parseMsDateToDate('V.ASSDDATE') . " AS ActivityDate
                FROM VEGUNIT V
                INNER JOIN ActiveAssessments A ON V.JOBGUID = A.JOBGUID
                WHERE V.ASSDDATE IS NOT NULL AND V.ASSDDATE != ''
                  AND V.UNIT IS NOT NULL AND V.UNIT != ''
                UNION ALL
                SELECT " . self::parseMsDateToDate('V.EDITDATE') . "
                FROM VEGUNIT V
                INNER JOIN ActiveAssessments A ON V.JOBGUID = A.JOBGUID
                WHERE V.EDITDATE IS NOT NULL
                  AND V.UNIT IS NOT NULL AND V.UNIT != ''
            ) AS AllDates
        )";
        $dateFilter = "WHERE R.Activity_Date = (SELECT Target_Date FROM LastActiveDay)";
    }

    $parseDateAssd = self::parseMsDateToDate('V.ASSDDATE');
    $parseDateEdit = self::parseMsDateToDate('V.EDITDATE');

    return "WITH ActiveAssessments AS ( ... ),
            {$lastActiveDayCte}
            RankedUnits AS ( ... ),
            StationOwners AS ( ... )
            SELECT ...
            {$dateFilter}
            GROUP BY ...
            ORDER BY ...";
}
```

**Key integration points:**
- Uses `self::parseMsDateToDate()` from `SqlFragmentHelpers` trait (avoids duplicating the CAST/REPLACE pattern)
- Uses `WSHelpers::toSqlInClause()` for safe array-to-SQL conversion
- Config values (`cycle_types`, `job_types`, `contractors`, `excludedUsers`, `scope_year`) are pulled from `config/ws_assessment_query.php`
- The method returns a raw SQL string — it does NOT execute the query directly

---

## 9. Output Columns Reference

| Column | Type | Description | Source |
|--------|------|-------------|--------|
| `Job_GUID` | `uniqueidentifier` | Assessment identifier (primary key) | SS.JOBGUID |
| `Work_Order` | `varchar` | Work order number | SS.WO |
| `Extension` | `varchar` | Work order extension | SS.EXT |
| `Line_Name` | `varchar` | Circuit/line name | VEGJOB.LINENAME |
| `Region` | `varchar` | Geographic region (CENTRAL, HARRISBURG, etc.) | VEGJOB.REGION |
| `Current_Owner` | `varchar` | Who has the assessment checked out (ASPLUNDH\\username) | SS.TAKENBY |
| `Total_Miles` | `decimal(10,2)` | Total circuit length in miles | VEGJOB.LENGTH |
| `Completed_Miles` | `decimal(10,2)` | Miles assessed so far | VEGJOB.LENGTHCOMP |
| `Percent_Complete` | `float` | Assessment completion percentage | VEGJOB.PRCENT |
| `Planner` | `varchar` | Planner name (FORESTER field from VEGUNIT) | VEGUNIT.FORESTER |
| `Activity_Date` | `date` | Date of the activity (parsed from ASSDDATE) | VEGUNIT.ASSDDATE |
| `Footage_Miles` | `decimal(10,4)` | Miles credited to this planner (First Unit Wins only) | STATIONS.SPANLGTH / 1609.34 |
| `Stations_Completed` | `int` | Distinct stations where this planner was the first unit | COUNT(DISTINCT ...) |
| `Work_Unit_Count` | `int` | Work units placed (excludes NW, NOT, SENSI) | COUNT(CASE ...) |
| `Non_Work_Unit_Count` | `int` | Non-work units (NW, NOT, SENSI only) | COUNT(CASE ...) |
| `Total_Unit_Count` | `int` | All units placed by this planner on this date | COUNT(*) |
| `Stations_By_Outside_Planners` | `int` | Stations where a *different* planner was first | StationOwners CTE comparison |

---

## 10. Performance Considerations

| Concern | Severity | Mitigation |
|---------|----------|------------|
| 4 CTEs + 3 JOINs in one query | Medium | CTEs are evaluated once by SQL Server's optimizer; `ActiveAssessments` narrows scope early, reducing downstream row counts dramatically |
| `ROW_NUMBER()` on VEGUNIT | Medium | Partitioned by (JOBGUID, STATNAME) — scope is limited to one assessment's units at a time. Recommend covering index. |
| Date parsing (`REPLACE`/`CAST`) repeated | Low | WorkStudio stores dates in Microsoft JSON format (`/Date(epoch)/`) — there's no avoiding the CAST chain. Computed columns could help if DBA approves. |
| `UNION ALL` in `LastActiveDay` | Low | Only runs when no date provided; already scoped to matching assessments via INNER JOIN to `ActiveAssessments` CTE |
| `StationOwners` CTE | Very Low | Lightweight filter (`unit_rank = 1`) on already-computed `RankedUnits` — no additional table scan |
| `LEFT JOIN STATIONS` | Low | Joined on compound key (JOBGUID + STATNAME); stations table is relatively small per assessment |

### Recommended Index

If a DBA can create a filtered nonclustered index on the WorkStudio server:

```sql
CREATE NONCLUSTERED INDEX IX_VEGUNIT_PlannerActivity
ON VEGUNIT (JOBGUID, STATNAME, UNIT)
INCLUDE (FORESTER, FRSTR_USER, ASSDDATE, EDITDATE, PERMSTAT)
WHERE UNIT IS NOT NULL AND UNIT != '';
```

This would cover the `RankedUnits` CTE entirely from the index, avoiding a clustered index scan on VEGUNIT.

---

## 11. Helper Methods Referenced

### `SqlFragmentHelpers::parseMsDateToDate(string $column)`

**Location:** `app/Services/WorkStudio/AssessmentsDx/Queries/SqlFragmentHelpers.php:18`

Converts Microsoft JSON date format to SQL DATE:
```sql
CAST(CAST(REPLACE(REPLACE({column}, '/Date(', ''), ')/', '') AS DATETIME) AS DATE)
```

**Why double CAST:** The inner CAST converts the epoch string to DATETIME (includes time). The outer CAST truncates to DATE only (removes time component). This is necessary because we GROUP BY Activity_Date — if time were included, each unit would be its own group.

### `SqlFragmentHelpers::validUnitFilter(string $tableAlias)`

**Location:** `app/Services/WorkStudio/AssessmentsDx/Queries/SqlFragmentHelpers.php:38`

Excludes invalid units:
```sql
{alias}.UNIT != 'NW' AND {alias}.UNIT != '' AND {alias}.UNIT IS NOT NULL
```

> **Note:** This query does NOT use `validUnitFilter()` because we *want* NW units in the results — they get non-work unit counts. We only exclude NULL and empty strings.

### `WSHelpers::toSqlInClause(array $collection)`

**Location:** `app/Services/WorkStudio/Helpers/WSHelpers.php`

Converts PHP array to SQL IN clause values:
```php
['Asplundh', 'Other'] → "'Asplundh', 'Other'"
```

---

## 12. Configuration Dependencies

All filter values come from `config/ws_assessment_query.php`:

| Config Key | Current Value | Used In |
|-----------|--------------|---------|
| `scope_year` | `'2026'` | `WP_STARTDATE LIKE '%2026%'` |
| `contractors` | `['Asplundh']` | `VEGJOB.CONTRACTOR IN (...)` |
| `excludedUsers` | `['ASPLUNDH\\jcompton', 'ASPLUNDH\\joseam']` | `SS.TAKENBY NOT IN (...)` |
| `cycle_types` | `['Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP']` | `VEGJOB.CYCLETYPE NOT IN (...)` |
| `job_types` | `['Assessment', 'Assessment Dx', 'Split_Assessment', 'Tandem_Assessment']` | `SS.JOBTYPE IN (...)` |

---

## 13. Related Files

| File | Purpose |
|------|---------|
| `app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php` | Parent class — this method would be added here |
| `app/Services/WorkStudio/AssessmentsDx/Queries/SqlFragmentHelpers.php` | Trait with `parseMsDateToDate()` and other SQL fragment helpers |
| `app/Services/WorkStudio/AssessmentsDx/Queries/SqlFieldBuilder.php` | Config-driven SELECT clause builder |
| `app/Services/WorkStudio/Helpers/WSHelpers.php` | `toSqlInClause()` utility |
| `config/ws_assessment_query.php` | Filter values (scope year, contractors, excluded users, etc.) |
| `config/workstudio_fields.php` | VEGUNIT and STATIONS field lists |
| `docs/specs/planner-activity-rules.md` | Business rules documentation |
| `docs/plans/task-4-sql-integration-plan.md` | Integration plan for planner activity feature |
| `docs/plans/task-5-query-optimization-plan.md` | Query optimization priorities |

---

## API Endpoint (Planned)

```http
GET /api/workstudio/assessments/{jobGuid}/planner-activity/daily
```

**Query Parameters:**
| Parameter | Type | Required | Default |
|-----------|------|----------|---------|
| `start_date` | `Y-m-d` | No | Auto-detect last active day |
| `end_date` | `Y-m-d` | No | Auto-detect last active day |
| `planner` | `string` | No | All planners |

**Response Shape:**
```json
{
  "success": true,
  "data": [
    {
      "planner": "John Smith",
      "planner_username": "jsmith",
      "activity_date": "2026-02-05",
      "footage_miles": 0.9465,
      "stations_completed": 12,
      "work_unit_count": 28,
      "non_work_unit_count": 3,
      "total_unit_count": 31,
      "stations_by_outside_planners": 2
    }
  ]
}
```










  1. Run the 3 discovery queries against the WorkStudio API to map HISTORYTYPE values,
   verify status transition patterns, and confirm planner username formats
  2. Produce a formal tech spec (via /bmad:bmm:workflows:quick-spec) documenting the
  complete schema, import logic, and analytics computations
  3. Generate the migrations + models (via the Laravel Generator workflows)
  4. Build the AssessmentArchivalService with the three-phase import logic
  5. Create a Livewire admin interface for configuring imports and viewing results

# Assessment Queries — Developer Analysis & Refactoring Plan

> **Author:** Query Specialist Agent
> **Date:** 2026-02-11
> **Scope:** `app/Services/WorkStudio/Assessments/Queries/AssessmentQueries.php` + `SqlFragmentHelpers.php`
> **Rule:** Do NOT modify `AssessmentQueries.php` — this document is analysis and plan only.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Bugs Found](#2-bugs-found)
3. [Per-Query Deep Analysis](#3-per-query-deep-analysis)
4. [Cross-Cutting Issues](#4-cross-cutting-issues)
5. [Index Recommendations](#5-index-recommendations)
6. [Code Extraction Plan](#6-code-extraction-plan)
7. [Architecture Recommendations](#7-architecture-recommendations)
8. [Priority Action Items](#8-priority-action-items)

---

## 1. Executive Summary

### Current State

The `AssessmentQueries.php` file (661 lines) contains 7 public query methods that build raw T-SQL strings sent to the WorkStudio DDOProtocol HTTP API. The `SqlFragmentHelpers.php` trait (238 lines) provides ~10 reusable SQL fragment builders. A `SqlFieldBuilder` class reads field lists from config.

### Key Findings

| Category | Count | Severity |
|----------|-------|----------|
| Data Bugs (wrong results) | 2 | Critical |
| Performance Issues | 7 | High |
| Consistency Issues | 5 | Medium |
| Code Duplication (extractable) | 6 patterns | Medium |
| Dead/Redundant Code | 2 | Low |

### Critical Constraint

**The DDOProtocol API does NOT support Common Table Expressions (CTEs).** Any optimization using `WITH...AS` syntax will silently fail and return empty results. The existing optimization plan (`docs/plans/task-5-query-optimization-plan.md`) recommends CTEs in multiple places — those recommendations are **invalid**. All optimizations must use derived tables (subqueries) instead.

---

## 2. Bugs Found

### BUG-001: PERMSTAT Value Inconsistency — RESOLVED 2026-02-11

**Impact:** Refusal counts were returning 0 in circuit-level and single-circuit views.

**Root Cause:** Three locations used `'Refusal'` instead of the correct value `'Refused'`:

| Location | Line | Was | Fixed To |
|----------|------|-----|----------|
| `groupedByRegionDataQuery()` | 156 | `'Refused'` | (already correct) |
| `groupedByCircuitDataQuery()` | 278 | `'Refusal'` | `'Refused'` |
| `unitCountsCrossApply()` (SqlFragmentHelpers) | 123 | `'Refusal'` | `'Refused'` |
| `getAllByJobGuid()` → `unitCountSubquery()` call | 405 | `'Refusal'` | `'Refused'` |

**Still Recommended:** Extract PERMSTAT values to config to prevent recurrence (see Section 6.3).

### BUG-002: LEFT JOIN Behaving as INNER JOIN

**Impact:** Could silently exclude circuits. Currently masked because all in-scope circuits have xref records.

Every query uses:
```sql
LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID
...
WHERE WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{year}%'
```

The WHERE clause on the LEFT JOINed table's column converts it to an effective INNER JOIN. If any circuit lacks a matching xref record, it's silently dropped. This is either:
- **Intentional** — in which case change to explicit `INNER JOIN` for clarity and optimizer hints
- **Unintentional** — in which case move the condition to the JOIN's ON clause:
  ```sql
  LEFT JOIN WPStartDate_Assessment_Xrefs
    ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID
    AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{year}%'
  ```

**Recommendation:** If all circuits must have an xref record, change to INNER JOIN. This gives the query optimizer better information and makes intent explicit.

---

## 3. Per-Query Deep Analysis

### Q1: `systemWideDataQuery()` (Lines 46-79)

**Purpose:** Single-row aggregate — total assessments, status counts, miles, active planners.

**Issues:**

| ID | Issue | Severity | Detail |
|----|-------|----------|--------|
| Q1-1 | Unnecessary TOP 1 subquery for contractor | Low | `(SELECT TOP 1 CONTRACTOR FROM VEGJOB WHERE VEGJOB.CONTRACTOR IN (...))` scans ALL VEGJOB rows. The contractor name is already known from config/context — pass it directly. |
| Q1-2 | CYCLETYPE uses `NOT IN ('Reactive')` | Medium | Other queries use `IN ({$this->cycleTypesSql})` from config. This only excludes 'Reactive' but NOT 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP'. **Result sets are different.** |
| Q1-3 | LIKE with leading wildcard | High | `WP_STARTDATE LIKE '%2026%'` prevents index usage on WP_STARTDATE. |
| Q1-4 | LEFT→INNER JOIN issue | Medium | See BUG-002. |

**Suggested Optimization:**
- Remove the contractor subquery — use `'{$this->context->contractors[0]}'` directly
- Standardize CYCLETYPE to use `IN ({$this->cycleTypesSql})`
- If WP_STARTDATE stores MS JSON date format, parse and compare year instead of LIKE

---

### Q2: `groupedByRegionDataQuery()` (Lines 104-191)

**Purpose:** Per-region aggregates — status counts, miles, unit permission counts, work measurements.

**Issues:**

| ID | Issue | Severity | Detail |
|----|-------|----------|--------|
| Q2-1 | CROSS APPLY acts as INNER JOIN | Medium | If a circuit has zero VEGUNIT or zero JOBVEGETATIONUNITS records, the circuit is silently excluded from results. Should be OUTER APPLY if circuits without units should appear with 0 counts. |
| Q2-2 | Uses `'Refused'` for PERMSTAT | Critical | See BUG-001. Other queries use `'Refusal'`. |
| Q2-3 | JOBVEGETATIONUNITS table undocumented | Low | This table is not in the WS schema docs. Verify it exists and understand its relationship to VEGUNIT. It appears to store work measurement aggregates per job (UNIT, ACRES, LENGTHWRK). |
| Q2-4 | No TAKENBY exclusion applied | Low | Unlike `systemWideDataQuery`, there's no `SS.TAKENBY NOT IN (...)` filter here. Wait — it IS present on line 185. Confirmed present. |

**Performance Note:** The two CROSS APPLYs execute correlated subqueries per row. For large result sets, this could be slow. However, since results are grouped by REGION (typically 6 regions), the outer query only has ~6 rows, so CROSS APPLY executes ~6 times total after GROUP BY. **This is actually efficient here.**

Wait — the CROSS APPLY executes BEFORE GROUP BY, not after. Each SS row gets its own CROSS APPLY execution, then results are grouped. If there are 1000 circuits, that's 2000 CROSS APPLY executions (1000 for UnitData, 1000 for WorkData).

**Optimization:** Consider pre-aggregating VEGUNIT and JOBVEGETATIONUNITS counts in a derived table joined by JOBGUID, rather than CROSS APPLY per row.

---

### Q3: `groupedByCircuitDataQuery()` (Lines 216-325)

**Purpose:** Per-circuit detail — all fields, planners, permission counts, work measurements.

**Issues:**

| ID | Issue | Severity | Detail |
|----|-------|----------|--------|
| Q3-1 | Uses `'Refusal'` for PERMSTAT | Critical | Inconsistent with Q2's `'Refused'`. One is wrong. |
| Q3-2 | STRING_AGG planners subquery | Low | The nested `SELECT DISTINCT + STRING_AGG` is correct T-SQL but could be simplified. |
| Q3-3 | Same CROSS APPLY concern as Q2 | Medium | But here there's no GROUP BY — every circuit gets its own CROSS APPLY execution. For 1000 circuits, that's 2000 correlated subqueries. |
| Q3-4 | ASSDDATE MIN/MAX on VEGUNIT | Low | Used for First/Last Assessed Date. Since ASSDDATE is stored as `/Date(ISO8601)/`, MIN/MAX on the string is lexicographic but happens to be chronologically correct for ISO 8601. Fragile but functional. |

**Suggested Optimization:**
- Combine UnitData and WorkData CROSS APPLYs into one, since both correlate on the same JOBGUID. This halves the number of correlated subquery executions.
- Or use a pre-aggregated derived table approach.

---

### Q4: `getAllAssessmentsDailyActivities()` (Lines 336-376)

**Purpose:** Per-circuit daily activity data with nested JSON.

**Issues:**

| ID | Issue | Severity | Detail |
|----|-------|----------|--------|
| Q4-1 | **SS self-join is redundant** | Medium | `INNER JOIN SS AS WSREQSS ON SS.JOBGUID = WSREQSS.JOBGUID` — JOBGUID is SS's primary key, so this is a 1:1 self-join. Both `SS.*` and `WSREQSS.*` reference the same row. The alias `WSREQSS` suggests this was copied from a pattern that originally joined to `SSREQ` (a different table). |
| Q4-2 | Inconsistent column references | Low | Query references `WSREQSS.TAKENBY`, `WSREQSS.STATUS`, `WSREQSS.JOBTYPE` but also `SS.EDITDATE` and `SS.JOBGUID`. Since they're the same row, this works but is confusing. |
| Q4-3 | Unnecessary `$cycleTypes` variable | Low | Line 340 creates `$cycleTypes` but the WHERE clause already uses `{$this->cycleTypesSql}` (from constructor). The local variable is unused unless it was meant for something different. |
| Q4-4 | FOR JSON PATH formatting | Info | Returns native JSON from SQL Server. Works but the entire result set is a single JSON string, which must be parsed client-side. |
| Q4-5 | `dailyRecordsQuery()` complexity | High | See Q7 below. This is the most complex fragment. |

**Optimization:**
- Remove the SS self-join entirely. Use `SS` for all column references.
- Remove the unused `$cycleTypes` local variable (or it may have been intended for a different filter — investigate).

---

### Q5: `getAllByJobGuid()` (Lines 390-453)

**Purpose:** Complete circuit data — all fields, unit counts, daily records, stations with nested units.

**Issues:**

| ID | Issue | Severity | Detail |
|----|-------|----------|--------|
| Q5-1 | **SQL injection risk (SEC-003)** | Critical | `$jobGuid` is interpolated directly: `WHERE WSREQSS.JOBGUID = '{$jobGuid}'`. No validation. Must add GUID format regex validation. |
| Q5-2 | **7 separate correlated subqueries** | High | `unitCountSubquery()` is called 7 times, each producing a `(SELECT COUNT(*) FROM VEGUNIT WHERE ...)`. That's 7 scans of VEGUNIT per call. The trait already has `unitCountsCrossApply()` which does this in ONE scan. **Use it.** |
| Q5-3 | SS self-join (same as Q4) | Medium | Redundant 1:1 self-join. |
| Q5-4 | Massive payload | Medium | `stationsWithUnitsQuery()` returns ALL 170+ VEGUNIT fields for every unit at every station. For a circuit with 200 stations and 5 units each, that's 1000 rows x 170 columns. |
| Q5-5 | No scope filtering | Info | Unlike other queries, this doesn't filter by region, contractor, or year. It takes any JOBGUID directly. This is by design (single circuit lookup) but the lack of scoping could return data the user shouldn't see. |

**Optimization:**
- Replace 7x `unitCountSubquery()` calls with single `unitCountsCrossApply()` — **this already exists in the codebase** but isn't used here.
- Add GUID format validation: `preg_match('/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $jobGuid)`
- Consider reducing VEGUNIT fields in `stationsWithUnitsQuery()` — most of the 170 fields are not needed for the stations view.
- Remove SS self-join.

---

### Q6: `getAllJobGUIDsForEntireScopeYear()` (Lines 562-602)

**Purpose:** List all JOBGUIDs with basic metadata for the scope year.

**Issues:**

| ID | Issue | Severity | Detail |
|----|-------|----------|--------|
| Q6-1 | Unused local variables | Low | Lines 566-567 create `$cycleTypes` and `$statues` but the query uses `{$cycleTypes}` and `{$statues}` — wait, it uses the LOCAL variables, not the constructor ones. This means it's using different cycle types/statuses than other queries. Verify this is intentional. |
| Q6-2 | LEFT→INNER JOIN issue | Medium | Same as BUG-002. |

**Note:** This is the simplest query. No major optimization needed.

---

### Q7: `getDistinctFieldValues()` (Lines 622-656)

**Purpose:** Dynamic field lookup — distinct values with counts.

**Issues:**

| ID | Issue | Severity | Detail |
|----|-------|----------|--------|
| Q7-1 | **Hardcoded CYCLETYPE exclusion** | Medium | Uses `NOT IN ('Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP')` instead of config-driven values. |
| Q7-2 | Hardcoded status filter | Low | Only uses `STATUS = 'ACTIV'` — other queries use multiple statuses. This may be intentional for field exploration. |
| Q7-3 | Input validation is good | Info | `preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/')` prevents SQL injection. |

---

### Q8 (Fragment): `dailyRecordsQuery()` in SqlFragmentHelpers (Lines 144-209)

**Purpose:** Daily footage + unit counts per assessment date.

**Issues:**

| ID | Issue | Severity | Detail |
|----|-------|----------|--------|
| Q8-1 | **Triple-nested derived tables** | High | `DistinctStationDates` → `StationFirstDate` (with ROW_NUMBER) → outer GROUP BY. This is the most complex fragment in the codebase. |
| Q8-2 | VEGUNIT scanned 3 times | High | V (main scan), V2 (unit list), V3 (unit count per date). Each is a separate correlated scan. |
| Q8-3 | STATIONS JOIN on WO instead of JOBGUID | Info | Line 188: `S.WO = V.WO` — uses WO for the join, not JOBGUID. This works because WO+STATNAME is unique, but JOBGUID+STATNAME would be more canonical. |
| Q8-4 | ROW_NUMBER() for deduplication | Info | Correctly assigns first occurrence of each station to a date. This cannot be simplified without CTEs (which aren't supported). |

**Optimization:** This fragment cannot easily be optimized without CTEs. The triple nesting is a necessary workaround. However, reducing the 3 VEGUNIT scans to 1 or 2 could help.

---

## 4. Cross-Cutting Issues

### 4.1 Duplicated WHERE Clause Pattern

Every query repeats this filter block (with slight variations):

```sql
WHERE VEGJOB.REGION IN ({$this->resourceGroupsSql})
AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
AND SS.STATUS IN (...)
AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})
AND SS.TAKENBY NOT IN ({$this->excludedUsersSql})
AND SS.JOBTYPE IN ({$this->jobTypesSql})
AND VEGJOB.CYCLETYPE IN ({$this->cycleTypesSql})
```

**Problem:** 6 of 7 queries have near-identical WHERE clauses. Variations include:
- `systemWideDataQuery` uses `CYCLETYPE NOT IN ('Reactive')` instead of `IN`
- `getDistinctFieldValues` uses hardcoded CYCLETYPE list
- `getActiveAssessmentsOrderedByOldest` adds domain and taken filters

**Solution:** Extract a base WHERE clause builder (see Section 6).

### 4.2 Inconsistent CYCLETYPE Filtering

| Query | Filter Approach |
|-------|----------------|
| `systemWideDataQuery` | `NOT IN ('Reactive')` |
| `groupedByRegionDataQuery` | `IN ({$this->cycleTypesSql})` |
| `groupedByCircuitDataQuery` | `IN ({$this->cycleTypesSql})` |
| `getAllAssessmentsDailyActivities` | `IN ({$this->cycleTypesSql})` |
| `getAllByJobGuid` | None (single job lookup) |
| `getAllJobGUIDsForEntireScopeYear` | `IN ({$cycleTypes})` (local variable) |
| `getActiveAssessmentsOrderedByOldest` | `NOT IN ('Reactive', 'Storm Follow Up', ...)` |
| `getDistinctFieldValues` | `NOT IN ('Reactive', 'Storm Follow Up', ...)` |

Three different approaches produce potentially different result sets. Standardize to config-driven values.

### 4.3 LIKE Wildcard on WP_STARTDATE

```sql
WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
```

The `%` prefix prevents index usage. If `WP_STARTDATE` stores the MS JSON date format `/Date(2026-01-15T00:00:00)/`, consider:

```sql
-- Instead of LIKE (no index usage):
WP_STARTDATE LIKE '%2026%'

-- Use year extraction (still no index, but more correct):
YEAR(CAST(REPLACE(REPLACE(WP_STARTDATE, '/Date(', ''), ')/', '') AS DATE)) = 2026

-- Best: If WP_STARTDATE format is predictable, use range comparison:
WP_STARTDATE >= '/Date(2026-01-01T00:00:00.000Z)/'
AND WP_STARTDATE < '/Date(2027-01-01T00:00:00.000Z)/'
```

The range comparison can use an index on WP_STARTDATE.

### 4.4 SS Self-Join Pattern

Two queries (`getAllAssessmentsDailyActivities`, `getAllByJobGuid`) use:
```sql
INNER JOIN SS AS WSREQSS ON SS.JOBGUID = WSREQSS.JOBGUID
```

Since JOBGUID is SS's primary key, this is a 1:1 self-join returning the same row. The `WSREQSS` alias suggests historical intent (perhaps originally joining to `SSREQ` or `WSREQUEST`). This adds an unnecessary table scan.

**Recommendation:** Remove the self-join. Replace all `WSREQSS.*` references with `SS.*`.

### 4.5 JOBVEGETATIONUNITS Table

Used in Q2 and Q3 but not documented in the WS schema files. Need to verify:
1. Does this table exist? (Queries work, so presumably yes)
2. What is its relationship to VEGUNIT?
3. Is it a view or a table?
4. What indexes exist on it?

---

## 5. Index Recommendations

Since the WorkStudio database is external (DDOProtocol API), we cannot create indexes directly. However, these recommendations should be communicated to the WorkStudio DBA team.

### High Priority

| Table | Recommended Index | Reason |
|-------|-------------------|--------|
| `WPStartDate_Assessment_Xrefs` | `(Assess_JOBGUID, WP_STARTDATE)` | Every query joins on `Assess_JOBGUID` and filters on `WP_STARTDATE`. Composite covering index eliminates key lookups. |
| `VEGUNIT` | `(JOBGUID, UNIT, PERMSTAT)` | All CROSS APPLY and correlated subqueries filter on JOBGUID, exclude specific UNITs, and aggregate by PERMSTAT. |
| `VEGUNIT` | `(JOBGUID, ASSDDATE)` | `dailyRecordsQuery` and `getActiveAssessmentsOrderedByOldest` filter and aggregate on ASSDDATE per JOBGUID. |
| `VEGUNIT` | `(JOBGUID, FORESTER)` | Forester subquery and STRING_AGG planners query. |

### Medium Priority

| Table | Recommended Index | Reason |
|-------|-------------------|--------|
| `JOBVEGETATIONUNITS` | `(JOBGUID, UNIT)` | Work measurement CROSS APPLY filters on JOBGUID and aggregates by UNIT. |
| `STATIONS` | `(JOBGUID, STATNAME, SPANLGTH)` | `dailyRecordsQuery` joins STATIONS on JOBGUID+WO and reads SPANLGTH. Covering index avoids key lookup. |
| `SS` | `(STATUS, JOBTYPE, TAKEN, TAKENBY)` | Multiple WHERE filters on these columns simultaneously. |
| `VEGJOB` | `(JOBGUID, REGION, CONTRACTOR, CYCLETYPE)` | Every query joins VEGJOB and filters on REGION, CONTRACTOR, CYCLETYPE. |

### Existing Indexes (from schema docs)

| Table | Index | Columns |
|-------|-------|---------|
| SS | JOBGUID (Primary) | JOBGUID |
| SS | WO | WO |
| SS | WOEXT | WO, EXT |
| SS | STATUS | STATUS |
| SS | JOBTYPE | JOBTYPE |
| GPS | JGUIDSTAT | JOBGUID, STATNAME |

**Gap:** VEGUNIT has no documented indexes. If it lacks an index on JOBGUID, every correlated subquery and CROSS APPLY is doing a full table scan.

---

## 6. Code Extraction Plan

### 6.1 Extract: Base WHERE Clause Builder

**Current Problem:** 6+ queries repeat the same WHERE conditions with minor variations.

**Proposed:** Add to `SqlFragmentHelpers` trait:

```php
/**
 * Build the standard assessment WHERE clause.
 *
 * @param array<string, mixed> $overrides Keys: statuses, cycleTypeMode ('in'|'not_in'),
 *                                        cycleTypeValues, includeTakenBy, includeExcludedUsers
 */
private function baseAssessmentWhereClause(array $overrides = []): string
{
    $clauses = [
        "VEGJOB.REGION IN ({$this->resourceGroupsSql})",
        "WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'",
        "VEGJOB.CONTRACTOR IN ({$this->contractorsSql})",
        "SS.JOBTYPE IN ({$this->jobTypesSql})",
    ];

    // Status filter
    $statuses = $overrides['statuses'] ?? "('ACTIV', 'QC', 'REWRK', 'CLOSE')";
    $clauses[] = "SS.STATUS IN {$statuses}";

    // Cycle type filter
    $cycleMode = $overrides['cycleTypeMode'] ?? 'in';
    if ($cycleMode === 'in') {
        $clauses[] = "VEGJOB.CYCLETYPE IN ({$this->cycleTypesSql})";
    } else {
        $excluded = $overrides['cycleTypeValues'] ?? "'Reactive'";
        $clauses[] = "VEGJOB.CYCLETYPE NOT IN ({$excluded})";
    }

    // Optional exclusions
    if ($overrides['includeExcludedUsers'] ?? true) {
        $clauses[] = "SS.TAKENBY NOT IN ({$this->excludedUsersSql})";
    }

    return implode("\n                    AND ", $clauses);
}
```

**Benefit:** Single source of truth for filtering. Changes to scope logic affect all queries.

### 6.2 Extract: Base FROM/JOIN Clause Builder

**Current Problem:** Every query has the same 3-table join chain.

**Proposed:**

```php
private function baseAssessmentFromClause(): string
{
    return "FROM SS
            INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
            INNER JOIN WPStartDate_Assessment_Xrefs
                ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID";
}
```

**Note:** Changed LEFT JOIN to INNER JOIN (see BUG-002 analysis).

### 6.3 Extract: PERMSTAT Values to Config

**Current Problem:** PERMSTAT string values are scattered across 3 files.

**Proposed:** Add to `config/ws_assessment_query.php`:

```php
'permission_statuses' => [
    'approved'     => 'Approved',
    'pending'      => 'Pending',   // Also includes NULL and empty string
    'no_contact'   => 'No Contact',
    'refusal'      => 'Refusal',   // VERIFY: is it 'Refusal' or 'Refused'?
    'deferred'     => 'Deferred',
    'ppl_approved' => 'PPL Approved',
],
```

Then reference via `config('ws_assessment_query.permission_statuses.refusal')`.

### 6.4 Extract: VEGUNIT Permission Counts (CROSS APPLY or Subqueries)

**Current Problem:** Permission count logic appears in 3+ places with slightly different implementations.

**Already exists:** `unitCountsCrossApply()` in `SqlFragmentHelpers` — but it's NOT used by `getAllByJobGuid()`, which instead calls `unitCountSubquery()` 7 times.

**Action:** Refactor `getAllByJobGuid()` to use the existing `unitCountsCrossApply()` instead of 7 separate `unitCountSubquery()` calls.

### 6.5 Extract: Work Measurement Aggregation

**Current Problem:** The WorkData CROSS APPLY (JOBVEGETATIONUNITS aggregation) is duplicated between `groupedByRegionDataQuery()` and `groupedByCircuitDataQuery()`.

**Proposed:** Add to `SqlFragmentHelpers`:

```php
private static function workMeasurementsCrossApply(string $jobGuidRef = 'SS.JOBGUID'): string
{
    return "CROSS APPLY (
        SELECT
            COUNT(CASE WHEN UNIT = 'REM612' THEN 1 END) AS Rem_6_12_Count,
            COUNT(CASE WHEN UNIT IN ('REM1218', 'REM1824', 'REM2430', 'REM3036') THEN 1 END) AS Rem_Over_12_Count,
            COUNT(CASE WHEN UNIT IN ('ASH612', 'ASH1218', 'ASH1824', 'ASH2430', 'ASH3036') THEN 1 END) AS Ash_Removal_Count,
            COUNT(CASE WHEN UNIT = 'VPS' THEN 1 END) AS VPS_Count,
            SUM(CASE WHEN UNIT IN ('BRUSH', 'HCB', 'BRUSHTRIM') THEN ACRES ELSE 0 END) AS Brush_Acres,
            SUM(CASE WHEN UNIT IN ('HERBA', 'HERBNA') THEN ACRES ELSE 0 END) AS Herbicide_Acres,
            SUM(CASE WHEN UNIT IN ('SPB', 'MPB') THEN LENGTHWRK ELSE 0 END) AS Bucket_Trim_Length,
            SUM(CASE WHEN UNIT IN ('SPM', 'MPM') THEN LENGTHWRK ELSE 0 END) AS Manual_Trim_Length
        FROM JOBVEGETATIONUNITS
        WHERE JOBVEGETATIONUNITS.JOBGUID = {$jobGuidRef}
    ) AS WorkData";
}
```

### 6.6 Extract: Unit Code Groups to Config

**Current Problem:** Unit codes like `'REM612'`, `'BRUSH'`, `'SPB'` are hardcoded in SQL strings.

**Proposed:** Add to `config/ws_assessment_query.php`:

```php
'unit_groups' => [
    'removal_6_12'   => ['REM612'],
    'removal_over_12' => ['REM1218', 'REM1824', 'REM2430', 'REM3036'],
    'ash_removal'    => ['ASH612', 'ASH1218', 'ASH1824', 'ASH2430', 'ASH3036'],
    'vps'            => ['VPS'],
    'brush'          => ['BRUSH', 'HCB', 'BRUSHTRIM'],
    'herbicide'      => ['HERBA', 'HERBNA'],
    'bucket_trim'    => ['SPB', 'MPB'],
    'manual_trim'    => ['SPM', 'MPM'],
],
```

Then the work measurements fragment can use `WSHelpers::toSqlInClause(config('ws_assessment_query.unit_groups.removal_6_12'))` instead of hardcoded strings.

**Benefit:** When new unit codes are added (e.g., new removal size categories), only config changes are needed — no SQL string editing.

### 6.7 Extract: Excluded CYCLETYPE Values to Config

**Current Problem:** Three different hardcoded exclusion lists in queries.

**Already partially done:** `config('ws_assessment_query.cycle_types')` has category arrays. But the `NOT IN` queries hardcode their own lists.

**Proposed:** Add a computed exclusion list:

```php
'cycle_types' => [
    'maintenance' => [...],     // existing
    'storm' => [...],           // existing
    'projects' => [...],        // existing
    'data_driven' => [...],     // existing

    // Derived: everything NOT maintenance
    'excluded_from_assessments' => [
        'Reactive',
        'Storm Follow Up',
        'Misc. Project Work',
        'PUC-STORM FOLLOW UP',
        'NON-PUC STORM',
    ],
],
```

---

## 7. Architecture Recommendations

### 7.1 Split AssessmentQueries into Domain Classes

Current `AssessmentQueries.php` handles 3 distinct domains:

| Domain | Methods | Proposed Class |
|--------|---------|----------------|
| System/Region Aggregates | `systemWideDataQuery`, `groupedByRegionDataQuery` | `AggregateQueries.php` |
| Circuit-Level Detail | `groupedByCircuitDataQuery`, `getAllByJobGuid`, `getAllJobGUIDsForEntireScopeYear` | `CircuitQueries.php` |
| Activity/Planner Data | `getAllAssessmentsDailyActivities`, `getActiveAssessmentsOrderedByOldest` | `ActivityQueries.php` |
| Dynamic Lookups | `getDistinctFieldValues` | `LookupQueries.php` |

All classes would:
- Accept `UserQueryContext` in constructor
- Use the `SqlFragmentHelpers` trait
- Share extracted base clause builders

### 7.2 Create a Query Builder Base Class

Instead of a trait, extract shared functionality into an abstract base class:

```php
abstract class BaseAssessmentQuery
{
    use SqlFragmentHelpers;

    protected string $resourceGroupsSql;
    protected string $contractorsSql;
    protected string $excludedUsersSql;
    protected string $jobTypesSql;
    protected string $cycleTypesSql;
    protected string $scopeYear;
    protected string $domainFilter;

    public function __construct(protected readonly UserQueryContext $context)
    {
        // ... same initialization as current constructor
    }

    protected function baseFromClause(): string { ... }
    protected function baseWhereClause(array $overrides = []): string { ... }
}
```

### 7.3 Use unit_types Table for Work Unit Classification

The project now has a local `unit_types` table (synced from WorkStudio UNITS table) with a `work_unit` boolean. Currently, work unit classification in SQL queries uses hardcoded UNIT codes.

**Future Enhancement:** Queries could dynamically pull work unit codes from the local `unit_types` table instead of hardcoding. However, since the queries run on the external API (not local DB), the unit codes would need to be passed as parameters built from the local table.

```php
// Example: Build unit group from local table
$removalUnits = UnitType::where('work_unit', true)
    ->where('summarygrp', 'Summary-REMOVAL')
    ->pluck('unit')
    ->toArray();
$removalSql = WSHelpers::toSqlInClause($removalUnits);
```

---

## 8. Priority Action Items

### Immediate (Before Next Feature Work)

| # | Action | Effort | Files |
|---|--------|--------|-------|
| ~~1~~ | ~~**Fix BUG-001:** Verify correct PERMSTAT value and standardize~~ | DONE | AssessmentQueries.php, SqlFragmentHelpers.php |
| 2 | **Fix SEC-003:** Add GUID validation to `getAllByJobGuid()` | 15 min | AssessmentQueries.php |
| 3 | **Decide BUG-002:** Change LEFT to INNER JOIN (or move filter to ON clause) | 15 min | AssessmentQueries.php |

### Short Term (Next Sprint)

| # | Action | Effort | Files |
|---|--------|--------|-------|
| 4 | Extract base WHERE clause builder | 1 hr | SqlFragmentHelpers.php |
| 5 | Extract base FROM/JOIN clause builder | 30 min | SqlFragmentHelpers.php |
| 6 | Extract PERMSTAT values to config | 30 min | ws_assessment_query.php, SqlFragmentHelpers.php |
| 7 | Extract unit code groups to config | 30 min | ws_assessment_query.php, SqlFragmentHelpers.php |
| 8 | Replace 7x `unitCountSubquery()` with `unitCountsCrossApply()` in `getAllByJobGuid()` | 30 min | AssessmentQueries.php |
| 9 | Remove SS self-join from Q4 and Q5 | 30 min | AssessmentQueries.php |
| 10 | Standardize CYCLETYPE filtering across all queries | 30 min | AssessmentQueries.php |

### Medium Term (Refactoring Sprint)

| # | Action | Effort | Files |
|---|--------|--------|-------|
| 11 | Extract work measurements CROSS APPLY to shared fragment | 1 hr | SqlFragmentHelpers.php, AssessmentQueries.php |
| 12 | Split AssessmentQueries into domain classes | 2-3 hr | New files + refactor callers |
| 13 | Create `BaseAssessmentQuery` abstract class | 1 hr | New file |
| 14 | Investigate JOBVEGETATIONUNITS table and document | 1 hr | _bmad/ws/data/tables/ |
| 15 | Request index additions from WS DBA team | N/A | External |

### Invalidated Recommendations from Existing Plan

The `docs/plans/task-5-query-optimization-plan.md` recommends CTEs in Sections 6.Q2, 6.Q3, 6.Q5, and 6.Q7. **These are ALL invalid** because the DDOProtocol API does not support CTEs. The plan should be updated to note this constraint and replace CTE suggestions with derived table approaches.

---

## Appendix: Query Dependency Map

```
AssessmentQueries.php
├── systemWideDataQuery()
│   └── [standalone — no fragment dependencies]
│
├── groupedByRegionDataQuery()
│   └── [inline CROSS APPLYs — no fragment dependencies]
│
├── groupedByCircuitDataQuery()
│   ├── formatToEasternTime() → WSSQLCaster::cast()
│   └── [inline CROSS APPLYs + STRING_AGG]
│
├── getAllAssessmentsDailyActivities()
│   ├── extractYearFromMsDate()
│   ├── formatToEasternTime()
│   └── dailyRecordsQuery()          ← COMPLEX
│       ├── parseMsDateToDate()
│       └── validUnitFilterNotIn()
│
├── getAllByJobGuid()
│   ├── extractYearFromMsDate()
│   ├── foresterSubquery()
│   ├── totalFootageSubquery()
│   ├── formatToEasternTime()
│   ├── dailyRecordsQuery()          ← COMPLEX
│   ├── unitCountSubquery() × 7      ← SHOULD USE unitCountsCrossApply()
│   └── stationsWithUnitsQuery()
│       └── SqlFieldBuilder::select()
│
├── getActiveAssessmentsOrderedByOldest()
│   ├── formatToEasternTime()
│   └── parseMsDateToDate() × 3
│
├── getAllJobGUIDsForEntireScopeYear()
│   ├── formatToEasternTime()
│   └── extractYearFromMsDate()
│
└── getDistinctFieldValues()
    └── [standalone — validation only]
```

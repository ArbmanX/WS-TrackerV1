---
title: 'Assessment Completion Rules — Daily Footage by Station Completion'
created: '2026-02-08'
status: 'draft'
related_spec: 'planner-activity-rules.md'
---

# Assessment Completion Rules — Daily Footage by Station Completion

> **Purpose:** Define the business rules for calculating total planned footage per assessment per day, based on station completion events.
> **Status:** Draft
> **Related:** [Planner Activity Rules](planner-activity-rules.md) (broader attribution model)

---

## 1. Core Concept

An **assessment** (identified by `JOBGUID`) contains **stations** (spans of overhead powerline). Each station has a `SPANLGTH` (span length in meters). A station is **completed** when its first `VEGUNIT` record is created. The daily footage for an assessment is the sum of `SPANLGTH` values for all stations that were completed on that day.

---

## 2. Station Completion Definition

### 2.1 When Is a Station Complete?

A station is considered **complete** once at least one VEGUNIT record exists for that station (matching `JOBGUID` + `STATNAME`) where `UNIT IS NOT NULL AND UNIT != ''`.

### 2.2 Which Unit Completes the Station?

The unit with the **oldest `ASSDDATE`** attached to a station is the completing unit. `ASSDDATE` is the sole ordering field — no secondary tiebreaker.

```sql
ROW_NUMBER() OVER (
    PARTITION BY VEGUNIT.JOBGUID, VEGUNIT.STATNAME
    ORDER BY VEGUNIT.ASSDDATE ASC
) AS unit_rank
-- unit_rank = 1 is the completing unit
```

### 2.3 Completion Date

The **completion date** of a station is the `ASSDDATE` of the completing unit (unit_rank = 1). This is the day that receives the footage credit.

### 2.4 Completion Credit (User Attribution)

The `FRSTR_USERNAME` of the completing unit (unit_rank = 1) is the **user who gets credit** for that station's span length. This value maps to `ws_users.username` in the local database.

---

## 3. Unit Types That Complete a Station

**All non-null, non-empty units complete the station**, including non-work units:

| Unit Code | Type | Completes Station? | Notes |
|-----------|------|-------------------|-------|
| `NW` | No Work | Yes | No billable work, but station is assessed |
| `NOT` | Notification | Yes | Planner note/marker |
| `SENSI` | Sensitive | Yes | Sensitive customer marker |
| `SPM`, `SPB`, `MPM`, `MPB` | Work Unit | Yes | Trim work |
| `REM*`, `ASH*` | Work Unit | Yes | Removal work |
| Any other non-null/non-empty | Work Unit | Yes | Catch-all |
| `NULL` or `''` | N/A | **No** | Station not yet assessed |

### 3.1 NW + NOT Tiebreaker Example

If a station has both a `NW` unit (ASSDDATE: 01-15) and a `NOT` unit (ASSDDATE: 01-14):
- The `NOT` unit wins because its `ASSDDATE` is older
- **01-14** gets the footage credit
- The `FRSTR_USERNAME` on the `NOT` unit gets the user credit

### 3.2 Multiple Units, Same Station

A station can have multiple units. Only the **first** unit (by ASSDDATE/EDITDATE) determines:
- The **completion date** (which day gets the footage)
- The **credited user** (which `FRSTR_USERNAME` gets the span length)

Later units do NOT re-credit the footage.

---

## 4. Daily Footage Calculation

### 4.1 Formula

```
Daily Footage for Assessment X on Date D =
    SUM(STATIONS.SPANLGTH)
    WHERE station's completing unit ASSDDATE = D
    AND station belongs to Assessment X (JOBGUID match)
```

### 4.2 Required Output Fields

| Field | Source | Description |
|-------|--------|-------------|
| `JOBGUID` | VEGUNIT / SS | The assessment identifier |
| `completion_date` | VEGUNIT.ASSDDATE (of first unit) | The day the station was completed |
| `ws_user_id` | `ws_users.id` (via FRSTR_USERNAME join) | Local FK for the credited user |
| `daily_footage_meters` | SUM(STATIONS.SPANLGTH) | Total span length completed that day |

### 4.3 Future Fields (Not Yet Implemented)

These will be added in later iterations:
- `daily_footage_feet` — converted from meters
- `daily_footage_miles` — converted from meters
- `station_count` — number of stations completed
- `work_unit_count` — number of work units (excluding NW/NOT/SENSI)
- `non_work_station_count` — stations completed with only non-work units

---

## 5. Query Scope

### 5.1 Job Selection

Jobs are selected from the **local `ss_jobs` table**, filtered by:

| Filter | Column | Values | Required? |
|--------|--------|--------|-----------|
| Status | `ss_jobs.status` | **Single value** (e.g., `'ACTIV'` or `'QC'` or `'REWRK'` or `'CLOSE'`) | Yes |
| Scope Year | `ss_jobs.scope_year` | From config: `ws_assessment_query.scope_year` | Yes |
| Taken | `ss_jobs.taken` | `true` / `false` / omit for all | Optional |

> **Note:** Status is always a single value per query, not a list. Run separate queries per status if multiple are needed.

### 5.2 Job GUIDs as Bridge

The `ss_jobs.job_guid` values are used to query the **WorkStudio API** tables (`VEGUNIT`, `STATIONS`) via HTTP POST. The local `ss_jobs` table acts as a scope filter — only jobs matching the criteria are queried against the external API.

### 5.3 User Attribution Join

The `FRSTR_USERNAME` value from the completing VEGUNIT record is matched to `ws_users.username` to obtain the local `ws_users.id`. This provides the FK relationship between external API data and local user records.

---

## 6. Data Storage (Phase 1: JSON File)

Until the full query scope is finalized, results will be saved as a **JSON file** for UI development purposes. No database persistence in Phase 1.

### 6.1 JSON Structure (Target)

```json
[
    {
        "job_guid": "{GUID-HERE}",
        "completion_date": "2026-01-15",
        "ws_user_id": 42,
        "frstr_username": "ASPLUNDH\\tgibson",
        "daily_footage_meters": 1523.7
    }
]
```

### 6.2 Future: Database Table

A migration will be created once the full query scope is validated. Target table: `assessment_daily_completions` (or similar).

---

## 7. SQL Pattern Reference

### 7.1 First Unit per Station (CTE)

```sql
WITH FirstUnits AS (
    SELECT
        VU.JOBGUID,
        VU.STATNAME,
        VU.ASSDDATE,
        VU.FRSTR_USERNAME,
        VU.UNIT,
        ROW_NUMBER() OVER (
            PARTITION BY VU.JOBGUID, VU.STATNAME
            ORDER BY VU.ASSDDATE ASC
        ) AS unit_rank
    FROM VEGUNIT VU
    WHERE VU.UNIT IS NOT NULL
      AND VU.UNIT != ''
      AND VU.JOBGUID IN ({job_guid_list})
)
SELECT
    FU.JOBGUID,
    CONVERT(VARCHAR(10), FU.ASSDDATE, 110) AS completion_date,
    FU.FRSTR_USERNAME,
    SUM(ST.SPANLGTH) AS daily_footage_meters
FROM FirstUnits FU
JOIN STATIONS ST
    ON ST.JOBGUID = FU.JOBGUID
    AND ST.STATNAME = FU.STATNAME
WHERE FU.unit_rank = 1
GROUP BY FU.JOBGUID, CONVERT(VARCHAR(10), FU.ASSDDATE, 110), FU.FRSTR_USERNAME
ORDER BY FU.JOBGUID, completion_date
```

### 7.2 Job GUID Selection (Local Query)

```sql
SELECT job_guid
FROM ss_jobs
WHERE status = 'ACTIV'       -- single status per query
  AND scope_year = '2026'
  -- AND taken = true         -- optional filter
```

---

## Revision History

| Date | Author | Changes |
|------|--------|---------|
| 2026-02-08 | Arbman / BMad Master | Initial draft |

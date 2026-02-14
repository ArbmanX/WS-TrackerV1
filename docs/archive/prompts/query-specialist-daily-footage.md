# WS Query Specialist Prompt — Daily Footage by Station Completion

> **Usage:** Feed this prompt to `/ws:query-specialist` or the WS Query Specialist agent.
> **Rules Document:** `docs/specs/assessment-completion-rules.md`
> **Related:** `docs/specs/planner-activity-rules.md`

---

## Task

Build a SQL query that calculates **total planned footage per assessment per day**, grouped by the user who completed each station.

## Domain Context

- **Stations** represent spans of overhead powerline. Each station has a `SPANLGTH` (span length in meters) in the `STATIONS` table.
- **Units** (in `VEGUNIT` table) are work items attached to stations. A unit is linked to a station by matching `JOBGUID` + `STATNAME`.
- A **station is completed** once any VEGUNIT record exists for it where `UNIT IS NOT NULL AND UNIT != ''`.
- The unit with the **oldest `ASSDDATE`** is the completing unit. `ASSDDATE` is the sole ordering field — no tiebreaker.
- The completing unit's `ASSDDATE` determines **which day** gets the footage credit.
- The completing unit's `FRSTR_USERNAME` determines **which user** gets the credit. This value is a foreign key to the local `ws_users.username` column.
- All unit types complete a station, including non-work units (`NW` = No Work, `NOT` = Notification, `SENSI` = Sensitive). NULL or empty string units are excluded.

## Query Requirements

### Input Parameters

The query receives a list of `JOBGUID` values. These are obtained from the **local PostgreSQL `ss_jobs` table** with these filters:

| Filter | Column | Behavior |
|--------|--------|----------|
| `status` | `ss_jobs.status` | **Single value** per query (e.g., `'ACTIV'`). NOT an IN clause. |
| `scope_year` | `ss_jobs.scope_year` | From config, currently `'2026'` |
| `taken` | `ss_jobs.taken` | Optional boolean filter. Omit to include all. |

```sql
-- Local query (PostgreSQL) to get JOBGUIDs
SELECT job_guid FROM ss_jobs
WHERE status = :status
  AND scope_year = :scope_year
  -- AND taken = :taken  (optional)
```

### External API Query (WorkStudio DDOProtocol — MS SQL Server)

Using the JOBGUIDs from above, build a query against the WorkStudio API tables:

**Tables involved:**
- `VEGUNIT` — contains units with `JOBGUID`, `STATNAME`, `UNIT`, `ASSDDATE`, `FRSTR_USERNAME`
- `STATIONS` — contains stations with `JOBGUID`, `STATNAME`, `SPANLGTH`

**Logic:**

1. Use a CTE (`FirstUnits`) to find the first unit per station per assessment:
   - `ROW_NUMBER() OVER (PARTITION BY VEGUNIT.JOBGUID, VEGUNIT.STATNAME ORDER BY VEGUNIT.ASSDDATE ASC)`
   - Filter: `UNIT IS NOT NULL AND UNIT != ''`
   - Keep only `unit_rank = 1`

2. Join `FirstUnits` to `STATIONS` on `JOBGUID` + `STATNAME` to get `SPANLGTH`.

3. Group by `JOBGUID`, completion date (from `ASSDDATE`), and `FRSTR_USERNAME`.

4. SUM the `SPANLGTH` for each group.

### Required Output Columns

| Column | Source | Description |
|--------|--------|-------------|
| `JOBGUID` | FirstUnits | Assessment identifier |
| `completion_date` | `CONVERT(VARCHAR(10), ASSDDATE, 110)` | Date the stations were completed (MM-DD-YYYY) |
| `FRSTR_USERNAME` | FirstUnits | The credited user's WS username (maps to `ws_users.username`) |
| `daily_footage_meters` | `SUM(STATIONS.SPANLGTH)` | Total span length completed that day |

### Output Ordering

```
ORDER BY JOBGUID, completion_date ASC
```

## Constraints

- The WorkStudio API uses **MS SQL Server** syntax (T-SQL). Use `CONVERT()` for date formatting, CTEs are supported.
- `ASSDDATE` is the **only** date field used for ordering and attribution. Do not use `EDITDATE`.
- The local `ss_jobs` table is **PostgreSQL**. The external API query is **MS SQL Server**. These are two separate queries executed in different contexts.
- The `FRSTR_USERNAME` value will be matched to `ws_users.username` in a subsequent local step (not part of this SQL query).

## Phase 1 Output

Results should be saved as a **JSON file** (not persisted to a database table yet). The JSON structure per row:

```json
{
    "job_guid": "{GUID-VALUE}",
    "completion_date": "01-15-2026",
    "frstr_username": "ASPLUNDH\\tgibson",
    "daily_footage_meters": 1523.7
}
```

The `ws_user_id` lookup (matching `frstr_username` to `ws_users.id`) will be done in PHP after the query returns, before writing the JSON file.

## Reference SQL Pattern

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

## What I Need From You

1. **Validate** the reference SQL pattern above against the WorkStudio table schemas.
2. **Optimize** if you see opportunities (CTE efficiency, join strategy).
3. **Build the PHP implementation** — an Artisan command or service method that:
   - Queries local `ss_jobs` for JOBGUIDs (single status, scope_year, optional taken)
   - Sends the external SQL to the WorkStudio API via `GetQueryService`
   - Maps `FRSTR_USERNAME` to `ws_users.id` via Eloquent lookup
   - Saves results as a JSON file to `storage/app/` (or a configurable path)
4. **Consider chunking** — if the JOBGUID list is large, the `IN ()` clause may need to be split into batches.

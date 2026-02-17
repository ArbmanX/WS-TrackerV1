# FetchAssessments Command — Implementation Spec

> New Artisan command + migration replacing the current `ws:fetch-jobs` / `FetchSsJobs` flow.
> Domain: Assessment. Table: `assessments`. Command: `ws:fetch-assessments` (`FetchAssessments`).

## Context (from data discovery)

- **207 Split_Assessment** jobs exist in WorkStudio, all with `PJOBGUID` pointing to an `Assessment Dx` parent
- Splits share the same `WO` and `TITLE` as their parent (100%)
- The `WPStartDate_Assessment_Xrefs` table only maps parent JOBGUIDs — splits are invisible to the current `INNER JOIN`
- Fix: join xref on `COALESCE(NULLIF(SS.PJOBGUID, ''), SS.JOBGUID)` so splits inherit parent scope year
- 2026 scope: ~2,469 parent assessments + 21 splits (17 ACTIV across all years)
- VEGJOB.EDITDATE is OLE Automation format — use `WSSQLCaster::cast()` for conversion

## New Table: `assessments`

### Columns

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | PK |
| `job_guid` | string(38), unique | From SS.JOBGUID |
| `parent_job_guid` | string(38), nullable, FK | From SS.PJOBGUID — NULL for parents, populated for Split_Assessment only. FK references `assessments.job_guid` |
| `circuit_id` | foreignId, constrained | FK to circuits table. **Required** — null circuit = skip + log |
| `work_order` | string(50) | SS.WO |
| `extension` | string(10) | SS.EXT — single value, NOT array. `@` = parent assessment, any other value = split child |
| `job_type` | string(50) | SS.JOBTYPE — `Assessment Dx` or `Split_Assessment` |
| `status` | string(10) | SS.STATUS |
| `scope_year` | string(4) | Derived from xref WP_STARTDATE year |
| `is_split` | boolean, default false | True on `Assessment Dx` parents that have Split_Assessment children. Set after upsert by checking if any record's `parent_job_guid` references this job's `job_guid` |
| `taken` | boolean | SS.TAKEN |
| `taken_by_username` | string, nullable | SS.TAKENBY |
| `modified_by_username` | string, nullable | SS.MODIFIEDBY |
| `assigned_to` | string, nullable | SS.ASSIGNEDTO |
| `raw_title` | string | SS.TITLE |
| `version` | integer, nullable | SS.VERSION |
| `sync_version` | integer, nullable | SS.SYNCHVERSN |
| `cycle_type` | string(50), nullable | VEGJOB.CYCLETYPE |
| `region` | string(20), nullable | VEGJOB.REGION |
| `percent_complete` | integer, nullable | VEGJOB.PRCENT |
| `length` | float, nullable | VEGJOB.LENGTH |
| `length_completed` | float, nullable | VEGJOB.LENGTHCOMP |
| `last_edited` | timestamp, nullable | Parsed from VEGJOB.EDITDATE (OLE → Carbon via WSSQLCaster) — for display, Eloquent scopes, human queries |
| `last_edited_ole` | float, nullable | Raw OLE Automation float from VEGJOB.EDITDATE — used exclusively for incremental sync comparison. No conversion round-trip risk |
| `discovered_at` | timestamp | Set once on first insert, never updated |
| `last_synced_at` | timestamp | Updated every time the record is upserted |

**No Laravel `created_at` / `updated_at`.** Use `$timestamps = false` on the model. The four explicit columns above replace them.

### Indexes

- `unique` on `job_guid`
- `index` on `parent_job_guid`
- `index` on `circuit_id`
- `index` on `scope_year`
- `index` on `job_type`

### Relationships

- `parent_job_guid` → self-referential FK to `assessments.job_guid` (nullable, cascade on delete)
- `circuit_id` → FK to `circuits.id`

## SQL Query

```sql
SELECT
    SS.JOBGUID,
    SS.PJOBGUID,
    SS.WO,
    SS.EXT,
    SS.JOBTYPE,
    SS.STATUS,
    SS.TAKEN,
    SS.TAKENBY,
    SS.MODIFIEDBY,
    SS.VERSION,
    SS.SYNCHVERSN,
    SS.ASSIGNEDTO,
    SS.TITLE,
    VEGJOB.CYCLETYPE,
    VEGJOB.REGION,
    VEGJOB.PRCENT,
    VEGJOB.LENGTH,
    VEGJOB.LENGTHCOMP,
    VEGJOB.EDITDATE AS EDITDATE_OLE,
    {cast VEGJOB.EDITDATE} AS EDITDATE
FROM SS
INNER JOIN VEGJOB ON VEGJOB.JOBGUID = SS.JOBGUID
INNER JOIN WPStartDate_Assessment_Xrefs xref
    ON xref.Assess_JOBGUID = COALESCE(NULLIF(SS.PJOBGUID, ''), SS.JOBGUID)
WHERE xref.WP_STARTDATE LIKE '%{year}%'
    AND SS.JOBTYPE IN ('Assessment Dx', 'Split_Assessment')
    -- optional: AND SS.STATUS = '{status}' (only when --status is provided)
ORDER BY SS.JOBGUID
```

- When no `--year` is provided, omit the xref join and WHERE on WP_STARTDATE entirely
- When no `--status` is provided, fetch all statuses (default). When provided, add `AND SS.STATUS = '{status}'` to the WHERE clause

## Command: `ws:fetch-assessments`

### Signature

```
ws:fetch-assessments
    {--year= : Scope to a specific year (omit for all years)}
    {--status= : Filter by status (e.g. ACTIV, CLOSE). Omit for all statuses}
    {--full : Force full re-sync, bypass incremental EDITDATE delta}
    {--dry-run : Preview without upserting}
```

### Processing Rules

1. **Fetch** all rows from API using query above
2. **Resolve circuit_id** from `raw_title` against circuit map
   - If `circuit_id` is null: **skip the record**, log to `storage/logs/failed-assessment-fetch.log` with JOBGUID, WO, TITLE, reason
3. **No grouping** — each row = one assessment record (extension is a single value, not array)
4. **Upsert order: by extension depth** — parents must exist before children can create FK. Splits can be nested (e.g. `C_ba` is a split of `C_b`). Sort by `strlen(EXT)` ascending to guarantee parents are inserted before their children:
   - Depth 0: `@` (parent assessments)
   - Depth 1: `C_a`, `C_b`, `C_c`, `C_d`, `C_e` (first-level splits)
   - Depth 2: `C_aa`, `C_ba`, `C_ca` (second-level splits)
   - Depth 3: `C_aaa`, `C_baa` (third-level splits)
5. **On insert:** set `discovered_at` = now(), `last_synced_at` = now()
6. **On update:** set `last_synced_at` = now(), do NOT touch `discovered_at`
7. **After upsert:** flag `is_split = true` on any parent `Assessment Dx` whose `job_guid` appears as a `parent_job_guid` in the table
8. **Update circuit properties** with jobguids for the scope year (existing behavior from FetchSsJobs)

### Incremental Sync Strategy (Option A — EDITDATE delta)

On subsequent runs, read `MAX(last_edited_ole)` from the local `assessments` table and inject it directly into the API query — no conversion needed:

```sql
-- Add to WHERE clause on incremental runs:
AND VEGJOB.EDITDATE > {max_last_edited_ole_from_db}
```

- First run (table empty): full fetch, no EDITDATE filter
- Subsequent runs: only fetch records with `VEGJOB.EDITDATE` newer than the stored max
- Add `--full` flag to bypass the delta and force a complete re-sync
- The raw OLE float comparison is exact — no round-trip conversion risk since `last_edited_ole` stores the original value

## File Locations

Following project conventions and Assessment domain grouping:

```
app/Console/Commands/Fetch/FetchAssessments.php
app/Models/Assessment.php
database/migrations/xxxx_xx_xx_create_assessments_table.php
database/factories/AssessmentFactory.php
tests/Feature/Commands/FetchAssessmentsCommandTest.php
```

## Migration Cleanup (same PR)

- Drop `ss_jobs` table via migration
- Delete `FetchSsJobs` command, `SsJob` model, `SsJobFactory`, and `FetchSsJobsCommandTest`
- Update any code referencing `SsJob` model to use `Assessment` model
- Remove `ss_jobs` references from circuit property update logic (replaced by `assessments` equivalent)

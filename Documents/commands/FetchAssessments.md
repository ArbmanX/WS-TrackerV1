# ws:fetch-assessments

**Command:** `php artisan ws:fetch-assessments`
**Class:** `App\Console\Commands\Fetch\FetchAssessments`
**Model:** `App\Models\Assessment` (`assessments` table)

Fetches assessment records from the WorkStudio DDOProtocol HTTP API and upserts them into the local PostgreSQL `assessments` table. Replaces the legacy `ws:fetch-jobs` command.

---

## Options

| Option | Type | Default | Description |
|---|---|---|---|
| `--year=YYYY` | string | *(all years)* | Filter by scope year (matched against `WPStartDate_Assessment_Xrefs.WP_STARTDATE`) |
| `--status=CODE` | string | planner_concern defaults | Filter by a single status code (e.g. `ACTIV`, `CLOSE`) |
| `--full` | flag | off | Force full re-sync, bypassing incremental EDITDATE delta |
| `--dry-run` | flag | off | Preview first 20 rows without writing to the database |

### Default Statuses (planner_concern)

When `--status` is omitted, the command fetches records matching these statuses from config:

```
ACTIV, QC, REWRK, CLOSE, DEF
```

Source: `config('ws_assessment_query.statuses.planner_concern')`

---

## Usage Examples

```bash
# Full sync — all years, all planner_concern statuses, incremental
php artisan ws:fetch-assessments

# Full re-sync (ignores EDITDATE delta)
php artisan ws:fetch-assessments --full

# Scope to 2026 only
php artisan ws:fetch-assessments --year=2026

# Fetch only active assessments
php artisan ws:fetch-assessments --status=ACTIV

# Preview what would be fetched without writing
php artisan ws:fetch-assessments --dry-run

# Combine options
php artisan ws:fetch-assessments --year=2025 --status=CLOSE --full
```

---

## How It Works

### 1. API Query Construction (`fetchFromApi`)

Builds a raw SQL query against the WorkStudio DDOProtocol API joining three tables:

- **SS** — core job/assessment metadata (GUID, status, work order, assignments)
- **VEGJOB** — vegetation job details (region, cycle type, edit tracking, VEGJOB-specific fields)
- **WPStartDate_Assessment_Xrefs** (LEFT JOIN) — scope year derivation from `WP_STARTDATE`

**Scope year derivation:** Extracts the year from the xref's `WP_STARTDATE` field, which is stored in a `/Date(...)` MS JSON wrapper. The SQL strips the wrapper and casts to DATE, then pulls the YEAR.

**Xref join logic:** For parent assessments (`EXT = @`), joins on `SS.JOBGUID`. For splits, joins on `COALESCE(NULLIF(SS.PJOBGUID, ''), SS.JOBGUID)` — preferring the parent GUID but falling back to self.

**Job type filter:** Only fetches job types defined in `config('ws_assessment_query.job_types.assessments')` — currently `Assessment Dx & Split_Assessment`.

### 2. Incremental Sync

By default, the command performs incremental syncing:

1. Reads `MAX(last_edited_ole)` from the local `assessments` table
2. Appends `AND VEGJOB.EDITDATE > {maxOle}` to the SQL query
3. Only new or recently edited assessments are returned

The `--full` flag skips this check and fetches all matching records.

**OLE Automation dates:** EDITDATE comes from WorkStudio as a floating-point OLE serial number (days since Dec 30, 1899). The raw float is stored in `last_edited_ole` for reliable delta comparisons. A human-readable `last_edited` Carbon datetime is also stored.

### 3. Upsert Process (`upsertAssessments`)

1. **Build circuit map** — loads all `Circuit` records with `properties->raw_line_name` into a lookup hash
2. **Sort by extension depth** — `strlen(EXT)` ensures parents (`@`, length 1) are inserted before children (`C_a`, `C_ba`, etc.) to satisfy the self-referential foreign key
3. **For each row:**
   - Resolve `circuit_id` by matching `raw_title` against the circuit map
   - Skip rows with no matching circuit (logged to `failed-assessment-fetch.log`)
   - `firstOrNew` on `job_guid` — creates or updates
   - Sets `discovered_at` on first creation, `last_synced_at` on every sync
4. **Post-upsert:**
   - `flagSplitParents()` — marks `is_split = true` on parent assessments that have children
   - `updateCircuitJobGuids()` — updates circuit `properties` with assessment GUIDs grouped by scope year and cycle type, recalculates `last_trim` and `next_trim` dates

### 4. Circuit GUID Enrichment (`updateCircuitJobGuids`)

After upserting assessments, the command enriches each related Circuit's `properties` JSON:

```json
{
  "raw_line_name": "Some Circuit Name",
  "2025": {
    "Cycle Maintenance - Trim": ["{guid-1}", "{guid-2}"],
    "Cycle Maintenance - Removal": ["{guid-3}"]
  },
  "2026": {
    "Cycle Maintenance - Trim": ["{guid-4}"]
  }
}
```

It also derives `last_trim` (most recent year with a "Cycle Maintenance - Trim" entry) and `next_trim` (last_trim + 5 years).

---

## Data Mapping

| API Field | Model Attribute | Notes |
|---|---|---|
| `SS.JOBGUID` | `job_guid` | Primary lookup key for upsert |
| `SS.PJOBGUID` | `parent_job_guid` | Null for parents (`EXT=@`), self-referential FK |
| *(resolved)* | `circuit_id` | FK to `circuits`, resolved from `raw_title` |
| `SS.WO` | `work_order` | |
| `SS.EXT` | `extension` | `@` = parent, `C_a`/`C_ba` = splits |
| `SS.JOBTYPE` | `job_type` | e.g. `Assessment Dx` |
| `SS.STATUS` | `status` | e.g. `ACTIV`, `CLOSE`, `QC` |
| `SCOPE_YEAR` | `scope_year` | Derived from xref join |
| `SS.TAKEN` | `taken` | Boolean (parsed from string) |
| `SS.TAKENBY` | `taken_by_username` | |
| `SS.MODIFIEDBY` | `modified_by_username` | |
| `SS.ASSIGNEDTO` | `assigned_to` | |
| `SS.TITLE` | `raw_title` | Used for circuit resolution |
| `SS.VERSION` | `version` | |
| `SS.SYNCHVERSN` | `sync_version` | |
| `VEGJOB.CYCLETYPE` | `cycle_type` | |
| `VEGJOB.REGION` | `region` | |
| `VEGJOB.PLANNEDEMERGENT` | `planned_emergent` | |
| `VEGJOB.VOLTAGE` | `voltage` | Float |
| `VEGJOB.COSTMETHOD` | `cost_method` | |
| `VEGJOB.PROGRAMNAME` | `program_name` | |
| `VEGJOB.PERMISSIONING_REQUIRED` | `permissioning_required` | Boolean (parsed from string) |
| `VEGJOB.PRCENT` | `percent_complete` | Integer |
| `VEGJOB.LENGTH` | `length` | Float |
| `VEGJOB.LENGTHCOMP` | `length_completed` | Float |
| `VEGJOB.EDITDATE` (cast) | `last_edited` | Carbon datetime |
| `VEGJOB.EDITDATE` (raw) | `last_edited_ole` | Raw OLE float for delta sync |

---

## Model Relationships

```
Assessment
  ├── belongsTo  Circuit       (circuit_id → circuits.id)
  ├── belongsTo  Assessment    (parent_job_guid → assessments.job_guid)  [self-ref]
  └── hasMany    Assessment[]  (job_guid ← assessments.parent_job_guid)  [children]
```

---

## Error Handling

| Scenario | Behavior |
|---|---|
| Empty API response | Logs error, returns `FAILURE` |
| API ERROR protocol | Displays `errorMessage`, returns `FAILURE` |
| Missing Heading/Data | Logs format error, returns `FAILURE` |
| HTTP/network failure | Catches `Throwable`, logs message, returns `FAILURE` |
| No matching circuit | Row skipped, logged to `storage/logs/failed-assessment-fetch.log` |
| Unparseable EDITDATE | `last_edited` set to null, `last_edited_ole` still stored |

---

## Dependencies

- **`ApiCredentialManager`** — service account credentials and DB parameter formatting
- **`WSHelpers::toSqlInClause()`** — converts arrays to SQL `IN (...)` clause strings
- **`WSSQLCaster::cast()`** — OLE date field casting for WorkStudio SQL
- **`Circuit` model** — circuit lookup via `properties->raw_line_name`
- **Config:** `workstudio.base_url`, `ws_assessment_query.job_types.assessments`, `ws_assessment_query.statuses.planner_concern`

---

## Logs

- **Console output:** Progress bar + summary counts (created/updated/skipped)
- **Failure log:** `storage/logs/failed-assessment-fetch.log` — rows that couldn't be matched to a circuit

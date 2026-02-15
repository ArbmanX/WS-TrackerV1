---
stepsCompleted: [1, 2, 3]
inputDocuments: ['docs/specs/planner-activity-rules.md', 'docs/project-context.md']
session_topic: 'Data collection & storage architecture for WorkStudio assessment lifecycle data'
session_goals: 'Determine optimal strategy for collecting, storing, and serving assessment data across active monitoring and historical archival use cases'
selected_approach: 'ai-recommended'
techniques_used: ['First Principles Thinking', 'Morphological Analysis', 'Constraint Mapping']
ideas_generated: ['FP1-FP7', 'Morpho1-Morpho4', 'AssessmentProgress1-5', 'PlannerActivity1', 'Historical1']
context_file: 'docs/project-context.md'
phase_status: 'All phases COMPLETE'
---

# Brainstorming Session Results

**Facilitator:** Arbman
**Date:** 2026-02-12

## Session Overview

**Topic:** Data collection & storage architecture for WorkStudio assessment lifecycle data
**Goals:** Determine optimal strategy for collecting, storing, and serving assessment data across active monitoring and historical archival use cases

### Context Guidance

_Project uses external WorkStudio DDOProtocol HTTP API for all business data. Local PostgreSQL stores auth, settings, and snapshot time-series. Existing patterns include CachedQueryService (TTL caching), SnapshotPersistenceService (fail-silent DB persistence on cache miss), and domain-split query builders (AggregateQueries, CircuitQueries, ActivityQueries, LookupQueries). Two data categories: Planner Activities (daily rollups) and Assessment Progress (granular breakdowns). Closed assessments are immutable; active/QC/rework need live monitoring._

### Session Setup

_Session confirmed with AI-Recommended technique approach. Architecture decision brainstorming for data lifecycle management across external API and local storage._

## Technique Selection

**Approach:** AI-Recommended Techniques
**Analysis Context:** Data collection & storage architecture with focus on optimal strategy for active monitoring vs. historical archival

**Recommended Techniques:**

- **First Principles Thinking:** Strip away architectural assumptions to identify fundamental data truths — what's immutable, what's volatile, what actually needs querying
- **Morphological Analysis:** Systematically explore all combinations of storage mechanism, collection trigger, data granularity, and lifecycle stage
- **Constraint Mapping + Chaos Engineering:** Stress-test top candidates against real-world constraints and failure scenarios

**AI Rationale:** Problem requires structured decomposition (not pure creativity) because the solution space is bounded by real technical constraints (external API, DDOProtocol quirks, existing cache/snapshot patterns). First Principles prevents over-engineering from inherited assumptions. Morphological Analysis ensures no viable combination is missed. Constraint Mapping validates the final candidates against production reality.

---

## Phase 1: First Principles Thinking (COMPLETE)

### Established Truths

| # | Truth | Architecture Implication |
|---|-------|------------------------|
| FP1 | Units have UNITGUID (unique, 38-char) | Ghost detection via set difference |
| FP2 | LASTEDITBY/LASTEDITDT exist on VEGUNIT | Edit attribution is free for non-deleted units |
| FP3 | JOBHISTORY tracks ownership changes | Ghost monitoring triggers on ONEPPL takeover |
| FP4 | Notes are PARCELCOMMENTS + ASSNOTE memos | Count presence, not content |
| FP5 | Audit fields live on VEGUNIT | Rework history captured per-unit |
| FP6 | Daily footage is reconstructable from ASSDDATE | No preemptive collection needed for history |
| FP7 | Career ledger = computed view of closed data | Persist the result, not the source |

### Assessment Lifecycle

- **Statuses:** SA → ACTIV → QC ↔ REWRK → CLOSE (+ DEF treated as closed)
- **Immutability:** Once CLOSE, data never changes (for our purposes)
- **All source data:** External API only — no local unit records

### Two Distinct Data Patterns Identified

1. **"Career Ledger" (Historical/Closed)** — Narrow, append-once, planner-keyed. Daily footage, circuit, miles, timeline, rework audit. Fetched once when assessment closes, never updated.

2. **"Live Monitor" (Active/QC/REWRK)** — Wide, daily-refreshed, assessment-keyed. Permission breakdowns, unit counts by type, notes compliance, edit recency, plus event-triggered ghost snapshots for utility ownership periods.

### Data Requirements Discovered

**Assessment Progress (Live Monitor):**

1. **Permission Completion Tracking** — Per-assessment permission status breakdown beyond just miles
2. **Work Type Proportions** — Hand cut brush vs herbicide, bucket trim vs manual trim ratios
3. **Ghost Unit Detection** — Daily snapshot comparison to detect unit deletions (especially during utility ownership in QC). Only source of truth for deleted unit history.
4. **Pending Unit Aging** — Age of units with empty/pending permission status
5. **Unit Notes Compliance** — Count of units with/without notes, especially vegetation polygon units (BRUSH, HCB, mowing) below 10'x10' threshold

**Planner Activity (Live Monitor → Career Ledger):**

1. **Unit Edit Recency** — When units were last edited by planner (attention monitoring for pending permissions)

**Historical (Career Ledger):**

1. **Rework Audit Trail** — For closed assessments that went through rework, capture audit fields

### Sizing

| Factor | Value |
|--------|-------|
| Units per assessment | 1 - thousands |
| Monitored assessments (ACTIV/QC/REWRK) | ~30 |
| Ghost monitoring trigger | Ownership changes to ONEPPL domain |
| Daily monitoring frequency | Once per day |

---

## Phase 2: Morphological Analysis (IN PROGRESS)

### Parameter Space Explored

**4 Parameters × multiple options each:**
- WHEN collected: On-demand / Scheduled / Event-driven / Hybrid
- WHERE stored: Cache / PostgreSQL / JSON files / No storage
- WHAT granularity: Raw records / Aggregated metrics / Diff/delta only
- HOW LONG kept: Ephemeral / Session-scoped / Permanent / Lifecycle-scoped

### Decisions Made

| Domain | Combo | When | Where | What | How Long |
|--------|-------|------|-------|------|----------|
| **Career Ledger** | CL-1 (bootstrap variant) | On-demand + bootstrap | PostgreSQL + JSON import file | Aggregated | Permanent |
| **Live Monitor** | LM-1 | Scheduled (daily cron) | PostgreSQL | Aggregated | Lifecycle-scoped |
| **Ghost Detection** | GU-1 (selective retention) | Event-driven (ONEPPL takeover) | PostgreSQL | Raw UNITGUIDs | Lifecycle-scoped (delete on close EXCEPT actual deletions → permanent) |

### Data Pipeline Architecture

```
[Bootstrap]                    [Daily Cron]                    [On Close]
Pre-computed JSON file    →    Live Monitor (LM-1)        →   Career Ledger append
loaded via artisan command     + Ghost Detection (GU-1)        + Ghost cleanup (keep deletions only)
for all historical closed      for ~30 active assessments
assessments

One-time install load          Ongoing daily                   Event-driven transition
```

### Career Ledger Table Design (AGREED)

**Table: `planner_career_entries`** — One row per planner per assessment

```
├── id
├── planner_username         (FORESTER or FRSTR_USER)
├── planner_display_name     (human-readable name)
├── job_guid                 (string — the assessment identifier)
├── line_name                (circuit)
├── region
├── scope_year
├── cycle_type               (trim, herbicide, etc.)
├── assessment_total_miles   (VEGJOB.LENGTH at time of close)
├── assessment_completed_miles
├── assessment_pickup_date   (first JOBHISTORY entry for this planner)
├── assessment_qc_date       (JOBHISTORY where status → QC)
├── went_to_rework           (boolean)
├── rework_details           (JSONB, nullable — audit info if applicable)
├── daily_metrics            (JSONB — daily breakdown keyed by date)
├── summary_totals           (JSONB — pre-computed aggregates)
├── source                   ('bootstrap' | 'live_monitor')
├── created_at
└── updated_at
```

**daily_metrics JSONB structure (date-keyed for O(1) lookup):**
```json
{
  "2026-01-15": {
    "footage_feet": 2340.5,
    "stations_completed": 12,
    "work_units": 15,
    "nw_units": 3
  },
  "2026-01-16": { ... }
}
```

**summary_totals JSONB (pre-computed for fast display):**
```json
{
  "total_footage_feet": 48250.7,
  "total_footage_miles": 9.14,
  "total_stations": 245,
  "total_work_units": 312,
  "total_nw_units": 67,
  "working_days": 52,
  "avg_daily_footage_feet": 928.1,
  "first_activity_date": "2026-01-15",
  "last_activity_date": "2026-03-28"
}
```

**rework_details JSONB (only if went_to_rework = true):**
```json
{
  "rework_count": 1,
  "audit_user": "ONEPPL\\jsmith",
  "audit_date": "2026-03-15",
  "audit_notes": "3 units failed QC...",
  "failed_unit_count": 3
}
```

**Bootstrap strategy:** JSON file + custom artisan command (`ws:import-career-ledger`) — shipped with app, loaded on install. Companion `ws:export-career-ledger` generates the JSON from API.

---

## Open Questions (Unanswered at Session End)

1. **Split/child assessments:** ✅ **RESOLVED** — Child and parent have **separate JOBGUIDs**. Parent EXT = `@`, children = `X_a`, `X_ab`, etc. Units transfer from child → parent on sync. One row per planner-per-JOBGUID works naturally. **Critical constraint:** Parent assessment must NOT be taken ownership of — if it is, child→parent unit merge breaks silently (changes never merge when user syncs). This is a Ghost Detection concern.

2. **Planner-level career summary:** ✅ **RESOLVED** — Compute on-the-fly from `planner_career_entries` using `jsonb_each(daily_metrics)`. Scale analysis: ~120 rows × ~30 JSONB keys = ~3,600 data points max for a 6-year career — trivial for PostgreSQL. UX determines granularity (group by day/week/month/year). Summary table can be added later if needed without schema changes.

3. **Live Monitor table design:** ✅ **RESOLVED** — See "Live Monitor Table Design" section below.

4. **Ghost Detection table design:** ✅ **RESOLVED** — See "Ghost Detection Table Design" section below. Two tables: `ghost_ownership_periods` (scaffolding, lifecycle-scoped) + `ghost_unit_evidence` (permanent). Includes `is_parent_takeover` flag for parent assessment ownership blocking detection.

5. **Notes compliance threshold:** ✅ **RESOLVED** — NOT VEG_POLYGONS (that's LiDAR canopy data). Use **JOBVEGETATIONUNITS** table instead — joined via `JOBGUID + STATNAME + SEQUENCE`. Three unit geometry types discovered:
   - **Polygon units** (BRUSH, HCB, mowing): AREA (square meters) + ACRES fields. 10'×10' = 100 sq ft = 9.29 sq m → check `AREA >= 9.29` or `ACRES >= 0.002296`.
   - **Tree/point units** (VPS, removals): NUMTREES field. No area dimension.
   - **Trim/line units** (SPM, SPB, MPM, MPB): LENGTHWRK (meters). No area dimension.
   - WIDTHWRK/LENGTHWRK are zero for polygon units (size comes from AREA/ACRES instead).
   - Notes compliance check applies only to polygon unit types above the threshold.

6. **Phase 3 (Constraint Mapping):** ✅ **COMPLETE** — See "Phase 3: Constraint Mapping" section below. All constraints assessed as Low/Medium risk with mitigations identified. Key findings: CLOSE is terminal (no CLOSE→REWRK→CLOSE), zero-count snapshots persisted with `suspicious` flag.

---

## Key VEGUNIT Fields Referenced

From extracted schema (`_bmad/ws/data/tables/extracted/02-ss-children/VEGUNIT.md`):

| Field | Type | Purpose in Our Design |
|-------|------|----------------------|
| UNITGUID | ftString(38) | Unique unit identifier — ghost detection key |
| JOBGUID | ftString(38) | Links to assessment (SS table) |
| UNIT | ftString(20) | Unit type code (SPB, BRUSH, etc.) |
| PERMSTAT | ftString(30) | Permission status |
| ASSDDATE | ftDate | Date assessed — daily footage attribution |
| DATEPOP | ftDate | Date unit was populated |
| LASTEDITBY | ftString(50) | Last editor — edit attribution |
| LASTEDITDT | ftDateTime | Last edit timestamp |
| FORESTER | ftString(50) | Planner display name |
| FRSTR_USER | ftString(50) | Planner username |
| PARCELCOMMENTS | ftMemo | Property-level notes |
| ASSNOTE | ftMemo | Unit-level notes |
| AUDIT_FAIL | ftBoolean | QC failure flag |
| AUDIT_USER | ftString(50) | Who audited |
| AUDITDATE | ftDate | When audited |
| AUDITNOTE | ftString(255) | Audit notes |
| QCOMMENTS | ftString(2000) | Work audit tab comments |
| STATNAME | ftString(100) | Station name — footage attribution key |
| SEQUENCE | ftInteger | Unit sequence |

## JOBHISTORY Fields Referenced

| Field | Type | Purpose |
|-------|------|---------|
| JOBGUID | ftString(38) | Links to assessment |
| USERNAME | ftString(150) | Who performed action |
| ACTION | ftString(40) | Transaction type |
| LOGDATE | ftDateTime | When |
| OLDSTATUS | ftString(5) | Previous status |
| JOBSTATUS | ftString(5) | New status |
| ASSIGNEDTO | ftString(150) | Current assignee — ghost trigger detection |

---

## Live Monitor Table Design (AGREED)

**Decision:** Option A — One row per assessment, JSONB time-series (matches Career Ledger pattern)

**Table: `assessment_monitors`** — One row per active assessment, updated daily by cron

```
├── id
├── job_guid                (string — the assessment identifier)
├── line_name               (circuit name)
├── region
├── scope_year
├── cycle_type
├── current_status          (ACTIV/QC/REWRK — updated each snapshot)
├── current_planner         (current FORESTER assigned)
├── total_miles             (VEGJOB.LENGTH — circuit total)
├── daily_snapshots         (JSONB — date-keyed daily metrics)
├── latest_snapshot         (JSONB — denormalized copy of most recent day for fast dashboard reads)
├── first_snapshot_date     (date — when monitoring began)
├── last_snapshot_date      (date — most recent snapshot)
├── created_at
└── updated_at
```

**daily_snapshots JSONB structure (date-keyed):**
```json
{
  "2026-02-10": {
    "permission_breakdown": {
      "Granted": 45,
      "Denied": 3,
      "Pending": 12,
      "Refused": 1,
      "Not Needed": 8
    },
    "unit_counts": {
      "work_units": 58,
      "nw_units": 11,
      "total_units": 69
    },
    "work_type_breakdown": {
      "SPM": 15, "SPB": 12, "REM612": 8, "BRUSH": 5,
      "HCB": 3, "HERBNA": 7, "NW": 8, "NOT": 2, "SENSI": 1
    },
    "footage": {
      "completed_feet": 24500.5,
      "completed_miles": 4.64,
      "percent_complete": 38.2
    },
    "notes_compliance": {
      "units_with_notes": 42,
      "units_without_notes": 27,
      "compliance_percent": 60.9
    },
    "planner_activity": {
      "last_edit_date": "2026-02-10",
      "days_since_last_edit": 0
    },
    "aging_units": {
      "pending_over_threshold": 4,
      "threshold_days": 14
    }
  },
  "2026-02-11": { ... }
}
```

**latest_snapshot JSONB:** Identical structure to a single day's entry — denormalized copy of the most recent `daily_snapshots` entry for O(1) dashboard reads without JSONB key traversal.

**Lifecycle:** Row created when assessment enters ACTIV. Updated daily by cron. On CLOSE: metrics rolled into `planner_career_entries`, then row deleted (or soft-deleted/archived).

**Data corrections:**
- **Pending unit aging:** DATEPOP is never empty (it's the unit creation date). Aging = units where DATEPOP is older than threshold (default 14 days) AND PERMSTAT is empty. Exact logic TBD — not a priority for initial implementation.
- **Notes compliance:** Counts presence of PARCELCOMMENTS or ASSNOTE, not content. Polygon size threshold (10'x10') deferred to Q5.

---

## Ghost Detection Table Design (AGREED)

**Decision:** Two-table design — scaffolding (lifecycle-scoped) + evidence (permanent)

### Table 1: `ghost_ownership_periods` (scaffolding — deleted on assessment CLOSE)

One row per ONEPPL ownership takeover event.

```
├── id
├── job_guid                 (the assessment)
├── line_name                (circuit — denormalized for display)
├── region
├── takeover_date            (when ONEPPL took ownership)
├── takeover_username        (which ONEPPL user)
├── return_date              (nullable — filled when ownership returns)
├── baseline_unit_count      (int — how many units existed at takeover)
├── baseline_snapshot        (JSONB — array of unit records at takeover)
├── is_parent_takeover       (boolean — true if EXT = '@', flags child sync blocking)
├── status                   (enum: 'active' | 'resolved' | 'closed')
├── created_at
└── updated_at
```

**is_parent_takeover flag:** When true, indicates a parent assessment (EXT = `@`) was taken by ONEPPL. While owned, child assessments cannot merge units into the parent. Units from children will populate on the parent once released — it's a blocking condition, not permanent damage. But any child work done during the blocked period is invisible to the parent until release.

**baseline_snapshot JSONB (array of unit records at time of takeover):**
```json
[
  {
    "unitguid": "ABC-123-...",
    "unit": "SPM",
    "statname": "10",
    "permstat": "Granted",
    "forester": "Alice Johnson"
  },
  { ... }
]
```

Captures enough metadata to identify deleted units after the fact — UNITGUID for set comparison, plus type/station/permission/planner for reporting context.

### Table 2: `ghost_unit_evidence` (permanent — never deleted)

One row per confirmed deleted unit.

```
├── id
├── ownership_period_id      (FK → ghost_ownership_periods, nullable after cleanup)
├── job_guid                 (denormalized — survives scaffolding deletion)
├── line_name                (denormalized)
├── region                   (denormalized)
├── unitguid                 (the deleted unit's GUID)
├── unit_type                (SPM, BRUSH, etc. — from baseline snapshot)
├── statname                 (which station)
├── permstat_at_snapshot     (permission status when we last saw it)
├── forester                 (who created the unit)
├── detected_date            (when we first noticed it missing)
├── takeover_date            (when ONEPPL took ownership — denormalized)
├── takeover_username        (which ONEPPL user had it — denormalized)
├── created_at
```

### Ghost Detection Lifecycle

```
JOBHISTORY: ONEPPL takes ownership
    │
    ▼
ghost_ownership_periods: INSERT (status='active', is_parent_takeover=EXT=='@')
    + baseline_snapshot: snapshot all UNITGUIDs + metadata
    │
    │  ◄── Daily cron: compare current UNITGUIDs vs baseline
    │       Missing? → INSERT into ghost_unit_evidence
    │
    ▼
JOBHISTORY: ownership returns to planner
    │
    ▼
ghost_ownership_periods: UPDATE (return_date, status='resolved')
    + Final comparison → any new ghosts → INSERT evidence
    │
    ▼
Assessment CLOSE event
    │
    ▼
ghost_ownership_periods: DELETE row (scaffolding cleanup)
ghost_unit_evidence: KEPT PERMANENTLY (self-contained, no FK dependency)
```

### Parent Takeover Alert Flow

```
JOBHISTORY: ONEPPL takes parent assessment (EXT = '@')
    │
    ▼
ghost_ownership_periods: INSERT with is_parent_takeover = true
    │
    ▼
Dashboard alert: "Parent assessment [line_name] owned by [username] — child sync blocked"
    │
    ▼
On release: alert clears, child units merge normally
```

---

## Supplementary Data Source: V_ASSESSMENT (View)

**Discovery:** V_ASSESSMENT is a pre-aggregated WorkStudio database view providing one row per assessment per unit type with quantities already summed.

### Fields (from API sample data)

| Field | Type | Description |
|-------|------|-------------|
| wo | string | Work order |
| ext | string | Extension (@ = parent) |
| jobguid | string | Assessment GUID |
| pjobguid | string | **Parent job GUID** — direct parent/child link |
| jobtype | string | Assessment type (e.g., "Assessment Dx") |
| title | string | Circuit name |
| status | string | Assessment status (ACTIV/QC/REWRK/CLOSE) |
| unit | string | Unit type code |
| UnitQty | float | Aggregated quantity — **meters** for trim/line, **sq meters** for polygon, **count** for discrete |

### Example Data (KINZER 69/12 KV 42-04 LINE — CLOSE)

| Unit | UnitQty | Interpretation |
|------|---------|---------------|
| HERBNA | 5,455.85 | meters of non-aquatic herbicide line |
| MPB | 1,898.75 | meters of multi-phase bucket trim |
| MPBE | 586.09 | meters of multi-phase bucket trim (emergency?) |
| MPM | 182.38 | meters of multi-phase manual trim |
| NOT | 21 | notification points (count) |
| NW | 1,722 | no-work stations (count) |
| REM1218 | 17 | 12-18" removals (count) |

### Architecture Impact

**Use V_ASSESSMENT for:**
- Career Ledger `summary_totals` work type breakdowns at close time (one query per assessment vs thousands of VEGUNIT records)
- Live Monitor `work_type_breakdown` in daily snapshots (cheap assessment-level rollup)
- Parent/child relationship confirmation via `pjobguid`

**Still requires VEGUNIT for:**
- Daily footage attribution (needs ASSDDATE + FORESTER per unit)
- Per-planner breakdowns (V_ASSESSMENT is assessment-level, not planner-level)
- Permission status counts (VEGUNIT.PERMSTAT)
- Notes compliance (VEGUNIT + JOBVEGETATIONUNITS)
- Ghost Detection baseline snapshots (needs individual UNITGUIDs)

### Unit Geometry Types (confirmed from JOBVEGETATIONUNITS samples)

| Geometry | Unit Types | Measurement Field | Unit |
|----------|-----------|-------------------|------|
| Polygon (area) | BRUSH, HCB, mowing | AREA / ACRES | Square meters / acres |
| Tree (point) | VPS, removals (REM*) | NUMTREES | Count |
| Trim (line) | SPM, SPB, MPM, MPB, HERB* | LENGTHWRK | Meters |

All measurements in the WorkStudio system use **meters** as the base unit (AREA = sq meters, LENGTHWRK = meters, SPANLGTH = meters). Conversions to feet/miles/acres done at display time.

---

## Phase 3: Constraint Mapping + Chaos Engineering (COMPLETE)

### API Rate & Capacity Constraints

| Constraint | Value | Source |
|---|---|---|
| Request timeout | 60 seconds | config/workstudio.php |
| Connect timeout | 10 seconds | config/workstudio.php |
| Max retries | 5 | config/workstudio.php |
| Rate limit | 0.5s delay every 5 calls | sync settings |
| No CTEs | Hard DDOProtocol limitation | Use derived tables |

### Per-Domain Load Analysis

| Domain | Calls per Run | At Current Scale (~30 active) | At 10x Scale (~300 active) |
|---|---|---|---|
| Career Ledger bootstrap | ~100-400 (batched) | One-time, 10-30 min | Same (one-time) |
| Live Monitor daily cron | ~60-90 | ~6-9s throttle delay | ~60-90s — still fine |
| Ghost Detection | ~2-4 per event | Trivial | Trivial |

**Verdict:** Well within API capacity at all projected scales.

### JSONB Growth Analysis

| Assessment Duration | Snapshot Keys | JSONB Size | Concern? |
|---|---|---|---|
| 1 month | ~30 | ~15 KB | None |
| 6 months | ~180 | ~90 KB | None |
| 1 year (extreme) | ~365 | ~180 KB | None |

PostgreSQL JSONB limit is 1 GB. Maximum realistic size is ~180 KB. **No concern.**

### Failure Scenario Analysis

| Failure Mode | Impact | Mitigation |
|---|---|---|
| API fully down | Missing snapshot day | Gap in daily_snapshots — acceptable, just a missing date key |
| Partial failure | Some assessments missing | Process each assessment independently — failures don't block others |
| Timeout on large assessment | One snapshot missing | Retry with max_retries=5, then log and skip |
| **Silent empty result** | Zero-count snapshot overwrites real data | **Sanity check: persist with `"suspicious": true` flag in JSONB when today's total_units = 0 but yesterday's was > 0** |

### Key Clarifications Resolved

1. **CLOSE is terminal.** An assessment never goes from CLOSE → REWRK → CLOSE. The status flow is strictly: SA → ACTIV → QC ↔ REWRK → CLOSE. Once closed, done. Career ledger entries are write-once, never updated.

2. **Zero-count sanity check:** Persist the snapshot with a `"suspicious": true` flag rather than skipping. This preserves the data point for investigation while preventing silent data corruption.

### Ghost Detection Edge Cases

| Scenario | Risk | Notes |
|---|---|---|
| Multiple ONEPPL takeovers on same assessment | Low | Each creates its own ownership_period row with separate baseline |
| Missed daily cron run | Low | Next run catches deletions, detected_date slightly late |
| ONEPPL adds units then removes them | Low | Only units in the baseline (pre-takeover) are tracked — new units added by ONEPPL and then removed are not flagged (correct behavior) |
| Parent assessment taken by ONEPPL | Medium | is_parent_takeover flag triggers dashboard alert about child sync blocking |

### No-CTE Query Assessment

| Query | Needs CTE? | Workaround |
|---|---|---|
| First-unit-wins (footage attribution) | Yes — ROW_NUMBER PARTITION | Derived table subquery (proven pattern) |
| Ghost UNITGUID snapshot | No | Simple SELECT UNITGUID WHERE JOBGUID = ? |
| V_ASSESSMENT rollup | No | Pre-built view |
| Permission breakdown | No | GROUP BY PERMSTAT |
| Notes compliance + JOBVEGETATIONUNITS | Possibly | Derived table if needed |

### Final Risk Summary

| Constraint | Risk | Status |
|---|---|---|
| API rate limits | **Low** | Within limits at 10x scale |
| JSONB growth | **Low** | Max ~180 KB |
| Network failures | **Medium** | Per-assessment isolation + suspicious flag |
| Ghost false positives | **Low** | Baseline-only comparison |
| Career ledger write conflicts | **None** | CLOSE is terminal, write-once |
| Bootstrap scale | **Low** | One-time, progress bar |
| No-CTE queries | **Low** | Derived table patterns proven |
| Empty API response trap | **Medium** | Suspicious flag on zero-count snapshots |

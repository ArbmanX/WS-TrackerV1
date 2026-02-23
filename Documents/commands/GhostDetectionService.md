# `GhostDetectionService`

> **Class:** `App\Services\WorkStudio\DataCollection\GhostDetectionService`
> **Called by:** [`ws:run-live-monitor --include-ghost`](./RunLiveMonitor.md)
> **Queries:** [`GhostDetectionQueries`](./GhostDetectionQueries.md)

---

## Purpose

Detects "ghost units" — vegetation units that disappear from an assessment after a third-party (ONEPPL) takes ownership. The system captures a baseline snapshot of all UNITGUIDs at the moment of takeover, then runs daily comparisons to find any units that were deleted.

---

## Concept

```
  ONEPPL takes over assessment
           │
           ▼
  ┌─────────────────────┐
  │  Baseline Snapshot   │    ← All UNITGUIDs at takeover time
  │  (GhostOwnership     │
  │    Period)            │
  └─────────────────────┘
           │
           ▼  (daily comparison)
  Current UNITGUIDs from API
           │
           ▼
  baseline − current − already_detected = NEW GHOSTS
           │
           ▼
  ┌─────────────────────┐
  │  GhostUnitEvidence   │    ← One row per disappeared unit
  └─────────────────────┘
```

---

## Dependencies

```
GhostDetectionService
  ├── GetQueryService            ── executes SQL against WorkStudio API
  ├── GhostDetectionQueries      ── generates T-SQL strings
  ├── GhostOwnershipPeriod       ── baseline tracking model
  └── GhostUnitEvidence          ── individual ghost unit records
```

---

## Public Methods

### `checkForOwnershipChanges(): int`

Scans `JOBHISTORY` for recent ONEPPL takeovers and creates baseline snapshots.

| Step | Detail |
|:--|:--|
| 1 | Determine `$since` — latest `GhostOwnershipPeriod.created_at`, or 7 days ago |
| 2 | Query [`getRecentOwnershipChanges()`](./GhostDetectionQueries.md#getrecentownershipchanges) |
| 3 | For each change not already tracked → `createBaseline()` |

**Returns:** Count of new ownership periods created.

---

### `createBaseline(string $jobGuid, string $username, bool $isParent, array $assessmentMeta = []): GhostOwnershipPeriod`

Captures a UNITGUID snapshot at the moment of takeover.

1. Queries [`getUnitGuidsForAssessment()`](./GhostDetectionQueries.md#getunitguidsforassessment) for all current units
2. Maps each unit to: `unitguid`, `unit_type`, `statname`, `permstat`, `forester`
3. Creates a `GhostOwnershipPeriod` with the baseline

**Baseline snapshot structure:**
```json
[
  {
    "unitguid": "{XXXXXXXX-...}",
    "unit_type": "Trim",
    "statname": "Oak St",
    "permstat": "Approved",
    "forester": "jsmith"
  }
]
```

---

### `runComparison(GhostOwnershipPeriod $period): int`

Daily set-difference comparison:

```
missing = baseline_guids − current_guids − already_detected_guids
```

For each missing unit, creates a [`GhostUnitEvidence`](./GhostUnitEvidence.md) record preserving the unit's metadata from the baseline snapshot.

**Returns:** Count of newly detected ghost units.

---

### `resolveOwnershipReturn(GhostOwnershipPeriod $period): void`

Called when an assessment returns to its original owner:
1. Runs one final comparison
2. Sets `status = 'resolved'` and `return_date = today`

---

### `cleanupOnClose(string $jobGuid): void`

Called via [`ProcessAssessmentClose`](./AssessmentClosed.md#listener) when an assessment closes:
- Deletes all `GhostOwnershipPeriod` rows for the job_guid
- FK `ON DELETE SET NULL` preserves `GhostUnitEvidence` rows (evidence survives, period reference nulled)

---

## Models

### [`GhostOwnershipPeriod`](./GhostOwnershipPeriod.md)

| Column | Type | Description |
|:--|:--|:--|
| `job_guid` | string | Assessment being tracked |
| `line_name` | string | Circuit/line name |
| `region` | string | Regional grouping |
| `takeover_date` | date | When ONEPPL took ownership |
| `takeover_username` | string | ONEPPL username |
| `return_date` | date | When ownership returned (null if active) |
| `baseline_unit_count` | int | Number of units at baseline |
| `baseline_snapshot` | json | Full unit snapshot array |
| `is_parent_takeover` | bool | True if `EXT = @` (parent assessment) |
| `status` | string | `active` or `resolved` |

**Scopes:** `active()`, `resolved()`, `parentTakeovers()`
**Relationship:** `evidence()` → `HasMany GhostUnitEvidence`

---

### [`GhostUnitEvidence`](./GhostUnitEvidence.md)

| Column | Type | Description |
|:--|:--|:--|
| `ownership_period_id` | FK (nullable) | Link to ownership period (SET NULL on delete) |
| `job_guid` | string | Assessment the unit belonged to |
| `unitguid` | string | The disappeared unit's GUID |
| `unit_type` | string | Unit classification |
| `statname` | string | Station name |
| `permstat_at_snapshot` | string | Permission status when baseline was taken |
| `forester` | string | Assigned forester |
| `detected_date` | date | When the unit was first noticed missing |
| `takeover_date` | date | When the takeover occurred |
| `takeover_username` | string | Who took over |

**Scopes:** `forAssessment($guid)`, `detectedBetween($from, $to)`
**Relationship:** `ownershipPeriod()` → `BelongsTo GhostOwnershipPeriod`

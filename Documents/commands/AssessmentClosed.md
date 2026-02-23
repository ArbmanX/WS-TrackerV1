# `AssessmentClosed` Event

> **Event:** `App\Events\AssessmentClosed`
> **Dispatched by:** [`LiveMonitorService::detectClosedAssessments()`](./LiveMonitorService.md)
> **Listener:** `App\Listeners\ProcessAssessmentClose`

---

## Event

Fired when a previously-monitored assessment is no longer in the active set returned by the WorkStudio API.

```php
AssessmentClosed::dispatch($monitor, $monitor->job_guid);
```

### Properties

| Property | Type | Description |
|:--|:--|:--|
| `$monitor` | `AssessmentMonitor` | The monitor row being closed |
| `$jobGuid` | `string` | Assessment GUID |

---

## Listener

**Class:** `App\Listeners\ProcessAssessmentClose`
**Queue:** Yes (`implements ShouldQueue`)

### What It Does

Runs inside a database transaction:

1. **Ghost cleanup** — calls [`GhostDetectionService::cleanupOnClose()`](./GhostDetectionService.md#cleanuponclosejobguid)
   - Deletes all `GhostOwnershipPeriod` rows for the job_guid
   - FK `ON DELETE SET NULL` preserves `GhostUnitEvidence` rows
2. **Monitor deletion** — removes the `AssessmentMonitor` row
3. **Logging** — logs `job_guid` and `line_name` to application log

### Flow

```
AssessmentClosed event
       │
       ▼
ProcessAssessmentClose (queued)
       │
       ├── DB::transaction
       │     ├── ghostDetection->cleanupOnClose(jobGuid)
       │     └── monitor->delete()
       │
       └── Log::info('Assessment close processed', [...])
```

---

## When Does Closure Happen?

An assessment is considered "closed" when:
1. It previously had an `AssessmentMonitor` row (was being tracked)
2. Its `job_guid` is **not** in the current set of active GUIDs returned by `getDailyActivitiesForAllAssessments()`

This means the assessment's status changed to something outside `ACTIV`, `QC`, `REWRK` — typically `CLOSE`, `DEF`, or it was deleted.

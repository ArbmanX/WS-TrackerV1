# Command Documentation

> Internal reference docs for WS-TrackerV1 artisan commands and their supporting architecture.

---

## `ws:run-live-monitor`

Daily assessment health monitoring and ghost unit detection.

### Architecture Map

```
┌──────────────────────────────────────────────────────────────────────┐
│                        ws:run-live-monitor                           │
│                         (RunLiveMonitor)                              │
└───────────┬─────────────────────────────────────┬────────────────────┘
            │                                     │
            ▼                                     ▼
┌───────────────────────┐             ┌───────────────────────────┐
│  LiveMonitorService   │             │  GhostDetectionService    │
│  ├── LiveMonitorQueries│             │  ├── GhostDetectionQueries│
│  ├── AssessmentMonitor │             │  ├── GhostOwnershipPeriod │
│  └── AssessmentClosed  │             │  └── GhostUnitEvidence    │
└───────────────────────┘             └───────────────────────────┘
            │                                     │
            ▼                                     ▼
┌──────────────────────────────────────────────────────────────────────┐
│                     WorkStudio DDOProtocol API                       │
│                      (T-SQL via HTTP POST)                           │
└──────────────────────────────────────────────────────────────────────┘
```

### File Index

| Document | What It Covers |
|:--|:--|
| [`RunLiveMonitor.md`](./RunLiveMonitor.md) | Command — options, usage, execution flow |
| [`LiveMonitorService.md`](./LiveMonitorService.md) | Service — snapshot logic, data flow, snapshot JSON structure |
| [`GhostDetectionService.md`](./GhostDetectionService.md) | Service — ownership tracking, baseline/comparison, models |
| [`LiveMonitorQueries.md`](./LiveMonitorQueries.md) | Query builder — daily snapshot SQL, tables, columns |
| [`GhostDetectionQueries.md`](./GhostDetectionQueries.md) | Query builder — ownership changes, unit GUIDs |
| [`AssessmentMonitor.md`](./AssessmentMonitor.md) | Model — schema, addSnapshot(), scopes, lifecycle |
| [`AssessmentClosed.md`](./AssessmentClosed.md) | Event + listener — closure handling, cleanup |
| [`Config.md`](./Config.md) | Configuration — thresholds, feature toggles |

### SQL Queries

| Query | Purpose |
|:--|:--|
| [`sql/daily-snapshot.sql`](./sql/daily-snapshot.sql) | Combined metrics query (permissions, units, notes, aging) |
| [`sql/ownership-changes.sql`](./sql/ownership-changes.sql) | JOBHISTORY scan for ONEPPL takeovers |
| [`sql/unit-guids.sql`](./sql/unit-guids.sql) | All valid UNITGUIDs for baseline/comparison |

---

## `ws:fetch-assessments`

| Document | What It Covers |
|:--|:--|
| [`FetchAssessments.md`](./FetchAssessments.md) | Command — API sync, upsert, circuit enrichment |

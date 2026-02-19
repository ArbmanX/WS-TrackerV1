# M10: Monitoring Hub + Ghost Detail

> Depends on: M2
> Unlocks: none

## Goal

Admin-only monitoring hub at `/monitoring` with ghost detail page at `/monitoring/ghosts/{id}`.

## Micro Tasks

### Hub
- [ ] Ghost Detection card — active/resolved counts → Ghost list
- [ ] Sync Status card — last sync time, failed rows
- [ ] Data Freshness card — per-table last update times, staleness flags
- [ ] Assessment Monitors card — active/QC/rework per circuit, anomaly flags

### Ghost Detail
- [ ] Ghost header — affected circuit, planner, status (active/resolved)
- [ ] Ownership periods timeline
- [ ] Unit evidence list
- [ ] Resolution status and actions

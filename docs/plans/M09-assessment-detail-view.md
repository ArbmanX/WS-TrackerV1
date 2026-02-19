# M9: Assessment Detail View

> Depends on: M8
> Unlocks: M16

## Goal

Assessment page at `/assessments/{job_guid}`. Full detail of a single assessment including splits and VEGJOB data.

## Micro Tasks

- [ ] Assessment header — job GUID, status badge, scope year, extension
- [ ] Core data section — circuit, planner, dates (assessed, populated, edited)
- [ ] Parent/child split display — if split, show parent link and sibling splits
- [ ] VEGJOB fields — planned/emergent, voltage, cost method, program name, permissioning required
- [ ] Permission status breakdown — per-unit permission statuses
- [ ] Work measurements — unit counts, work vs non-work
- [ ] Edit history / last synced info
- [ ] Cross-links: circuit → Circuit Detail, planner → Planner Detail, parent GUID → Assessment Detail

# M6: Assessments Hub

> Depends on: M2
> Unlocks: M7, M8, M15

## Goal

Admin/manager view of all assessments. Stat cards at top, card grid for regional, status, scope year, and split views.

## Micro Tasks

- [ ] Stat cards row — Total, Active, QC, Rework, Deferred, Closed
- [ ] By Region card — region tiles with circuit count and completion % → Region Detail
- [ ] By Status card — pipeline/kanban-style view (Active→QC→Complete) → filtered list
- [ ] Scope Years card — progress bars per year (2025, 2026, ...) → filtered list
- [ ] Split Tracking card — parent/child split completion status
- [ ] Wire to Assessment model, AggregateQueries, CircuitQueries, RegionalSnapshot

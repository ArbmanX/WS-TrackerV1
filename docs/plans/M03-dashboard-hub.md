# M3: Dashboard Hub

> Depends on: M2
> Unlocks: none (standalone)

## Goal

The admin/manager landing page. Two-column layout: planner health + assessment progress. Every card links deeper into other hubs or detail views.

## Micro Tasks

- [ ] Planner Snapshot card — active count, behind-quota count, link to Planners Hub
- [ ] Assessment Pipeline card — active/QC/rework/deferred counts, link to Assessments Hub
- [ ] Regional Summary card — 5 regions with status bars, links to Region Detail
- [ ] Recent Activity card — last 24h events (new assessments, syncs)
- [ ] Alerts card — active ghost detections, stale planners, link to Monitoring Hub
- [ ] Scope Year Progress card — completion % by year with mini bars
- [ ] Wire cards to existing data sources (SystemWideSnapshot, RegionalSnapshot, AssessmentMonitor, PlannerMetricsService, GhostOwnershipPeriod)

# M15: General Foreman Role Views

> Depends on: M1, M4, M6
> Unlocks: none

## Goal

Tailored sidebar and hub pages for the general-foreman role. Three hubs scoped to their region.

## Micro Tasks

- [ ] Dashboard hub — regional KPIs, team summary stats (scoped to foreman's region)
- [ ] Team hub — planner cards (reuse `<x-planner.card>`) sorted by performance
- [ ] Assessments hub — regional assessments filtered to their region
- [ ] Region scoping — use ResourceGroupAccessService to determine foreman's region
- [ ] Reuse admin hub components with region filter applied

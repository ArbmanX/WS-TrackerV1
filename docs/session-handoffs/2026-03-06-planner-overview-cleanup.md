# Planner Overview Cleanup — Session Handoff

**Date:** 2026-03-06
**Status:** Planning complete, implementation not started
**Branch:** None yet — create `feature/planner-overview-data-source` before starting

---

## STOP — Check for Over-Scoping

This session started with "clean up the planner metrics views" and expanded into cache layers, API resources, multi-component sharing, and edit persistence. **The actual objective is simpler:**

> Switch where the Overview page gets its data from. Keep it to the Overview page. Don't redesign the entire data layer.

Before implementing, ask: "Does this change touch more than the Overview data source?" If yes, cut scope.

---

## Objective

Refactor the Overview Livewire component's data source so it consumes a single, pre-assembled JSON payload instead of calling multiple service methods inline via computed properties.

## What Exists Today

- `app/Livewire/PlannerMetrics/Overview.php` — assembles data via computed properties calling `PlannerMetricsServiceInterface`
- `app/Services/PlannerMetrics/PlannerMetricsService.php` — `getUnifiedMetrics()`, `getPeriodLabel()`, `getDefaultOffset()`
- `app/Services/PlannerMetrics/CoachingMessageGenerator.php` — exists but NOT wired into Overview
- Views: `overview.blade.php`, `components/planner/card.blade.php`, partials `_stat-cards`, `_circuit-accordion`, `_coaching-message`

## Diagrams Created

All in Obsidian vault (`~/ObsidianVault/Projects/WS-Tracker/`):

1. **`planner-metrics-dataflow.excalidraw`** — Full system DFD showing career-based path vs daily operational path, all data stores, all processes, with field annotations on each side
2. **`planner-overview-architecture.excalidraw`** — Function-level comparison of controller flow vs cache-first flow, showing every sub-function in `getUnifiedMetrics()` and data passing between them

Also copied to `BMAD_WS/excalidraw-diagrams/`.

## JSON Payload Shape (agreed upon)

The Overview page needs this structure (see diagram annotations for full field list):

```json
{
  "period": { "label", "offset", "is_current", "week_start_date", "week_end_date" },
  "summary": { "on_track", "total_planners", "needs_attention", "team_avg_percent", "total_aging", "total_miles" },
  "config": { "quota_target", "staleness_warning_days", "staleness_critical_days", "gap_warning_threshold" },
  "planners": [{
    "username", "display_name", "initials", "status", "status_label",
    "period_miles", "quota_target", "quota_percent", "gap_miles",
    "streak_weeks", "days_since_last_edit", "pending_over_threshold",
    "overall_percent", "active_assessment_count", "total_miles",
    "permission_breakdown", "coaching_message", "daily_miles[]",
    "circuits": [{ "job_guid", "line_name", "region", "total_miles",
      "completed_miles", "percent_complete", "permission_breakdown" }]
  }],
  "meta": { "generated_at" }
}
```

## Key Findings

- `initials` and `status_label` are currently derived inline in blade views — should be promoted into the payload
- `coaching_message` generator exists but was never wired in — can be activated during payload assembly
- `needs_attention` is computed only in `_stat-cards.blade.php` — belongs in the payload
- `total_miles` (circuit career total) vs `period_miles` (weekly quota miles) — both exist, different meanings, don't conflate
- `display_name` is derived differently in Overview (service) vs Production (component) — normalizing it in the payload fixes this
- `getDateWindow()` is private on the service — needs to be exposed if `week_start_date`/`week_end_date` are wanted in the payload

## Implementation Approach

**Single action class** that:
1. Calls existing service methods (`getUnifiedMetrics`, `getPeriodLabel`, `getDefaultOffset`)
2. Runs `CoachingMessageGenerator::generate()` per planner
3. Promotes `initials`, `status_label`, `needs_attention`
4. Assembles the full payload
5. Caches it (or returns it directly — decide at implementation time)

**Then** the Overview Livewire component reads from that payload instead of calling services directly.

## What NOT To Do

- Don't refactor Production component in this branch
- Don't build a full API endpoint / controller / resource pattern
- Don't add edit mutation persistence yet
- Don't change the service layer internals
- Don't add new routes
- Keep it to: one action class + update Overview component to consume it

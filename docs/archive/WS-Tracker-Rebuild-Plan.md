# WS-Tracker Final Version Rebuild Plan

## Overview

Rebuild WS-Tracker from scratch in a fresh Laravel 12 + Livewire 4 project, cherry-picking functionality piece by piece with proper integration.

## Current State

| Item | Old (WS-Tracker) | New (WS-TrackerV1) |
|------|------------------|-------------------|
| **Location** | `~/WorkStudioDev/WS-Tracker/` | `~/WorkStudioDev/WS-TrackerV1/` |
| **Framework** | Laravel 12 + Livewire 3 | Laravel 12 + Livewire 4 |
| **UI** | Flux UI Free | DaisyUI 5 |
| **Data Source** | WorkStudio DDOProtocol | New REST API |
| **Approach** | Monolithic sync | TBD (decoupled) |

## Key Decisions

- **Keep:** All major functionality (Kanban, Analytics, Admin tools, Sync)
- **Discard:** Current API integration, Flux UI, existing sync architecture
- **Change:** Data model, UI framework, sync architecture
- **Method:** Hybrid BMad approach (architecture → quick-spec iterations)

---

## Phase 1: Project Setup

### 1.1 Install DaisyUI 5 in WS-TrackerV1

```bash
cd ~/WorkStudioDev/WS-TrackerV1
npm install daisyui@5
```

Update CSS to use DaisyUI:
```css
/* resources/css/app.css */
@import "tailwindcss";
@plugin "daisyui";
```

### 1.2 Copy DaisyUI Agent

Copy the DaisyUI design agent from old project:
```
~/.../WS-Tracker/.claude/agents/DaisyUI.md → WS-TrackerV1/.claude/agents/
```

### 1.3 Configure Base Layout

Create a DaisyUI-based app shell layout (navbar, sidebar, content area).

---

## Phase 2: Architecture Planning (BMad Method)

### 2.1 Run Architecture Command

```
/create-architecture
```

Define:
- New data model structure
- REST API integration pattern (decoupled, interface-based)
- Sync architecture (generic, not tied to specific models)
- Component structure

### 2.2 Key Architecture Decisions to Make

| Decision | Options | Notes |
|----------|---------|-------|
| API Client | Interface-based service | Allow swapping data sources |
| Sync Pattern | Event-driven vs scheduled | Decouple from specific models |
| Data Model | Similar to old vs redesign | Review Circuit/Region/Aggregate structure |
| State Management | Livewire properties vs Alpine stores | Livewire 4 has changes |

---

## Phase 3: Iterative Feature Development

Build features one at a time using quick-spec → dev-story → code-review cycle.

### Suggested Feature Order

| # | Feature | Dependencies | Cherry-pick from |
|---|---------|--------------|------------------|
| 1 | Auth + User roles | None | Fortify config, role middleware |
| 2 | Dashboard shell | Auth | Layouts, navigation |
| 3 | Core data model | Dashboard | Model structure (adapt) |
| 4 | API client service | Models | Interface design (new) |
| 5 | Basic CRUD | Models, API | Livewire patterns |
| 6 | Kanban board | CRUD | CircuitKanban component |
| 7 | Analytics dashboard | Models | Aggregate services |
| 8 | Admin tools | All above | Admin components |
| 9 | Sync system | API client | New architecture |

### Per-Feature Workflow

```
/quick-spec   → Document what to build
/dev-story    → Implement the story
/code-review  → Validate quality
```

---

## Phase 4: Cherry-Pick Reference

### Code Worth Adapting (from WS-Tracker)

**Models & Database:**
- `app/Models/` - Review structure, adapt schema
- `database/migrations/` - Reference for new migrations

**Services (adapt patterns, not code):**
- `AggregateCalculationService` - Computation logic
- `SyncOutputLogger` - Progress tracking pattern
- `DDOTableTransformer` - Transformer pattern

**Livewire Components (adapt to Livewire 4 + DaisyUI):**
- `WithCircuitFilters` trait - Filter pattern
- `OnboardingWizard` - Wizard pattern
- Component structure/organization

**Tests:**
- Test patterns and coverage approach
- Pest configuration

### Code to NOT Copy

- `WorkStudioApiService` - Completely new API
- `CircuitSyncService` - New sync architecture
- Flux UI components - Replace with DaisyUI
- Hard-coded WorkStudio references

---

## Verification

After each feature:
1. Run `php artisan test --compact` for affected tests
2. Run `npm run build` and verify UI
3. Manual smoke test in browser
4. Run `vendor/bin/pint --dirty` for code style

---

## Files to Modify

### Phase 1 (Setup)
- `WS-TrackerV1/resources/css/app.css` - DaisyUI setup
- `WS-TrackerV1/package.json` - Add DaisyUI dependency
- `WS-TrackerV1/.claude/agents/DaisyUI.md` - Copy agent
- `WS-TrackerV1/resources/views/components/layouts/` - Base layouts

### Phase 2+ (Per Feature)
- Determined by `/quick-spec` output for each feature

---

## Next Steps

1. **Approve this plan** to begin
2. **Phase 1:** Set up DaisyUI 5 in fresh project
3. **Phase 2:** Run `/create-architecture` to design the new system
4. **Phase 3:** Pick first feature and start iterating

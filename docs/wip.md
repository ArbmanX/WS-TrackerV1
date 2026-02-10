# Work In Progress

## Active: Planner Analytics Design + Credential Security Fix

**Handoff:** `docs/session-handoffs/2026-02-10-credential-audit-and-planner-design.md`

### Uncommitted on `main`:
- Design prototypes (mock-a, mock-b, MockPreview component, route, design docs)
- Test fix (ReferenceDataSeederTest region order)
- **Branch before committing!**

### Pending Actions:
1. Review design prototypes at `/design/planner-analytics` (?design=a or ?design=b)
2. Edit `docs/design/planner-analytics-data-inventory.md` (add/remove/adjust data points)
3. Implement credential security fix (SEC-001) — audit complete, plan ready
   - Fix `GetQueryService.php` hardcoded credentials (CRITICAL)
   - Route all 11 files through `ApiCredentialManager`
   - Remove config defaults, add `DBParameters` helper

# Work In Progress

## Branch: `feature/ws-sql-caster`

### WSSQLCaster — Data-Driven SQL Field Casting

**Status:** Implementation complete, ready for review/merge

**Changes:**
| Action | File |
|--------|------|
| Created | `app/Services/WorkStudio/Shared/Helpers/WSSQLCaster.php` |
| Modified | `app/Services/WorkStudio/Assessments/Queries/SqlFragmentHelpers.php` |
| Modified | `app/Console/Commands/FetchSsJobs.php` |
| Created | `tests/Unit/WSSQLCasterTest.php` |

**Key decisions:**
- Removed incorrect `-2 day` offset from OLE Automation date conversion
- Field registry as `private const FIELDS` for zero-allocation lookup
- `oleDateToCarbon()` uses `round()` on fractional seconds for accuracy

**Test results:** 240 passed, 1 pre-existing failure (ReferenceDataSeederTest — region sort order, unrelated)

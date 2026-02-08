# Work In Progress

## Feature: SS Jobs & WS Users Data Sync
**Branch:** `feature/ss-jobs-sync`
**Started:** 2026-02-07

---

## Implementation Status

| Step | Task | Status |
|------|------|--------|
| 1 | Migration: `ws_users` table | done |
| 2 | Model + Factory: `WsUser` | done |
| 3 | Command: `ws:fetch-users` | done |
| 4 | Migration: `ss_jobs` table | done |
| 5 | Model + Factory: `SsJob` | done |
| 6 | Command: `ws:fetch-jobs` | done |
| 7 | Add `ssJobs()` to Circuit model | done |
| 8 | Tests (41 tests, 96 assertions) | done |
| 9 | Drop self-referential FK on parent_job_guid | done |

## Files Created
- `database/migrations/2026_02_07_223216_create_ws_users_table.php`
- `database/migrations/2026_02_08_012417_create_ss_jobs_table.php`
- `database/migrations/2026_02_08_053417_drop_parent_job_guid_foreign_from_ss_jobs.php`
- `app/Models/WsUser.php`
- `app/Models/SsJob.php`
- `database/factories/WsUserFactory.php`
- `database/factories/SsJobFactory.php`
- `app/Console/Commands/FetchWsUsers.php`
- `app/Console/Commands/FetchSsJobs.php`
- `tests/Feature/Models/WsUserTest.php`
- `tests/Feature/Models/SsJobTest.php`
- `tests/Feature/Commands/FetchWsUsersCommandTest.php`
- `tests/Feature/Commands/FetchSsJobsCommandTest.php`

## Files Modified
- `app/Models/Circuit.php` — added `ssJobs()` HasMany + `HasMany` import
- `CHANGELOG.md` — added feature entry

## Ready for: commit, then merge to main

# Project Rules — WS-TrackerV1

> These rules apply to ALL development on this project. No exceptions without team approval.

---

## UI Framework

- **Use DaisyUI** for all user interface components
- **Leverage DaisyUI theming** — use theme variables, not hardcoded colors
- Components should support theme switching without code changes

---

## AI Context Management

- look for context management file in .claude folder if one does not exist create one. 
- use the context management file to continue work / tasks after context is cleared by user. 
- warn user when context is at 60% 
- at 70% context update the context management file in the .claude folder with the relevant info for the current tasks
- at 70% context prompt user to clear context and refer agent to context file to continue where you left off

---

**Once finished with new phase:** 

  1. ensure the TODO tracker and changelog is up to date.
  2. Check if wip file is clear. If not clear prompt user to decide what to do next.

**Before starting any new phase:**

  1. ensure the TODO tracker and changelog is up to date.
  2. Check for wip file and it is clear. If it doesent exitst or is not clear prompt user to decide what to do next.
  3. Clear wip file once branch is commited and merged.

## Git Workflow

1. **Before starting a new phase:**
   - Confirm previous phase was merged to `main`
   - Confirm current branch is `main`
   - Pull latest: `git pull origin main`

2. **Create a new branch for each phase:**
   - Naming: `phase/{phase-name}` or `feature/{feature-name}`
   - Example: `phase/testing-infrastructure`, `feature/dusk-setup`

3. **On phase completion:**
   - Run all tests: `php artisan test`
   - Run code formatter: `vendor/bin/pint`
   - Merge branch to `main`
   - **Get user confirmation before pushing to origin**

4. **Maintain CHANGELOG.md:**
   - Update changelog with each meaningful change
   - Follow Keep a Changelog format

---

## Code Style & Quality

- **Run `vendor/bin/pint`** before every commit
- **No `dd()` in committed code** — use conditional `dump()` or logging
- **Use `config()` not `env()`** in application code (env() only in config files)
- **Strict types** encouraged in new PHP files

---

## Domain Rules Documents

Business rules for data queries and attribution logic are maintained in `docs/specs/`. These are the source of truth for query construction.

| Document | Purpose |
|----------|---------|
| [`assessment-completion-rules.md`](docs/specs/assessment-completion-rules.md) | Daily footage calculation by station completion |
| [`planner-activity-rules.md`](docs/specs/planner-activity-rules.md) | First Unit Wins attribution, unit classification, chunking |

---

## Architecture

- **Services must implement interfaces** — enables testing and flexibility
- **Single source of truth for config** — no duplicate configuration across files
- **Inject dependencies via constructor** — use Laravel's container
- **Keep folder structure minimal** — prefer simplicity over deep nesting
- **No business logic in controllers** — delegate to services

---

## Security

- **Never commit credentials or secrets** — use `.env` and credential managers
- **Validate all user input** — use Form Requests
- **Sanitize output** — prevent XSS via Blade escaping

---

## Testing

- **New features require tests** — no exceptions
- **Run tests before merging** — `php artisan test` must pass
- **Use factories for test data** — keep tests isolated and repeatable
- **Pest 4** is the testing framework — follow Pest conventions

---

## Database

- **Use migrations for all schema changes** — never modify DB directly
- **Use Eloquent** — avoid raw queries unless absolutely necessary
- **Factories required for new models**

---

## Quick Reference

```bash
# Before starting work
git checkout main && git pull origin main
git checkout -b phase/your-phase-name

# Before committing
vendor/bin/pint
php artisan test

# Before merging
php artisan test --compact
git checkout main && git merge phase/your-phase-name

# Before pushing (get confirmation)
git push origin main
```

# Session Handoff — Phase 5 Ready

**Start message:** `Read docs/session-handoffs/2026-02-13-phase5-ready.md and start Phase 5`

## Branch & Status

- **Branch:** `feature/data-collection-architecture` (all work uncommitted)
- **Full tech spec:** `docs/specs/tech-spec-data-collection-architecture.md` (only read if you need something not covered below)
- **WIP tracker:** `docs/wip.md`
- **Test suite:** 467 passed, 0 failures, 3 pre-existing skips

### Completed Phases

| Phase | What | Tests |
|-------|------|-------|
| 1 | 4 migrations + config | Verified via migrate |
| 2 | 4 models + 4 factories | 45 tests, 126 assertions |
| 3 | 3 query builder classes | 59 tests, 139 assertions |
| 4 | 3 services + event/listener + provider | 24 tests, 100 assertions |

## Phase 5: What to Build

### Directory Structure

```
app/Console/Commands/
├── ImportCareerLedger.php     # NEW — Phase 5
├── ExportCareerLedger.php     # NEW — Phase 5
├── RunLiveMonitor.php         # NEW — Phase 5
├── FetchDailyFootage.php      # EXISTS — reference for patterns
└── FetchUnitTypes.php         # EXISTS — reference for patterns

routes/console.php             # MODIFY — add scheduler entry
```

### Task 5.1: `ws:import-career-ledger`

**File:** `app/Console/Commands/ImportCareerLedger.php`

**Signature:**
```
ws:import-career-ledger
    {--path= : Path to JSON file (default: config)}
    {--dry-run : Show what would be imported without writing}
```

**Behavior:**
- Reads from `--path` or `config('ws_data_collection.career_ledger.bootstrap_path')` (default: `storage_path('app/career-ledger-bootstrap.json')`)
- `--dry-run`: reads file, reports count + sample entries, does NOT write to DB
- Normal run: calls `CareerLedgerService::importFromJson($path)`
- Shows progress (file size/entry count)
- Reports stats: `Imported: X, Skipped: Y, Errors: Z`
- Returns `self::SUCCESS` or `self::FAILURE`

**Inject dependency:**
```php
public function handle(CareerLedgerService $service): int
```

### Task 5.2: `ws:export-career-ledger`

**File:** `app/Console/Commands/ExportCareerLedger.php`

**Signature:**
```
ws:export-career-ledger
    {--path= : Output path (default: config)}
    {--scope-year= : Filter by scope year}
    {--region= : Filter by region}
```

**Behavior:**
- Outputs to `--path` or config default path
- Calls `CareerLedgerService::exportToJson($path)`
- Shows progress bar during API calls
- Reports: `Exported X career entries to {path}`
- Note: `--scope-year` and `--region` are future filter hooks — for now, pass them through or log a "not yet implemented" info message if provided
- Returns `self::SUCCESS` or `self::FAILURE`

### Task 5.3: `ws:run-live-monitor`

**File:** `app/Console/Commands/RunLiveMonitor.php`

**Signature:**
```
ws:run-live-monitor
    {--job-guid= : Snapshot a single assessment}
    {--include-ghost : Also run ghost detection checks}
```

**Behavior:**
- Default: calls `LiveMonitorService::runDailySnapshot()`
- `--job-guid`: snapshot only that assessment (call `snapshotAssessment()` directly with mock assessment data from the API)
- `--include-ghost`: after snapshots, run `GhostDetectionService::checkForOwnershipChanges()` + `runComparison()` on all active periods
- Reports: `Snapshots: X, New monitors: Y, Closed: Z`
- If `--include-ghost`: also reports `Ghost checks: X ownership changes, Y new ghost units`
- Returns `self::SUCCESS`

**Inject dependencies:**
```php
public function handle(LiveMonitorService $monitor, GhostDetectionService $ghost): int
```

### Task 5.4: Register Scheduler

**File:** `routes/console.php`

Add:
```php
Schedule::command('ws:run-live-monitor --include-ghost')->daily();
```

### Task 5.5: Write Command Tests

Feature tests for all 3 commands using `$this->artisan()`.

## Service Method Signatures (for commands to call)

```php
// CareerLedgerService
public function importFromJson(string $path): array  // ['imported' => int, 'skipped' => int, 'errors' => int]
public function exportToJson(string $path): int       // count of entries exported

// LiveMonitorService
public function runDailySnapshot(): array             // ['snapshots' => int, 'new' => int, 'closed' => int]
public function snapshotAssessment(string $jobGuid, array $assessmentData): void

// GhostDetectionService
public function checkForOwnershipChanges(): int       // count of new ownership periods
public function runComparison(GhostOwnershipPeriod $period): int  // count of new ghosts
```

## Existing Command Patterns to Follow

Studied from `FetchDailyFootage.php` and `FetchUnitTypes.php`:

- Commands inject services via `handle()` parameter (Laravel auto-resolves)
- `--dry-run` shows a table preview + warning message, returns `self::SUCCESS`
- Progress bars: `$this->output->createProgressBar($total)` with `->advance()` and `->finish()`
- Status output: `$this->info()` for success, `$this->warn()` for empty results, `$this->error()` for failures
- Return `self::SUCCESS` or `self::FAILURE`
- Boolean options: checked with `$this->option('dry-run')` (returns truthy when flag present)

## Config Values Commands Need

```php
config('ws_data_collection.career_ledger.bootstrap_path')  // storage_path('app/career-ledger-bootstrap.json')
config('ws_data_collection.live_monitor.enabled')           // true
config('ws_data_collection.ghost_detection.enabled')        // true
```

## Test Patterns for Commands

```php
// Test artisan commands using artisan helper
test('import command imports entries', function () {
    // Create temp JSON file, run command, assert DB entries
    $this->artisan('ws:import-career-ledger', ['--path' => $tempPath])
        ->expectsOutput('Imported: 2, Skipped: 0, Errors: 0')
        ->assertSuccessful();
});

// Mock services for commands that hit the API
test('export command calls service', function () {
    $mock = Mockery::mock(CareerLedgerService::class);
    $mock->shouldReceive('exportToJson')->once()->andReturn(5);
    $this->app->instance(CareerLedgerService::class, $mock);

    $this->artisan('ws:export-career-ledger')
        ->assertSuccessful();
});

// For live monitor, mock both services
test('live monitor with ghost detection', function () {
    $mockMonitor = Mockery::mock(LiveMonitorService::class);
    $mockMonitor->shouldReceive('runDailySnapshot')->once()
        ->andReturn(['snapshots' => 3, 'new' => 1, 'closed' => 0]);

    $mockGhost = Mockery::mock(GhostDetectionService::class);
    $mockGhost->shouldReceive('checkForOwnershipChanges')->once()->andReturn(2);

    $this->app->instance(LiveMonitorService::class, $mockMonitor);
    $this->app->instance(GhostDetectionService::class, $mockGhost);

    $this->artisan('ws:run-live-monitor', ['--include-ghost' => true])
        ->assertSuccessful();
});
```

## Gotchas

- **Services are singletons** — when mocking in tests, use `$this->app->instance()` to replace the singleton before calling `artisan()`
- **Artisan boolean options** return `false` (not `null`) when absent — check with `if ($this->option('include-ghost'))`
- **CareerLedgerService constructor** builds its own query builder internally (takes only `GetQueryService`) — mocking requires replacing the service instance, not injecting query builders
- **LiveMonitorService and GhostDetectionService** same pattern — only `GetQueryService` in constructor
- **DDOProtocol API calls** happen inside the services, not the commands — commands are thin wrappers
- **`exportToJson` makes many API calls** — the command should warn that this may take a while for large datasets

## Remaining Phases After 5

- Phase 6: Integration Testing
- Phase 7: Documentation & Cleanup (CHANGELOG, pint, project-context.md update)

## Files NOT to Read (already done, don't waste tokens)

- `docs/TODO.md` — large file, not relevant
- `docs/project-context.md` — MEMORY.md covers what you need
- Phase 1-4 test files — they pass, no action needed
- `docs/archive/` — historical files
- Model/Factory files — already loaded into MEMORY.md context
- Query builder files — only referenced by services, not by commands
- `docs/specs/tech-spec-data-collection-architecture.md` — everything needed is above
- Ask user before reading any other files 

# Query Optimization Workflow Plan

## Executive Summary

This document provides a systematic workflow for optimizing all WorkStudio queries in the WS-TrackerV1 project using the WS module's Query Specialist agent and query-builder workflow. The plan identifies 7 distinct queries across 2 main query-building files, prioritizes them by complexity and impact, and defines a repeatable optimization process.

---

## 1. WS Module Query Optimization Capabilities

### Query Specialist Agent (`_bmad/ws/agents/query-specialist.md`)

The Query Specialist provides the following optimization-relevant capabilities:

| Command | Purpose |
|---------|---------|
| `[WQ]` | Write a SQL query from natural language description |
| `[OQ]` | Optimize an existing SQL query |
| `[EQ]` | Explain what a SQL query does |
| `[JH]` | Help construct JOINs between specific tables |
| `[PT]` | Common queries for priority tables |

**Key Principles from the Agent:**
- Write clean, readable SQL with proper formatting
- Always explain the JOIN chain and why each table is included
- Consider query performance and suggest indexes when relevant
- Provide both simple and optimized versions when appropriate
- Use table aliases consistently for readability
- Warn about potential N+1 issues or expensive operations

### Query Builder Workflow (`_bmad/ws/workflows/query-builder/workflow.md`)

The workflow process:
1. Analyze the request to identify required tables
2. Determine necessary JOINs and relationships
3. Construct the SELECT clause
4. Add appropriate WHERE conditions
5. Optimize the query structure
6. Explain the query logic

**Output includes:**
- Complete SQL query
- Explanation of each JOIN and why it's needed
- Performance considerations
- Alternative approaches if applicable

### Available Knowledge Resources

The WS module has documented:
- **Relationships:** `_bmad/ws/data/tables/relationships.md`
- **Core Jobs:** `_bmad/ws/data/tables/core-jobs.md`
- **Vegetation:** `_bmad/ws/data/tables/vegetation.md`
- **GPS/Geospatial:** `_bmad/ws/data/tables/gps-geospatial.md`
- **Full Schema:** `_bmad/ws/data/schema/full-schema.json`

---

## 2. Current Query Inventory

All queries are located in:
- **Primary:** `/app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php`
- **Helpers:** `/app/Services/WorkStudio/AssessmentsDx/Queries/SqlFragmentHelpers.php`
- **Field Builder:** `/app/Services/WorkStudio/AssessmentsDx/Queries/SqlFieldBuilder.php`

### Query Catalog

| ID | Query Method | Location | Tables Used | Complexity | JOINs | Subqueries |
|----|-------------|----------|-------------|------------|-------|------------|
| Q1 | `systemWideDataQuery()` | AssessmentQueries.php:16 | SS, VEGJOB, WPStartDate_Assessment_Xrefs | Medium | 2 JOINs | 1 (TOP 1) |
| Q2 | `groupedByRegionDataQuery()` | AssessmentQueries.php:81 | SS, VEGJOB, WPStartDate_Assessment_Xrefs, VEGUNIT, JOBVEGETATIONUNITS | High | 2 JOINs + 2 CROSS APPLY | 0 |
| Q3 | `groupedByCircuitDataQuery()` | AssessmentQueries.php:200 | SS, VEGJOB, WPStartDate_Assessment_Xrefs, VEGUNIT, JOBVEGETATIONUNITS | Very High | 2 JOINs + 2 CROSS APPLY + STRING_AGG subquery | 1 |
| Q4 | `getAllAssessmentsDailyActivities()` | AssessmentQueries.php:329 | SS, VEGJOB, WPStartDate_Assessment_Xrefs, STATIONS, VEGUNIT | Very High | 3 JOINs + nested JSON PATH | Multiple nested |
| Q5 | `getAllByJobGuid()` | AssessmentQueries.php:393 | SS, VEGJOB, WPStartDate_Assessment_Xrefs, VEGUNIT, STATIONS | Very High | 2 JOINs + many subqueries | 8+ subqueries |
| Q6 | `getAllJobGUIDsForEntireScopeYear()` | AssessmentQueries.php:466 | SS, VEGJOB, WPStartDate_Assessment_Xrefs | Medium | 2 JOINs | 0 |
| Q7 | `dailyRecordsQuery()` (fragment) | SqlFragmentHelpers.php:150 | STATIONS, VEGUNIT | Very High | Complex nested with ROW_NUMBER | 4 nested |

### Helper Fragments Analysis

The `SqlFragmentHelpers.php` trait provides reusable SQL fragments:
- `parseMsDateToDate()` - Date conversion
- `extractYearFromMsDate()` - Year extraction
- `validUnitFilter()` / `validUnitFilterNotIn()` - Unit filtering
- `foresterSubquery()` - First forester lookup
- `totalFootageSubquery()` - Station footage sum
- `formatToEasternTime()` - Timezone conversion
- `unitCountSubquery()` - Permission status counts
- `unitCountsCrossApply()` - Efficient batch unit counts
- `dailyRecordsQuery()` - Complex daily records aggregation
- `stationsWithUnitsQuery()` - Nested JSON structure

---

## 3. Prioritized Query Optimization List

### Priority Matrix

| Priority | Query | Rationale | Estimated Impact |
|----------|-------|-----------|------------------|
| **P1** | Q4: `getAllAssessmentsDailyActivities()` | Most complex, uses FOR JSON PATH, nested subqueries, high execution time expected | High |
| **P2** | Q5: `getAllByJobGuid()` | 8+ subqueries, complete circuit data, called per-job | High |
| **P3** | Q3: `groupedByCircuitDataQuery()` | Large result set, 2 CROSS APPLYs, STRING_AGG | High |
| **P4** | Q2: `groupedByRegionDataQuery()` | 2 CROSS APPLYs, aggregation-heavy | Medium-High |
| **P5** | Q7: `dailyRecordsQuery()` | Complex fragment used by Q4/Q5, ROW_NUMBER partitioning | Medium |
| **P6** | Q1: `systemWideDataQuery()` | Simpler aggregation, good baseline | Medium |
| **P7** | Q6: `getAllJobGUIDsForEntireScopeYear()` | Simple list query, likely performant | Low |

---

## 4. Repeatable Optimization Workflow

### Phase A: Pre-Optimization Analysis

**Step A1: Baseline Performance Capture**
```
1. Use ExecutionTimer class to measure current query execution time
2. Record result set size (row count)
3. Note API response time (transfer time from Guzzle stats)
4. Document current SQL statement
```

**Step A2: Invoke Query Specialist for Analysis**
```
/ws:query-specialist
[EQ] Explain what this SQL query does
- Paste the current query
- Request explanation of JOIN logic
- Ask for identified performance concerns
```

**Step A3: Document Current State**
```
- Query purpose and business requirements
- Tables involved and relationships
- Known issues or complaints from users
- Current execution time baseline
```

### Phase B: Optimization Design

**Step B1: Use Query Specialist Optimization**
```
/ws:query-specialist
[OQ] Optimize an existing SQL query
- Provide current query
- Specify performance goals (faster execution, smaller payload, etc.)
- Request multiple optimization approaches
```

**Step B2: Review Recommended Changes**
Query Specialist will typically suggest:
- Index recommendations
- JOIN order optimization
- Subquery to CTE conversion
- CROSS APPLY vs subquery trade-offs
- Column selection reduction
- Conditional aggregation improvements
- Pagination strategies for large result sets

**Step B3: Use Join Helper if Needed**
```
/ws:query-specialist
[JH] Help construct JOINs between specific tables
- Verify optimal JOIN strategy
- Check for missing relationships
- Validate composite key usage (e.g., JOBGUID + STATNAME)
```

### Phase C: Implementation

**Step C1: Create Optimized Version**
1. Implement suggested optimizations in a new method (e.g., `systemWideDataQueryV2()`)
2. Keep original method intact for A/B comparison
3. Use `SqlFragmentHelpers` trait patterns for consistency
4. Follow existing code conventions from `SqlFieldBuilder`

**Step C2: Code Review Checklist**
- [ ] SQL formatting follows project conventions
- [ ] Table aliases are consistent
- [ ] WHERE clauses use config values via `WSHelpers::toSqlInClause()`
- [ ] Date handling uses helper methods
- [ ] No N+1 query patterns introduced
- [ ] Comments explain optimization rationale

### Phase D: Validation and Testing

**Step D1: Functional Validation**
```php
// Compare result sets
$original = AssessmentQueries::systemWideDataQuery();
$optimized = AssessmentQueries::systemWideDataQueryV2();

$originalResults = $queryService->executeAndHandle($original);
$optimizedResults = $queryService->executeAndHandle($optimized);

// Assert same data
$this->assertEquals($originalResults->count(), $optimizedResults->count());
$this->assertEquals($originalResults->toArray(), $optimizedResults->toArray());
```

**Step D2: Performance Benchmarking**
```php
$timer = new ExecutionTimer();

// Benchmark original
$timer->start('original');
for ($i = 0; $i < 5; $i++) {
    $queryService->executeAndHandle($original);
}
$originalTime = $timer->stop('original') / 5;

// Benchmark optimized
$timer->start('optimized');
for ($i = 0; $i < 5; $i++) {
    $queryService->executeAndHandle($optimized);
}
$optimizedTime = $timer->stop('optimized') / 5;

$improvement = (($originalTime - $optimizedTime) / $originalTime) * 100;
logger()->info("Performance improvement: {$improvement}%");
```

**Step D3: Write Test Coverage**
```php
test('optimized query returns same results as original', function () {
    // Test implementation
});

test('optimized query executes faster than original', function () {
    // Performance assertion
});
```

### Phase E: Deployment

**Step E1: Gradual Rollout**
1. Deploy optimized version alongside original
2. Use feature flag or configuration to switch between versions
3. Monitor production performance metrics
4. Log any discrepancies in results

**Step E2: Documentation**
1. Update inline comments explaining optimization
2. Record benchmark results in optimization log
3. Update any relevant API documentation

**Step E3: Cleanup**
1. Remove original version after validation period
2. Update method name to standard (remove V2 suffix)
3. Update all callers

---

## 5. Performance Benchmarking Approach

### Existing Infrastructure

The project already has:
- `ExecutionTimer` class at `/app/Services/WorkStudio/Helpers/ExecutionTimer.php`
- HTTP transfer time logging in `GetQueryService.php` (Guzzle `on_stats` callback)
- Logger integration for timing data

### Recommended Benchmark Strategy

**Per-Query Metrics:**
| Metric | Source | Target |
|--------|--------|--------|
| Query build time | ExecutionTimer | < 10ms |
| API response time | Guzzle TransferStats | Varies by query |
| Data transformation time | ExecutionTimer | < 50ms per 1000 rows |
| Total end-to-end time | ExecutionTimer total | 20% improvement |
| Result set size | Collection count | Document baseline |
| Memory usage | memory_get_peak_usage() | No regression |

**Benchmark Test Template:**
```php
describe('Query Performance Benchmarks', function () {
    test('systemWideDataQuery executes within acceptable time', function () {
        $timer = new ExecutionTimer();
        $timer->start('query');

        $service = app(GetQueryService::class);
        $result = $service->getSystemWideMetrics();

        $executionTime = $timer->stop('query');

        expect($executionTime)->toBeLessThan(5.0); // 5 second threshold
        expect($result)->not->toBeEmpty();
    });
});
```

### Performance Tracking Log

Create a performance tracking document with:
```markdown
## Query: systemWideDataQuery

### Baseline (Date: YYYY-MM-DD)
- Execution time: X.XXs
- Rows returned: XXX
- Tables involved: SS, VEGJOB, WPStartDate_Assessment_Xrefs

### Optimization 1 (Date: YYYY-MM-DD)
- Change: [Description]
- New execution time: X.XXs
- Improvement: XX%

### Final Optimized Version
- Execution time: X.XXs
- Total improvement: XX%
```

---

## 6. Query-Specific Optimization Notes

### Q1: systemWideDataQuery()
**Current Issues:**
- TOP 1 subquery for CONTRACTOR is inefficient
- Could use window function instead

**Optimization Targets:**
- Remove unnecessary TOP 1 subquery
- Consider indexed view for frequent access

### Q2/Q3: Regional and Circuit Data Queries
**Current Issues:**
- CROSS APPLY executed for every row
- Repeated VEGUNIT scans

**Optimization Targets:**
- Pre-aggregate VEGUNIT counts in CTE
- Use conditional aggregation instead of multiple CASE statements
- Consider materialized view for WorkData

### Q4: getAllAssessmentsDailyActivities()
**Current Issues:**
- FOR JSON PATH performance overhead
- Complex nested subqueries

**Optimization Targets:**
- Split into multiple simpler queries
- Use pagination for large result sets
- Consider caching intermediate results

### Q5: getAllByJobGuid()
**Current Issues:**
- 8+ separate subqueries per job
- Repeated table scans

**Optimization Targets:**
- Convert subqueries to single CROSS APPLY
- Use CTE for shared calculations
- Batch unit counts

### Q7: dailyRecordsQuery() Fragment
**Current Issues:**
- Triple-nested derived tables
- ROW_NUMBER with complex partitioning
- Multiple VEGUNIT scans

**Optimization Targets:**
- Simplify nesting with CTEs
- Consider temp table for first assessment dates
- Index on (JOBGUID, ASSDDATE)

---

## 7. Summary Workflow Checklist

For each query optimization iteration:

- [ ] **Capture baseline** - Execute query, record timing, document results
- [ ] **Analyze with Query Specialist** - Use `[EQ]` to understand current query
- [ ] **Request optimization** - Use `[OQ]` for improvement suggestions
- [ ] **Validate JOINs** - Use `[JH]` to verify relationship patterns
- [ ] **Implement changes** - Create V2 method, follow existing patterns
- [ ] **Write tests** - Functional correctness and performance assertions
- [ ] **Benchmark** - Compare original vs optimized execution times
- [ ] **Document** - Record optimization details and results
- [ ] **Deploy** - Gradual rollout with monitoring
- [ ] **Cleanup** - Remove original after validation

---

## Critical Files for Implementation

- `/app/Services/WorkStudio/AssessmentsDx/Queries/AssessmentQueries.php` - Primary file containing all 6 main queries to optimize
- `/app/Services/WorkStudio/AssessmentsDx/Queries/SqlFragmentHelpers.php` - Contains reusable SQL fragments including the complex dailyRecordsQuery
- `/_bmad/ws/agents/query-specialist.md` - Query Specialist agent definition with optimization commands
- `/app/Services/WorkStudio/Helpers/ExecutionTimer.php` - Existing performance benchmarking utility
- `/_bmad/ws/data/tables/relationships.md` - Table relationship documentation for JOIN optimization

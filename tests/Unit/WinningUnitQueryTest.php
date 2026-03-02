<?php

use App\Services\WorkStudio\Assessments\Queries\WinningUnitQuery;

// ─── Structure Tests ────────────────────────────────────────────────────────

test('build returns valid SQL with required clauses', function () {
    $sql = WinningUnitQuery::build(['guid-1', 'guid-2']);

    expect($sql)
        ->toContain('SELECT')
        ->toContain('FROM')
        ->toContain('WHERE')
        ->toContain('GROUP BY')
        ->toContain('ORDER BY');
});

test('build interpolates job GUIDs into IN clause', function () {
    $sql = WinningUnitQuery::build(['aaa-111', 'bbb-222', 'ccc-333']);

    expect($sql)->toContain("IN ('aaa-111','bbb-222','ccc-333')");
});

test('build handles single GUID without extra commas', function () {
    $sql = WinningUnitQuery::build(['only-guid']);

    expect($sql)->toContain("IN ('only-guid')");
});

// ─── Output Columns ─────────────────────────────────────────────────────────

test('build selects all expected output columns', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)
        ->toContain('w.JOBGUID')
        ->toContain('w.FRSTR_USER')
        ->toContain('w.WO')
        ->toContain('w.EXT')
        ->toContain('AS ASSESS_DATE')
        ->toContain('w.STATNAME')
        ->toContain('w.SEQUENCE')
        ->toContain('w.UNITGUID')
        ->toContain('w.UNIT')
        ->toContain('AS LAT')
        ->toContain('AS [LONG]')
        ->toContain('AS COORD_SOURCE')
        ->toContain('w.SPANLGTH')
        ->toContain('AS SPAN_MILES');
});

test('LAT uses COALESCE preferring station YCOORD over unit ASSLAT', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)->toContain('COALESCE(w.YCOORD, w.ASSLAT) AS LAT');
});

test('LONG uses COALESCE preferring station XCOORD over unit ASSLONG', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)->toContain('COALESCE(w.XCOORD, w.ASSLONG) AS [LONG]');
});

test('COORD_SOURCE indicates station when YCOORD is present, otherwise unit', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)->toContain("CASE WHEN w.YCOORD IS NOT NULL THEN 'station' ELSE 'unit' END AS COORD_SOURCE");
});

test('SPAN_MILES converts meters to miles via feet', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)->toContain('(w.SPANLGTH * 3.28084) / 5280.0 AS SPAN_MILES');
});

// ─── Winning Unit Logic ─────────────────────────────────────────────────────

test('ROW_NUMBER partitions by JOBGUID and STATNAME ordering by ASSDDATE ASC', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)
        ->toContain('ROW_NUMBER() OVER')
        ->toContain('PARTITION BY ranked.JOBGUID, ranked.STATNAME')
        ->toContain('ORDER BY ranked.ASSDDATE ASC')
        ->toContain('WHERE w.RN = 1');
});

test('inner query filters out null ASSDDATE', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)->toContain('vu.ASSDDATE IS NOT NULL');
});

// ─── Split Exclusion Logic ──────────────────────────────────────────────────

test('split exclusion drops @ parent when split children exist', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)
        ->toContain("ss.EXT = '@'")
        ->toContain('AND EXISTS')
        ->toContain("s2.EXT <> '@'")
        ->toContain('s2.WO = ss.WO');
});

// ─── DDOProtocol Compliance ─────────────────────────────────────────────────

test('query uses no CTEs (DDOProtocol restriction)', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)
        ->not->toContain('WITH ')
        ->not->toContain(' AS (');
});

test('query uses derived tables instead of CTEs', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    // Two levels of derived tables: ) ranked and ) w
    expect($sql)
        ->toContain(') ranked')
        ->toContain(') w');
});

// ─── Joins ──────────────────────────────────────────────────────────────────

test('inner query joins VEGUNIT to STATIONS on JOBGUID and STATNAME', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)->toContain('INNER JOIN STATIONS st')
        ->toContain('vu.JOBGUID = st.JOBGUID AND vu.STATNAME = st.STATNAME');
});

test('inner query joins VEGUNIT to SS on JOBGUID', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)->toContain('INNER JOIN SS ss')
        ->toContain('vu.JOBGUID = ss.JOBGUID');
});

// ─── Grouping & Ordering ────────────────────────────────────────────────────

test('GROUP BY includes all non-computed output columns for deduplication', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)
        ->toContain('GROUP BY w.JOBGUID, w.FRSTR_USER, w.WO, w.EXT, w.ASSDDATE, w.STATNAME,')
        ->toContain('w.SEQUENCE, w.UNITGUID, w.UNIT, w.YCOORD, w.ASSLAT,')
        ->toContain('w.XCOORD, w.ASSLONG, w.SPANLGTH');
});

test('ORDER BY sorts by User then WO then Date', function () {
    $sql = WinningUnitQuery::build(['guid-1']);

    expect($sql)->toContain('ORDER BY w.FRSTR_USER, w.WO, CAST(w.ASSDDATE AS DATE)');
});

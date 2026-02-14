<?php

use App\Services\WorkStudio\Planners\Queries\PlannerCareerLedger;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;

uses(Tests\TestCase::class);

function makePlannerContext(array $overrides = []): UserQueryContext
{
    return new UserQueryContext(
        resourceGroups: $overrides['resourceGroups'] ?? ['CENTRAL', 'HARRISBURG'],
        contractors: $overrides['contractors'] ?? ['Asplundh'],
        domain: $overrides['domain'] ?? 'ASPLUNDH',
        username: $overrides['username'] ?? 'jsmith',
        userId: $overrides['userId'] ?? 1,
    );
}

const PLANNER_TEST_GUID = '{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}';
const PLANNER_TEST_GUID_2 = '{B2C3D4E5-F6A7-8901-BCDE-F12345678901}';

// ─── getDistinctJobGuids ─────────────────────────────────────────────────────

test('getDistinctJobGuids queries VEGUNIT joined with SS for closed parent assessments', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids('jsmith');

    expect($sql)
        ->toContain('SELECT DISTINCT VU.JOBGUID')
        ->toContain('FROM VEGUNIT VU')
        ->toContain('INNER JOIN SS ON SS.JOBGUID = VU.JOBGUID')
        ->toContain("SS.STATUS = 'CLOSE'")
        ->toContain("SS.EXT = '@'")
        ->toContain('VU.ASSDDATE IS NOT NULL');
});

test('getDistinctJobGuids defaults to scope year filter via xref join', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids('jsmith');

    expect($sql)
        ->toContain('WPStartDate_Assessment_Xrefs')
        ->toContain('WP_STARTDATE LIKE');
});

test('getDistinctJobGuids with allYears skips xref join and year filter', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids('jsmith', allYears: true);

    expect($sql)
        ->not->toContain('WPStartDate_Assessment_Xrefs')
        ->not->toContain('WP_STARTDATE');
});

test('getDistinctJobGuids current mode skips year filter regardless of allYears', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids('jsmith', current: true, allYears: false);

    expect($sql)
        ->not->toContain('WPStartDate_Assessment_Xrefs')
        ->not->toContain('WP_STARTDATE');
});

test('getDistinctJobGuids accepts single user string', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids('jsmith');

    expect($sql)->toContain("FRSTR_USER IN ('jsmith')");
});

test('getDistinctJobGuids accepts array of users', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids(['jsmith', 'jdoe']);

    expect($sql)
        ->toContain('FRSTR_USER IN (')
        ->toContain("'jsmith'")
        ->toContain("'jdoe'");
});

test('getDistinctJobGuids uses no CTEs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids('jsmith');

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
});

// ─── getDistinctJobGuids — current mode ─────────────────────────────────────

test('getDistinctJobGuids with current flag queries active statuses', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids('jsmith', current: true);

    expect($sql)
        ->toContain("SS.STATUS IN ('ACTIV', 'QC', 'REWRK')")
        ->not->toContain("SS.STATUS = 'CLOSE'");
});

test('getDistinctJobGuids without current flag queries closed status', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids('jsmith', current: false);

    expect($sql)
        ->toContain("SS.STATUS = 'CLOSE'")
        ->not->toContain("'ACTIV'");
});

test('getDistinctJobGuids current mode still requires parent assessments and ASSDDATE', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDistinctJobGuids('jsmith', current: true);

    expect($sql)
        ->toContain("SS.EXT = '@'")
        ->toContain('VU.ASSDDATE IS NOT NULL');
});

// ─── getFullCareerData — Metadata (flat columns) ────────────────────────────

test('getFullCareerData includes metadata columns from SS and VEGJOB', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('SS.JOBGUID')
        ->toContain('SS.STATUS')
        ->toContain('VEGJOB.LINENAME AS line_name')
        ->toContain('VEGJOB.REGION AS region')
        ->toContain('VEGJOB.CYCLETYPE AS cycle_type')
        ->toContain('VEGJOB.FRSTR_USER AS assigned_planner')
        ->toContain('VEGJOB.LENGTH AS total_miles')
        ->toContain('VEGJOB.LENGTHCOMP AS total_miles_planned')
        ->toContain('INNER JOIN VEGJOB ON VEGJOB.JOBGUID = SS.JOBGUID');
});

test('getFullCareerData derives scope_year from WPStartDate_Assessment_Xrefs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('WPStartDate_Assessment_Xrefs XR')
        ->toContain('XR.Assess_JOBGUID = SS.JOBGUID')
        ->toContain('AS scope_year');
});

test('getFullCareerData uses IN clause for multiple GUIDs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID, PLANNER_TEST_GUID_2]);

    expect($sql)
        ->toContain('IN (')
        ->toContain(PLANNER_TEST_GUID)
        ->toContain(PLANNER_TEST_GUID_2);
});

// ─── getFullCareerData — Timeline (JSON column) ────────────────────────────

test('getFullCareerData embeds timeline as FOR JSON PATH subquery', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('JOBHISTORY JH')
        ->toContain('JH.LOGDATE')
        ->toContain('JH.JOBSTATUS')
        ->toContain('FOR JSON PATH')
        ->toContain('AS timeline');
});

test('getFullCareerData timeline is correlated to SS.JOBGUID', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)->toContain('JH.JOBGUID = SS.JOBGUID');
});

// ─── getFullCareerData — Work Type Breakdown (JSON column) ──────────────────

test('getFullCareerData embeds work type breakdown as FOR JSON PATH', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('V_ASSESSMENT VA')
        ->toContain('VA.unit')
        ->toContain('VA.UnitQty')
        ->toContain('AS work_type_breakdown');
});

// ─── getFullCareerData — Rework Details (JSON column) ───────────────────────

test('getFullCareerData embeds rework details as FOR JSON PATH', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('AUDIT_FAIL')
        ->toContain('AUDIT_USER')
        ->toContain('AUDITDATE')
        ->toContain('AS rework_details');
});

test('getFullCareerData rework excludes NW and empty units', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)->toContain("VUR.UNIT != 'NW'");
});

// ─── getFullCareerData — Daily Metrics (OUTER APPLY + JSON) ────────────────

test('getFullCareerData includes daily metrics via OUTER APPLY', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('OUTER APPLY')
        ->toContain('DailyData.daily_metrics')
        ->toContain('AS DailyData');
});

test('getFullCareerData daily metrics uses ASSDDATE exclusively — no DATEPOP', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('ASSDDATE')
        ->not->toContain('DATEPOP')
        ->not->toContain('COALESCE');
});

test('getFullCareerData daily metrics uses First Unit Wins pattern', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('ROW_NUMBER()')
        ->toContain('PARTITION BY VU.STATNAME')
        ->toContain('unit_rank = 1');
});

test('getFullCareerData daily metrics joins STATIONS for footage', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('JOIN STATIONS ST')
        ->toContain('SPANLGTH')
        ->toContain('daily_footage_miles');
});

test('getFullCareerData daily metrics joins UNITS for work classification', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('JOIN UNITS U')
        ->toContain('SUMMARYGRP')
        ->toContain('Summary-NonWork')
        ->toContain('unit_count');
});

test('getFullCareerData applies date filter when provided', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID], '2026-01-01', '2026-03-31');

    expect($sql)->toContain("BETWEEN '2026-01-01' AND '2026-03-31'");
});

test('getFullCareerData omits date filter when not provided', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)->not->toContain('BETWEEN');
});

// ─── DDOProtocol Compatibility ──────────────────────────────────────────────

test('getFullCareerData uses no CTEs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
});

test('getFullCareerData produces exactly one SELECT statement', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getFullCareerData([PLANNER_TEST_GUID]);

    // The top-level SELECT should be the only non-subquery SELECT at position 0
    expect($sql)->toStartWith('SELECT');
});

// ─── GUID Validation ────────────────────────────────────────────────────────

test('getFullCareerData rejects invalid GUIDs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());

    expect(fn () => $queries->getFullCareerData(['DROP TABLE; --']))
        ->toThrow(InvalidArgumentException::class);
});

test('getFullCareerData rejects any invalid GUID in batch', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());

    expect(fn () => $queries->getFullCareerData([PLANNER_TEST_GUID, 'invalid']))
        ->toThrow(InvalidArgumentException::class);
});

// ─── getEditDates ────────────────────────────────────────────────────────────

test('getEditDates queries SS joined with VEGJOB for EDITDATE', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getEditDates([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('SS.JOBGUID')
        ->toContain('INNER JOIN VEGJOB ON VEGJOB.JOBGUID = SS.JOBGUID')
        ->toContain('VEGJOB.EDITDATE');
});

test('getEditDates converts EDITDATE to ISO 8601 format', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getEditDates([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('CONVERT(VARCHAR(23)')
        ->toContain('CAST(VEGJOB.EDITDATE AS DATETIME)')
        ->toContain('126')
        ->toContain('AS edit_date');
});

test('getEditDates uses IN clause for multiple GUIDs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getEditDates([PLANNER_TEST_GUID, PLANNER_TEST_GUID_2]);

    expect($sql)
        ->toContain('IN (')
        ->toContain(PLANNER_TEST_GUID)
        ->toContain(PLANNER_TEST_GUID_2);
});

test('getEditDates validates GUIDs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());

    expect(fn () => $queries->getEditDates(['DROP TABLE; --']))
        ->toThrow(InvalidArgumentException::class);
});

test('getEditDates uses no CTEs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getEditDates([PLANNER_TEST_GUID]);

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
});

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

// ─── getAssessmentMetadataBatch ─────────────────────────────────────────────

test('getAssessmentMetadataBatch queries SS and VEGJOB for metadata', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getAssessmentMetadataBatch([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('SS.JOBGUID')
        ->toContain('VEGJOB.LINENAME AS line_name')
        ->toContain('VEGJOB.REGION AS region')
        ->toContain('VEGJOB.CYCLETYPE AS cycle_type')
        ->toContain('total_miles')
        ->toContain('INNER JOIN VEGJOB ON VEGJOB.JOBGUID = SS.JOBGUID');
});

test('getAssessmentMetadataBatch includes total miles from VEGJOB', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getAssessmentMetadataBatch([PLANNER_TEST_GUID]);

    expect($sql)->toContain('VEGJOB.LENGTH AS total_miles');
});

test('getAssessmentMetadataBatch uses IN clause for multiple GUIDs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getAssessmentMetadataBatch([PLANNER_TEST_GUID, PLANNER_TEST_GUID_2]);

    expect($sql)
        ->toContain('IN (')
        ->toContain(PLANNER_TEST_GUID)
        ->toContain(PLANNER_TEST_GUID_2);
});

test('getAssessmentMetadataBatch rejects invalid GUIDs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());

    expect(fn () => $queries->getAssessmentMetadataBatch(['DROP TABLE; --']))
        ->toThrow(InvalidArgumentException::class);
});

test('getAssessmentMetadataBatch uses no CTEs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getAssessmentMetadataBatch([PLANNER_TEST_GUID]);

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
});

// ─── getDailyFootageAttribution (ASSDDATE-only) ─────────────────────────────

test('getDailyFootageAttribution uses ASSDDATE exclusively — no DATEPOP', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttribution(PLANNER_TEST_GUID);

    expect($sql)
        ->toContain('ASSDDATE')
        ->not->toContain('DATEPOP')
        ->not->toContain('COALESCE');
});

test('getDailyFootageAttribution uses First Unit Wins SQL structure', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttribution(PLANNER_TEST_GUID);

    expect($sql)
        ->toContain('ROW_NUMBER()')
        ->toContain('PARTITION BY VU.JOBGUID, VU.STATNAME')
        ->toContain('unit_rank = 1');
});

test('getDailyFootageAttribution orders by ASSDDATE for ranking', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttribution(PLANNER_TEST_GUID);

    expect($sql)->toContain('ORDER BY VU.ASSDDATE ASC');
});

test('getDailyFootageAttribution uses JOBGUID in WHERE', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttribution(PLANNER_TEST_GUID);

    expect($sql)->toContain(PLANNER_TEST_GUID);
});

test('getDailyFootageAttribution joins STATIONS for footage', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttribution(PLANNER_TEST_GUID);

    expect($sql)
        ->toContain('JOIN STATIONS ST')
        ->toContain('SPANLGTH')
        ->toContain('daily_footage_miles');
});

test('getDailyFootageAttribution joins UNITS for work classification', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttribution(PLANNER_TEST_GUID);

    expect($sql)
        ->toContain('JOIN UNITS U')
        ->toContain('SUMMARYGRP')
        ->toContain('Summary-NonWork')
        ->toContain('unit_count');
});

test('getDailyFootageAttribution applies date filter when provided', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttribution(PLANNER_TEST_GUID, '2026-01-01', '2026-03-31');

    expect($sql)->toContain("BETWEEN '2026-01-01' AND '2026-03-31'");
});

test('getDailyFootageAttribution omits date filter when not provided', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttribution(PLANNER_TEST_GUID);

    expect($sql)->not->toContain('BETWEEN');
});

test('getDailyFootageAttribution uses no CTEs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttribution(PLANNER_TEST_GUID);

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
});

test('getDailyFootageAttribution rejects invalid GUID', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());

    expect(fn () => $queries->getDailyFootageAttribution('DROP TABLE; --'))
        ->toThrow(InvalidArgumentException::class);
});

// ─── getDailyFootageAttributionBatch ────────────────────────────────────────

test('getDailyFootageAttributionBatch uses IN clause for multiple GUIDs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttributionBatch([PLANNER_TEST_GUID, PLANNER_TEST_GUID_2]);

    expect($sql)
        ->toContain('IN (')
        ->toContain(PLANNER_TEST_GUID)
        ->toContain(PLANNER_TEST_GUID_2);
});

test('getDailyFootageAttributionBatch uses ASSDDATE-only — no DATEPOP', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getDailyFootageAttributionBatch([PLANNER_TEST_GUID]);

    expect($sql)
        ->toContain('ASSDDATE')
        ->not->toContain('DATEPOP')
        ->not->toContain('COALESCE');
});

test('getDailyFootageAttributionBatch rejects any invalid GUID', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());

    expect(fn () => $queries->getDailyFootageAttributionBatch([PLANNER_TEST_GUID, 'invalid']))
        ->toThrow(InvalidArgumentException::class);
});

// ─── getAssessmentTimeline ──────────────────────────────────────────────────

test('getAssessmentTimeline queries JOBHISTORY for lifecycle events', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getAssessmentTimeline(PLANNER_TEST_GUID);

    expect($sql)
        ->toContain('JOBHISTORY')
        ->toContain('LOGDATE')
        ->toContain('JOBSTATUS')
        ->toContain(PLANNER_TEST_GUID)
        ->toContain('ORDER BY JH.LOGDATE ASC');
});

// ─── getWorkTypeBreakdown ───────────────────────────────────────────────────

test('getWorkTypeBreakdown queries V_ASSESSMENT view', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getWorkTypeBreakdown(PLANNER_TEST_GUID);

    expect($sql)
        ->toContain('V_ASSESSMENT')
        ->toContain('unit')
        ->toContain('UnitQty')
        ->toContain(PLANNER_TEST_GUID);
});

// ─── getReworkDetails ───────────────────────────────────────────────────────

test('getReworkDetails queries audit fields from VEGUNIT', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getReworkDetails(PLANNER_TEST_GUID);

    expect($sql)
        ->toContain('AUDIT_FAIL')
        ->toContain('AUDIT_USER')
        ->toContain('AUDITDATE')
        ->toContain(PLANNER_TEST_GUID);
});

test('getReworkDetails uses valid unit filter', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getReworkDetails(PLANNER_TEST_GUID);

    expect($sql)->toContain("VU.UNIT != 'NW'");
});

test('getReworkDetails uses no CTEs', function () {
    $queries = new PlannerCareerLedger(makePlannerContext());
    $sql = $queries->getReworkDetails(PLANNER_TEST_GUID);

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
});

// ─── GUID Validation ────────────────────────────────────────────────────────

test('all single-GUID methods reject invalid GUIDs', function (string $method) {
    $queries = new PlannerCareerLedger(makePlannerContext());

    expect(fn () => $queries->{$method}('not-a-guid'))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'getDailyFootageAttribution',
    'getAssessmentTimeline',
    'getWorkTypeBreakdown',
    'getReworkDetails',
]);

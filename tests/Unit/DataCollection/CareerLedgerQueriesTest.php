<?php

use App\Services\WorkStudio\DataCollection\Queries\CareerLedgerQueries;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;

uses(Tests\TestCase::class);

function makeCareerContext(array $overrides = []): UserQueryContext
{
    return new UserQueryContext(
        resourceGroups: $overrides['resourceGroups'] ?? ['CENTRAL', 'HARRISBURG'],
        contractors: $overrides['contractors'] ?? ['Asplundh'],
        domain: $overrides['domain'] ?? 'ASPLUNDH',
        username: $overrides['username'] ?? 'jsmith',
        userId: $overrides['userId'] ?? 1,
    );
}

const TEST_GUID = '{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}';

// ─── getDailyFootageAttribution ─────────────────────────────────────────────

test('getDailyFootageAttribution contains First Unit Wins SQL structure', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getDailyFootageAttribution(TEST_GUID);

    expect($sql)
        ->toContain('ROW_NUMBER()')
        ->toContain('PARTITION BY VU.JOBGUID, VU.STATNAME')
        ->toContain('unit_rank = 1')
        ->toContain('COALESCE')
        ->toContain('DATEPOP')
        ->toContain('ASSDDATE');
});

test('getDailyFootageAttribution uses JOBGUID in WHERE clause', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getDailyFootageAttribution(TEST_GUID);

    expect($sql)->toContain(TEST_GUID);
});

test('getDailyFootageAttribution joins STATIONS for footage', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getDailyFootageAttribution(TEST_GUID);

    expect($sql)
        ->toContain('JOIN STATIONS ST')
        ->toContain('SPANLGTH')
        ->toContain('daily_footage_meters');
});

test('getDailyFootageAttribution joins UNITS for work classification', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getDailyFootageAttribution(TEST_GUID);

    expect($sql)
        ->toContain('JOIN UNITS U')
        ->toContain('SUMMARYGRP')
        ->toContain('Summary-NonWork')
        ->toContain('unit_count');
});

test('getDailyFootageAttribution applies date filter when provided', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getDailyFootageAttribution(TEST_GUID, '2026-01-01', '2026-03-31');

    expect($sql)->toContain("BETWEEN '2026-01-01' AND '2026-03-31'");
});

test('getDailyFootageAttribution omits date filter when not provided', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getDailyFootageAttribution(TEST_GUID);

    expect($sql)->not->toContain('BETWEEN');
});

test('getDailyFootageAttribution uses no CTEs', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getDailyFootageAttribution(TEST_GUID);

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
});

test('getDailyFootageAttribution rejects invalid GUID', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());

    expect(fn () => $queries->getDailyFootageAttribution('DROP TABLE; --'))
        ->toThrow(InvalidArgumentException::class);
});

// ─── getDailyFootageAttributionBatch ────────────────────────────────────────

test('getDailyFootageAttributionBatch uses IN clause for multiple GUIDs', function () {
    $guid2 = '{B2C3D4E5-F6A7-8901-BCDE-F12345678901}';
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getDailyFootageAttributionBatch([TEST_GUID, $guid2]);

    expect($sql)
        ->toContain('IN (')
        ->toContain(TEST_GUID)
        ->toContain($guid2);
});

test('getDailyFootageAttributionBatch rejects any invalid GUID in array', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());

    expect(fn () => $queries->getDailyFootageAttributionBatch([TEST_GUID, 'invalid']))
        ->toThrow(InvalidArgumentException::class);
});

// ─── getReworkDetails ───────────────────────────────────────────────────────

test('getReworkDetails queries audit fields from VEGUNIT', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getReworkDetails(TEST_GUID);

    expect($sql)
        ->toContain('AUDIT_FAIL')
        ->toContain('AUDIT_USER')
        ->toContain('AUDITDATE')
        ->toContain('AUDITNOTE')
        ->toContain(TEST_GUID);
});

test('getReworkDetails filters to failed audits only', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getReworkDetails(TEST_GUID);

    expect($sql)
        ->toContain('AUDIT_FAIL IS NOT NULL')
        ->toContain("AUDIT_FAIL != ''");
});

test('getReworkDetails uses valid unit filter', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getReworkDetails(TEST_GUID);

    expect($sql)->toContain("VU.UNIT != 'NW'");
});

test('getReworkDetails uses no CTEs', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getReworkDetails(TEST_GUID);

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
});

// ─── getAssessmentTimeline ──────────────────────────────────────────────────

test('getAssessmentTimeline queries JOBHISTORY for lifecycle events', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getAssessmentTimeline(TEST_GUID);

    expect($sql)
        ->toContain('JOBHISTORY')
        ->toContain('LOGDATE')
        ->toContain('OLDSTATUS')
        ->toContain('JOBSTATUS')
        ->toContain('ASSIGNEDTO')
        ->toContain(TEST_GUID)
        ->toContain('ORDER BY JH.LOGDATE ASC');
});

// ─── getWorkTypeBreakdown ───────────────────────────────────────────────────

test('getWorkTypeBreakdown queries V_ASSESSMENT view', function () {
    $queries = new CareerLedgerQueries(makeCareerContext());
    $sql = $queries->getWorkTypeBreakdown(TEST_GUID);

    expect($sql)
        ->toContain('V_ASSESSMENT')
        ->toContain('unit')
        ->toContain('UnitQty')
        ->toContain(TEST_GUID);
});

// ─── GUID Validation ────────────────────────────────────────────────────────

test('all methods reject invalid GUIDs', function (string $method, array $args) {
    $queries = new CareerLedgerQueries(makeCareerContext());

    expect(fn () => $queries->{$method}('not-a-guid', ...$args))
        ->toThrow(InvalidArgumentException::class);
})->with([
    ['getDailyFootageAttribution', []],
    ['getReworkDetails', []],
    ['getAssessmentTimeline', []],
    ['getWorkTypeBreakdown', []],
]);

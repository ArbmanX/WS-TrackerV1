<?php

use App\Services\WorkStudio\DataCollection\Queries\LiveMonitorQueries;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;

uses(Tests\TestCase::class);

function makeMonitorContext(array $overrides = []): UserQueryContext
{
    return new UserQueryContext(
        resourceGroups: $overrides['resourceGroups'] ?? ['CENTRAL', 'HARRISBURG'],
        contractors: $overrides['contractors'] ?? ['Asplundh'],
        domain: $overrides['domain'] ?? 'ASPLUNDH',
        username: $overrides['username'] ?? 'jsmith',
        userId: $overrides['userId'] ?? 1,
    );
}

const MONITOR_TEST_GUID = '{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}';

// ─── Permission Breakdown ───────────────────────────────────────────────────

test('getDailySnapshot includes all permission status columns', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)
        ->toContain('total_units')
        ->toContain('approved')
        ->toContain('pending')
        ->toContain('refused')
        ->toContain('no_contact')
        ->toContain('deferred')
        ->toContain('ppl_approved')
        ->toContain(MONITOR_TEST_GUID);
});

// ─── Unit Counts ────────────────────────────────────────────────────────────

test('getDailySnapshot classifies work vs non-work via SUMMARYGRP', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)
        ->toContain('work_units')
        ->toContain('nw_units')
        ->toContain('SUMMARYGRP')
        ->toContain('Summary-NonWork')
        ->toContain('JOIN UNITS U');
});

// ─── Notes Compliance ───────────────────────────────────────────────────────

test('getDailySnapshot LEFT JOINs JOBVEGETATIONUNITS for notes compliance', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)
        ->toContain('LEFT JOIN JOBVEGETATIONUNITS JVU')
        ->toContain('JVU.AREA');
});

test('getDailySnapshot uses config area threshold for notes compliance', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    $threshold = config('ws_data_collection.thresholds.notes_compliance_area_sqm');
    expect($sql)->toContain((string) $threshold);
});

test('getDailySnapshot checks PARCELCOMMENTS and ASSNOTE for notes', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)
        ->toContain('PARCELCOMMENTS')
        ->toContain('ASSNOTE');
});

test('getDailySnapshot outputs all notes compliance columns with safe division', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)
        ->toContain('units_requiring_notes')
        ->toContain('units_with_notes')
        ->toContain('units_without_notes')
        ->toContain('compliance_percent')
        ->toContain('NULLIF');
});

// ─── Edit Recency ───────────────────────────────────────────────────────────

test('getDailySnapshot includes MAX edit timestamp columns', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)
        ->toContain('last_edit_date')
        ->toContain('last_edit_by')
        ->toContain('LASTEDITDT');
});

// ─── Aging Units ────────────────────────────────────────────────────────────

test('getDailySnapshot counts aging units with DATEDIFF and threshold', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)
        ->toContain('pending_over_threshold')
        ->toContain('DATEDIFF')
        ->toContain('ASSDDATE')
        ->toContain('14');
});

test('getDailySnapshot aging filter targets pending PERMSTAT only', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)
        ->toContain("PERMSTAT IS NULL OR VU.PERMSTAT = ''")
        ->toContain("'Pending'");
});

test('getDailySnapshot uses parseMsDateToDate for ASSDDATE', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)->toContain("REPLACE(VU.ASSDDATE, '/Date('");
});

// ─── Work Type Breakdown ────────────────────────────────────────────────────

test('getDailySnapshot includes V_ASSESSMENT work types via FOR JSON PATH', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)
        ->toContain('V_ASSESSMENT')
        ->toContain('unit')
        ->toContain('UnitQty')
        ->toContain('FOR JSON PATH')
        ->toContain('work_type_breakdown');
});

// ─── Valid Unit Filter ──────────────────────────────────────────────────────

test('getDailySnapshot uses valid unit filter', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)->toContain("VU.UNIT != 'NW'");
});

// ─── No CTEs ────────────────────────────────────────────────────────────────

test('getDailySnapshot does not use CTEs', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 14);

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
});

// ─── GUID Validation ────────────────────────────────────────────────────────

test('getDailySnapshot rejects invalid GUIDs', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());

    expect(fn () => $queries->getDailySnapshot('not-a-guid', 14))
        ->toThrow(InvalidArgumentException::class);
});

// ─── Threshold Parameterization ─────────────────────────────────────────────

test('getDailySnapshot uses provided aging threshold days', function () {
    $queries = new LiveMonitorQueries(makeMonitorContext());
    $sql = $queries->getDailySnapshot(MONITOR_TEST_GUID, 30);

    expect($sql)->toContain('30');
});

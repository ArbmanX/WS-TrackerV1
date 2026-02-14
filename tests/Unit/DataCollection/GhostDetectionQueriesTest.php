<?php

use App\Services\WorkStudio\DataCollection\Queries\GhostDetectionQueries;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;

uses(Tests\TestCase::class);

function makeGhostContext(array $overrides = []): UserQueryContext
{
    return new UserQueryContext(
        resourceGroups: $overrides['resourceGroups'] ?? ['CENTRAL', 'HARRISBURG'],
        contractors: $overrides['contractors'] ?? ['Asplundh'],
        domain: $overrides['domain'] ?? 'ASPLUNDH',
        username: $overrides['username'] ?? 'jsmith',
        userId: $overrides['userId'] ?? 1,
    );
}

const GHOST_TEST_GUID = '{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}';

// ─── getRecentOwnershipChanges ──────────────────────────────────────────────

test('getRecentOwnershipChanges queries JOBHISTORY for domain changes', function () {
    $queries = new GhostDetectionQueries(makeGhostContext());
    $sql = $queries->getRecentOwnershipChanges('ONEPPL', '2026-02-01');

    expect($sql)
        ->toContain('JOBHISTORY')
        ->toContain("LIKE 'ONEPPL%'")
        ->toContain("'2026-02-01'")
        ->toContain('ASSIGNEDTO');
});

test('getRecentOwnershipChanges scopes to user resource groups', function () {
    $queries = new GhostDetectionQueries(makeGhostContext(['resourceGroups' => ['LEHIGH', 'LANCASTER']]));
    $sql = $queries->getRecentOwnershipChanges('ONEPPL', '2026-02-01');

    expect($sql)
        ->toContain("'LEHIGH'")
        ->toContain("'LANCASTER'")
        ->toContain('VEGJOB.REGION IN');
});

test('getRecentOwnershipChanges filters to active assessments only', function () {
    $queries = new GhostDetectionQueries(makeGhostContext());
    $sql = $queries->getRecentOwnershipChanges('ONEPPL', '2026-02-01');

    expect($sql)
        ->toContain("'ACTIV'")
        ->toContain("'QC'")
        ->toContain("'REWRK'");
});

test('getRecentOwnershipChanges joins SS and VEGJOB for assessment context', function () {
    $queries = new GhostDetectionQueries(makeGhostContext());
    $sql = $queries->getRecentOwnershipChanges('ONEPPL', '2026-02-01');

    expect($sql)
        ->toContain('INNER JOIN SS')
        ->toContain('INNER JOIN VEGJOB')
        ->toContain('LINENAME')
        ->toContain('REGION')
        ->toContain('WO')
        ->toContain('EXT');
});

// ─── getUnitGuidsForAssessment ──────────────────────────────────────────────

test('getUnitGuidsForAssessment returns unit metadata for snapshot', function () {
    $queries = new GhostDetectionQueries(makeGhostContext());
    $sql = $queries->getUnitGuidsForAssessment(GHOST_TEST_GUID);

    expect($sql)
        ->toContain('UNITGUID')
        ->toContain('unit_type')
        ->toContain('STATNAME')
        ->toContain('PERMSTAT')
        ->toContain('FORESTER')
        ->toContain('FRSTR_USER')
        ->toContain(GHOST_TEST_GUID);
});

test('getUnitGuidsForAssessment uses valid unit filter', function () {
    $queries = new GhostDetectionQueries(makeGhostContext());
    $sql = $queries->getUnitGuidsForAssessment(GHOST_TEST_GUID);

    expect($sql)->toContain("VU.UNIT != 'NW'");
});

test('getUnitGuidsForAssessment orders by station and unit', function () {
    $queries = new GhostDetectionQueries(makeGhostContext());
    $sql = $queries->getUnitGuidsForAssessment(GHOST_TEST_GUID);

    expect($sql)->toContain('ORDER BY VU.STATNAME, VU.UNIT');
});

// ─── getAssessmentExtension ─────────────────────────────────────────────────

test('getAssessmentExtension returns EXT field from SS', function () {
    $queries = new GhostDetectionQueries(makeGhostContext());
    $sql = $queries->getAssessmentExtension(GHOST_TEST_GUID);

    expect($sql)
        ->toContain('SS.EXT')
        ->toContain('SS.WO')
        ->toContain('SS.STATUS')
        ->toContain('SS.TAKENBY')
        ->toContain(GHOST_TEST_GUID);
});

// ─── No CTEs ────────────────────────────────────────────────────────────────

test('no method uses CTEs', function (string $method, array $args) {
    $queries = new GhostDetectionQueries(makeGhostContext());
    $sql = $queries->{$method}(...$args);

    expect($sql)->not->toMatch('/\bWITH\b(?!IN)/');
})->with([
    ['getRecentOwnershipChanges', ['ONEPPL', '2026-02-01']],
    ['getUnitGuidsForAssessment', [GHOST_TEST_GUID]],
    ['getAssessmentExtension', [GHOST_TEST_GUID]],
]);

// ─── GUID Validation ────────────────────────────────────────────────────────

test('getUnitGuidsForAssessment rejects invalid GUID', function () {
    $queries = new GhostDetectionQueries(makeGhostContext());

    expect(fn () => $queries->getUnitGuidsForAssessment('DROP TABLE; --'))
        ->toThrow(InvalidArgumentException::class);
});

test('getAssessmentExtension rejects invalid GUID', function () {
    $queries = new GhostDetectionQueries(makeGhostContext());

    expect(fn () => $queries->getAssessmentExtension('not-a-guid'))
        ->toThrow(InvalidArgumentException::class);
});

test('getRecentOwnershipChanges does not validate domain as GUID', function () {
    // Domain is a string prefix like 'ONEPPL', not a GUID — should not throw
    $queries = new GhostDetectionQueries(makeGhostContext());
    $sql = $queries->getRecentOwnershipChanges('ONEPPL', '2026-02-01');

    expect($sql)->toBeString()->not->toBeEmpty();
});

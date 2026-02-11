<?php

use App\Services\WorkStudio\Assessments\Queries\AssessmentQueries;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;

uses(Tests\TestCase::class);

function makeContext(array $overrides = []): UserQueryContext
{
    return new UserQueryContext(
        resourceGroups: $overrides['resourceGroups'] ?? ['CENTRAL', 'HARRISBURG'],
        contractors: $overrides['contractors'] ?? ['Asplundh'],
        domain: $overrides['domain'] ?? 'ASPLUNDH',
        username: $overrides['username'] ?? 'jsmith',
        userId: $overrides['userId'] ?? 1,
    );
}

test('systemWideDataQuery uses context resource groups', function () {
    $context = makeContext(['resourceGroups' => ['LEHIGH', 'LANCASTER']]);
    $queries = new AssessmentQueries($context);
    $sql = $queries->systemWideDataQuery();

    expect($sql)->toContain("'LEHIGH', 'LANCASTER'");
    expect($sql)->not->toContain("'CENTRAL'");
});

test('systemWideDataQuery uses context contractors', function () {
    $context = makeContext(['contractors' => ['TestCorp']]);
    $queries = new AssessmentQueries($context);
    $sql = $queries->systemWideDataQuery();

    expect($sql)->toContain("'TestCorp'");
    expect($sql)->not->toContain("'Asplundh'");
});

test('systemWideDataQuery still uses config for excludedUsers', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->systemWideDataQuery();

    $excludedUsers = config('ws_assessment_query.excludedUsers');
    foreach ($excludedUsers as $user) {
        expect($sql)->toContain($user);
    }
});

test('systemWideDataQuery still uses config for job types', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->systemWideDataQuery();

    $jobTypes = config('ws_assessment_query.job_types.assessments');
    foreach ($jobTypes as $type) {
        expect($sql)->toContain($type);
    }
});

test('groupedByRegionDataQuery uses context values', function () {
    $context = makeContext(['resourceGroups' => ['DISTRIBUTION']]);
    $queries = new AssessmentQueries($context);
    $sql = $queries->groupedByRegionDataQuery();

    expect($sql)->toContain("'DISTRIBUTION'");
    expect($sql)->not->toContain("'CENTRAL'");
});

test('getActiveAssessmentsOrderedByOldest uses context domain', function () {
    $context = makeContext(['domain' => 'TESTDOMAIN']);
    $queries = new AssessmentQueries($context);
    $sql = $queries->getActiveAssessmentsOrderedByOldest(10);

    expect($sql)->toContain("'TESTDOMAIN'");
    expect($sql)->toContain('TOP (10)');
});

test('getAllJobGUIDsForEntireScopeYear uses context values', function () {
    $context = makeContext(['resourceGroups' => ['PRE_PLANNER'], 'contractors' => ['CustomCo']]);
    $queries = new AssessmentQueries($context);
    $sql = $queries->getAllJobGUIDsForEntireScopeYear();

    expect($sql)->toContain("'PRE_PLANNER'");
    expect($sql)->toContain("'CustomCo'");
});

test('getDistinctFieldValues uses context values', function () {
    $context = makeContext(['resourceGroups' => ['VEG_PLANNERS']]);
    $queries = new AssessmentQueries($context);
    $sql = $queries->getDistinctFieldValues('VEGUNIT', 'PERMSTAT');

    expect($sql)->toContain("'VEG_PLANNERS'");
    expect($sql)->toContain('VEGUNIT.PERMSTAT');
});

test('getAllAssessmentsDailyActivities uses context values', function () {
    $context = makeContext(['contractors' => ['TreeCorp']]);
    $queries = new AssessmentQueries($context);
    $sql = $queries->getAllAssessmentsDailyActivities();

    expect($sql)->toContain("'TreeCorp'");
});

test('scope year comes from config not context', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->systemWideDataQuery();

    $scopeYear = config('ws_assessment_query.scope_year');
    expect($sql)->toContain($scopeYear);
});

test('active_planners filters by domain in systemWideDataQuery', function () {
    $context = makeContext(['domain' => 'TESTCORP']);
    $queries = new AssessmentQueries($context);
    $sql = $queries->systemWideDataQuery();

    expect($sql)->toContain("'TESTCORP'")
        ->and($sql)->toContain('active_planners')
        ->and($sql)->toContain('CHARINDEX');
});

test('Active_Planners filters by domain in groupedByRegionDataQuery', function () {
    $context = makeContext(['domain' => 'OTHERDOMAIN']);
    $queries = new AssessmentQueries($context);
    $sql = $queries->groupedByRegionDataQuery();

    expect($sql)->toContain("'OTHERDOMAIN'")
        ->and($sql)->toContain('Active_Planners')
        ->and($sql)->toContain('CHARINDEX');
});

// ─── Config Value Tests ─────────────────────────────────────────────────────

test('permission_statuses config is a non-empty array with expected keys', function () {
    $statuses = config('ws_assessment_query.permission_statuses');

    expect($statuses)->toBeArray()
        ->not->toBeEmpty()
        ->toHaveKeys(['approved', 'pending', 'no_contact', 'refused', 'deferred', 'ppl_approved']);
});

test('permission_statuses.refused is Refused (BUG-001 regression guard)', function () {
    expect(config('ws_assessment_query.permission_statuses.refused'))->toBe('Refused');
});

test('unit_groups config is a non-empty array with expected keys', function () {
    $groups = config('ws_assessment_query.unit_groups');

    expect($groups)->toBeArray()
        ->not->toBeEmpty()
        ->toHaveKeys(['removal_6_12', 'removal_over_12', 'ash_removal', 'vps', 'brush', 'herbicide', 'bucket_trim', 'manual_trim']);
});

test('unit_groups values are non-empty arrays of strings', function () {
    $groups = config('ws_assessment_query.unit_groups');

    foreach ($groups as $key => $codes) {
        expect($codes)->toBeArray()->not->toBeEmpty("unit_groups.{$key} should not be empty");

        foreach ($codes as $code) {
            expect($code)->toBeString();
        }
    }
});

test('excluded_from_assessments cycle types config is a non-empty array', function () {
    $excluded = config('ws_assessment_query.cycle_types.excluded_from_assessments');

    expect($excluded)->toBeArray()
        ->not->toBeEmpty()
        ->toContain('Reactive')
        ->toContain('Storm Follow Up');
});

// ─── BUG-001 Regression: PERMSTAT uses 'Refused' not 'Refusal' ──────────────

test('groupedByCircuitDataQuery uses Refused not Refusal for PERMSTAT', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->groupedByCircuitDataQuery();

    expect($sql)->toContain("'Refused'")
        ->and($sql)->not->toContain("'Refusal'");
});

test('groupedByRegionDataQuery uses Refused not Refusal for PERMSTAT', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->groupedByRegionDataQuery();

    expect($sql)->toContain("'Refused'")
        ->and($sql)->not->toContain("'Refusal'");
});

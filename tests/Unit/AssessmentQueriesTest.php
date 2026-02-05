<?php

use App\Services\WorkStudio\AssessmentsDx\Queries\AssessmentQueries;
use App\Services\WorkStudio\ValueObjects\UserQueryContext;

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

    $jobTypes = config('ws_assessment_query.job_types');
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

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

// ─── Phase 2: Shared Fragment Tests ─────────────────────────────────────────

test('baseFromClause uses INNER JOIN not LEFT JOIN (BUG-002)', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->systemWideDataQuery();

    expect($sql)
        ->toContain('INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID')
        ->toContain('INNER JOIN WPStartDate_Assessment_Xrefs');

    // Ensure no LEFT JOIN for xrefs (BUG-002 resolution)
    expect($sql)->not->toContain('LEFT JOIN WPStartDate_Assessment_Xrefs');
});

test('baseWhereClause includes all standard filters', function () {
    $context = makeContext(['resourceGroups' => ['TESTING_REGION'], 'contractors' => ['TestCorp']]);
    $queries = new AssessmentQueries($context);
    $sql = $queries->systemWideDataQuery();

    // Standard WHERE clause should include all these filters
    expect($sql)
        ->toContain("VEGJOB.REGION IN ('TESTING_REGION')")
        ->toContain("VEGJOB.CONTRACTOR IN ('TestCorp')")
        ->toContain('SS.STATUS IN')
        ->toContain('SS.JOBTYPE IN')
        ->toContain('VEGJOB.CYCLETYPE IN')
        ->toContain('SS.TAKENBY NOT IN');
});

test('baseWhereClause overrides work for dailyActivities', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);

    $systemSql = $queries->systemWideDataQuery();
    $dailySql = $queries->getAllAssessmentsDailyActivities();

    // systemWideDataQuery uses default statuses (ACTIV, QC, REWRK, CLOSE)
    expect($systemSql)->toContain("'ACTIV', 'QC', 'REWRK', 'CLOSE'");

    // dailyActivities uses planner_concern from config via statusSql override
    $plannerConcern = config('ws_assessment_query.statuses.planner_concern');
    foreach ($plannerConcern as $status) {
        expect($dailySql)->toContain("'{$status}'");
    }

    // dailyActivities does NOT include excluded users (includeExcludedUsers: false)
    expect($dailySql)->not->toContain('TAKENBY NOT IN');
});

test('permissionCountsCrossApply uses config PERMSTAT values', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->groupedByRegionDataQuery();

    // Should contain config-driven values from permissionCountsCrossApply
    $statuses = config('ws_assessment_query.permission_statuses');
    foreach ($statuses as $status) {
        expect($sql)->toContain("'{$status}'");
    }

    // Should use CROSS APPLY pattern
    expect($sql)->toContain('CROSS APPLY');
    expect($sql)->toContain('AS UnitData');
});

test('workMeasurementsCrossApply uses config unit codes', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->groupedByRegionDataQuery();

    // Should contain config-driven unit codes from workMeasurementsCrossApply
    $unitGroups = config('ws_assessment_query.unit_groups');
    foreach ($unitGroups as $codes) {
        foreach ($codes as $code) {
            expect($sql)->toContain("'{$code}'");
        }
    }

    // Should reference JOBVEGETATIONUNITS table
    expect($sql)->toContain('JOBVEGETATIONUNITS');
    expect($sql)->toContain('AS WorkData');
});

test('groupedByCircuitDataQuery uses CROSS APPLY fragments', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->groupedByCircuitDataQuery();

    // Uses permissionCountsWithDatesCrossApply (includes assessed dates)
    expect($sql)
        ->toContain('First_Assessed_Date')
        ->toContain('Last_Assessed_Date')
        ->toContain('AS UnitData')
        ->toContain('AS WorkData')
        ->toContain('CROSS APPLY');

    // Uses workMeasurementsCrossApply column references
    expect($sql)
        ->toContain('WorkData.Rem_6_12_Count')
        ->toContain('WorkData.Brush_Acres')
        ->toContain('UnitData.Approved_Count');
});

test('getAllJobGUIDsForEntireScopeYear uses baseFromClause and baseWhereClause', function () {
    $context = makeContext(['resourceGroups' => ['FRAGMENT_TEST']]);
    $queries = new AssessmentQueries($context);
    $sql = $queries->getAllJobGUIDsForEntireScopeYear();

    // Uses baseFromClause (INNER JOIN)
    expect($sql)->toContain('INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID');

    // Uses baseWhereClause with overrides (planner_concern statuses, no excluded users)
    expect($sql)->toContain("VEGJOB.REGION IN ('FRAGMENT_TEST')");
    expect($sql)->not->toContain('TAKENBY NOT IN');
});

// ─── Phase 3: Bug Fixes & Security ─────────────────────────────────────────

test('getAllByJobGuid validates GUID format (SEC-003)', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);

    // Valid GUIDs should work
    $sql = $queries->getAllByJobGuid('12345678-1234-1234-1234-123456789abc');
    expect($sql)->toContain('12345678-1234-1234-1234-123456789abc');

    // Braced GUID should also work
    $sql = $queries->getAllByJobGuid('{12345678-1234-1234-1234-123456789abc}');
    expect($sql)->toContain('{12345678-1234-1234-1234-123456789abc}');
});

test('getAllByJobGuid rejects invalid GUID (SEC-003)', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);

    $queries->getAllByJobGuid("'; DROP TABLE SS; --");
})->throws(\InvalidArgumentException::class, 'Invalid JOBGUID format.');

test('getAllByJobGuid rejects empty string (SEC-003)', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);

    $queries->getAllByJobGuid('');
})->throws(\InvalidArgumentException::class, 'Invalid JOBGUID format.');

test('getAllAssessmentsDailyActivities has no SS self-join', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->getAllAssessmentsDailyActivities();

    // Should NOT contain the self-join alias
    expect($sql)->not->toContain('SS AS WSREQSS');
    expect($sql)->not->toContain('WSREQSS.');

    // Should use SS directly for TAKENBY
    expect($sql)->toContain('SS.TAKENBY AS Current_Owner');
});

test('getAllByJobGuid has no SS self-join', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->getAllByJobGuid('12345678-1234-1234-1234-123456789abc');

    // Should NOT contain the self-join alias
    expect($sql)->not->toContain('SS AS WSREQSS');
    expect($sql)->not->toContain('WSREQSS.');

    // Should use SS directly
    expect($sql)->toContain('SS.JOBGUID AS Job_ID');
    expect($sql)->toContain('SS.TAKENBY AS Current_Owner');
});

test('getAllByJobGuid uses unitCountsCrossApply instead of subqueries', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->getAllByJobGuid('12345678-1234-1234-1234-123456789abc');

    // Should use CROSS APPLY pattern
    expect($sql)->toContain('CROSS APPLY');
    expect($sql)->toContain('AS UnitCounts');

    // Should reference UnitCounts columns, not inline subqueries
    expect($sql)->toContain('UnitCounts.Total_Units_Planned');
    expect($sql)->toContain('UnitCounts.Total_Approvals');
    expect($sql)->toContain('UnitCounts.Total_Refusals');
});

test('getActiveAssessmentsOrderedByOldest uses config-driven CYCLETYPE exclusion', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->getActiveAssessmentsOrderedByOldest(10);

    // Should contain all excluded cycle types from config
    $excluded = config('ws_assessment_query.cycle_types.excluded_from_assessments');
    foreach ($excluded as $type) {
        expect($sql)->toContain("'{$type}'");
    }

    // Should use NOT IN pattern
    expect($sql)->toContain('CYCLETYPE NOT IN');
});

test('getDistinctFieldValues uses config-driven CYCLETYPE exclusion', function () {
    $context = makeContext();
    $queries = new AssessmentQueries($context);
    $sql = $queries->getDistinctFieldValues('VEGUNIT', 'PERMSTAT');

    // Should contain config-driven excluded cycle types
    $excluded = config('ws_assessment_query.cycle_types.excluded_from_assessments');
    foreach ($excluded as $type) {
        expect($sql)->toContain("'{$type}'");
    }

    expect($sql)->toContain('CYCLETYPE NOT IN');
});

// ─── Phase 4: Domain Class Split Tests ──────────────────────────────────────

test('domain classes produce identical SQL to facade', function () {
    $context = makeContext(['resourceGroups' => ['SPLIT_TEST'], 'contractors' => ['SplitCorp']]);

    $facade = new AssessmentQueries($context);

    $aggregates = new \App\Services\WorkStudio\Assessments\Queries\AggregateQueries($context);
    $circuits = new \App\Services\WorkStudio\Assessments\Queries\CircuitQueries($context);
    $activities = new \App\Services\WorkStudio\Assessments\Queries\ActivityQueries($context);
    $lookups = new \App\Services\WorkStudio\Assessments\Queries\LookupQueries($context);

    // Each domain class method should produce the same SQL as the facade
    expect($aggregates->systemWideDataQuery())->toBe($facade->systemWideDataQuery());
    expect($aggregates->groupedByRegionDataQuery())->toBe($facade->groupedByRegionDataQuery());
    expect($circuits->groupedByCircuitDataQuery())->toBe($facade->groupedByCircuitDataQuery());
    expect($circuits->getAllJobGUIDsForEntireScopeYear())->toBe($facade->getAllJobGUIDsForEntireScopeYear());
    expect($activities->getAllAssessmentsDailyActivities())->toBe($facade->getAllAssessmentsDailyActivities());
    expect($activities->getActiveAssessmentsOrderedByOldest(10))->toBe($facade->getActiveAssessmentsOrderedByOldest(10));
    expect($lookups->getDistinctFieldValues('VEGUNIT', 'PERMSTAT'))->toBe($facade->getDistinctFieldValues('VEGUNIT', 'PERMSTAT'));
});

test('CircuitQueries.getAllByJobGuid validates GUID via domain class', function () {
    $context = makeContext();
    $circuits = new \App\Services\WorkStudio\Assessments\Queries\CircuitQueries($context);

    $circuits->getAllByJobGuid('invalid-guid');
})->throws(\InvalidArgumentException::class, 'Invalid JOBGUID format.');

test('LookupQueries.getDistinctFieldValues validates input via domain class', function () {
    $context = makeContext();
    $lookups = new \App\Services\WorkStudio\Assessments\Queries\LookupQueries($context);

    $lookups->getDistinctFieldValues('DROP TABLE', 'PERMSTAT');
})->throws(\InvalidArgumentException::class, 'Invalid table or field name.');

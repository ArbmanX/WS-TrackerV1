<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;
use App\Services\WorkStudio\Shared\Helpers\WSSQLCaster;

class FetchAssessmentQueries
{
    /**
     * Build the main fetch query for assessments from WorkStudio API.
     *
     * @param  string|null  $year  Scope year filter (e.g. '2026')
     * @param  string|null  $status  Single status filter; null uses planner_concern defaults
     * @param  float|null  $maxEditDateOle  Incremental sync threshold (OLE float); null skips EDITDATE filter
     */
    public static function buildFetchQuery(?string $year = null, ?string $status = null, ?float $maxEditDateOle = null): string
    {
        $jobTypes = WSHelpers::toSqlInClause(config('workstudio.assessments.job_types.assessments_dx'));
        $editDateCast = WSSQLCaster::cast('VEGJOB.EDITDATE');

        $scopeYearExpr = "CASE WHEN xref.WP_STARTDATE IS NULL OR xref.WP_STARTDATE = '' THEN NULL "
            ."ELSE YEAR(CAST(REPLACE(REPLACE(xref.WP_STARTDATE, '/Date(', ''), ')/', '') AS DATE)) END";

        $sql = 'SELECT SS.JOBGUID, SS.PJOBGUID, SS.WO, SS.EXT, SS.JOBTYPE, SS.STATUS, '
            .'SS.TAKEN, SS.TAKENBY, SS.MODIFIEDBY, SS.VERSION, SS.SYNCHVERSN, '
            .'SS.ASSIGNEDTO, SS.TITLE, '
            .'VEGJOB.CYCLETYPE, VEGJOB.REGION, '
            .'VEGJOB.PLANNEDEMERGENT, VEGJOB.VOLTAGE, VEGJOB.COSTMETHOD, '
            .'VEGJOB.PROGRAMNAME, VEGJOB.PERMISSIONING_REQUIRED, '
            .'VEGJOB.PRCENT, VEGJOB.LENGTH, VEGJOB.LENGTHCOMP, '
            .'VEGJOB.EDITDATE AS EDITDATE_OLE, '
            ."{$editDateCast} AS EDITDATE, "
            ."{$scopeYearExpr} AS SCOPE_YEAR "
            .'FROM SS '
            .'INNER JOIN VEGJOB ON VEGJOB.JOBGUID = SS.JOBGUID '
            .'LEFT JOIN WPStartDate_Assessment_Xrefs xref '
            .'ON xref.Assess_JOBGUID = CASE '
            .'WHEN SS.EXT = \'@\' THEN SS.JOBGUID '
            ."ELSE COALESCE(NULLIF(SS.PJOBGUID, ''), SS.JOBGUID) END "
            ."WHERE SS.JOBTYPE IN ({$jobTypes}) ";

        if ($year) {
            $sql .= "AND xref.WP_STARTDATE LIKE '%{$year}%' ";
        }

        if ($status) {
            $sql .= "AND SS.STATUS = '{$status}' ";
        } else {
            $statuses = WSHelpers::toSqlInClause(config('workstudio.statuses.planner_concern'));
            $sql .= "AND SS.STATUS IN ({$statuses}) ";
        }

        if ($maxEditDateOle !== null) {
            $sql .= "AND VEGJOB.EDITDATE > {$maxEditDateOle} ";
        }

        $sql .= 'ORDER BY SS.JOBGUID';

        return $sql;
    }

    /**
     * Build a query to fetch specific assessments by JOBGUID.
     *
     * WHERE clause is ONLY the JOBGUID IN filter — no status, year,
     * job type, or EDITDATE conditions.
     *
     * @param  array<int, string>  $jobGuids  JOBGUID values to fetch
     */
    public static function buildFetchByJobGuids(array $jobGuids): string
    {
        $jobGuidsSql = WSHelpers::toSqlInClause($jobGuids);
        $editDateCast = WSSQLCaster::cast('VEGJOB.EDITDATE');

        $scopeYearExpr = "CASE WHEN xref.WP_STARTDATE IS NULL OR xref.WP_STARTDATE = '' THEN NULL "
            ."ELSE YEAR(CAST(REPLACE(REPLACE(xref.WP_STARTDATE, '/Date(', ''), ')/', '') AS DATE)) END";

        return 'SELECT SS.JOBGUID, SS.PJOBGUID, SS.WO, SS.EXT, SS.JOBTYPE, SS.STATUS, '
            .'SS.TAKEN, SS.TAKENBY, SS.MODIFIEDBY, SS.VERSION, SS.SYNCHVERSN, '
            .'SS.ASSIGNEDTO, SS.TITLE, '
            .'VEGJOB.CYCLETYPE, VEGJOB.REGION, '
            .'VEGJOB.PLANNEDEMERGENT, VEGJOB.VOLTAGE, VEGJOB.COSTMETHOD, '
            .'VEGJOB.PROGRAMNAME, VEGJOB.PERMISSIONING_REQUIRED, '
            .'VEGJOB.PRCENT, VEGJOB.LENGTH, VEGJOB.LENGTHCOMP, '
            .'VEGJOB.EDITDATE AS EDITDATE_OLE, '
            ."{$editDateCast} AS EDITDATE, "
            ."{$scopeYearExpr} AS SCOPE_YEAR "
            .'FROM SS '
            .'INNER JOIN VEGJOB ON VEGJOB.JOBGUID = SS.JOBGUID '
            .'LEFT JOIN WPStartDate_Assessment_Xrefs xref '
            .'ON xref.Assess_JOBGUID = CASE '
            .'WHEN SS.EXT = \'@\' THEN SS.JOBGUID '
            ."ELSE COALESCE(NULLIF(SS.PJOBGUID, ''), SS.JOBGUID) END "
            ."WHERE SS.JOBGUID IN ({$jobGuidsSql}) "
            .'ORDER BY SS.JOBGUID';
    }

    /**
     * Build a user-scoped query for assessments by username(s).
     *
     * @param  array<int, string>|string  $usernames  One or more usernames
     * @param  string|null  $scopeYear  Optional scope year filter
     *
     * @todo Implement user-scoped WHERE clause — currently a no-op stub
     */
    public static function forUsers(array|string $usernames, ?string $scopeYear = null): void
    {
        // Stub — implementation deferred
    }
}

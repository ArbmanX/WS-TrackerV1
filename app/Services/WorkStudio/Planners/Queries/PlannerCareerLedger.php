<?php

namespace App\Services\WorkStudio\Planners\Queries;

use App\Services\WorkStudio\Assessments\Queries\AbstractQueryBuilder;
use App\Services\WorkStudio\Shared\Helpers\WSHelpers;

class PlannerCareerLedger extends AbstractQueryBuilder
{
    /**
     * Discover distinct JOBGUIDs for one or more FRSTR_USERs.
     *
     * Joins VEGUNIT → SS to find assessments where the user performed
     * field work (has ASSDDATE). Only includes parent assessments (EXT = '@').
     *
     * When $current is true, returns active assessments (ACTIV, QC, REWRK).
     * When false (default), returns closed assessments only.
     *
     * @param  string|array<int, string>  $frstrUsers
     */
    public function getDistinctJobGuids(string|array $frstrUsers, bool $current = false): string
    {
        $users = is_array($frstrUsers) ? $frstrUsers : [$frstrUsers];
        $usersSql = WSHelpers::toSqlInClause($users);

        $statusFilter = $current
            ? "SS.STATUS IN ('ACTIV', 'QC', 'REWRK')"
            : "SS.STATUS = 'CLOSE'";

        return "SELECT DISTINCT VU.JOBGUID
FROM VEGUNIT VU
INNER JOIN SS ON SS.JOBGUID = VU.JOBGUID
WHERE VU.FRSTR_USER IN ({$usersSql})
    AND {$statusFilter}
    AND SS.EXT = '@'
    AND VU.ASSDDATE IS NOT NULL
    AND VU.ASSDDATE != ''";
    }

    /**
     * Consolidated career data — one row per assessment with nested JSON columns.
     *
     * Replaces the need to call getAssessmentMetadataBatch(),
     * getDailyFootageAttributionBatch(), getAssessmentTimeline(),
     * getWorkTypeBreakdown(), and getReworkDetails() separately.
     *
     * JOIN chain:
     *   SS (root) → VEGJOB (metadata: line, region, cycle, miles)
     *   OUTER APPLY → JOBHISTORY (lifecycle timeline as JSON)
     *   OUTER APPLY → V_ASSESSMENT (work type breakdown as JSON)
     *   OUTER APPLY → VEGUNIT audit (rework details as JSON, null if clean)
     *   OUTER APPLY → VEGUNIT+STATIONS+UNITS (daily footage metrics as JSON)
     *
     * Flat columns: JOBGUID, STATUS, line_name, region, cycle_type,
     *               assigned_planner, total_miles
     * JSON columns: timeline, work_type_breakdown, rework_details, daily_metrics
     *
     * Uses ASSDDATE exclusively (not DATEPOP) for date attribution.
     * DDOProtocol compatible — no CTEs, uses derived tables + FOR JSON PATH.
     *
     * @param  array<int, string>  $jobGuids
     */
    public function getFullCareerData(array $jobGuids, ?string $dateStart = null, ?string $dateEnd = null): string
    {
        foreach ($jobGuids as $guid) {
            self::validateGuid($guid);
        }

        $guidsSql = WSHelpers::toSqlInClause($jobGuids);
        $parseAssddate = self::parseMsDateToDate('VU.ASSDDATE');

        $dateFilter = '';
        if ($dateStart !== null && $dateEnd !== null) {
            $dateFilter = "AND FU.completion_date BETWEEN '{$dateStart}' AND '{$dateEnd}'";
        }

        return "SELECT
    SS.JOBGUID,
    SS.STATUS,
    VEGJOB.LINENAME AS line_name,
    VEGJOB.REGION AS region,
    VEGJOB.CYCLETYPE AS cycle_type,
    VEGJOB.FRSTR_USER AS assigned_planner,
    VEGJOB.LENGTH AS total_miles,
    VEGJOB.LENGTHCOMP AS total_miles_planned,
    (SELECT
        JH.USERNAME, JH.ACTION, JH.LOGDATE,
        JH.OLDSTATUS, JH.JOBSTATUS, JH.ASSIGNEDTO
     FROM JOBHISTORY JH
     WHERE JH.JOBGUID = SS.JOBGUID
     ORDER BY JH.LOGDATE ASC
     FOR JSON PATH
    ) AS timeline,
    (SELECT VA.unit, ROUND(VA.UnitQty, 2) AS UnitQty
     FROM V_ASSESSMENT VA
     WHERE VA.jobguid = SS.JOBGUID
     ORDER BY VA.unit
     FOR JSON PATH
    ) AS work_type_breakdown,
    (SELECT
        VUR.UNITGUID,
        VUR.UNIT AS unit_type,
        VUR.STATNAME,
        VUR.FORESTER,
        VUR.AUDIT_FAIL,
        VUR.AUDIT_USER,
        VUR.AUDITDATE,
        VUR.AUDITNOTE
     FROM VEGUNIT VUR
     WHERE VUR.JOBGUID = SS.JOBGUID
       AND VUR.UNIT != 'NW' AND VUR.UNIT != '' AND VUR.UNIT IS NOT NULL
       AND VUR.AUDIT_FAIL IS NOT NULL AND VUR.AUDIT_FAIL != ''
     ORDER BY VUR.AUDITDATE, VUR.STATNAME
     FOR JSON PATH
    ) AS rework_details,
    DailyData.daily_metrics
FROM SS
INNER JOIN VEGJOB ON VEGJOB.JOBGUID = SS.JOBGUID
OUTER APPLY (
    SELECT (
        SELECT
            FU.completion_date,
            FU.FRSTR_USER,
            CAST(SUM(ISNULL(ST.SPANLGTH, 0)) / 1609.34 AS DECIMAL(10,2)) AS daily_footage_miles,
            STRING_AGG(CAST(FU.STATNAME AS VARCHAR(MAX)), ',') WITHIN GROUP (ORDER BY FU.STATNAME) AS station_list,
            SUM(CASE WHEN U.SUMMARYGRP IS NOT NULL AND U.SUMMARYGRP != '' AND U.SUMMARYGRP != 'Summary-NonWork' THEN 1 ELSE 0 END) AS unit_count
        FROM (
            SELECT
                VU.STATNAME,
                {$parseAssddate} AS completion_date,
                VU.FRSTR_USER,
                VU.UNIT,
                ROW_NUMBER() OVER (
                    PARTITION BY VU.STATNAME
                    ORDER BY VU.ASSDDATE ASC
                ) AS unit_rank
            FROM VEGUNIT VU
            WHERE VU.UNIT IS NOT NULL
              AND VU.UNIT != ''
              AND VU.ASSDDATE IS NOT NULL
              AND VU.ASSDDATE != ''
              AND VU.JOBGUID = SS.JOBGUID
        ) FU
        JOIN STATIONS ST
            ON ST.JOBGUID = SS.JOBGUID
            AND ST.STATNAME = FU.STATNAME
        JOIN UNITS U
            ON U.UNIT = FU.UNIT
        WHERE FU.unit_rank = 1
            {$dateFilter}
        GROUP BY FU.completion_date, FU.FRSTR_USER
        ORDER BY FU.completion_date
        FOR JSON PATH
    ) AS daily_metrics
) AS DailyData
WHERE SS.JOBGUID IN ({$guidsSql})";
    }
}

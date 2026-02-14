<?php

namespace App\Services\WorkStudio\Planners\Queries;

use App\Services\WorkStudio\Assessments\Queries\AbstractQueryBuilder;
use App\Services\WorkStudio\Shared\Helpers\WSHelpers;

class PlannerCareerLedger extends AbstractQueryBuilder
{
    /**
     * Discover distinct closed JOBGUIDs for one or more FRSTR_USERs.
     *
     * Joins VEGUNIT → SS to find assessments where the user performed
     * field work (has ASSDDATE) and the assessment is closed.
     * Only includes parent assessments (EXT = '@').
     *
     * @param  string|array<int, string>  $frstrUsers
     */
    public function getDistinctJobGuids(string|array $frstrUsers): string
    {
        $users = is_array($frstrUsers) ? $frstrUsers : [$frstrUsers];
        $usersSql = WSHelpers::toSqlInClause($users);

        return "SELECT DISTINCT VU.JOBGUID
FROM VEGUNIT VU
INNER JOIN SS ON SS.JOBGUID = VU.JOBGUID
WHERE VU.FRSTR_USER IN ({$usersSql})
    AND SS.STATUS = 'CLOSE'
    AND SS.EXT = '@'
    AND VU.ASSDDATE IS NOT NULL
    AND VU.ASSDDATE != ''";
    }

    /**
     * Batch metadata for multiple assessments: line name, region, cycle type, miles.
     *
     * Reuses totalFootageSubquery() from SqlFragmentHelpers for consistent
     * mileage calculation across the application.
     *
     * @param  array<int, string>  $jobGuids
     */
    public function getAssessmentMetadataBatch(array $jobGuids): string
    {
        foreach ($jobGuids as $guid) {
            self::validateGuid($guid);
        }

        $guidsSql = WSHelpers::toSqlInClause($jobGuids);

        return "SELECT
    SS.JOBGUID,
    SS.STATUS,
    VEGJOB.LINENAME AS line_name,
    VEGJOB.REGION AS region,
    VEGJOB.CYCLETYPE AS cycle_type,
    VEGJOB.FRSTR_USER AS assigned_planner,
    VEGJOB.LENGTH AS total_miles
FROM SS
INNER JOIN VEGJOB ON VEGJOB.JOBGUID = SS.JOBGUID
WHERE SS.JOBGUID IN ({$guidsSql})";
    }

    /**
     * Daily footage attribution using ASSDDATE exclusively.
     *
     * Unlike CareerLedgerQueries::getDailyFootageAttribution which uses
     * COALESCE(DATEPOP, ASSDDATE), this method uses ASSDDATE only.
     * For planner career tracking, the field assessment date is the
     * authoritative date for attribution, not the data entry date.
     *
     * Output columns: JOBGUID, completion_date, FRSTR_USER,
     * daily_footage_meters, station_list, unit_count
     *
     * NOTE: DDOProtocol does NOT support CTEs — uses derived tables.
     */
    public function getDailyFootageAttribution(string $jobGuid, ?string $dateStart = null, ?string $dateEnd = null): string
    {
        self::validateGuid($jobGuid);

        $parseAssddate = self::parseMsDateToDate('VU.ASSDDATE');

        $dateFilter = '';
        if ($dateStart !== null && $dateEnd !== null) {
            $dateFilter = "AND FU.completion_date BETWEEN '{$dateStart}' AND '{$dateEnd}'";
        }

        return "SELECT
    FU.JOBGUID,
    FU.completion_date,
    FU.FRSTR_USER,
    CAST(SUM(ISNULL(ST.SPANLGTH, 0)) / 1609.34 AS DECIMAL(10,4)) AS daily_footage_miles,
    STRING_AGG(CAST(FU.STATNAME AS VARCHAR(MAX)), ',') WITHIN GROUP (ORDER BY FU.STATNAME) AS station_list,
    SUM(CASE WHEN U.SUMMARYGRP IS NOT NULL AND U.SUMMARYGRP != '' AND U.SUMMARYGRP != 'Summary-NonWork' THEN 1 ELSE 0 END) AS unit_count
FROM (
    SELECT
        VU.JOBGUID,
        VU.STATNAME,
        {$parseAssddate} AS completion_date,
        VU.FRSTR_USER,
        VU.UNIT,
        ROW_NUMBER() OVER (
            PARTITION BY VU.JOBGUID, VU.STATNAME
            ORDER BY VU.ASSDDATE ASC
        ) AS unit_rank
    FROM VEGUNIT VU
    WHERE VU.UNIT IS NOT NULL
      AND VU.UNIT != ''
      AND VU.ASSDDATE IS NOT NULL
      AND VU.ASSDDATE != ''
      AND VU.JOBGUID = '{$jobGuid}'
) FU
JOIN STATIONS ST
    ON ST.JOBGUID = FU.JOBGUID
    AND ST.STATNAME = FU.STATNAME
JOIN UNITS U
    ON U.UNIT = FU.UNIT
WHERE FU.unit_rank = 1
    {$dateFilter}
GROUP BY FU.JOBGUID, FU.completion_date, FU.FRSTR_USER
ORDER BY FU.JOBGUID, FU.completion_date";
    }

    /**
     * Batch version of getDailyFootageAttribution for multiple assessments.
     *
     * @param  array<int, string>  $jobGuids
     */
    public function getDailyFootageAttributionBatch(array $jobGuids, ?string $dateStart = null, ?string $dateEnd = null): string
    {
        foreach ($jobGuids as $guid) {
            self::validateGuid($guid);
        }

        $jobGuidsSql = WSHelpers::toSqlInClause($jobGuids);
        $parseAssddate = self::parseMsDateToDate('VU.ASSDDATE');

        $dateFilter = '';
        if ($dateStart !== null && $dateEnd !== null) {
            $dateFilter = "AND FU.completion_date BETWEEN '{$dateStart}' AND '{$dateEnd}'";
        }

        return "SELECT
    FU.JOBGUID,
    FU.completion_date,
    FU.FRSTR_USER,
    CAST(SUM(ISNULL(ST.SPANLGTH, 0)) / 1609.34 AS DECIMAL(10,4)) AS daily_footage_miles,
    STRING_AGG(CAST(FU.STATNAME AS VARCHAR(MAX)), ',') WITHIN GROUP (ORDER BY FU.STATNAME) AS station_list,
    SUM(CASE WHEN U.SUMMARYGRP IS NOT NULL AND U.SUMMARYGRP != '' AND U.SUMMARYGRP != 'Summary-NonWork' THEN 1 ELSE 0 END) AS unit_count
FROM (
    SELECT
        VU.JOBGUID,
        VU.STATNAME,
        {$parseAssddate} AS completion_date,
        VU.FRSTR_USER,
        VU.UNIT,
        ROW_NUMBER() OVER (
            PARTITION BY VU.JOBGUID, VU.STATNAME
            ORDER BY VU.ASSDDATE ASC
        ) AS unit_rank
    FROM VEGUNIT VU
    WHERE VU.UNIT IS NOT NULL
      AND VU.UNIT != ''
      AND VU.ASSDDATE IS NOT NULL
      AND VU.ASSDDATE != ''
      AND VU.JOBGUID IN ({$jobGuidsSql})
) FU
JOIN STATIONS ST
    ON ST.JOBGUID = FU.JOBGUID
    AND ST.STATNAME = FU.STATNAME
JOIN UNITS U
    ON U.UNIT = FU.UNIT
WHERE FU.unit_rank = 1
    {$dateFilter}
GROUP BY FU.JOBGUID, FU.completion_date, FU.FRSTR_USER
ORDER BY FU.JOBGUID, FU.completion_date";
    }

    /**
     * Assessment timeline from JOBHISTORY: pickup, QC, and close dates.
     *
     * Identical to CareerLedgerQueries::getAssessmentTimeline().
     */
    public function getAssessmentTimeline(string $jobGuid): string
    {
        self::validateGuid($jobGuid);

        return "SELECT
    JH.JOBGUID,
    JH.USERNAME,
    JH.ACTION,
    JH.LOGDATE,
    JH.OLDSTATUS,
    JH.JOBSTATUS,
    JH.ASSIGNEDTO
FROM JOBHISTORY JH
WHERE JH.JOBGUID = '{$jobGuid}'
ORDER BY JH.LOGDATE ASC";
    }

    /**
     * Work type breakdown from V_ASSESSMENT view.
     *
     * Identical to CareerLedgerQueries::getWorkTypeBreakdown().
     */
    public function getWorkTypeBreakdown(string $jobGuid): string
    {
        self::validateGuid($jobGuid);

        return "SELECT
    VA.unit,
    VA.UnitQty
FROM V_ASSESSMENT VA
WHERE VA.jobguid = '{$jobGuid}'
ORDER BY VA.unit";
    }

    /**
     * Rework details from VEGUNIT audit fields for a specific assessment.
     *
     * Identical to CareerLedgerQueries::getReworkDetails().
     */
    public function getReworkDetails(string $jobGuid): string
    {
        self::validateGuid($jobGuid);
        $validUnit = self::validUnitFilter('VU');

        return "SELECT
    VU.UNITGUID,
    VU.UNIT AS unit_type,
    VU.STATNAME,
    VU.FORESTER,
    VU.AUDIT_FAIL,
    VU.AUDIT_USER,
    VU.AUDITDATE,
    VU.AUDITNOTE
FROM VEGUNIT VU
WHERE VU.JOBGUID = '{$jobGuid}'
    AND {$validUnit}
    AND VU.AUDIT_FAIL IS NOT NULL
    AND VU.AUDIT_FAIL != ''
ORDER BY VU.AUDITDATE, VU.STATNAME";
    }
}

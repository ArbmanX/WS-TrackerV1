<?php

namespace App\Services\WorkStudio\DataCollection\Queries;

use App\Services\WorkStudio\Assessments\Queries\AbstractQueryBuilder;
use App\Services\WorkStudio\Shared\Helpers\WSHelpers;

class CareerLedgerQueries extends AbstractQueryBuilder
{
    /**
     * Daily footage attribution using First Unit Wins logic.
     *
     * Refactored from DailyFootageQuery::build(). Uses a derived table with
     * ROW_NUMBER() to identify the first VEGUNIT per station (by DATEPOP,
     * falling back to ASSDDATE). The completing unit's date determines which
     * day gets footage credit, and its FRSTR_USER determines who gets credit.
     *
     * Output columns: JOBGUID, completion_date, FRSTR_USER, daily_footage_meters,
     * station_list, unit_count (work units only).
     *
     * NOTE: DDOProtocol does NOT support CTEs — uses derived tables.
     * NOTE: DDOProtocol re-wraps dates in /Date(...)/ — parsing happens in PHP.
     */
    public function getDailyFootageAttribution(string $jobGuid, ?string $dateStart = null, ?string $dateEnd = null): string
    {
        self::validateGuid($jobGuid);

        $parseDatepop = "CAST(CAST(REPLACE(REPLACE(VU.DATEPOP, '/Date(', ''), ')/', '') AS DATETIME) AS DATE)";
        $parseAssddate = "CAST(CAST(REPLACE(REPLACE(VU.ASSDDATE, '/Date(', ''), ')/', '') AS DATETIME) AS DATE)";

        $dateFilter = '';
        if ($dateStart !== null && $dateEnd !== null) {
            $dateFilter = "AND FU.completion_date BETWEEN '{$dateStart}' AND '{$dateEnd}'";
        }

        return "SELECT
    FU.JOBGUID,
    FU.completion_date,
    FU.FRSTR_USER,
    SUM(ISNULL(ST.SPANLGTH, 0)) AS daily_footage_meters,
    STRING_AGG(CAST(FU.STATNAME AS VARCHAR(MAX)), ',') WITHIN GROUP (ORDER BY FU.STATNAME) AS station_list,
    SUM(CASE WHEN U.SUMMARYGRP IS NOT NULL AND U.SUMMARYGRP != '' AND U.SUMMARYGRP != 'Summary-NonWork' THEN 1 ELSE 0 END) AS unit_count
FROM (
    SELECT
        VU.JOBGUID,
        VU.STATNAME,
        COALESCE({$parseDatepop}, {$parseAssddate}) AS completion_date,
        VU.FRSTR_USER,
        VU.UNIT,
        ROW_NUMBER() OVER (
            PARTITION BY VU.JOBGUID, VU.STATNAME
            ORDER BY COALESCE(VU.DATEPOP, VU.ASSDDATE) ASC
        ) AS unit_rank
    FROM VEGUNIT VU
    WHERE VU.UNIT IS NOT NULL
      AND VU.UNIT != ''
      AND (VU.DATEPOP IS NOT NULL OR VU.ASSDDATE IS NOT NULL)
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
     * Used by ws:export-career-ledger when processing all CLOSE assessments.
     *
     * @param  array<int, string>  $jobGuids
     */
    public function getDailyFootageAttributionBatch(array $jobGuids, ?string $dateStart = null, ?string $dateEnd = null): string
    {
        foreach ($jobGuids as $guid) {
            self::validateGuid($guid);
        }

        $jobGuidsSql = WSHelpers::toSqlInClause($jobGuids);

        $parseDatepop = "CAST(CAST(REPLACE(REPLACE(VU.DATEPOP, '/Date(', ''), ')/', '') AS DATETIME) AS DATE)";
        $parseAssddate = "CAST(CAST(REPLACE(REPLACE(VU.ASSDDATE, '/Date(', ''), ')/', '') AS DATETIME) AS DATE)";

        $dateFilter = '';
        if ($dateStart !== null && $dateEnd !== null) {
            $dateFilter = "AND FU.completion_date BETWEEN '{$dateStart}' AND '{$dateEnd}'";
        }

        return "SELECT
    FU.JOBGUID,
    FU.completion_date,
    FU.FRSTR_USER,
    SUM(ISNULL(ST.SPANLGTH, 0)) AS daily_footage_meters,
    STRING_AGG(CAST(FU.STATNAME AS VARCHAR(MAX)), ',') WITHIN GROUP (ORDER BY FU.STATNAME) AS station_list,
    SUM(CASE WHEN U.SUMMARYGRP IS NOT NULL AND U.SUMMARYGRP != '' AND U.SUMMARYGRP != 'Summary-NonWork' THEN 1 ELSE 0 END) AS unit_count
FROM (
    SELECT
        VU.JOBGUID,
        VU.STATNAME,
        COALESCE({$parseDatepop}, {$parseAssddate}) AS completion_date,
        VU.FRSTR_USER,
        VU.UNIT,
        ROW_NUMBER() OVER (
            PARTITION BY VU.JOBGUID, VU.STATNAME
            ORDER BY COALESCE(VU.DATEPOP, VU.ASSDDATE) ASC
        ) AS unit_rank
    FROM VEGUNIT VU
    WHERE VU.UNIT IS NOT NULL
      AND VU.UNIT != ''
      AND (VU.DATEPOP IS NOT NULL OR VU.ASSDDATE IS NOT NULL)
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
     * Rework details from VEGUNIT audit fields for a specific assessment.
     *
     * Returns audit failure information: which units failed QC, who audited,
     * when, and audit notes. Used to populate rework_details JSONB on
     * PlannerCareerEntry when went_to_rework = true.
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

    /**
     * Assessment timeline from JOBHISTORY: pickup, QC, and close dates.
     *
     * Extracts key lifecycle dates from the assessment's history log.
     * Used to populate assessment_pickup_date, assessment_qc_date,
     * and assessment_close_date on PlannerCareerEntry.
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
     * V_ASSESSMENT pre-aggregates unit quantities per assessment per unit type.
     * Much cheaper than aggregating raw VEGUNIT records. Used for summary_totals
     * JSONB on PlannerCareerEntry and work_type_breakdown on AssessmentMonitor.
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
}

<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;

class DailyFootageQuery
{
    /**
     * Build the T-SQL query for daily footage by station completion.
     *
     * Uses a derived table with ROW_NUMBER() to identify the first VEGUNIT
     * per station (by DATEPOP, falling back to ASSDDATE). The completing unit's
     * date determines which day gets the footage credit, and its FRSTR_USER
     * determines who gets credit.
     *
     * Output columns: JOBGUID, completion_date (/Date(...)/ wrapper — parsed in PHP),
     * FRSTR_USER, daily_footage_meters, station_list (comma-separated station names),
     * unit_count (count of working units — excludes non-work SUMMARYGRP types).
     *
     * NOTE: The DDOProtocol API does not support CTEs (WITH...AS).
     * All queries must use derived tables (subqueries) instead.
     * NOTE: DDOProtocol re-wraps date columns in /Date(...)/ JSON format
     * regardless of SQL CONVERT — all date parsing happens in PHP.
     *
     * @param  array<int, string>  $jobGuids  List of JOBGUID values to query
     * @param  string|null  $dateStart  Start date filter (YYYY-MM-DD) for completion_date
     * @param  string|null  $dateEnd  End date filter (YYYY-MM-DD) for completion_date
     */
    public static function build(array $jobGuids, ?string $dateStart = null, ?string $dateEnd = null): string
    {
        $jobGuidsSql = WSHelpers::toSqlInClause($jobGuids);

        // Both dates stored as '/Date(2026-01-05T15:12:33.803Z)/' — strip wrapper before casting
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
}

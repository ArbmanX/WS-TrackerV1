<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;

class DailyFootageQuery
{
    /**
     * Build the T-SQL query for daily footage by station completion.
     *
     * Uses a derived table with ROW_NUMBER() to identify the first VEGUNIT
     * per station (by DATEPOP). The completing unit's date determines which
     * day gets the footage credit, and its FRSTR_USER determines who gets credit.
     *
     * Output columns: JOBGUID, completion_date (MM-DD-YYYY), FRSTR_USER,
     * daily_footage_meters, station_list (comma-separated station names).
     *
     * NOTE: The DDOProtocol API does not support CTEs (WITH...AS).
     * All queries must use derived tables (subqueries) instead.
     *
     * @param  array<int, string>  $jobGuids  List of JOBGUID values to query
     */
    public static function build(array $jobGuids): string
    {
        $jobGuidsSql = WSHelpers::toSqlInClause($jobGuids);

        // DATEPOP is stored as '/Date(2026-01-05T15:12:33.803Z)/' — must strip wrapper before casting
        $parseDate = "CAST(CAST(REPLACE(REPLACE(VU.DATEPOP, '/Date(', ''), ')/', '') AS DATETIME) AS DATE)";

        return "SELECT
    FU.JOBGUID,
    CONVERT(VARCHAR(10), FU.datepop, 110) AS completion_date,
    FU.FRSTR_USER,
    SUM(ISNULL(ST.SPANLGTH, 0)) AS daily_footage_meters,
    STRING_AGG(FU.STATNAME, ',') WITHIN GROUP (ORDER BY FU.STATNAME) AS station_list
FROM (
    SELECT
        VU.JOBGUID,
        VU.STATNAME,
        {$parseDate} AS datepop,
        VU.FRSTR_USER,
        VU.UNIT,
        ROW_NUMBER() OVER (
            PARTITION BY VU.JOBGUID, VU.STATNAME
            ORDER BY VU.DATEPOP ASC
        ) AS unit_rank
    FROM VEGUNIT VU
    WHERE VU.UNIT IS NOT NULL
      AND VU.UNIT != ''
      AND VU.JOBGUID IN ({$jobGuidsSql})
) FU
JOIN STATIONS ST
    ON ST.JOBGUID = FU.JOBGUID
    AND ST.STATNAME = FU.STATNAME
WHERE FU.unit_rank = 1
GROUP BY FU.JOBGUID, CONVERT(VARCHAR(10), FU.datepop, 110), FU.FRSTR_USER
ORDER BY FU.JOBGUID, completion_date";
    }
}

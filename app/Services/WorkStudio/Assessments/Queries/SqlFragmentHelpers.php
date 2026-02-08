<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSSQLCaster;

trait SqlFragmentHelpers
{
    // =========================================================================
    // SQL Fragment Helpers
    // =========================================================================

    /**
     * Parse Microsoft JSON date format to SQL DATE.
     * Converts '/Date(1234567890000)/' to a proper DATE.
     */
    private static function parseMsDateToDate(string $column): string
    {
        return "CAST(CAST(REPLACE(REPLACE({$column}, '/Date(', ''), ')/', '') AS DATETIME) AS DATE)";
    }

    /**
     * Extract YEAR from date format: /Date(Jan 1, 2026)/ or similar
     * Used for Scope_Year calculation.
     */
    private static function extractYearFromMsDate(string $column): string
    {
        return "CASE
            WHEN {$column} IS NULL OR {$column} = '' THEN NULL
            ELSE YEAR(CAST(REPLACE(REPLACE({$column}, '/Date(', ''), ')/', '') AS DATE))
        END";
    }

    /**
     * Filter condition for valid units (excludes NW, empty, and null).
     */
    private static function validUnitFilter(string $tableAlias = 'VEGUNIT'): string
    {
        return "{$tableAlias}.UNIT != 'NW' AND {$tableAlias}.UNIT != '' AND {$tableAlias}.UNIT IS NOT NULL";
    }

    /**
     * Filter condition using NOT IN syntax.
     */
    private static function validUnitFilterNotIn(string $tableAlias = 'V'): string
    {
        return "{$tableAlias}.UNIT NOT IN ('NW', '') AND {$tableAlias}.UNIT IS NOT NULL";
    }

    /**
     * Get the first forester for a circuit.
     */
    private static function foresterSubquery(string $jobGuidRef = 'SS.JOBGUID'): string
    {
        return "(SELECT TOP 1 VEGUNIT.FORESTER
            FROM VEGUNIT
            WHERE VEGUNIT.JOBGUID = {$jobGuidRef}
              AND VEGUNIT.FORESTER IS NOT NULL
              AND VEGUNIT.FORESTER != '')";
    }

    /**
     * Get total footage for a circuit (sum of all station span lengths).
     */
    private static function totalFootageSubquery(string $jobGuidRef = 'SS.JOBGUID'): string
    {
        return "(SELECT CAST(SUM(SPANLGTH) AS DECIMAL(10,2)) FROM STATIONS WHERE STATIONS.JOBGUID = {$jobGuidRef})";
    }

    /**
     * Format OLE Automation datetime to Eastern time with readable format.
     */
    private static function formatToEasternTime(string $column): string
    {
        return WSSQLCaster::cast($column, 'MM/dd/yyyy h:mm tt');
    }

    /**
     * Build a unit count subquery for a specific permission status.
     */
    private static function unitCountSubquery(string $jobGuidRef, ?string $permStatus = null, bool $requireAssessedDate = false): string
    {
        $validUnit = self::validUnitFilter();

        $conditions = ["VEGUNIT.JOBGUID = {$jobGuidRef}", $validUnit];

        if ($requireAssessedDate) {
            $conditions[] = "VEGUNIT.ASSDDATE IS NOT NULL AND VEGUNIT.ASSDDATE != ''";
        }

        if ($permStatus === 'Pending') {
            $conditions[] = "(VEGUNIT.PERMSTAT = 'Pending' OR VEGUNIT.PERMSTAT = '' OR VEGUNIT.PERMSTAT IS NULL)";
        } elseif ($permStatus !== null) {
            $conditions[] = "VEGUNIT.PERMSTAT = '{$permStatus}'";
        }

        $where = implode("\n                AND ", $conditions);

        return "(SELECT COUNT(*) FROM VEGUNIT WHERE {$where})";
    }

    /**
     * Build the CROSS APPLY for unit counts (more efficient for list queries).
     */
    private static function unitCountsCrossApply(string $jobGuidRef = 'SS.JOBGUID'): string
    {
        $validUnit = self::validUnitFilter();

        return "CROSS APPLY (
            SELECT
                COUNT(CASE WHEN VEGUNIT.ASSDDATE IS NOT NULL AND VEGUNIT.ASSDDATE != ''
                        AND {$validUnit}
                    THEN 1 END) AS Total_Units_Planned,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Approved'
                        AND {$validUnit}
                    THEN 1 END) AS Total_Approvals,
                COUNT(CASE WHEN (VEGUNIT.PERMSTAT = '' OR VEGUNIT.PERMSTAT IS NULL)
                        AND {$validUnit}
                    THEN 1 END) AS Total_Pending,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'No Contact'
                        AND {$validUnit}
                    THEN 1 END) AS Total_No_Contacts,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Refusal'
                        AND {$validUnit}
                    THEN 1 END) AS Total_Refusals,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Deferred'
                        AND {$validUnit}
                    THEN 1 END) AS Total_Deferred,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'PPL Approved'
                        AND {$validUnit}
                    THEN 1 END) AS Total_PPL_Approved
            FROM VEGUNIT
            WHERE VEGUNIT.JOBGUID = {$jobGuidRef}
        ) AS UnitCounts";
    }

    /**
     * Build the Daily Records subquery/OUTER APPLY.
     * Groups by assessment date with distinct station footage calculation.
     *
     * Each station's SPANLGTH is only counted ONCE - on its first assessment date.
     * Uses ROW_NUMBER() to identify each station's first appearance.
     */
    private static function dailyRecordsQuery(string $jobGuidRef = 'WSREQSS.JOBGUID', bool $asOuterApply = true): string
    {
        $parseDateV = self::parseMsDateToDate('V.ASSDDATE');
        $parseDateV2 = self::parseMsDateToDate('V2.ASSDDATE');
        $validUnitV2 = self::validUnitFilterNotIn('V2');

        // Build the inner SELECT for daily records
        // Uses nested derived tables to:
        // 1. Get distinct (STATNAME, SPANLGTH, Date) combinations
        // 2. Assign ROW_NUMBER per station ordered by date
        // 3. Only sum where rn=1 (first occurrence of each station)
        $innerSelect = "SELECT
                    StationFirstDate.Assessed_Date,
                    CAST(SUM(StationFirstDate.SPANLGTH) / 1609.34 AS DECIMAL(10,4)) AS Total_Day_Miles,
                    (
                        SELECT COUNT(*)
                        FROM VEGUNIT V3
                        WHERE V3.JOBGUID = {$jobGuidRef}
                            AND V3.UNIT IS NOT NULL AND V3.UNIT != '' AND V3.UNIT != 'NW'
                            AND V3.ASSDDATE IS NOT NULL AND V3.ASSDDATE != ''
                            AND ".self::parseMsDateToDate('V3.ASSDDATE')." = StationFirstDate.Assessed_Date
                    ) AS Total_Unit_Count,
                    (
                        SELECT STRING_AGG(UNIT, ', ')
                        FROM (
                            SELECT DISTINCT V2.UNIT
                            FROM VEGUNIT V2
                            WHERE V2.JOBGUID = {$jobGuidRef}
                                AND {$validUnitV2}
                                AND {$parseDateV2} = StationFirstDate.Assessed_Date
                        ) AS UniqueUnits
                    ) AS Unit_List
                FROM (
                    SELECT
                        STATNAME,
                        SPANLGTH,
                        Assessed_Date,
                        ROW_NUMBER() OVER (PARTITION BY STATNAME ORDER BY Assessed_Date ASC) AS rn
                    FROM (
                        SELECT DISTINCT
                            S.STATNAME,
                            S.SPANLGTH,
                            {$parseDateV} AS Assessed_Date
                        FROM STATIONS S
                        INNER JOIN VEGUNIT V ON S.WO = V.WO AND S.STATNAME = V.STATNAME
                        WHERE S.JOBGUID = {$jobGuidRef}
                            AND S.SPANLGTH IS NOT NULL
                            AND S.SPANLGTH != ''
                            AND V.UNIT IS NOT NULL
                            AND V.UNIT != ''
                            AND V.ASSDDATE IS NOT NULL
                            AND V.ASSDDATE != ''
                    ) AS DistinctStationDates
                ) AS StationFirstDate
                WHERE StationFirstDate.rn = 1
                GROUP BY StationFirstDate.Assessed_Date
                FOR JSON PATH";

        if ($asOuterApply) {
            return "OUTER APPLY (
            SELECT ({$innerSelect}) AS Daily_Records
        ) AS DailyData";
        }

        return "({$innerSelect})";
    }

    /**
     * Build the Stations with nested Units subquery.
     */
    private static function stationsWithUnitsQuery(string $jobGuidRef = 'SS.JOBGUID'): string
    {
        $stationFields = SqlFieldBuilder::select('Stations', 'S');
        $vegunitFields = SqlFieldBuilder::select('VEGUNIT', 'U');

        return "(
            SELECT
                {$stationFields},
                (
                    SELECT {$vegunitFields}
                    FROM VEGUNIT U
                    WHERE U.WO = S.WO
                        AND U.STATNAME = S.STATNAME
                        AND U.UNIT IS NOT NULL
                        AND U.UNIT != ''
                        AND U.UNIT != 'NW'
                    FOR JSON PATH
                ) AS Units
            FROM STATIONS S
            WHERE S.JOBGUID = {$jobGuidRef}
            AND S.STATNAME NOT LIKE '%EX%'
            FOR JSON PATH
        )";
    }
}

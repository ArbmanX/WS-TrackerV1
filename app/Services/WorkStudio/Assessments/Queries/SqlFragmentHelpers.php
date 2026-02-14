<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;
use App\Services\WorkStudio\Shared\Helpers\WSSQLCaster;

trait SqlFragmentHelpers
{
    // =========================================================================
    // Shared Query Building Blocks (context-aware, non-static)
    // =========================================================================

    /**
     * Standard 3-table FROM clause used by all assessment queries.
     * Uses INNER JOIN for xrefs since WHERE filters on WP_STARTDATE
     * already force INNER behavior (BUG-002 resolution).
     */
    protected function baseFromClause(): string
    {
        return 'FROM SS
                INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                INNER JOIN WPStartDate_Assessment_Xrefs
                    ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID';
    }

    /**
     * Standard WHERE clause for assessment queries.
     *
     * @param  array{
     *     statusSql?: string,
     *     includeExcludedUsers?: bool,
     *     cycleTypeSql?: string,
     * }  $overrides  Per-query variations
     */
    protected function baseWhereClause(array $overrides = []): string
    {
        $statusSql = $overrides['statusSql'] ?? "('ACTIV', 'QC', 'REWRK', 'CLOSE')";
        $cycleTypeSql = $overrides['cycleTypeSql'] ?? $this->cycleTypesSql;
        $includeExcludedUsers = $overrides['includeExcludedUsers'] ?? true;

        $clauses = [
            "VEGJOB.REGION IN ({$this->resourceGroupsSql})",
            "WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'",
            "SS.STATUS IN {$statusSql}",
            "VEGJOB.CONTRACTOR IN ({$this->contractorsSql})",
            "SS.JOBTYPE IN ({$this->jobTypesSql})",
            "VEGJOB.CYCLETYPE IN ({$cycleTypeSql})",
        ];

        if ($includeExcludedUsers) {
            $clauses[] = "SS.TAKENBY NOT IN ({$this->excludedUsersSql})";
        }

        return implode("\n                    AND ", $clauses);
    }

    /**
     * CROSS APPLY for permission counts from VEGUNIT.
     * Uses config-driven PERMSTAT values. Includes valid unit filter in WHERE.
     */
    protected static function permissionCountsCrossApply(string $jobGuidRef = 'SS.JOBGUID'): string
    {
        $approved = config('ws_assessment_query.permission_statuses.approved');
        $pending = config('ws_assessment_query.permission_statuses.pending');
        $noContact = config('ws_assessment_query.permission_statuses.no_contact');
        $refused = config('ws_assessment_query.permission_statuses.refused');
        $deferred = config('ws_assessment_query.permission_statuses.deferred');
        $pplApproved = config('ws_assessment_query.permission_statuses.ppl_approved');
        $validUnit = self::validUnitFilter();

        return "CROSS APPLY (
                    SELECT
                        COUNT(*) AS Total_Units,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$approved}' THEN 1 END) AS Approved_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$pending}' OR VEGUNIT.PERMSTAT IS NULL OR VEGUNIT.PERMSTAT = '' THEN 1 END) AS Pending_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$noContact}' THEN 1 END) AS No_Contact_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$refused}' THEN 1 END) AS Refusal_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$deferred}' THEN 1 END) AS Deferred_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$pplApproved}' THEN 1 END) AS PPL_Approved_Count
                    FROM VEGUNIT
                    WHERE VEGUNIT.JOBGUID = {$jobGuidRef}
                    AND {$validUnit}
                ) AS UnitData";
    }

    /**
     * Extended CROSS APPLY for permission counts with assessed dates.
     * Used by circuit-detail views that need First/Last Assessed Date.
     */
    protected static function permissionCountsWithDatesCrossApply(string $jobGuidRef = 'SS.JOBGUID'): string
    {
        $approved = config('ws_assessment_query.permission_statuses.approved');
        $pending = config('ws_assessment_query.permission_statuses.pending');
        $noContact = config('ws_assessment_query.permission_statuses.no_contact');
        $refused = config('ws_assessment_query.permission_statuses.refused');
        $deferred = config('ws_assessment_query.permission_statuses.deferred');
        $pplApproved = config('ws_assessment_query.permission_statuses.ppl_approved');
        $validUnit = self::validUnitFilter();

        return "CROSS APPLY (
                    SELECT
                        MIN(VEGUNIT.ASSDDATE) AS First_Assessed_Date,
                        MAX(VEGUNIT.ASSDDATE) AS Last_Assessed_Date,
                        COUNT(*) AS Total_Units,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$approved}' THEN 1 END) AS Approved_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$pending}' OR VEGUNIT.PERMSTAT IS NULL OR VEGUNIT.PERMSTAT = '' THEN 1 END) AS Pending_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$noContact}' THEN 1 END) AS No_Contact_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$refused}' THEN 1 END) AS Refusal_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$deferred}' THEN 1 END) AS Deferred_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$pplApproved}' THEN 1 END) AS PPL_Approved_Count
                    FROM VEGUNIT
                    WHERE VEGUNIT.JOBGUID = {$jobGuidRef}
                    AND {$validUnit}
                ) AS UnitData";
    }

    /**
     * CROSS APPLY for work measurements from JOBVEGETATIONUNITS.
     * Uses config-driven unit code groups.
     */
    protected static function workMeasurementsCrossApply(string $jobGuidRef = 'SS.JOBGUID'): string
    {
        $rem612 = WSHelpers::toSqlInClause(config('ws_assessment_query.unit_groups.removal_6_12'));
        $remOver12 = WSHelpers::toSqlInClause(config('ws_assessment_query.unit_groups.removal_over_12'));
        $ash = WSHelpers::toSqlInClause(config('ws_assessment_query.unit_groups.ash_removal'));
        $vps = WSHelpers::toSqlInClause(config('ws_assessment_query.unit_groups.vps'));
        $brush = WSHelpers::toSqlInClause(config('ws_assessment_query.unit_groups.brush'));
        $herbicide = WSHelpers::toSqlInClause(config('ws_assessment_query.unit_groups.herbicide'));
        $bucketTrim = WSHelpers::toSqlInClause(config('ws_assessment_query.unit_groups.bucket_trim'));
        $manualTrim = WSHelpers::toSqlInClause(config('ws_assessment_query.unit_groups.manual_trim'));

        return "CROSS APPLY (
                    SELECT
                        COUNT(CASE WHEN UNIT IN ({$rem612}) THEN 1 END) AS Rem_6_12_Count,
                        COUNT(CASE WHEN UNIT IN ({$remOver12}) THEN 1 END) AS Rem_Over_12_Count,
                        COUNT(CASE WHEN UNIT IN ({$ash}) THEN 1 END) AS Ash_Removal_Count,
                        COUNT(CASE WHEN UNIT IN ({$vps}) THEN 1 END) AS VPS_Count,
                        SUM(CASE WHEN UNIT IN ({$brush}) THEN ACRES ELSE 0 END) AS Brush_Acres,
                        SUM(CASE WHEN UNIT IN ({$herbicide}) THEN ACRES ELSE 0 END) AS Herbicide_Acres,
                        SUM(CASE WHEN UNIT IN ({$bucketTrim}) THEN LENGTHWRK ELSE 0 END) AS Bucket_Trim_Length,
                        SUM(CASE WHEN UNIT IN ({$manualTrim}) THEN LENGTHWRK ELSE 0 END) AS Manual_Trim_Length
                    FROM JOBVEGETATIONUNITS
                    WHERE JOBVEGETATIONUNITS.JOBGUID = {$jobGuidRef}
                ) AS WorkData";
    }

    // =========================================================================
    // Validation Helpers
    // =========================================================================

    /**
     * Validate a GUID string format before SQL interpolation.
     * Accepts both braced {GUID} and unbraced GUID formats.
     *
     * @throws \InvalidArgumentException If the GUID format is invalid
     */
    protected static function validateGuid(string $guid, string $paramName = 'JOBGUID'): void
    {
        if (! preg_match('/^\{?[0-9a-fA-F]{8}-([0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}\}?$/', $guid)) {
            throw new \InvalidArgumentException("Invalid {$paramName} format.");
        }
    }

    // =========================================================================
    // SQL Fragment Helpers (static utilities)
    // =========================================================================

    /**
     * Parse Microsoft JSON date format to SQL DATE.
     * Converts '/Date(1234567890000)/' to a proper DATE.
     */
    protected static function parseMsDateToDate(string $column): string
    {
        return "CAST(CAST(REPLACE(REPLACE({$column}, '/Date(', ''), ')/', '') AS DATETIME) AS DATE)";
    }

    /**
     * Extract YEAR from date format: /Date(Jan 1, 2026)/ or similar
     * Used for Scope_Year calculation.
     */
    protected static function extractYearFromMsDate(string $column): string
    {
        return "CASE
            WHEN {$column} IS NULL OR {$column} = '' THEN NULL
            ELSE YEAR(CAST(REPLACE(REPLACE({$column}, '/Date(', ''), ')/', '') AS DATE))
        END";
    }

    /**
     * Filter condition for valid units (excludes NW, empty, and null).
     */
    protected static function validUnitFilter(string $tableAlias = 'VEGUNIT'): string
    {
        return "{$tableAlias}.UNIT != 'NW' AND {$tableAlias}.UNIT != '' AND {$tableAlias}.UNIT IS NOT NULL";
    }

    /**
     * Filter condition using NOT IN syntax.
     */
    protected static function validUnitFilterNotIn(string $tableAlias = 'V'): string
    {
        return "{$tableAlias}.UNIT NOT IN ('NW', '') AND {$tableAlias}.UNIT IS NOT NULL";
    }

    /**
     * Get the first forester for a circuit.
     */
    protected static function foresterSubquery(string $jobGuidRef = 'SS.JOBGUID'): string
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
    protected static function totalFootageSubquery(string $jobGuidRef = 'SS.JOBGUID'): string
    {
        return "(SELECT CAST(SUM(SPANLGTH) AS DECIMAL(10,2)) FROM STATIONS WHERE STATIONS.JOBGUID = {$jobGuidRef})";
    }

    /**
     * Format OLE Automation datetime to Eastern time with readable format.
     */
    protected static function formatToEasternTime(string $column): string
    {
        return WSSQLCaster::cast($column, 'MM/dd/yyyy h:mm tt');
    }

    /**
     * Build a unit count subquery for a specific permission status.
     */
    protected static function unitCountSubquery(string $jobGuidRef, ?string $permStatus = null, bool $requireAssessedDate = false): string
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
     * Includes ASSDDATE filter for Total_Units_Planned. Uses config PERMSTAT values.
     */
    protected static function unitCountsCrossApply(string $jobGuidRef = 'SS.JOBGUID'): string
    {
        $validUnit = self::validUnitFilter();
        $approved = config('ws_assessment_query.permission_statuses.approved');
        $noContact = config('ws_assessment_query.permission_statuses.no_contact');
        $refused = config('ws_assessment_query.permission_statuses.refused');
        $deferred = config('ws_assessment_query.permission_statuses.deferred');
        $pplApproved = config('ws_assessment_query.permission_statuses.ppl_approved');

        return "CROSS APPLY (
            SELECT
                COUNT(CASE WHEN VEGUNIT.ASSDDATE IS NOT NULL AND VEGUNIT.ASSDDATE != ''
                        AND {$validUnit}
                    THEN 1 END) AS Total_Units_Planned,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$approved}'
                        AND {$validUnit}
                    THEN 1 END) AS Total_Approvals,
                COUNT(CASE WHEN (VEGUNIT.PERMSTAT = '' OR VEGUNIT.PERMSTAT IS NULL)
                        AND {$validUnit}
                    THEN 1 END) AS Total_Pending,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$noContact}'
                        AND {$validUnit}
                    THEN 1 END) AS Total_No_Contacts,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$refused}'
                        AND {$validUnit}
                    THEN 1 END) AS Total_Refusals,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$deferred}'
                        AND {$validUnit}
                    THEN 1 END) AS Total_Deferred,
                COUNT(CASE WHEN VEGUNIT.PERMSTAT = '{$pplApproved}'
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
    protected static function dailyRecordsQuery(string $jobGuidRef = 'WSREQSS.JOBGUID', bool $asOuterApply = true): string
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
    protected static function stationsWithUnitsQuery(string $jobGuidRef = 'SS.JOBGUID'): string
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

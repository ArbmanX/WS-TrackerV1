<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;

class AssessmentQueries
{
    use SqlFragmentHelpers;

    private string $resourceGroupsSql;

    private string $contractorsSql;

    private string $excludedUsersSql;

    private string $jobTypesSql;

    private string $cycleTypesSql;

    private string $scopeYear;

    private string $domainFilter;

    private string $excludedCycleTypesSql;

    /**
     * @param  UserQueryContext  $context  User-specific query parameters
     */
    public function __construct(private readonly UserQueryContext $context)
    {
        $this->resourceGroupsSql = WSHelpers::toSqlInClause($context->resourceGroups);
        $this->contractorsSql = WSHelpers::toSqlInClause($context->contractors);
        $this->domainFilter = $context->domain;

        // System-level values stay in config
        $this->excludedUsersSql = WSHelpers::toSqlInClause(config('ws_assessment_query.excludedUsers'));
        $this->jobTypesSql = WSHelpers::toSqlInClause(config('ws_assessment_query.job_types.assessments'));
        $this->cycleTypesSql = WSHelpers::toSqlInClause(config('ws_assessment_query.cycle_types.maintenance'));
        $this->excludedCycleTypesSql = WSHelpers::toSqlInClause(config('ws_assessment_query.cycle_types.excluded_from_assessments'));
        $this->scopeYear = config('ws_assessment_query.scope_year');
    }

    /* =========================================================================
    * System Wide Data - This is broad counts and totals of data
    * =========================================================================
    */
    public function systemWideDataQuery(): string
    {
        $from = $this->baseFromClause();
        $where = $this->baseWhereClause();

        return "SELECT
                (SELECT TOP 1 CONTRACTOR FROM VEGJOB WHERE VEGJOB.CONTRACTOR IN ({$this->contractorsSql})) AS contractor,
                        -- Circuit Counts
                        COUNT(*) AS total_assessments,
                        SUM(CASE WHEN SS.STATUS = 'ACTIV' THEN 1 ELSE 0 END) AS active_count,
                        SUM(CASE WHEN SS.STATUS = 'QC' THEN 1 ELSE 0 END) AS qc_count,
                        SUM(CASE WHEN SS.STATUS = 'REWRK' THEN 1 ELSE 0 END) AS rework_count,
                        SUM(CASE WHEN SS.STATUS = 'CLOSE' THEN 1 ELSE 0 END) AS closed_count,

                        -- Miles
                        CAST(SUM(VEGJOB.LENGTH) AS DECIMAL(10,2)) AS total_miles,
                        CAST(SUM(VEGJOB.LENGTHCOMP) AS DECIMAL(10,2)) AS completed_miles,

                        -- Active Planners (unique TAKENBY matching domain, ACTIV only)
                        COUNT(DISTINCT CASE WHEN SS.STATUS = 'ACTIV'
                            AND UPPER(LEFT(SS.TAKENBY, CHARINDEX('\\', SS.TAKENBY + '\\') - 1)) = '{$this->domainFilter}'
                            THEN SS.TAKENBY END) AS active_planners

                    {$from}

                    WHERE {$where}";
    }
    /* =========================================================================
    * END
    * =========================================================================
    */

    /* =========================================================================
    * Regional Data - This is regional based data
        *    - same data as System Wide Data but per region DONE
        *    - total unit count
        *    - total units pending
        *    - total units approved
        *    - total units no contact
        *    - total units refused
        *    - total units defered
        *    - total units ppl approved
        *    - total removals = 6-12 | count
        *    - total removal greater then 6-12 | count
        *    - total vps | count
        *    - total hand cut brush | acres
        *    - total herbicide | acres
        *    - total manaual trimmimg  | linear feet
        *    - total bucket trimmimg | linear feet
    * =========================================================================
    */
    public function groupedByRegionDataQuery(): string
    {
        $from = $this->baseFromClause();
        $unitData = self::permissionCountsCrossApply();
        $workData = self::workMeasurementsCrossApply();
        $where = $this->baseWhereClause();

        return "SELECT
                    -- Region Identifier
                    VEGJOB.REGION AS Region,

                    -- Circuit Counts by Status
                    COUNT(*) AS Total_Circuits,
                    SUM(CASE WHEN SS.STATUS = 'ACTIV' THEN 1 ELSE 0 END) AS Active_Count,
                    SUM(CASE WHEN SS.STATUS = 'QC' THEN 1 ELSE 0 END) AS QC_Count,
                    SUM(CASE WHEN SS.STATUS = 'REWRK' THEN 1 ELSE 0 END) AS Rework_Count,
                    SUM(CASE WHEN SS.STATUS = 'CLOSE' THEN 1 ELSE 0 END) AS Closed_Count,

                    -- Miles
                    CAST(SUM(VEGJOB.LENGTH) AS DECIMAL(10,2)) AS Total_Miles,
                    CAST(SUM(VEGJOB.LENGTHCOMP) AS DECIMAL(10,2)) AS Completed_Miles,

                    -- Active Planners (unique TAKENBY matching domain, ACTIV only)
                    COUNT(DISTINCT CASE WHEN SS.STATUS = 'ACTIV'
                        AND UPPER(LEFT(SS.TAKENBY, CHARINDEX('\\', SS.TAKENBY + '\\') - 1)) = '{$this->domainFilter}'
                        THEN SS.TAKENBY END) AS Active_Planners,

                    -- Permission Counts (aggregated from CROSS APPLY)
                    SUM(UnitData.Total_Units) AS Total_Units,
                    SUM(UnitData.Approved_Count) AS Approved_Count,
                    SUM(UnitData.Pending_Count) AS Pending_Count,
                    SUM(UnitData.No_Contact_Count) AS No_Contact_Count,
                    SUM(UnitData.Refusal_Count) AS Refusal_Count,
                    SUM(UnitData.Deferred_Count) AS Deferred_Count,
                    SUM(UnitData.PPL_Approved_Count) AS PPL_Approved_Count,

                    -- Work Measurements (aggregated from CROSS APPLY)
                    SUM(WorkData.Rem_6_12_Count) AS Rem_6_12_Count,
                    SUM(WorkData.Rem_Over_12_Count) AS Rem_Over_12_Count,
                    SUM(WorkData.Ash_Removal_Count) AS Ash_Removal_Count,
                    SUM(WorkData.VPS_Count) AS VPS_Count,
                    CAST(SUM(WorkData.Brush_Acres) AS DECIMAL(10,2)) AS Brush_Acres,
                    CAST(SUM(WorkData.Herbicide_Acres) AS DECIMAL(10,2)) AS Herbicide_Acres,
                    CAST(SUM(WorkData.Bucket_Trim_Length) AS DECIMAL(10,2)) AS Bucket_Trim_Length,
                    CAST(SUM(WorkData.Manual_Trim_Length) AS DECIMAL(10,2)) AS Manual_Trim_Length

                {$from}

                {$unitData}

                {$workData}

                WHERE {$where}

                GROUP BY VEGJOB.REGION
                ORDER BY VEGJOB.REGION";
    }
    /* =========================================================================
    * END
    * =========================================================================
    */

    /* =========================================================================
    * Circuit Data - All data should be grouped by the JOBGUID
        *    - Planner / Planners, while going thru the units we must check the VEGUNIT.FORESTER field to determine all the planners because multipule planners can work on the same assessment and the VEGUNIT stores the name of the person who physically planned that specific unit
        *    - date of first assessed unit
        *    - date of last unit assessed
        *    - date of last Sync Date
        *    - same data as System Wide Data but per circuit
        *    - total unit count
        *    - total units pending
        *    - total units approved
        *    - total units no contact
        *    - total units refused
        *    - total units defered
        *    - total units ppl approved
        *    - total count of occurance of unit | REM612, REM1218, REM1824, REM2430, REM3036, ASH612, ASH1218, ASH1824, ASH2430, ASH3036, VPS
        *    - total acres of units | BRUSH, HCB (Hand Cut Brush), HERBA (Herbicide Aquatic), HERBNA (Herbicide Non-aqutic), BRUSHTRIM (Hand Cut Brush w/ Trim)
        *    - total length of units | 'SPB', 'MPB', 'SPM', 'MPM'
    * =========================================================================
    */
    public function groupedByCircuitDataQuery(): string
    {
        $lastSync = self::formatToEasternTime('SS.EDITDATE');
        $from = $this->baseFromClause();
        $unitData = self::permissionCountsWithDatesCrossApply();
        $workData = self::workMeasurementsCrossApply();
        $where = $this->baseWhereClause();

        return "SELECT
                    -- Circuit Identifiers
                    SS.JOBGUID AS Job_GUID,
                    SS.WO AS Work_Order,
                    SS.EXT AS Extension,
                    SS.STATUS AS Status,
                    VEGJOB.LINENAME AS Line_Name,
                    VEGJOB.REGION AS Region,
                    VEGJOB.CYCLETYPE AS Cycle_Type,
                    CAST(VEGJOB.LENGTH AS DECIMAL(10,2)) AS Total_Miles,
                    CAST(VEGJOB.LENGTHCOMP AS DECIMAL(10,2)) AS Completed_Miles,
                    VEGJOB.PRCENT AS Percent_Complete,
                    {$lastSync} AS Last_Sync,

                    -- Planners (distinct foresters via subquery)
                    (SELECT STRING_AGG(DF.FORESTER, ', ')
                    FROM (SELECT DISTINCT VEGUNIT.FORESTER
                        FROM VEGUNIT
                        WHERE VEGUNIT.JOBGUID = SS.JOBGUID
                            AND VEGUNIT.FORESTER IS NOT NULL
                            AND VEGUNIT.FORESTER != '') AS DF) AS Planners,

                    -- Permission Data (from VEGUNIT)
                    UnitData.First_Assessed_Date,
                    UnitData.Last_Assessed_Date,
                    UnitData.Total_Units,
                    UnitData.Approved_Count,
                    UnitData.Pending_Count,
                    UnitData.No_Contact_Count,
                    UnitData.Refusal_Count,
                    UnitData.Deferred_Count,
                    UnitData.PPL_Approved_Count,

                    -- Work Measurements (from JOBVEGETATIONUNITS)
                    WorkData.Rem_6_12_Count,
                    WorkData.Rem_Over_12_Count,
                    WorkData.Ash_Removal_Count,
                    WorkData.VPS_Count,
                    WorkData.Brush_Acres,
                    WorkData.Herbicide_Acres,
                    WorkData.Bucket_Trim_Length,
                    WorkData.Manual_Trim_Length

                {$from}

                {$unitData}

                {$workData}

                WHERE {$where}

                ORDER BY VEGJOB.REGION, SS.STATUS, SS.WO";
    }
    /* =========================================================================
    * END
    * =========================================================================
    */

    /* =========================================================================
    * Planner Data - This is measurements only
        *  There should be no permission relevant data collected here
    * =========================================================================
    */
    public function getAllAssessmentsDailyActivities(): string
    {
        $scopeYear = self::extractYearFromMsDate('WPStartDate_Assessment_Xrefs.WP_STARTDATE');
        $statusSql = '('.WSHelpers::toSqlInClause(config('ws_assessment_query.statuses.planner_concern')).')';

        $lastSync = self::formatToEasternTime('SS.EDITDATE');
        $dailyRecords = self::dailyRecordsQuery('SS.JOBGUID', false);
        $from = $this->baseFromClause();
        $where = $this->baseWhereClause([
            'statusSql' => $statusSql,
            'includeExcludedUsers' => false,
        ]);

        return "SELECT
                        {$scopeYear} AS Scope_Year,
                        SS.JOBGUID AS Job_GUID,
                        SS.WO AS Work_Order,
                        SS.EXT AS Extension,
                        SS.STATUS AS Status,
                        VEGJOB.LINENAME AS Line_Name,
                        VEGJOB.REGION AS Region,
                        VEGJOB.CYCLETYPE AS Cycle_Type,
                        CAST(VEGJOB.LENGTH AS DECIMAL(10,2)) AS Total_Miles,
                        CAST(VEGJOB.LENGTHCOMP AS DECIMAL(10,2)) AS Completed_Miles,
                        VEGJOB.PRCENT AS Percent_Complete,
                        SS.TAKENBY AS Current_Owner,
                        {$lastSync} AS Last_Sync,

                        {$dailyRecords} AS Daily_Records

                    {$from}

                    WHERE {$where}

                    ORDER BY SS.EDITDATE DESC, SS.WO DESC, SS.EXT DESC
                    FOR JSON PATH";
    }
    /* =========================================================================
    * END
    * =========================================================================
    */

    /** =========================================================================
     * THIS GETS IT ALL
     * Get the SQL query for a single circuit by JOBGUID.
     * Includes nested Stations array with Units sub-array.
     *
     * @param  string  $jobGuid  The circuit's JOBGUID
     *=========================================================================
     */
    public function getAllByJobGuid(string $jobGuid): string
    {
        // SEC-003: Validate GUID format before interpolation
        if (! preg_match('/^\{?[0-9a-fA-F]{8}-([0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}\}?$/', $jobGuid)) {
            throw new \InvalidArgumentException('Invalid JOBGUID format.');
        }

        // Build reusable fragments
        $scopeYear = self::extractYearFromMsDate('WPStartDate_Assessment_Xrefs.WP_STARTDATE');
        $forester = self::foresterSubquery();
        $totalFootage = self::totalFootageSubquery();
        $lastSync = self::formatToEasternTime('SS.EDITDATE');
        $dailyRecords = self::dailyRecordsQuery('SS.JOBGUID', false);
        $stationsWithUnits = self::stationsWithUnitsQuery();
        $unitCounts = self::unitCountsCrossApply();

        return "SELECT
                    -- Circuit Info
                    SS.JOBGUID AS Job_ID,
                    VEGJOB.LINENAME AS Line_Name,
                    SS.WO AS Work_Order,
                    SS.EXT AS Extension,
                    SS.STATUS AS Status,
                    SS.TAKEN AS Taken,
                    {$scopeYear} AS Scope_Year,
                    {$forester} AS Forester,
                    VEGJOB.OPCO AS Utility,
                    VEGJOB.REGION AS Region,
                    VEGJOB.SERVCOMP AS Department,
                    SS.JOBTYPE AS Job_Type,
                    VEGJOB.CYCLETYPE AS Cycle_Type,
                    {$totalFootage} AS Total_Footage,
                    CAST(VEGJOB.LENGTH AS DECIMAL(10,2)) AS Total_Miles,
                    CAST(VEGJOB.LENGTHCOMP AS DECIMAL(10,2)) AS Completed_Miles,
                    VEGJOB.PRCENT AS Percent_Complete,
                    VEGJOB.CONTRACTOR AS Contractor,
                    SS.TAKENBY AS Current_Owner,
                    {$lastSync} AS Last_Sync,

                    -- Unit Counts (single CROSS APPLY replaces 7 correlated subqueries)
                    UnitCounts.Total_Units_Planned,
                    UnitCounts.Total_Approvals,
                    UnitCounts.Total_Pending,
                    UnitCounts.Total_No_Contacts,
                    UnitCounts.Total_Refusals,
                    UnitCounts.Total_Deferred,
                    UnitCounts.Total_PPL_Approved,

                    -- Daily Records
                    {$dailyRecords} AS Daily_Records,

                    -- Stations with nested Units array
                    {$stationsWithUnits} AS Stations

                FROM SS
                    INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                    LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID

                {$unitCounts}

                WHERE SS.JOBGUID = '{$jobGuid}'
                FOR JSON PATH, WITHOUT_ARRAY_WRAPPER";
    }
    /* =========================================================================
    * END
    * =========================================================================
    */

    /** =========================================================================
     * Active Assessments Ordered by Oldest Unit
     * =========================================================================
     * Retrieves N circuits filtered by:
     *   - STATUS = 'ACTIV'
     *   - TAKEN = 1 (true)
     *   - TAKENBY domain matches user's domain
     *   - Assessment started (LENGTHCOMP > 0)
     * Ordered by oldest first assessed unit (cascading down)
     *
     * @param  int  $limit  Number of results to return (default 50)
     *                      =========================================================================
     */
    public function getActiveAssessmentsOrderedByOldest(int $limit = 5): string
    {
        $lastSync = self::formatToEasternTime('SS.EDITDATE');
        $firstEditDate = self::parseMsDateToDate('MIN(V.ASSDDATE)');
        $lastEditDate = self::parseMsDateToDate('MAX(V.ASSDDATE)');
        $oldestRaw = self::parseMsDateToDate('MIN(V.ASSDDATE)');

        return "SELECT TOP ({$limit})
                    -- Current Owner (full username)
                    SS.TAKENBY AS Current_Owner,

                    -- Line Name
                    VEGJOB.LINENAME AS Line_Name,

                    -- Job Identifiers
                    SS.JOBGUID AS Job_GUID,
                    SS.WO AS Work_Order,

                    -- Miles
                    CAST(VEGJOB.LENGTH AS DECIMAL(10,2)) AS Total_Miles,
                    CAST(VEGJOB.LENGTHCOMP AS DECIMAL(10,2)) AS Completed_Miles,

                    -- First Edit Date (oldest assessed unit)
                    FORMAT(UnitDates.First_Edit_Date, 'MM/dd/yyyy') AS First_Edit_Date,

                    -- Last Edit Date (most recent assessed unit)
                    FORMAT(UnitDates.Last_Edit_Date, 'MM/dd/yyyy') AS Last_Edit_Date,

                    -- Last Sync
                    {$lastSync} AS Last_Sync

                {$this->baseFromClause()}

                -- Get first/last assessed dates and raw oldest date for ordering
                CROSS APPLY (
                    SELECT
                        {$firstEditDate} AS First_Edit_Date,
                        {$lastEditDate} AS Last_Edit_Date,
                        {$oldestRaw} AS Oldest_Unit_Date
                    FROM VEGUNIT V
                    WHERE V.JOBGUID = SS.JOBGUID
                      AND V.ASSDDATE IS NOT NULL
                      AND V.ASSDDATE != ''
                      AND V.UNIT IS NOT NULL
                      AND V.UNIT != ''
                      AND V.UNIT != 'NW'
                ) AS UnitDates

                WHERE
                    -- Must be ACTIV status
                    SS.STATUS = 'ACTIV'

                    -- Must be taken (checked out)
                    AND SS.TAKEN = 1

                    -- Resource group / region filter
                    AND VEGJOB.REGION IN ({$this->resourceGroupsSql})

                    -- Scope year filter
                    AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'

                    -- Contractor filter
                    AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})

                    -- Job type filter
                    AND SS.JOBTYPE IN ({$this->jobTypesSql})

                    -- Exclude non-assessment cycle types (config-driven)
                    AND VEGJOB.CYCLETYPE NOT IN ({$this->excludedCycleTypesSql})

                    -- Domain must match (extract part before backslash)
                    AND UPPER(LEFT(SS.TAKENBY, CHARINDEX('\\', SS.TAKENBY + '\\') - 1)) = '{$this->domainFilter}'

                    -- Assessment must be started (completed miles > 0)
                    AND VEGJOB.LENGTHCOMP > 0

                -- Order by oldest assessed unit first
                ORDER BY UnitDates.Oldest_Unit_Date ASC";
    }
    /* =========================================================================
    * END
    * =========================================================================
    */

    /** =========================================================================
     * Gets JOBGUID for Entire Scope Year
     * =========================================================================
     */
    public function getAllJobGUIDsForEntireScopeYear(): string
    {
        $lastSync = self::formatToEasternTime('SS.EDITDATE');
        $scopeYear = self::extractYearFromMsDate('WPStartDate_Assessment_Xrefs.WP_STARTDATE');
        $statusSql = '('.WSHelpers::toSqlInClause(config('ws_assessment_query.statuses.planner_concern')).')';
        $from = $this->baseFromClause();
        $where = $this->baseWhereClause([
            'statusSql' => $statusSql,
            'includeExcludedUsers' => false,
        ]);

        return "SELECT
                            {$scopeYear} AS Scope_Year,
                            SS.JOBGUID AS JOB_GUID,
                            SS.WO AS Work_Order,
                            SS.EXT AS Extension,
                            SS.STATUS AS Assessment_Status,

                            VEGJOB.REGION AS Region,
                            SS.TITLE AS Circuit_Name,
                            CAST(VEGJOB.LENGTH AS DECIMAL(10,2)) AS Total_Miles,
                            CAST(VEGJOB.LENGTHCOMP AS DECIMAL(10,2)) AS Completed_Miles,
                            VEGJOB.PRCENT AS Percent_Complete,
                            VEGJOB.SITECOUNT AS Site_Count,

                            SS.JOBTYPE AS Job_Type,
                            VEGJOB.CYCLETYPE AS Cycle_Type,

                            VEGJOB.CONTRACTOR AS Contractor,
                            {$lastSync} AS Last_Sync,
                            SS.MODIFIEDBY AS Last_User_To_Edit,
                            SS.TAKENBY AS Current_Owner

                            {$from}

                            WHERE {$where}

                        ORDER BY SS.EDITDATE DESC, SS.WO DESC, SS.EXT DESC";
    }
    /* =========================================================================
    * END
    * =========================================================================
    */

    /* =========================================================================
    * Dynamic Field Lookup
    * =========================================================================
    * Get distinct values of any field from any table, scoped to active
    * assessments for the current scope year and contractor.
    *
    * Joins the target table to SS via JOBGUID (unless it's SS or VEGJOB,
    * which are already in the base query).
    *
    * @param  string  $table  Table name (e.g., 'VEGUNIT', 'STATIONS')
    * @param  string  $field  Column name (e.g., 'LASTNAME', 'PERMSTAT')
    * @param  int     $limit  Max rows to return (default 500)
    * =========================================================================
    */
    public function getDistinctFieldValues(string $table, string $field, int $limit = 500): string
    {
        // Validate table/field names — alphanumeric + underscore only
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
            throw new \InvalidArgumentException('Invalid table or field name.');
        }

        // Determine the qualified column reference and any extra JOIN needed
        $upperTable = strtoupper($table);
        $qualifiedField = "{$table}.{$field}";
        $extraJoin = '';

        if ($upperTable !== 'SS' && $upperTable !== 'VEGJOB') {
            $extraJoin = "INNER JOIN {$table} ON SS.JOBGUID = {$table}.JOBGUID";
        }

        $from = $this->baseFromClause();

        return "SELECT TOP ({$limit})
                    {$qualifiedField} AS value,
                    COUNT(*) AS record_count
                {$from}
                {$extraJoin}
                WHERE VEGJOB.REGION IN ({$this->resourceGroupsSql})
                AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
                AND SS.STATUS = 'ACTIV'
                AND SS.TAKEN = 1
                AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})
                AND SS.JOBTYPE IN ({$this->jobTypesSql})
                AND VEGJOB.CYCLETYPE NOT IN ({$this->excludedCycleTypesSql})
                AND {$qualifiedField} IS NOT NULL
                AND {$qualifiedField} != ''
                GROUP BY {$qualifiedField}
                ORDER BY record_count DESC";
    }
    /* =========================================================================
    * END
    * =========================================================================
    */
}

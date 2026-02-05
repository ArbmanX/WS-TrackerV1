<?php

namespace App\Services\WorkStudio\AssessmentsDx\Queries;

use App\Services\WorkStudio\Helpers\WSHelpers;
use App\Services\WorkStudio\ValueObjects\UserQueryContext;

class AssessmentQueries
{
    use SqlFragmentHelpers;

    private string $resourceGroupsSql;

    private string $contractorsSql;

    private string $excludedUsersSql;

    private string $jobTypesSql;

    private string $scopeYear;

    /**
     * @param  UserQueryContext  $context  User-specific query parameters
     */
    public function __construct(private readonly UserQueryContext $context)
    {
        $this->resourceGroupsSql = WSHelpers::toSqlInClause($context->resourceGroups);
        $this->contractorsSql = WSHelpers::toSqlInClause($context->contractors);

        // System-level values stay in config
        $this->excludedUsersSql = WSHelpers::toSqlInClause(config('ws_assessment_query.excludedUsers'));
        $this->jobTypesSql = WSHelpers::toSqlInClause(config('ws_assessment_query.job_types'));
        $this->scopeYear = config('ws_assessment_query.scope_year');
    }

    /* =========================================================================
    * System Wide Data - This is broad counts and totals of data
    * =========================================================================
    */
    public function systemWideDataQuery(): string
    {
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


                        -- Active Planners (unique TAKENBY usernames from assessments with status 'ACTIV' only)
                        COUNT(DISTINCT CASE WHEN SS.STATUS = 'ACTIV' THEN SS.TAKENBY END) AS active_planners

                    FROM SS
                    INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                    LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID

                    WHERE VEGJOB.REGION IN ({$this->resourceGroupsSql})
                    AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
                    AND VEGJOB.CYCLETYPE NOT IN ('Reactive')
                    AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})
                    AND SS.STATUS IN ('ACTIV', 'QC', 'REWRK', 'CLOSE')
                    AND SS.TAKENBY NOT IN ({$this->excludedUsersSql})
                    AND SS.JOBTYPE IN ({$this->jobTypesSql})
                    AND VEGJOB.CYCLETYPE NOT IN ('Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP')";
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

                    -- Active Planners (unique TAKENBY with ACTIV status)
                    COUNT(DISTINCT CASE WHEN SS.STATUS = 'ACTIV' THEN SS.TAKENBY END) AS Active_Planners,

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

                FROM SS
                INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID

                -- CROSS APPLY for VEGUNIT (permission counts per circuit)
                CROSS APPLY (
                    SELECT
                        COUNT(*) AS Total_Units,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Approved' THEN 1 END) AS Approved_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Pending' OR VEGUNIT.PERMSTAT IS NULL OR VEGUNIT.PERMSTAT = '' THEN 1 END) AS Pending_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'No Contact' THEN 1 END) AS No_Contact_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Refused' THEN 1 END) AS Refusal_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Deferred' THEN 1 END) AS Deferred_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'PPL Approved' THEN 1 END) AS PPL_Approved_Count
                    FROM VEGUNIT
                    WHERE VEGUNIT.JOBGUID = SS.JOBGUID
                    AND VEGUNIT.UNIT IS NOT NULL
                    AND VEGUNIT.UNIT != ''
                    AND VEGUNIT.UNIT != 'NW'
                ) AS UnitData

                -- CROSS APPLY for JOBVEGETATIONUNITS (work measurements per circuit)
                CROSS APPLY (
                    SELECT
                        COUNT(CASE WHEN UNIT = 'REM612' THEN 1 END) AS Rem_6_12_Count,
                        COUNT(CASE WHEN UNIT IN ('REM1218', 'REM1824', 'REM2430', 'REM3036') THEN 1 END) AS Rem_Over_12_Count,
                        COUNT(CASE WHEN UNIT IN ('ASH612', 'ASH1218', 'ASH1824', 'ASH2430', 'ASH3036') THEN 1 END) AS Ash_Removal_Count,
                        COUNT(CASE WHEN UNIT = 'VPS' THEN 1 END) AS VPS_Count,
                        SUM(CASE WHEN UNIT IN ('BRUSH', 'HCB', 'BRUSHTRIM') THEN ACRES ELSE 0 END) AS Brush_Acres,
                        SUM(CASE WHEN UNIT IN ('HERBA', 'HERBNA') THEN ACRES ELSE 0 END) AS Herbicide_Acres,
                        SUM(CASE WHEN UNIT IN ('SPB', 'MPB') THEN LENGTHWRK ELSE 0 END) AS Bucket_Trim_Length,
                        SUM(CASE WHEN UNIT IN ('SPM', 'MPM') THEN LENGTHWRK ELSE 0 END) AS Manual_Trim_Length
                    FROM JOBVEGETATIONUNITS
                    WHERE JOBVEGETATIONUNITS.JOBGUID = SS.JOBGUID
                ) AS WorkData

                WHERE VEGJOB.REGION IN ({$this->resourceGroupsSql})
                AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
                AND SS.STATUS IN ('ACTIV', 'QC', 'REWRK', 'CLOSE')
                AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})
                AND SS.TAKENBY NOT IN ({$this->excludedUsersSql})
                AND SS.JOBTYPE IN ({$this->jobTypesSql})
                AND VEGJOB.CYCLETYPE NOT IN ('Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP')

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

                    -- Phase 1: Permission Data (from VEGUNIT)
                    UnitData.First_Assessed_Date,
                    UnitData.Last_Assessed_Date,
                    UnitData.Total_Units,
                    UnitData.Approved_Count,
                    UnitData.Pending_Count,
                    UnitData.No_Contact_Count,
                    UnitData.Refusal_Count,
                    UnitData.Deferred_Count,
                    UnitData.PPL_Approved_Count,

                    -- Phase 2: Work Measurements (from JOBVEGETATIONUNITS)
                    WorkData.Rem_6_12_Count,
                    WorkData.Rem_Over_12_Count,
                    WorkData.Ash_Removal_Count,
                    WorkData.VPS_Count,
                    WorkData.Brush_Acres,
                    WorkData.Herbicide_Acres,
                    WorkData.Bucket_Trim_Length,
                    WorkData.Manual_Trim_Length

                FROM SS
                INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID

                -- Phase 1: CROSS APPLY for VEGUNIT (dates, permission counts)
                CROSS APPLY (
                    SELECT
                        MIN(VEGUNIT.ASSDDATE) AS First_Assessed_Date,
                        MAX(VEGUNIT.ASSDDATE) AS Last_Assessed_Date,
                        COUNT(*) AS Total_Units,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Approved' THEN 1 END) AS Approved_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Pending' OR VEGUNIT.PERMSTAT IS NULL OR VEGUNIT.PERMSTAT = '' THEN 1 END) AS Pending_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'No Contact' THEN 1 END) AS No_Contact_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Refusal' THEN 1 END) AS Refusal_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'Deferred' THEN 1 END) AS Deferred_Count,
                        COUNT(CASE WHEN VEGUNIT.PERMSTAT = 'PPL Approved' THEN 1 END) AS PPL_Approved_Count
                    FROM VEGUNIT
                    WHERE VEGUNIT.JOBGUID = SS.JOBGUID
                    AND VEGUNIT.UNIT IS NOT NULL
                    AND VEGUNIT.UNIT != ''
                    AND VEGUNIT.UNIT != 'NW'
                ) AS UnitData

                -- Phase 2: CROSS APPLY for JOBVEGETATIONUNITS (work measurements)
                CROSS APPLY (
                    SELECT
                        -- Removals 6-12 (separate)
                        COUNT(CASE WHEN UNIT = 'REM612' THEN 1 END) AS Rem_6_12_Count,

                        -- Removals > 12 (grouped)
                        COUNT(CASE WHEN UNIT IN ('REM1218', 'REM1824', 'REM2430', 'REM3036') THEN 1 END) AS Rem_Over_12_Count,

                        -- All Ash Removals (grouped)
                        COUNT(CASE WHEN UNIT IN ('ASH612', 'ASH1218', 'ASH1824', 'ASH2430', 'ASH3036') THEN 1 END) AS Ash_Removal_Count,

                        -- VPS count
                        COUNT(CASE WHEN UNIT = 'VPS' THEN 1 END) AS VPS_Count,

                        -- Brush acres (grouped)
                        SUM(CASE WHEN UNIT IN ('BRUSH', 'HCB', 'BRUSHTRIM') THEN ACRES ELSE 0 END) AS Brush_Acres,

                        -- Herbicide acres (grouped)
                        SUM(CASE WHEN UNIT IN ('HERBA', 'HERBNA') THEN ACRES ELSE 0 END) AS Herbicide_Acres,

                        -- Bucket trimming length (SPB, MPB)
                        SUM(CASE WHEN UNIT IN ('SPB', 'MPB') THEN LENGTHWRK ELSE 0 END) AS Bucket_Trim_Length,

                        -- Manual trimming length (SPM, MPM)
                        SUM(CASE WHEN UNIT IN ('SPM', 'MPM') THEN LENGTHWRK ELSE 0 END) AS Manual_Trim_Length
                    FROM JOBVEGETATIONUNITS
                    WHERE JOBVEGETATIONUNITS.JOBGUID = SS.JOBGUID
                ) AS WorkData

                WHERE VEGJOB.REGION IN ({$this->resourceGroupsSql})
                AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
                AND SS.STATUS IN ('ACTIV', 'QC', 'REWRK', 'CLOSE')
                AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})
                AND SS.TAKENBY NOT IN ({$this->excludedUsersSql})
                AND SS.JOBTYPE IN ({$this->jobTypesSql})
                AND VEGJOB.CYCLETYPE NOT IN ('Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP', 'FFP CPM Maintenance')

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

        $cycleTypes = WSHelpers::toSqlInClause(config('ws_assessment_query.cycle_types'));
        $statues = WSHelpers::toSqlInClause(config('ws_assessment_query.statuses.planner_concern'));

        $lastSync = self::formatToEasternTime('SS.EDITDATE');
        $dailyRecords = self::dailyRecordsQuery('WSREQSS.JOBGUID', false);

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
                        WSREQSS.TAKENBY AS Current_Owner,
                        {$lastSync} AS Last_Sync,

                        {$dailyRecords} AS Daily_Records

                    FROM SS
                        INNER JOIN SS AS WSREQSS ON SS.JOBGUID = WSREQSS.JOBGUID
                        INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                        LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID
                    WHERE VEGJOB.REGION IN ({$this->resourceGroupsSql})
                    AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
                    AND WSREQSS.STATUS IN ({$statues})
                    AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})
                    AND WSREQSS.JOBTYPE IN ({$this->jobTypesSql})
                    AND VEGJOB.CYCLETYPE NOT IN ({$cycleTypes})

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
        // Build reusable fragments
        $scopeYear = self::extractYearFromMsDate('WPStartDate_Assessment_Xrefs.WP_STARTDATE');
        $forester = self::foresterSubquery();
        $totalFootage = self::totalFootageSubquery();
        $lastSync = self::formatToEasternTime('SS.EDITDATE');
        $dailyRecords = self::dailyRecordsQuery('WSREQSS.JOBGUID', false);
        $stationsWithUnits = self::stationsWithUnitsQuery();

        // Unit count subqueries
        $totalUnitsPlanned = self::unitCountSubquery('WSREQSS.JOBGUID', null, true);
        $totalApprovals = self::unitCountSubquery('WSREQSS.JOBGUID', 'Approved');
        $totalPending = self::unitCountSubquery('WSREQSS.JOBGUID', 'Pending');
        $totalNoContacts = self::unitCountSubquery('WSREQSS.JOBGUID', 'No Contact');
        $totalRefusals = self::unitCountSubquery('WSREQSS.JOBGUID', 'Refusal');
        $totalDeferred = self::unitCountSubquery('WSREQSS.JOBGUID', 'Deferred');
        $totalPplApproved = self::unitCountSubquery('WSREQSS.JOBGUID', 'PPL Approved');

        return "SELECT
                    -- Circuit Info
                    WSREQSS.JOBGUID AS Job_ID,
                    VEGJOB.LINENAME AS Line_Name,
                    WSREQSS.WO AS Work_Order,
                    WSREQSS.EXT AS Extension,
                    WSREQSS.STATUS AS Status,
                    WSREQSS.TAKEN AS Taken,
                    {$scopeYear} AS Scope_Year,
                    {$forester} AS Forester,
                    VEGJOB.OPCO AS Utility,
                    VEGJOB.REGION AS Region,
                    VEGJOB.SERVCOMP AS Department,
                    WSREQSS.JOBTYPE AS Job_Type,
                    VEGJOB.CYCLETYPE AS Cycle_Type,
                    {$totalFootage} AS Total_Footage,
                    CAST(VEGJOB.LENGTH AS DECIMAL(10,2)) AS Total_Miles,
                    CAST(VEGJOB.LENGTHCOMP AS DECIMAL(10,2)) AS Completed_Miles,
                    VEGJOB.PRCENT AS Percent_Complete,
                    VEGJOB.CONTRACTOR AS Contractor,
                    WSREQSS.TAKENBY AS Current_Owner,
                    {$lastSync} AS Last_Sync,

                    -- Unit Counts
                    {$totalUnitsPlanned} AS Total_Units_Planned,
                    {$totalApprovals} AS Total_Approvals,
                    {$totalPending} AS Total_Pending,
                    {$totalNoContacts} AS Total_No_Contacts,
                    {$totalRefusals} AS Total_Refusals,
                    {$totalDeferred} AS Total_Deferred,
                    {$totalPplApproved} AS Total_PPL_Approved,

                    -- Daily Records
                    {$dailyRecords} AS Daily_Records,

                    -- Stations with nested Units array
                    {$stationsWithUnits} AS Stations

                FROM SS
                    INNER JOIN SS AS WSREQSS ON SS.JOBGUID = WSREQSS.JOBGUID
                    INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                    LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID
                WHERE WSREQSS.JOBGUID = '{$jobGuid}'
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
        $domainFilter = $this->context->domain;

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

                FROM SS
                INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID

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

                    -- Exclude reactive cycle types
                    AND VEGJOB.CYCLETYPE NOT IN ('Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP')

                    -- Domain must match (extract part before backslash)
                    AND UPPER(LEFT(SS.TAKENBY, CHARINDEX('\\', SS.TAKENBY + '\\') - 1)) = '{$domainFilter}'

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

        $statues = WSHelpers::toSqlInClause(config('ws_assessment_query.statuses.planner_concern'));

        return "SELECT
                -- getAllJobGUIDsForEntireScopeYear
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

                            FROM SS
                            INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                            LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID
                            WHERE VEGJOB.REGION IN ({$this->resourceGroupsSql})
                            AND SS.STATUS IN ({$statues})
                            AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})
                            AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
                            AND VEGJOB.CYCLETYPE NOT IN ('Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP')
                            AND SS.JOBTYPE IN ({$this->jobTypesSql})
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

        return "SELECT TOP ({$limit})
                    {$qualifiedField} AS value,
                    COUNT(*) AS record_count
                FROM SS
                INNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID
                LEFT JOIN WPStartDate_Assessment_Xrefs ON SS.JOBGUID = WPStartDate_Assessment_Xrefs.Assess_JOBGUID
                {$extraJoin}
                WHERE VEGJOB.REGION IN ({$this->resourceGroupsSql})
                AND WPStartDate_Assessment_Xrefs.WP_STARTDATE LIKE '%{$this->scopeYear}%'
                AND SS.STATUS = 'ACTIV'
                AND SS.TAKEN = 1
                AND VEGJOB.CONTRACTOR IN ({$this->contractorsSql})
                AND SS.JOBTYPE IN ({$this->jobTypesSql})
                AND VEGJOB.CYCLETYPE NOT IN ('Reactive', 'Storm Follow Up', 'Misc. Project Work', 'PUC-STORM FOLLOW UP')
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

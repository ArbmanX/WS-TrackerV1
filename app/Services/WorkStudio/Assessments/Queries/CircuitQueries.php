<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;

class CircuitQueries extends AbstractQueryBuilder
{
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

    public function getAllByJobGuid(string $jobGuid): string
    {
        // SEC-003: Validate GUID format before interpolation
        self::validateGuid($jobGuid);

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
}

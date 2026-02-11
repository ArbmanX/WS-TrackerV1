<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;

class ActivityQueries extends AbstractQueryBuilder
{
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
}

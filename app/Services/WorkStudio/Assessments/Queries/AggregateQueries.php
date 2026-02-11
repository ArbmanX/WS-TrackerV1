<?php

namespace App\Services\WorkStudio\Assessments\Queries;

class AggregateQueries extends AbstractQueryBuilder
{
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
}

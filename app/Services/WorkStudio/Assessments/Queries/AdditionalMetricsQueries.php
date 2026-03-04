<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;

class AdditionalMetricsQueries extends AbstractQueryBuilder
{
    public static function build($jobGuid)
    {
        self::validateGuid($jobGuid);

        return self::buildQuery("= '{$jobGuid}'");
    }

    public static function buildBatched($jobGuids): string
    {
        foreach ($jobGuids as $guid) {
            self::validateGuid($guid);
        }

        $jobGuidsSql = WSHelpers::toSqlInClause($jobGuids);

        return self::buildQuery("IN ({$jobGuidsSql})");
    }

    private static function buildQuery(string $jobFilter): string
    {
        $validUnit = self::validUnitFilter('VU');
        $approved = config('workstudio.assessments.permission_statuses.approved');
        $pending = config('workstudio.assessments.permission_statuses.pending');
        $noContact = config('workstudio.assessments.permission_statuses.no_contact');
        $refused = config('workstudio.assessments.permission_statuses.refused');
        $deferred = config('workstudio.assessments.permission_statuses.deferred');
        $pplApproved = config('workstudio.assessments.permission_statuses.ppl_approved');
        $validUnitPending = self::validUnitFilter('VP');
        $areaThreshold = config('workstudio.data_collection.thresholds.notes_compliance_area_sqm');
        $agingUnitThreshold = config('workstudio.data_collection.thresholds.aging_unit_days');

        return
            "SELECT
                A.*,

                -- Station breakdown
                STC.stations_with_work,
                STC.stations_no_work,
                STC.stations_not_planned,

                -- Work type breakdown (JSON from V_ASSESSMENT)
                (SELECT VA.unit, CAST(VA.UnitQty AS DECIMAL(18,2)) AS UnitQty
                FROM V_ASSESSMENT VA
                WHERE VA.jobguid = A.JOBGUID
                ORDER BY VA.unit
                FOR JSON PATH) AS work_type_breakdown,

                -- Foresters with unit counts (JSON from VEGUNIT)
                (SELECT VU2.FRSTR_USER AS forester, COUNT(*) AS unit_count
                FROM VEGUNIT VU2
                WHERE VU2.JOBGUID = A.JOBGUID
                    AND VU2.FRSTR_USER IS NOT NULL AND VU2.FRSTR_USER != ''
                    AND VU2.UNIT != 'NW' AND VU2.UNIT != '' AND VU2.UNIT IS NOT NULL
                GROUP BY VU2.FRSTR_USER
                FOR JSON PATH) AS foresters
            FROM (
                SELECT
                    SS.JOBGUID,
                    SS.WO,
                    SS.EXT,

                    -- Permission breakdown
                    COUNT(*) AS total_units,
                    COUNT(CASE WHEN VU.PERMSTAT = '{$approved}' THEN 1 END) AS approved,
                    COUNT(CASE WHEN VU.PERMSTAT = '{$pending}' OR VU.PERMSTAT IS NULL OR VU.PERMSTAT = '' THEN 1 END) AS pending,
                    COUNT(CASE WHEN VU.PERMSTAT = '{$refused}' THEN 1 END) AS refused,
                    COUNT(CASE WHEN VU.PERMSTAT = '{$noContact}' THEN 1 END) AS no_contact,
                    COUNT(CASE WHEN VU.PERMSTAT = '{$deferred}' THEN 1 END) AS deferred,
                    COUNT(CASE WHEN VU.PERMSTAT = '{$pplApproved}' THEN 1 END) AS ppl_approved,

                    -- Oldest pending unit per job (lowest sequence with no permission decision)
                    OldestPending.STATNAME AS oldest_pending_statname,
                    OldestPending.UNIT AS oldest_pending_unit,
                    OldestPending.SEQUENCE AS oldest_pending_sequence,
                    CAST(OldestPending.ASSDDATE AS DATE) AS oldest_pending_date,

                    -- Split assessment info
                    SC.SplitAssessmentCount AS split_count,
                    SC.SplitAssessmentUpdatedFlag AS split_updated,

                    -- Timeline dates (from JOBHISTORY)
                    JobDates.taken_date,
                    JobDates.sent_to_qc_date,
                    JobDates.sent_to_rework_date,
                    JobDates.closed_date,

                    -- Notes compliance (ASSNOTE only, guarded by JVU area threshold)
                    SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
                        THEN 1 ELSE 0
                    END) AS units_requiring_notes,
                    SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
                        AND VU.ASSNOTE IS NOT NULL AND DATALENGTH(VU.ASSNOTE) > 0
                        THEN 1 ELSE 0
                    END) AS units_with_notes,
                    SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
                        AND (VU.ASSNOTE IS NULL OR DATALENGTH(VU.ASSNOTE) = 0)
                        THEN 1 ELSE 0
                    END) AS units_without_notes,
                    CAST(
                        SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
                            AND VU.ASSNOTE IS NOT NULL AND DATALENGTH(VU.ASSNOTE) > 0
                            THEN 1.0 ELSE 0.0
                        END) / NULLIF(SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
                            THEN 1 ELSE 0 END), 0) * 100
                    AS DECIMAL(5,1)) AS notes_compliance_percent,

                    -- Aging units (pending PERMSTAT, work units only, older than threshold)
                    SUM(CASE
                        WHEN (VU.PERMSTAT IS NULL OR VU.PERMSTAT = '')
                            AND VU.ASSDDATE IS NOT NULL AND VU.ASSDDATE != ''
                            AND U_AGING.SUMMARYGRP IS NOT NULL
                            AND U_AGING.SUMMARYGRP NOT IN ('', 'Summary-NonWork')
                            AND DATEDIFF(DAY, CAST(VU.ASSDDATE AS DATE), GETDATE()) > {$agingUnitThreshold}
                        THEN 1 ELSE 0
                    END) AS pending_over_threshold,

                    -- First/last work unit placed (single scan via OUTER APPLY)
                    UnitDates.first_unit_date,
                    UnitDates.last_unit_date
                FROM SS
                    INNER JOIN VEGUNIT VU ON VU.JOBGUID = SS.JOBGUID
                    LEFT JOIN JOBVEGETATIONUNITS JVU
                        ON JVU.JOBGUID = VU.JOBGUID
                        AND JVU.STATNAME = VU.STATNAME
                        AND JVU.SEQUENCE = VU.SEQUENCE
                    LEFT JOIN UNITS U_AGING ON U_AGING.UNIT = VU.UNIT
                    LEFT JOIN SSCUSTOM SC ON SC.JOBGUID = SS.JOBGUID
                    OUTER APPLY (
                        SELECT TOP 1 VP.STATNAME, VP.UNIT, VP.SEQUENCE, VP.ASSDDATE
                        FROM VEGUNIT VP
                            INNER JOIN UNITS UF ON UF.UNIT = VP.UNIT
                        WHERE VP.JOBGUID = SS.JOBGUID
                            AND (VP.PERMSTAT IS NULL OR VP.PERMSTAT = '' OR VP.PERMSTAT = '{$pending}')
                            AND {$validUnitPending}
                            AND UF.SUMMARYGRP NOT IN ('', 'Summary-NonWork')
                            AND UF.SUMMARYGRP IS NOT NULL
                        ORDER BY VP.SEQUENCE ASC
                    ) AS OldestPending
                    OUTER APPLY (
                        SELECT
                            CAST(MIN(CASE WHEN JH.JOBSTATUS = 'ACTIV' THEN JH.LOGDATE END) AS DATE) AS taken_date,
                            CAST(MIN(CASE WHEN JH.JOBSTATUS = 'QC' THEN JH.LOGDATE END) AS DATE) AS sent_to_qc_date,
                            CAST(MIN(CASE WHEN JH.JOBSTATUS = 'REWRK' THEN JH.LOGDATE END) AS DATE) AS sent_to_rework_date,
                            CAST(MIN(CASE WHEN JH.JOBSTATUS = 'CLOSE' THEN JH.LOGDATE END) AS DATE) AS closed_date
                        FROM JOBHISTORY JH
                        WHERE JH.JOBGUID = SS.JOBGUID
                    ) AS JobDates
                    OUTER APPLY (
                        SELECT
                            CAST(MIN(VD.ASSDDATE) AS DATE) AS first_unit_date,
                            CAST(MAX(VD.ASSDDATE) AS DATE) AS last_unit_date
                        FROM VEGUNIT VD
                            INNER JOIN UNITS UD ON UD.UNIT = VD.UNIT
                        WHERE VD.JOBGUID = SS.JOBGUID
                            AND VD.ASSDDATE IS NOT NULL AND VD.ASSDDATE != ''
                            AND VD.UNIT != 'NW' AND VD.UNIT != '' AND VD.UNIT IS NOT NULL
                            AND UD.SUMMARYGRP NOT IN ('', 'Summary-NonWork')
                            AND UD.SUMMARYGRP IS NOT NULL
                    ) AS UnitDates
                WHERE SS.JOBGUID {$jobFilter}
                    AND {$validUnit}
                GROUP BY SS.JOBGUID, SS.WO, SS.EXT,
                    OldestPending.STATNAME, OldestPending.UNIT, OldestPending.SEQUENCE, OldestPending.ASSDDATE,
                    SC.SplitAssessmentCount, SC.SplitAssessmentUpdatedFlag,
                    JobDates.taken_date, JobDates.sent_to_qc_date,
                    JobDates.sent_to_rework_date, JobDates.closed_date,
                    UnitDates.first_unit_date, UnitDates.last_unit_date
            ) AS A
            LEFT JOIN (
                SELECT S.JOBGUID,
                    SUM(CASE WHEN W.STATNAME IS NOT NULL THEN 1 ELSE 0 END) AS stations_with_work,
                    SUM(CASE WHEN W.STATNAME IS NULL AND U.STATNAME IS NOT NULL THEN 1 ELSE 0 END) AS stations_no_work,
                    SUM(CASE WHEN U.STATNAME IS NULL THEN 1 ELSE 0 END) AS stations_not_planned
                FROM STATIONS S
                LEFT JOIN (
                    SELECT DISTINCT V2.JOBGUID, V2.STATNAME
                    FROM VEGUNIT V2
                        INNER JOIN UNITS U2 ON U2.UNIT = V2.UNIT
                    WHERE V2.JOBGUID {$jobFilter}
                        AND V2.UNIT != 'NW' AND V2.UNIT != '' AND V2.UNIT IS NOT NULL
                        AND U2.SUMMARYGRP NOT IN ('', 'Summary-NonWork')
                        AND U2.SUMMARYGRP IS NOT NULL
                ) AS W ON W.JOBGUID = S.JOBGUID AND W.STATNAME = S.STATNAME
                LEFT JOIN (
                    SELECT DISTINCT V3.JOBGUID, V3.STATNAME
                    FROM VEGUNIT V3
                    WHERE V3.JOBGUID {$jobFilter}
                ) AS U ON U.JOBGUID = S.JOBGUID AND U.STATNAME = S.STATNAME
                WHERE S.JOBGUID {$jobFilter}
                GROUP BY S.JOBGUID
            ) AS STC ON STC.JOBGUID = A.JOBGUID";
    }
}

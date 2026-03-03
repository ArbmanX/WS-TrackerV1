<?php

namespace App\Services\WorkStudio\Assessments\Queries;

use App\Services\WorkStudio\Shared\Helpers\WSHelpers;

class AdditionalMetricsQueries extends AbstractQueryBuilder
{
 
    public static function build($jobGuid)
    {
        self::validateGuid($jobGuid);

        $validUnit = self::validUnitFilter('VU');
        $approved = config('workstudio.assessments.permission_statuses.approved');
        $pending = config('workstudio.assessments.permission_statuses.pending');
        $noContact = config('workstudio.assessments.permission_statuses.no_contact');
        $refused = config('workstudio.assessments.permission_statuses.refused');
        $deferred = config('workstudio.assessments.permission_statuses.deferred');
        $pplApproved = config('workstudio.assessments.permission_statuses.ppl_approved');

        $validUnitPending = self::validUnitFilter('VP');

        return
            "SELECT
                A.*,

                -- Station breakdown (correlated subqueries)
                (SELECT COUNT(*)
                FROM STATIONS S
                WHERE S.JOBGUID = A.JOBGUID
                    AND EXISTS (
                        SELECT 1 FROM VEGUNIT V2
                            INNER JOIN UNITS U2 ON U2.UNIT = V2.UNIT
                        WHERE V2.JOBGUID = S.JOBGUID AND V2.STATNAME = S.STATNAME
                            AND V2.UNIT != 'NW' AND V2.UNIT != '' AND V2.UNIT IS NOT NULL
                            AND U2.SUMMARYGRP NOT IN ('', 'Summary-NonWork')
                            AND U2.SUMMARYGRP IS NOT NULL
                    )
                ) AS stations_with_work,
                (SELECT COUNT(*)
                FROM STATIONS S
                WHERE S.JOBGUID = A.JOBGUID
                    AND NOT EXISTS (
                        SELECT 1 FROM VEGUNIT V2
                            INNER JOIN UNITS U2 ON U2.UNIT = V2.UNIT
                        WHERE V2.JOBGUID = S.JOBGUID AND V2.STATNAME = S.STATNAME
                            AND V2.UNIT != 'NW' AND V2.UNIT != '' AND V2.UNIT IS NOT NULL
                            AND U2.SUMMARYGRP NOT IN ('', 'Summary-NonWork')
                            AND U2.SUMMARYGRP IS NOT NULL
                    )
                    AND EXISTS (
                        SELECT 1 FROM VEGUNIT V3
                        WHERE V3.JOBGUID = S.JOBGUID AND V3.STATNAME = S.STATNAME
                    )
                ) AS stations_no_work,
                (SELECT COUNT(*)
                FROM STATIONS S
                WHERE S.JOBGUID = A.JOBGUID
                    AND NOT EXISTS (
                        SELECT 1 FROM VEGUNIT V4
                        WHERE V4.JOBGUID = S.JOBGUID AND V4.STATNAME = S.STATNAME
                    )
                ) AS stations_not_planned,

                -- Work type breakdown (JSON from V_ASSESSMENT)
                (SELECT VA.unit, VA.UnitQty
                FROM V_ASSESSMENT VA
                WHERE VA.jobguid = A.JOBGUID
                ORDER BY VA.unit
                FOR JSON PATH) AS work_type_breakdown,

                -- Unique foresters (JSON from VEGUNIT)
                (SELECT DISTINCT VU2.FRSTR_USER AS forester
                FROM VEGUNIT VU2
                WHERE VU2.JOBGUID = A.JOBGUID
                    AND VU2.FRSTR_USER IS NOT NULL AND VU2.FRSTR_USER != ''
                FOR JSON PATH) AS foresters,

                -- Job history timeline (JSON from JOBHISTORY)
                (SELECT
                    JH.LOGDATE, JH.LOGTIME, JH.OLDSTATUS, JH.JOBSTATUS,
                    JH.USERNAME, JH.ACTION, JH.ASSIGNEDTO
                FROM JOBHISTORY JH
                WHERE JH.JOBGUID = A.JOBGUID
                    AND JH.OLDSTATUS NOT IN ('NEW', 'NRS', 'SA')
                    AND (
                        (JH.JOBSTATUS IS NOT NULL AND JH.JOBSTATUS != '')
                        OR (JH.ASSIGNEDTO IS NOT NULL AND JH.ASSIGNEDTO != '')
                    )
                ORDER BY JH.LOGDATE ASC
                FOR JSON PATH) AS job_history
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

                    -- Oldest pending unit (lowest sequence with no permission decision)
                    OldestPending.STATNAME AS oldest_pending_statname,
                    OldestPending.UNIT AS oldest_pending_unit,
                    OldestPending.SEQUENCE AS oldest_pending_sequence,

                    -- Split assessment info
                    SC.SplitAssessmentCount AS split_count,
                    SC.SplitAssessmentUpdatedFlag AS split_updated
                FROM SS
                    INNER JOIN VEGUNIT VU ON VU.JOBGUID = SS.JOBGUID
                    LEFT JOIN SSCUSTOM SC ON SC.JOBGUID = SS.JOBGUID
                    OUTER APPLY (
                        SELECT TOP 1 VP.STATNAME, VP.UNIT, VP.SEQUENCE
                        FROM VEGUNIT VP
                        WHERE VP.JOBGUID = SS.JOBGUID
                            AND (VP.PERMSTAT IS NULL OR VP.PERMSTAT = '' OR VP.PERMSTAT = '{$pending}')
                            AND {$validUnitPending}
                        ORDER BY VP.SEQUENCE ASC
                    ) AS OldestPending
                WHERE SS.JOBGUID = '{$jobGuid}'
                    AND {$validUnit}
                GROUP BY SS.JOBGUID, SS.WO, SS.EXT,
                    OldestPending.STATNAME, OldestPending.UNIT, OldestPending.SEQUENCE,
                    SC.SplitAssessmentCount, SC.SplitAssessmentUpdatedFlag
            ) AS A";
    }

    public static function buildBatched($jobGuids): string
    {
        foreach ($jobGuids as $guid) {
            self::validateGuid($guid);
        }

        $jobGuidsSql = WSHelpers::toSqlInClause($jobGuids);
        $validUnit = self::validUnitFilter('VU');
        $approved = config('workstudio.assessments.permission_statuses.approved');
        $pending = config('workstudio.assessments.permission_statuses.pending');
        $noContact = config('workstudio.assessments.permission_statuses.no_contact');
        $refused = config('workstudio.assessments.permission_statuses.refused');
        $deferred = config('workstudio.assessments.permission_statuses.deferred');
        $pplApproved = config('workstudio.assessments.permission_statuses.ppl_approved');
        $validUnitPending = self::validUnitFilter('VP');

        return
            "SELECT
                A.*,

                -- Station breakdown (correlated subqueries)
                (SELECT COUNT(*)
                FROM STATIONS S
                WHERE S.JOBGUID = A.JOBGUID
                    AND EXISTS (
                        SELECT 1 FROM VEGUNIT V2
                            INNER JOIN UNITS U2 ON U2.UNIT = V2.UNIT
                        WHERE V2.JOBGUID = S.JOBGUID AND V2.STATNAME = S.STATNAME
                            AND V2.UNIT != 'NW' AND V2.UNIT != '' AND V2.UNIT IS NOT NULL
                            AND U2.SUMMARYGRP NOT IN ('', 'Summary-NonWork')
                            AND U2.SUMMARYGRP IS NOT NULL
                    )
                ) AS stations_with_work,
                (SELECT COUNT(*)
                FROM STATIONS S
                WHERE S.JOBGUID = A.JOBGUID
                    AND NOT EXISTS (
                        SELECT 1 FROM VEGUNIT V2
                            INNER JOIN UNITS U2 ON U2.UNIT = V2.UNIT
                        WHERE V2.JOBGUID = S.JOBGUID AND V2.STATNAME = S.STATNAME
                            AND V2.UNIT != 'NW' AND V2.UNIT != '' AND V2.UNIT IS NOT NULL
                            AND U2.SUMMARYGRP NOT IN ('', 'Summary-NonWork')
                            AND U2.SUMMARYGRP IS NOT NULL
                    )
                    AND EXISTS (
                        SELECT 1 FROM VEGUNIT V3
                        WHERE V3.JOBGUID = S.JOBGUID AND V3.STATNAME = S.STATNAME
                    )
                ) AS stations_no_work,
                (SELECT COUNT(*)
                FROM STATIONS S
                WHERE S.JOBGUID = A.JOBGUID
                    AND NOT EXISTS (
                        SELECT 1 FROM VEGUNIT V4
                        WHERE V4.JOBGUID = S.JOBGUID AND V4.STATNAME = S.STATNAME
                    )
                ) AS stations_not_planned,

                -- Work type breakdown (JSON from V_ASSESSMENT)
                (SELECT VA.unit, VA.UnitQty
                FROM V_ASSESSMENT VA
                WHERE VA.jobguid = A.JOBGUID
                ORDER BY VA.unit
                FOR JSON PATH) AS work_type_breakdown,

                -- Unique foresters (JSON from VEGUNIT)
                (SELECT DISTINCT VU2.FRSTR_USER AS forester
                FROM VEGUNIT VU2
                WHERE VU2.JOBGUID = A.JOBGUID
                    AND VU2.FRSTR_USER IS NOT NULL AND VU2.FRSTR_USER != ''
                FOR JSON PATH) AS foresters,

                -- Job history timeline (JSON from JOBHISTORY)
                (SELECT
                    JH.LOGDATE, JH.LOGTIME, JH.OLDSTATUS, JH.JOBSTATUS,
                    JH.USERNAME, JH.ACTION, JH.ASSIGNEDTO
                FROM JOBHISTORY JH
                WHERE JH.JOBGUID = A.JOBGUID
                    AND JH.OLDSTATUS NOT IN ('NEW', 'NRS', 'SA')
                    AND (
                        (JH.JOBSTATUS IS NOT NULL AND JH.JOBSTATUS != '')
                        OR (JH.ASSIGNEDTO IS NOT NULL AND JH.ASSIGNEDTO != '')
                    )
                ORDER BY JH.LOGDATE ASC
                FOR JSON PATH) AS job_history
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

                    -- Split assessment info
                    SC.SplitAssessmentCount AS split_count,
                    SC.SplitAssessmentUpdatedFlag AS split_updated
                FROM SS
                    INNER JOIN VEGUNIT VU ON VU.JOBGUID = SS.JOBGUID
                    LEFT JOIN SSCUSTOM SC ON SC.JOBGUID = SS.JOBGUID
                    OUTER APPLY (
                        SELECT TOP 1 VP.STATNAME, VP.UNIT, VP.SEQUENCE
                        FROM VEGUNIT VP
                        WHERE VP.JOBGUID = SS.JOBGUID
                            AND (VP.PERMSTAT IS NULL OR VP.PERMSTAT = '' OR VP.PERMSTAT = '{$pending}')
                            AND {$validUnitPending}
                        ORDER BY VP.SEQUENCE ASC
                    ) AS OldestPending
                WHERE SS.JOBGUID IN ({$jobGuidsSql})
                    AND {$validUnit}
                GROUP BY SS.JOBGUID, SS.WO, SS.EXT,
                    OldestPending.STATNAME, OldestPending.UNIT, OldestPending.SEQUENCE,
                    SC.SplitAssessmentCount, SC.SplitAssessmentUpdatedFlag
            ) AS A";
    }
}

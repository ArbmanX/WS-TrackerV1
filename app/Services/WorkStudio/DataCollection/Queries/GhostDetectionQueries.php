<?php

namespace App\Services\WorkStudio\DataCollection\Queries;

use App\Services\WorkStudio\Assessments\Queries\AbstractQueryBuilder;

class GhostDetectionQueries extends AbstractQueryBuilder
{
    /**
     * Recent ownership changes from JOBHISTORY.
     *
     * Scans JOBHISTORY for ASSIGNEDTO changes to the specified domain
     * (typically 'ONEPPL'). Scoped to the user's resource groups to
     * only return assessments the user has visibility into.
     *
     * @param  string  $domain  Domain prefix to match (e.g. 'ONEPPL')
     * @param  string  $since  ISO date string (YYYY-MM-DD) — only changes after this date
     */
    public function getRecentOwnershipChanges(string $domain, string $since): string
    {
        return "SELECT
    JH.JOBGUID,
    JH.USERNAME,
    JH.ACTION,
    JH.LOGDATE,
    JH.OLDSTATUS,
    JH.JOBSTATUS,
    JH.ASSIGNEDTO,
    SS.WO,
    SS.EXT,
    VEGJOB.LINENAME,
    VEGJOB.REGION
FROM JOBHISTORY JH
INNER JOIN SS ON SS.JOBGUID = JH.JOBGUID
INNER JOIN VEGJOB ON VEGJOB.JOBGUID = JH.JOBGUID
WHERE JH.ASSIGNEDTO LIKE '{$domain}%'
    AND JH.LOGDATE >= '{$since}'
    AND VEGJOB.REGION IN ({$this->resourceGroupsSql})
    AND SS.STATUS IN ('ACTIV', 'QC', 'REWRK')
ORDER BY JH.LOGDATE DESC";
    }

    /**
     * All UNITGUIDs with metadata for a specific assessment.
     *
     * Used to capture the baseline snapshot when ONEPPL takes ownership,
     * and for daily comparison to detect missing (deleted) units.
     * Returns one row per unit with identifying metadata for evidence records.
     */
    public function getUnitGuidsForAssessment(string $jobGuid): string
    {
        self::validateGuid($jobGuid);
        $validUnit = self::validUnitFilter('VU');

        return "SELECT
    VU.UNITGUID,
    VU.UNIT AS unit_type,
    VU.STATNAME,
    VU.PERMSTAT,
    VU.FORESTER,
    VU.FRSTR_USER
FROM VEGUNIT VU
WHERE VU.JOBGUID = '{$jobGuid}'
    AND {$validUnit}
ORDER BY VU.STATNAME, VU.UNIT";
    }

    /**
     * Assessment extension field (EXT) from SS table.
     *
     * Used to determine if an assessment is a parent (EXT = '@')
     * for the is_parent_takeover flag on ghost_ownership_periods.
     * Parent takeover blocks child→parent unit sync in WorkStudio.
     */
    public function getAssessmentExtension(string $jobGuid): string
    {
        self::validateGuid($jobGuid);

        return "SELECT
    SS.JOBGUID,
    SS.EXT,
    SS.WO,
    SS.STATUS,
    SS.TAKENBY
FROM SS
WHERE SS.JOBGUID = '{$jobGuid}'";
    }
}

<?php

namespace App\Services\WorkStudio\DataCollection\Queries;

use App\Services\WorkStudio\Assessments\Queries\AbstractQueryBuilder;

class LiveMonitorQueries extends AbstractQueryBuilder
{
    /**
     * Combined daily snapshot query for a single assessment.
     *
     * Returns one row with all metrics from a single VEGUNIT scan:
     * - Permission breakdown (PERMSTAT counts)
     * - Unit counts (work vs non-work via UNITS.SUMMARYGRP)
     * - Notes compliance (conditional on JVU area threshold)
     * - Edit recency (MAX LASTEDITDT/LASTEDITBY)
     * - Aging units (pending PERMSTAT older than threshold)
     * - Work type breakdown (FOR JSON PATH from V_ASSESSMENT)
     *
     * Replaces 6 separate API calls with 1.
     */
    public function getDailySnapshot(string $jobGuid, int $agingThresholdDays): string
    {
        self::validateGuid($jobGuid);

        $validUnit = self::validUnitFilter('VU');
        $areaThreshold = config('ws_data_collection.thresholds.notes_compliance_area_sqm');
        $parseAssddate = self::parseMsDateToDate('VU.ASSDDATE');

        return "SELECT
    -- Permission breakdown
    COUNT(*) AS total_units,
    COUNT(CASE WHEN VU.PERMSTAT = 'Approved' THEN 1 END) AS approved,
    COUNT(CASE WHEN VU.PERMSTAT IS NULL OR VU.PERMSTAT = '' OR VU.PERMSTAT = 'Pending' THEN 1 END) AS pending,
    COUNT(CASE WHEN VU.PERMSTAT = 'Refused' THEN 1 END) AS refused,
    COUNT(CASE WHEN VU.PERMSTAT = 'No Contact' THEN 1 END) AS no_contact,
    COUNT(CASE WHEN VU.PERMSTAT = 'Deferred' THEN 1 END) AS deferred,
    COUNT(CASE WHEN VU.PERMSTAT = 'PPL Approved' THEN 1 END) AS ppl_approved,

    -- Unit counts (work vs non-work)
    SUM(CASE
        WHEN U.SUMMARYGRP IS NOT NULL
            AND U.SUMMARYGRP != ''
            AND U.SUMMARYGRP != 'Summary-NonWork'
        THEN 1 ELSE 0
    END) AS work_units,
    SUM(CASE
        WHEN U.SUMMARYGRP IS NULL
            OR U.SUMMARYGRP = ''
            OR U.SUMMARYGRP = 'Summary-NonWork'
        THEN 1 ELSE 0
    END) AS nw_units,

    -- Notes compliance (guarded by area threshold)
    SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
        THEN 1 ELSE 0
    END) AS units_requiring_notes,
    SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
        AND ((VU.PARCELCOMMENTS IS NOT NULL AND DATALENGTH(VU.PARCELCOMMENTS) > 0)
            OR (VU.ASSNOTE IS NOT NULL AND DATALENGTH(VU.ASSNOTE) > 0))
        THEN 1 ELSE 0
    END) AS units_with_notes,
    SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
        AND (VU.PARCELCOMMENTS IS NULL OR DATALENGTH(VU.PARCELCOMMENTS) = 0)
        AND (VU.ASSNOTE IS NULL OR DATALENGTH(VU.ASSNOTE) = 0)
        THEN 1 ELSE 0
    END) AS units_without_notes,
    CAST(
        SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
            AND ((VU.PARCELCOMMENTS IS NOT NULL AND DATALENGTH(VU.PARCELCOMMENTS) > 0)
                OR (VU.ASSNOTE IS NOT NULL AND DATALENGTH(VU.ASSNOTE) > 0))
            THEN 1.0 ELSE 0.0
        END) / NULLIF(SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {$areaThreshold}
            THEN 1 ELSE 0 END), 0) * 100
    AS DECIMAL(5,1)) AS compliance_percent,

    -- Edit recency
    MAX(CASE WHEN VU.LASTEDITDT IS NOT NULL AND VU.LASTEDITDT != ''
        THEN VU.LASTEDITDT END) AS last_edit_date,
    MAX(CASE WHEN VU.LASTEDITDT IS NOT NULL AND VU.LASTEDITDT != ''
        THEN VU.LASTEDITBY END) AS last_edit_by,

    -- Aging units
    SUM(CASE
        WHEN (VU.PERMSTAT IS NULL OR VU.PERMSTAT = '' OR VU.PERMSTAT = 'Pending')
            AND VU.ASSDDATE IS NOT NULL
            AND VU.ASSDDATE != ''
            AND DATEDIFF(DAY, {$parseAssddate}, GETDATE()) > {$agingThresholdDays}
        THEN 1 ELSE 0
    END) AS pending_over_threshold,

    -- Work type breakdown (JSON from V_ASSESSMENT)
    (SELECT VA.unit, VA.UnitQty
     FROM V_ASSESSMENT VA
     WHERE VA.jobguid = '{$jobGuid}'
     ORDER BY VA.unit
     FOR JSON PATH) AS work_type_breakdown

FROM VEGUNIT VU
JOIN UNITS U ON U.UNIT = VU.UNIT
LEFT JOIN JOBVEGETATIONUNITS JVU
    ON JVU.JOBGUID = VU.JOBGUID
    AND JVU.STATNAME = VU.STATNAME
    AND JVU.SEQUENCE = VU.SEQUENCE
WHERE VU.JOBGUID = '{$jobGuid}'
    AND {$validUnit}";
    }
}

<?php

namespace App\Services\WorkStudio\Assessments\Queries;



class AdditionalMetricsQueries extends AbstractQueryBuilder
{

    public array $jobGuids;

    public function __construct(Array $jobGuids)
    {
        // the build method will determine what happens here
        // either they get feed one by one or chunked
        $this->jobGuids = $jobGuids;  

        // self::validateGuid($jobGuid);

    }
  
    public function build($jobGuid)
    {
        $validUnit = self::validUnitFilter('VU');

        return 
        // I need to make this a proper query and start it with the SS table and use indexes if possible
        // I need to grab the oldest pending unit while we are here..  

            "SELECT
                -- Permission breakdown
                COUNT(*) AS total_units,
                COUNT(CASE WHEN VU.PERMSTAT = 'Approved' THEN 1 END) AS approved,
                COUNT(CASE WHEN VU.PERMSTAT IS NULL OR VU.PERMSTAT = '' THEN 1 END) AS pending,
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

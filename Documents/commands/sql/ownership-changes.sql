-- ============================================================================
-- Recent Ownership Changes Query
-- ============================================================================
-- Source:  GhostDetectionQueries::getRecentOwnershipChanges()
-- Docs:   ../GhostDetectionQueries.md
-- Target:  WorkStudio DDOProtocol API (T-SQL)
--
-- Scans JOBHISTORY for assessments recently assigned to a specific domain
-- (typically ONEPPL). Used to detect ownership takeovers that need baseline
-- snapshots for ghost unit tracking.
-- ============================================================================

-- Variables injected by PHP:
--   {$domain}             — Domain prefix, e.g. 'ONEPPL'
--   {$since}              — ISO date string (YYYY-MM-DD)
--   {$resourceGroupsSql}  — SQL IN clause from AbstractQueryBuilder (region filter)

SELECT
    JH.JOBGUID,
    JH.USERNAME,                     -- who made the change
    JH.ACTION,                       -- action type
    JH.LOGDATE,                      -- when it happened
    JH.OLDSTATUS,                    -- status before change
    JH.JOBSTATUS,                    -- status after change
    JH.ASSIGNEDTO,                   -- new owner (ONEPPL\username)
    SS.WO,                           -- work order number
    SS.EXT,                          -- extension (@ = parent)
    VEGJOB.LINENAME,                 -- circuit/line name
    VEGJOB.REGION                    -- regional grouping

FROM JOBHISTORY JH
    INNER JOIN SS
        ON SS.JOBGUID = JH.JOBGUID
    INNER JOIN VEGJOB
        ON VEGJOB.JOBGUID = JH.JOBGUID

WHERE JH.ASSIGNEDTO LIKE '{domain}%'         -- ownership changed to target domain
    AND JH.LOGDATE >= '{since}'               -- only recent changes
    AND VEGJOB.REGION IN ({resourceGroupsSql}) -- scoped to visible regions
    AND SS.STATUS IN ('ACTIV', 'QC', 'REWRK') -- only live assessments

ORDER BY JH.LOGDATE DESC

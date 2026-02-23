-- ============================================================================
-- Daily Snapshot Query
-- ============================================================================
-- Source:  LiveMonitorQueries::getDailySnapshot()
-- Docs:   ../LiveMonitorQueries.md
-- Target:  WorkStudio DDOProtocol API (T-SQL)
--
-- Returns ONE ROW per assessment with all daily health metrics.
-- Replaces 6 separate API calls with a single combined query.
-- ============================================================================

-- Variables injected by PHP:
--   {$jobGuid}            — Assessment GUID (validated via regex)
--   {$validUnit}          — SqlFragmentHelpers::validUnitFilter('VU')
--   {$areaThreshold}      — config('ws_data_collection.thresholds.notes_compliance_area_sqm')
--   {$castEditDate}       — WSSQLCaster::cast('VU.EDITDATE')  [OLE → DATETIME]
--   {$parseAssddate}      — SqlFragmentHelpers::parseMsDateToDate('VU.ASSDDATE')
--   {$agingThresholdDays} — config('ws_data_collection.thresholds.aging_unit_days')

SELECT

    -- ┌─────────────────────────────────────────┐
    -- │  Permission Breakdown (PERMSTAT counts)  │
    -- └─────────────────────────────────────────┘
    COUNT(*)                                                             AS total_units,
    COUNT(CASE WHEN VU.PERMSTAT = 'Approved'                  THEN 1 END) AS approved,
    COUNT(CASE WHEN VU.PERMSTAT IS NULL
               OR VU.PERMSTAT = ''
               OR VU.PERMSTAT = 'Pending'                     THEN 1 END) AS pending,
    COUNT(CASE WHEN VU.PERMSTAT = 'Refused'                   THEN 1 END) AS refused,
    COUNT(CASE WHEN VU.PERMSTAT = 'No Contact'                THEN 1 END) AS no_contact,
    COUNT(CASE WHEN VU.PERMSTAT = 'Deferred'                  THEN 1 END) AS deferred,
    COUNT(CASE WHEN VU.PERMSTAT = 'PPL Approved'              THEN 1 END) AS ppl_approved,

    -- ┌─────────────────────────────────────────┐
    -- │  Unit Counts (work vs non-work)          │
    -- │  Determined by UNITS.SUMMARYGRP          │
    -- └─────────────────────────────────────────┘
    SUM(CASE
        WHEN U.SUMMARYGRP IS NOT NULL
            AND U.SUMMARYGRP != ''
            AND U.SUMMARYGRP != 'Summary-NonWork'
        THEN 1 ELSE 0
    END)                                                                 AS work_units,
    SUM(CASE
        WHEN U.SUMMARYGRP IS NULL
            OR U.SUMMARYGRP = ''
            OR U.SUMMARYGRP = 'Summary-NonWork'
        THEN 1 ELSE 0
    END)                                                                 AS nw_units,

    -- ┌─────────────────────────────────────────┐
    -- │  Notes Compliance                        │
    -- │  Only units with AREA >= threshold       │
    -- │  need notes (PARCELCOMMENTS or ASSNOTE)  │
    -- └─────────────────────────────────────────┘
    SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {areaThreshold}
        THEN 1 ELSE 0
    END)                                                                 AS units_requiring_notes,

    SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {areaThreshold}
        AND ((VU.PARCELCOMMENTS IS NOT NULL AND DATALENGTH(VU.PARCELCOMMENTS) > 0)
            OR (VU.ASSNOTE IS NOT NULL AND DATALENGTH(VU.ASSNOTE) > 0))
        THEN 1 ELSE 0
    END)                                                                 AS units_with_notes,

    SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {areaThreshold}
        AND (VU.PARCELCOMMENTS IS NULL OR DATALENGTH(VU.PARCELCOMMENTS) = 0)
        AND (VU.ASSNOTE IS NULL OR DATALENGTH(VU.ASSNOTE) = 0)
        THEN 1 ELSE 0
    END)                                                                 AS units_without_notes,

    CAST(
        SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {areaThreshold}
            AND ((VU.PARCELCOMMENTS IS NOT NULL AND DATALENGTH(VU.PARCELCOMMENTS) > 0)
                OR (VU.ASSNOTE IS NOT NULL AND DATALENGTH(VU.ASSNOTE) > 0))
            THEN 1.0 ELSE 0.0
        END) / NULLIF(SUM(CASE WHEN JVU.AREA IS NOT NULL AND JVU.AREA >= {areaThreshold}
            THEN 1 ELSE 0 END), 0) * 100
    AS DECIMAL(5,1))                                                     AS compliance_percent,

    -- ┌─────────────────────────────────────────┐
    -- │  Planner Activity (edit recency)         │
    -- │  EDITDATE is OLE float → cast to DATETIME│
    -- └─────────────────────────────────────────┘
    MAX(CASE WHEN VU.EDITDATE IS NOT NULL AND VU.EDITDATE != '' AND VU.EDITDATE != 0
        THEN {castEditDate} END)                                         AS last_edit_date,
    MAX(CASE WHEN VU.EDITDATE IS NOT NULL AND VU.EDITDATE != '' AND VU.EDITDATE != 0
        THEN VU.LASTEDITBY END)                                          AS last_edit_by,

    -- ┌─────────────────────────────────────────┐
    -- │  Aging Units                             │
    -- │  Pending permission > threshold days     │
    -- │  ASSDDATE uses /Date(...)/ wrapper       │
    -- └─────────────────────────────────────────┘
    SUM(CASE
        WHEN (VU.PERMSTAT IS NULL OR VU.PERMSTAT = '' OR VU.PERMSTAT = 'Pending')
            AND VU.ASSDDATE IS NOT NULL
            AND VU.ASSDDATE != ''
            AND DATEDIFF(DAY, {parseAssddate}, GETDATE()) > {agingThresholdDays}
        THEN 1 ELSE 0
    END)                                                                 AS pending_over_threshold,

    -- ┌─────────────────────────────────────────┐
    -- │  Work Type Breakdown (JSON subquery)     │
    -- │  Returns [{unit, UnitQty}, ...]          │
    -- └─────────────────────────────────────────┘
    (SELECT VA.unit, VA.UnitQty
     FROM V_ASSESSMENT VA
     WHERE VA.jobguid = '{jobGuid}'
     ORDER BY VA.unit
     FOR JSON PATH)                                                      AS work_type_breakdown

-- ┌─────────────────────────────────────────────┐
-- │  Table Joins                                 │
-- └─────────────────────────────────────────────┘
FROM VEGUNIT VU
    JOIN UNITS U
        ON U.UNIT = VU.UNIT
    LEFT JOIN JOBVEGETATIONUNITS JVU
        ON JVU.JOBGUID = VU.JOBGUID
        AND JVU.STATNAME = VU.STATNAME
        AND JVU.SEQUENCE = VU.SEQUENCE

-- ┌─────────────────────────────────────────────┐
-- │  Filters                                     │
-- └─────────────────────────────────────────────┘
WHERE VU.JOBGUID = '{jobGuid}'
    AND {validUnit}

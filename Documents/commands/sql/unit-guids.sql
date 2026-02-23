-- ============================================================================
-- Unit GUIDs for Assessment
-- ============================================================================
-- Source:  GhostDetectionQueries::getUnitGuidsForAssessment()
-- Docs:   ../GhostDetectionQueries.md
-- Target:  WorkStudio DDOProtocol API (T-SQL)
--
-- Returns all valid vegetation units for a single assessment.
-- Used in two contexts:
--   1. Baseline capture — snapshot all UNITGUIDs when ONEPPL takes ownership
--   2. Daily comparison — fetch current UNITGUIDs to diff against baseline
--
-- The set-difference (baseline - current - already_detected) reveals
-- ghost units that were deleted after takeover.
-- ============================================================================

-- Variables injected by PHP:
--   {$jobGuid}    — Assessment GUID (validated via validateGuid() regex)
--   {$validUnit}  — SqlFragmentHelpers::validUnitFilter('VU')

SELECT
    VU.UNITGUID,                     -- unique identifier for this vegetation unit
    VU.UNIT        AS unit_type,     -- unit classification (e.g. Trim, Removal)
    VU.STATNAME,                     -- station name (physical location)
    VU.PERMSTAT,                     -- permission status at query time
    VU.FORESTER,                     -- assigned forester name
    VU.FRSTR_USER                    -- forester username

FROM VEGUNIT VU

WHERE VU.JOBGUID = '{jobGuid}'
    AND {validUnit}                  -- excludes invalid/deleted units

ORDER BY VU.STATNAME, VU.UNIT

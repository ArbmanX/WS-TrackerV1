<?php

namespace App\Services\WorkStudio\Assessments\Queries;



// <!-- -- Winning Unit Query: First-assessed unit per station for Assessment Dx jobs
// -- Returns flat rows sorted for User → WO → Date grouping
// -- DDOProtocol-safe: no CTEs, single SELECT, read-only
// --
// -- "Winning unit" = the VEGUNIT with the OLDEST ASSDDATE on each station
// -- Split assessment logic: if a WO has non-@ extensions, exclude the @ parent
// -- Coords: prefer station XCOORD/YCOORD (100% from 2022+, 85% unique),
// --         fall back to unit ASSLAT/ASSLONG when station coords missing -->
class WinningUnitQuery
{
    /**
     * Build the T-SQL query for fetching the "winning unit" per station for Assessment Dx jobs.
     *
     * Output columns: JOBGUID, FRSTR_USER, WO, EXT, ASSESS_DATE (date part of ASSDDATE), STATNAME,
     * SEQUENCE, UNITGUID, UNIT, LAT (YCOORD or ASSLAT), [LONG] (XCOORD or ASSLONG),
     * COORD_SOURCE ('station' or 'unit'), SPANLGTH (station span length in meters),
     * SPAN_MILES (calculated miles).
     *
     * Results are grouped by all output columns to deduplicate rows from the
     * VEGUNIT × STATIONS × SS join, then ordered by User → WO → Date.
     *
     * NOTE: The DDOProtocol API does not support CTEs (WITH...AS).
     * All queries must use derived tables (subqueries) instead.
     *
     * @return string  The T-SQL query string to execute against the WorkStudio database
     */
    public static function build(array $jobGuids): string
    {
        $uids = implode("','", $jobGuids);
        return "SELECT
                w.JOBGUID,
                w.FRSTR_USER,
                w.WO,
                w.EXT,
                CAST(w.ASSDDATE AS DATE) AS ASSESS_DATE,
                w.STATNAME,
                w.SEQUENCE,
                w.UNITGUID,
                w.UNIT,
                COALESCE(w.YCOORD, w.ASSLAT) AS LAT,
                COALESCE(w.XCOORD, w.ASSLONG) AS [LONG],
                CASE WHEN w.YCOORD IS NOT NULL THEN 'station' ELSE 'unit' END AS COORD_SOURCE,
                w.SPANLGTH,
                (w.SPANLGTH * 3.28084) / 5280.0 AS SPAN_MILES
            FROM (
                -- Middle layer: rank units per station, pick oldest ASSDDATE
                SELECT
                    ranked.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY ranked.JOBGUID, ranked.STATNAME
                        ORDER BY ranked.ASSDDATE ASC
                    ) AS RN
                FROM (
                    -- Inner layer: Assessment Dx jobs with split exclusion
                    SELECT
                        vu.UNITGUID,
                        vu.JOBGUID,
                        vu.WO,
                        vu.EXT,
                        vu.STATNAME,
                        vu.SEQUENCE,
                        vu.ASSDDATE,
                        vu.UNIT,
                        vu.ASSLAT,
                        vu.ASSLONG,
                        st.SPANLGTH,
                        vu.FRSTR_USER,
                        st.XCOORD,
                        st.YCOORD
                    FROM VEGUNIT vu
                    INNER JOIN STATIONS st
                        ON vu.JOBGUID = st.JOBGUID AND vu.STATNAME = st.STATNAME
                    INNER JOIN SS ss
                        ON vu.JOBGUID = ss.JOBGUID
                    WHERE ss.JOBGUID IN ('{$uids}')
                    AND vu.ASSDDATE IS NOT NULL
                    -- Split exclusion: drop '@' parent ONLY when split children exist for same WO
                    AND NOT (
                        ss.EXT = '@'
                        AND EXISTS (
                            SELECT 1 FROM SS s2
                            WHERE s2.WO = ss.WO
                                AND s2.JOBGUID IN ('{$uids}')
                                AND s2.EXT <> '@'
                        )
                    )
                ) ranked
            ) w
            WHERE w.RN = 1
            GROUP BY w.JOBGUID, w.FRSTR_USER, w.WO, w.EXT, w.ASSDDATE, w.STATNAME,
                     w.SEQUENCE, w.UNITGUID, w.UNIT, w.YCOORD, w.ASSLAT,
                     w.XCOORD, w.ASSLONG, w.SPANLGTH
            ORDER BY w.FRSTR_USER, w.WO, CAST(w.ASSDDATE AS DATE)";
    }
}

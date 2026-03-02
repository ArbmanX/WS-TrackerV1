-- Winning Unit Query: First-assessed unit per station for Assessment Dx jobs
-- Returns flat rows sorted for User → WO → Date grouping
-- DDOProtocol-safe: no CTEs, single SELECT, read-only
--
-- "Winning unit" = the VEGUNIT with the OLDEST ASSDDATE on each station
-- Split assessment logic: if a WO has non-@ extensions, exclude the @ parent
-- Coords: prefer station XCOORD/YCOORD (100% from 2022+, 85% unique),
--         fall back to unit ASSLAT/ASSLONG when station coords missing

SELECT
    w.FRSTR_USER,
    w.WO,
    w.EXT,
    CAST(w.ASSDDATE AS DATE) AS ASSESS_DATE,
    w.STATNAME,
    w.SEQUENCE,
    w.UNITGUID,
    w.UNIT,
    COALESCE(w.YCOORD, w.ASSLAT) AS LAT,
    COALESCE(w.XCOORD, w.ASSLONG) AS LONG,
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
            vu.FRSTR_USER,
            vu.WO,
            vu.EXT,
            vu.ASSDDATE,
            vu.STATNAME,
            vu.SEQUENCE,
            vu.UNITGUID,
            vu.UNIT,
            vu.ASSLAT,
            vu.ASSLONG,
            vu.JOBGUID,
            st.SPANLGTH,
            st.XCOORD,
            st.YCOORD
        FROM VEGUNIT vu
        INNER JOIN STATIONS st
            ON vu.JOBGUID = st.JOBGUID AND vu.STATNAME = st.STATNAME
        INNER JOIN SS ss
            ON vu.JOBGUID = ss.JOBGUID
        WHERE ss.JOBTYPE = 'Assessment Dx'
          AND vu.ASSDDATE IS NOT NULL
          -- Split exclusion: drop '@' parent ONLY when split children exist for same WO
          AND NOT (
              ss.EXT = '@'
              AND EXISTS (
                  SELECT 1 FROM SS s2
                  WHERE s2.WO = ss.WO
                    AND s2.JOBTYPE = 'Assessment Dx'
                    AND s2.EXT <> '@'
              )
          )
    ) ranked
) w
WHERE w.RN = 1
ORDER BY w.FRSTR_USER, w.WO, w.ASSDDATE

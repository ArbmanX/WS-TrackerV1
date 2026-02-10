# Tinker Queries — Unit Count & Unit Types Exploration

> Session: 2026-02-09
> Context: Exploring UNITS table and DailyFootageQuery for unit_count feature

---

## 1. UNITS Table Sample (TOP 20)

```sql
SELECT TOP 20 UNIT, UNITSSNAME, UNITSETID, SUMMARYGRP, ENTITYNAME
FROM UNITS
ORDER BY UNIT
```

| UNIT | UNITSSNAME | UNITSETID | SUMMARYGRP | ENTITYNAME |
|------|-----------|-----------|------------|------------|
| 1PB | 1P/SP Bucket | Vegetation | Summary-TRIM | Tree Unit |
| 1PM | 1P/SP Manual | Vegetation | Summary-TRIM | Tree Unit |
| 3PB | 3P OW Bucket | Vegetation | Summary-TRIM | Tree Unit |
| 3PM | 3P OW Manual | Vegetation | Summary-TRIM | Tree Unit |
| ACSRD | Access Road | Vegetation | Summary-NonWork | Veg Span Symbol |
| AI | Aerial Inspection | Vegetation | | |
| ASH1218 | Ash 12.1-18" | Vegetation | Summary-REMOVAL | Tree Unit |
| ASH1824 | Ash 18.1 -24" | Vegetation | Summary-REMOVAL | Tree Unit |
| ASH2430 | Ash 24.1 - 30" | Vegetation | Summary-REMOVAL | Tree Unit |
| ASH3036 | Ash 30.1-36" | Vegetation | Summary-REMOVAL | Tree Unit |
| ASH36 | Ash > 36.1" | Vegetation | Summary-REMOVAL | Tree Unit |
| ASH612 | Ash 6-12" | Vegetation | Summary-REMOVAL | Tree Unit |
| AUD | Audit | Vegetation | SummaryAudit | Veg Point Symbol |
| BRDG | Bridge | Vegetation | | Veg Span Symbol |
| BRUSH | Hand Cut Brush/Mowin | Vegetation | Summary | Veg Poly Symbol |
| BRUSHTRIM | Hand Cut Brush w Tri | Vegetation | Summary | Veg Poly Symbol |
| BTR | Bucket Trim | BLANK | Summary-TRIM | Tree Unit |
| BUF | Buffer | Vegetation | Summary-NonWork | Veg Poly Symbol |
| Canopy | | Explorer | | Canopy |
| Clearance | | Explorer | | |

---

## 2. SUMMARYGRP Distribution

```sql
SELECT SUMMARYGRP, COUNT(*) AS cnt
FROM UNITS
GROUP BY SUMMARYGRP
ORDER BY SUMMARYGRP
```

| SUMMARYGRP | Count |
|------------|-------|
| (empty) | 19 |
| Removal | 1 |
| Summary | 13 |
| Summary-NonWork | 46 |
| Summary-REMOVAL | 20 |
| Summary-TRIM | 23 |
| SummaryAudit | 1 |
| **TOTAL** | **123** |

**work_unit derivation:**
- `true` = SUMMARYGRP is populated AND not `Summary-NonWork` (58 units)
- `false` = SUMMARYGRP is empty/null OR `Summary-NonWork` (65 units)

---

## 3. All Summary-NonWork Units (46)

```sql
SELECT UNIT, UNITSSNAME, ENTITYNAME
FROM UNITS
WHERE SUMMARYGRP = 'Summary-NonWork'
ORDER BY UNIT
```

| UNIT | UNITSSNAME | ENTITYNAME |
|------|-----------|------------|
| ACSRD | Access Road | Veg Span Symbol |
| BUF | Buffer | Veg Poly Symbol |
| CLN | Clean | Veg Point Symbol |
| COMP | Complaint | Veg Poly Symbol |
| CPP | County/City Park | Veg Poly Symbol |
| EA | Animals | Veg Poly Symbol |
| EATV | ATV | Veg Point Symbol |
| EB | Boat | Veg Point Symbol |
| EC | Car | Veg Point Symbol |
| ECA | Construction Activit | Veg Poly Symbol |
| EF | Fence | Veg Span Symbol |
| EG | Garden | Veg Poly Symbol |
| EO | Other | Veg Poly Symbol |
| EPL | Parking Lot | Veg Poly Symbol |
| ERP | Retention Pond | Veg Poly Symbol |
| ERV | RV | Veg Point Symbol |
| ESD | Shed | Veg Poly Symbol |
| ESN | Sign | Veg Point Symbol |
| ESP | Swimming Pool | Veg Poly Symbol |
| ESS | Swing Set | Veg Point Symbol |
| ET | Truck | Veg Point Symbol |
| ETR | Trails | Veg Poly Symbol |
| EWS | Well/Septic | Veg Point Symbol |
| GATE | Gate | Veg Point Symbol |
| GOV | Government Property | Veg Poly Symbol |
| INS | Install | Veg Point Symbol |
| JD | Junk/Debris | Veg Poly Symbol |
| MILI | Military Property | Veg Poly Symbol |
| NOT | Notification | Veg Point Symbol |
| NW | No Work Needed | Veg Span Symbol |
| OTHER | Other | Veg Poly Symbol |
| PD | Property Damage | Veg Poly Symbol |
| RD | Remove Debris | Veg Point Symbol |
| RPC | Replace | Veg Point Symbol |
| RPR | Repair | Veg Point Symbol |
| SCROSSARM | Cross Arm | Veg Point Symbol |
| SENSI | Sensitive Customer | Veg Poly Symbol |
| SFUSE | Fuse | Veg Point Symbol |
| SPOLE | Pole | Veg Point Symbol |
| SPP | State Park | Veg Poly Symbol |
| SSI | Safety Issue | Veg Point Symbol |
| STRANSFORMER | Transformer | Veg Point Symbol |
| SVEG | Vegetation | Veg Point Symbol |
| SWIRE | Wire | Veg Point Symbol |
| VEG ENCROACHMENT | VEG ENCROACHMENT | Veg Span Symbol |
| WL | Wetland | Veg Poly Symbol |

---

## 4. Empty/NULL SUMMARYGRP Units (19)

```sql
SELECT UNIT, UNITSSNAME, UNITSETID, SUMMARYGRP, ENTITYNAME
FROM UNITS
WHERE SUMMARYGRP IS NULL OR SUMMARYGRP = '' OR LEN(SUMMARYGRP) = 0
ORDER BY UNIT
```

| UNIT | UNITSSNAME | UNITSETID | ENTITYNAME |
|------|-----------|-----------|------------|
| AI | Aerial Inspection | Vegetation | |
| BRDG | Bridge | Vegetation | Veg Span Symbol |
| Canopy | | Explorer | Canopy |
| Clearance | | Explorer | |
| CLVT | Culvert | Vegetation | Veg Span Symbol |
| DMGASSET | Damage Location | Vegetation | Damaged Asset |
| ERSN | Erosion | Vegetation | Veg Span Symbol |
| FCD | | Vegetation | Polygon Unit |
| FENCE | Fence | Vegetation | Veg Span Symbol |
| FORDS | Fords | Vegetation | Veg Span Symbol |
| GI | Ground Inspection | Vegetation | Polygon Unit |
| GIO | Canopy Segment | Vegetation | Veg Poly Symbol |
| HTI | Hazard Tree Inspect | Vegetation | Veg Point Symbol |
| MGI | Mid-Year Ground Insp | Vegetation | Polygon Unit |
| OTH | Other | Vegetation | Veg Poly Symbol |
| TRASH | Trash | Vegetation | Veg Point Symbol |
| VEGBUCKET | | Vegetation | |
| VEGCHEM | | Vegetation | |
| VEGLABOR | | Vegetation | |

---

## 5. Existing DailyFootageQuery Baseline (1 JOBGUID)

```sql
-- DailyFootageQuery::build(['{69AF142D-172A-4E47-93CA-E0F6862FC265}'])
-- (standard query — no unit_count yet)
```

| JOBGUID | completion_date | FRSTR_USER | daily_footage_meters | station_list (truncated) |
|---------|----------------|------------|---------------------|--------------------------|
| {69AF142D-...} | /Date(2026-02-06)/ | ASPLUNDH\tgibson | 12914.67 | 10,100,1000,...(300+ stations) |
| {69AF142D-...} | /Date(2026-02-07)/ | ASPLUNDH\tgibson | 180.65 | 800,Ex 15103_219,Ex 15103_447,Ex 15103_448 |

---

## 6. First-Unit per Station — Raw UNIT Values (TOP 30)

```sql
SELECT TOP 30
    FU.JOBGUID, FU.completion_date, FU.FRSTR_USER, FU.STATNAME, FU.UNIT
FROM (
    SELECT VU.JOBGUID, VU.STATNAME,
        COALESCE(
            CAST(CAST(REPLACE(REPLACE(VU.DATEPOP, '/Date(', ''), ')/', '') AS DATETIME) AS DATE),
            CAST(CAST(REPLACE(REPLACE(VU.ASSDDATE, '/Date(', ''), ')/', '') AS DATETIME) AS DATE)
        ) AS completion_date,
        VU.FRSTR_USER, VU.UNIT,
        ROW_NUMBER() OVER (
            PARTITION BY VU.JOBGUID, VU.STATNAME
            ORDER BY COALESCE(VU.DATEPOP, VU.ASSDDATE) ASC
        ) AS unit_rank
    FROM VEGUNIT VU
    WHERE VU.UNIT IS NOT NULL AND VU.UNIT != ''
      AND (VU.DATEPOP IS NOT NULL OR VU.ASSDDATE IS NOT NULL)
      AND VU.JOBGUID IN ('{69AF142D-172A-4E47-93CA-E0F6862FC265}')
) FU
WHERE FU.unit_rank = 1
ORDER BY FU.completion_date, FU.STATNAME
```

| STATNAME | UNIT | completion_date | FRSTR_USER |
|----------|------|----------------|------------|
| 10 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 100 | MPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1000 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1010 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1020 | MPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1030 | MPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1040 | MPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1050 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1060 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1070 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1080 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1090 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 110 | MPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1100 | SPM | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1110 | REM612 | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1120 | SPM | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1130 | REM612 | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1140 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1150 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1160 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1170 | SPM | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1180 | REM612 | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1190 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 120 | MPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 1200 | MPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 130 | MPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 140 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 150 | SPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 160 | SPM | /Date(2026-02-06)/ | ASPLUNDH\tgibson |
| 170 | MPB | /Date(2026-02-06)/ | ASPLUNDH\tgibson |

**Unit distribution in sample:** SPB: 14, MPB: 9, SPM: 4, REM612: 3 — all are work units (Summary-TRIM / Summary-REMOVAL)

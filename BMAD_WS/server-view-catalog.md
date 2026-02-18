# WorkStudio Server View Catalog

> Verified live via DDOProtocol on 2026-02-18. All 33 views queryable with `SELECT * FROM [ViewName]`.

## CTE Note

**DDOProtocol does NOT support CTE queries** (`WITH ... AS`) — they silently return empty results. However, CDB-defined views (PctWorkComplete, PctAuditedUnits, CP_Units) use CTEs internally because they execute server-side on SQL Server directly. We can query these views by name through DDOProtocol without issue.

---

## Server Views (server-views.sql) — 10 views

These are `CREATE VIEW` statements installed directly on SQL Server.

| View | Cols | Key Columns | Purpose |
|------|------|-------------|---------|
| **worktocomplete** | 23 | wo, ext, descriptio, gf, contractor, crew_date, assddate, objectid | Units ready for crew (UNIT_CLASS=0, TAKEN=0, excludes VNW) |
| **worktoassign** | 29 | wo, ext, geometry, region, JOBTYPE, gf_user, objectid | Units dispatched for work (UNIT_CLASS=2, TAKEN=0, excludes VNW/DEF/REWRK/AUDIT_FAIL) |
| **unitcounts** | 6 | JOBGUID, ACRES, AREA, LENGTHWRK, MANHOURS, NUMTREES | Aggregated JVU measurements per job |
| **AssessmentJobData** | 9 | JOBGUID, WO, EXT, PJOBGUID, STATUS, TITLE, JOBTYPE, PRCENT, ASSIGNEDTO | Assessment job overview (JOBTYPE='Assessment') |
| **VEGCOMPMET** | 0* | stationcount, assessed_stations, wrkcmpdpct | Completion metrics v1 (*empty — superseded by VegCompMet2) |
| **WOSTATUSSUMMARY** | 10 | WorkOrder, CurrentStatus, Description, Logdate, Logtime, USERNAME, ASSIGNEDTO | Latest JOBHISTORY status per job |
| **V_Assessment** | 9 | wo, ext, jobguid, pjobguid, unit, UnitQty | Assessment unit quantity aggregates (JOBTYPE LIKE 'Assessment%') |
| **V_Work** | 9 | wo, ext, jobguid, pjobguid, unit, UnitQty | Work unit quantity aggregates (JOBTYPE LIKE 'Work%') |
| **V_AssessmentToWork** | 17 | A_JobGuid, A_UnitQty, W_JobGuid, W_PJobGuid, W_UnitQty | Assessment↔Work mapping via PJOBGUID + UNIT (FULL OUTER JOIN) |
| **V_SearchAssessmentToWork** | 83 | Full SS columns + A↔W mapping | Enriched assessment search with work linkage |

### Key SQL Patterns
- `worktoassign`: 7-table canonical join (VEGUNIT→VEGSTAT→UNITS→JVU→SSUNITS→SS→VEGJOB)
- `worktocomplete`: 6-table join (same minus VEGJOB)
- `unitcounts`: Simple GROUP BY on JOBVEGETATIONUNITS
- `WOSTATUSSUMMARY`: Self-join JOBHISTORY for latest status with JOBSTATUS lookup
- `V_AssessmentToWork`: FULL OUTER JOIN on `A.JobGuid = W.PJOBGUID AND A.unit = W.unit`

---

## Cost & Completion Views — 8 views

Pre-aggregated at project level (PJOBGUID). Ideal for dashboard rollups.

| View | Cols | Key Columns | Purpose |
|------|------|-------------|---------|
| **PctWorkComplete** | 16 | ProjJobGUID, EstAssessedCost, ApprovedCost, TotAddedUnitCost, TotRemainingUnitCost, EstPctWorkCompl | Full project cost completion (CTE-based, uses ITEMRATE) |
| **ProjectCostRollup** | 5 | PJOBGUID, RawCost, ApprovedCost, TMApprovedCost, UnitApprovedCost | Assessment-side cost summary |
| **ProjectCost_Audited** | 5 | PJOBGUID, RawCost, ApprovedCost, TMApprovedCost, UnitApprovedCost | Audited cost version |
| **WorkJobCostRollup** | 5 | JOBGUID, RawCost, ApprovedCost, TMApprovedCost, UnitApprovedCost | Work job cost summary |
| **EstimatedUnitCost2** | 2 | WP_JOBGUID, Cost_Est_SUM | Estimated cost per project |
| **AssessedUnitCost** | 2 | PJOBGUID, AssessedUnitCost | Assessed cost per project |
| **ProjectWorkJobStatus** | 2 | WP_JOBGUID, Work_Percent_Closed | % of work jobs closed per project |
| **VegCompMet2** | 12 | PJOBGUID, stationcount, assessed_stations, no_work_count, work_stationcount, work_incomplete_stations, assmtpct, passdwrk, wrkcmpdpct | Vegetation completion metrics (stations, miles, percentages) |

### Dashboard Usage
```sql
-- Project completion dashboard: one row per project with cost + completion
SELECT p.ProjJobGUID, p.EstAssessedCost, p.ApprovedCost, p.EstPctWorkCompl,
       m.stationcount, m.assessed_stations, m.assmtpct, m.wrkcmpdpct
FROM PctWorkComplete p
LEFT JOIN VegCompMet2 m ON p.ProjJobGUID = m.PJOBGUID
```

---

## Audit Views — 3 views

| View | Cols | Key Columns | Purpose |
|------|------|-------------|---------|
| **PctAuditedUnits** | 6 | AuditedUnits_JOBGUID, AuditedUnits_WO, Percent_Units_Pass, Percent_Units_Fail, Percent_Units_UnAudited, Total_Pct_Audited | Assessment-side audit % (CTE-based) |
| **PctWorkAuditedUnits** | 6 | Same column structure | Work-side audit % |
| **VegUnitAudited** | 4 | WPJobGUID, AudAcres, AudLength, AudTrees | Audited unit measurement totals |

---

## Crew & Resource Views — 3 views

| View | Cols | Key Columns | Purpose |
|------|------|-------------|---------|
| **GFCREWS** | 9 | RELTYPE, GFGUID, GFFIRSTNAME, GFLASTNAME, GFUSERNAME, CREWGUID | GF-to-crew mapping via RELATIONSHIPS table |
| **ResourcePerson** | 14 | GROUPGUID, GROUPID, GROUPTYPE, GROUPDESC, GUID | Person-to-resource group linkage |
| **CP_Hours** | 28 | JobGuid, WO, JobType, Status, GroupGUID | Contractor payroll hours (Contractor Payroll, Storm Work, Capital/Special Projects) |

---

## Contractor & Reporting Views — 4 views

| View | Cols | Key Columns | Purpose |
|------|------|-------------|---------|
| **WORKCONTRACTOR** | 33 | VEGJOB_JOBGUID, SS_EXT, SS_JOBTYPE, SS_STATUS | Jobs grouped by contractor |
| **WORKCONTRACTOR_PROJECT** | 33 | SS_PJOBGUID, SS_JOBTYPE, SS_STATUS | Jobs grouped by project |
| **CP_Units** | 10 | WO, Ext, Crew_Date, GF_User, Unit, Unit_Class | Contractor payroll unit data (Quantity = Coalesce(Area, NumTrees, LengthWrk)) |
| **v_ECIReport** | 15 | WO, LINENAME, LENGTH, LENGTHCOMP, PRCENT, NUMTREES, FORESTER, CONTRACTOR | ECI contractor assessment report (hardcoded LIKE '%ECI%') |

### Note on v_ECIReport
Hardcoded to ECI contractor. Only returns assessment-type jobs (Assessment, Assessment Dx, Tandem_Assessment) in non-CLOSE statuses. As of 2026-02-18: 16 rows, all DEF status. Use the underlying SQL pattern with dynamic contractor filter for a universal version.

---

## Planning & Project Views — 3 views

| View | Cols | Key Columns | Purpose |
|------|------|-------------|---------|
| **WPPROJECTS** | 62 | WO, EXT, STARTDATE, GROUPTITLE | WorkPlanner projects (AppAttributes: NoBuildWSClient) |
| **SCENARIOPROJECTS** | 81 | WO, EXT, STARTDATE, GROUPTITLE | Scenario project data |
| **WPStartDate_Assessment_Xrefs** | 3 | WP_STARTDATE, WP_JOBGUID, Assess_JOBGUID | Work plan → assessment cross-reference (scope year derivation) |

---

## Other Views — 2 views

| View | Cols | Key Columns | Purpose |
|------|------|-------------|---------|
| **WSWEBUNPROCESSED** | 48 | CREWGUID, USERID, UNDO, DONE, ROWINDEX, WS_PROCESSED | Pending VegWorker mobile submissions (self-join for latest per job+unit) |
| **ASSESSMENTJOBDATA** | 9 | JOBGUID, WO, EXT, PJOBGUID, STATUS, TITLE, JOBTYPE, PRCENT, ASSIGNEDTO | Assessment job data (mirror of AssessmentJobData with different casing) |
| **JOBUSAGEROLLUP** | 25 | ITEMUSAGEGUID, CREWGUID, BILLABLE, ITEMID, JOBGUID, RATECODE | Item usage and cost rollup |

---

## Quick Reference: Best Views for Dashboard Features

| Dashboard Feature | Best View(s) | Why |
|---|---|---|
| **Circuit completion %** | VegCompMet2 | Pre-calculated station counts + percentages |
| **Project cost tracking** | PctWorkComplete + ProjectCostRollup | Estimated vs approved vs remaining |
| **Audit compliance** | PctAuditedUnits + PctWorkAuditedUnits | Pass/fail/unaudited percentages |
| **Unit pipeline** | worktoassign (CLASS=2) + worktocomplete (CLASS=0) | Ready-made 7-table joins |
| **Job status history** | WOSTATUSSUMMARY | Latest status with timestamps |
| **Assessment↔Work linkage** | V_AssessmentToWork | FULL OUTER JOIN with unit quantities |
| **Crew structure** | GFCREWS + ResourcePerson | GF→crew and person→group mappings |
| **Scope year** | WPStartDate_Assessment_Xrefs | WorkPlan→Assessment cross-reference |
| **Contractor progress** | WORKCONTRACTOR + v_ECIReport pattern | Jobs by contractor with completion data |
| **Pending mobile sync** | WSWEBUNPROCESSED | Unprocessed VegWorker submissions |

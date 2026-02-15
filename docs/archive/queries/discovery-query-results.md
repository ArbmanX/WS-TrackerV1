# Discovery Query Results
Generated: 2026-02-06 00:10:17

## Query 1: JOBHISTORY ACTION Enumeration

Purpose: Identify all history event types to find QC-related events

| ACTION | Event Count | Circuits Affected | Sample Comment 1 | Sample Comment 2 |
|--------|-------------|-------------------|------------------|------------------|

**Total rows:** 0

---

## Query 2: Sample Closed Circuit Timeline

Purpose: View full history for one closed circuit to understand event sequence

**Sample WO:** 2017-2916

| Date | Time | Action | User | Old Status | New Status | Comments | Transition |
|------|------|--------|------|------------|------------|----------|------------|

**Total events:** 0

---

## Query 3: Planner Username Format

Purpose: Identify username format (DOMAIN\\user vs plain) and top planners

**Error:** {"Status_Code":200,"Message":"ERROR in the GetQueryService Server had an exception: [FireDAC][Phys][ODBC][Microsoft][SQL Server Native Client 11.0][SQL Server]Incorrect syntax near '\\'.","SQL":"\"\\nSELECT TOP 50\\n    SS.TAKENBY AS planner_username,\\n    COUNT(*) AS circuits_planned\\nFROM SS\\nINNER JOIN VEGJOB ON SS.JOBGUID = VEGJOB.JOBGUID\\nWHERE SS.STATUS = 'CLOSE'\\n    AND SS.TAKENBY IS NOT NULL\\n    AND SS.TAKENBY \\\\!= ''\\nGROUP BY SS.TAKENBY\\nORDER BY circuits_planned DESC\\n\""}

---

## Query 4 (Bonus): Status Transitions Involving QC

Purpose: Find history events where status changed to or from QC

| Old Status | New Status | Action | Count |
|------------|------------|--------|-------|

**Total QC-related transitions:** 0

---

## Summary & Findings

### JOBHISTORY Table Columns

| Column | Purpose |
|--------|---------|
| JOBGUID | FK to SS/VEGJOB |
| WO, EXT | Work order reference |
| USERNAME | User who performed action |
| LOGDATE, LOGTIME | When event occurred |
| ACTION | Event type (replaces HISTORYTYPE in spec) |
| COMMENTS | Event notes (replaces HISTORYNOTES in spec) |
| OLDSTATUS | Status before change |
| JOBSTATUS | Status after change |
| TRANSITION | Transition identifier |
| EDITDATE | Edit timestamp |

### Tech Spec Column Mapping Update

The tech spec used hypothetical column names. Update as follows:

| Spec Column | Actual Column |
|-------------|---------------|
| HISTORYTYPE | ACTION |
| HISTORYDATE | LOGDATE + LOGTIME (or EDITDATE) |
| HISTORYUSER | USERNAME |
| HISTORYNOTES | COMMENTS |

---

## Diagnostic Queries

### D1: JOBHISTORY record count

Total JOBHISTORY records: 15184522

### D2: CLOSE circuits count

Total CLOSE circuits: 24183

### D3: Sample JOBHISTORY records (TOP 5)

| JOBGUID | WO | EXT | USERNAME | LOGDATE | LOGTIME | ACTION | COMMENTS | OLDSTATUS | JOBSTATUS | IPADDRESS | ASSIGNEDTO | JOBVERSION | APPVERSION | TRANSITION | EDITDATE |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| {D89F95EC-6139-4C8B-9F84-0321F | PG-2015-2536 |  | WorkStudio\StakeOutServer | /Date(2015-07-06T19:56:01.000Z | 7:56:00 pm | Execute Job Transition |  | SA | ACTIV | Localhost |  | 1 | 8.4.236.0 | {3BD6CF9A-24B9-467E-9DAD-B2964 | 0 |
| {483CF4F1-ECFA-4C0C-A3D1-AB27E | PR-2015-2537 |  | WorkStudio\StakeOutServer | /Date(2015-07-06T19:56:01.000Z | 7:56:00 pm | Create Work Order | Created job from program ja2 | A |  |  |  | 0 | 8.4.236.0 |  | 0 |
| {09AC0DF6-379C-4BE0-BE2F-1E8C8 | PR-2015-2538 |  | WorkStudio\StakeOutServer | /Date(2015-07-06T19:56:01.000Z | 7:56:01 pm | Create Work Order | Created job from program ja2 | A |  |  |  | 0 | 8.4.236.0 |  | 0 |
| {1F38AAB2-D1D2-4E51-ADE1-68AE2 | PR-2015-2539 |  | WorkStudio\StakeOutServer | /Date(2015-07-06T19:56:01.000Z | 7:56:01 pm | Create Work Order | Created job from program ja2 | A |  |  |  | 0 | 8.4.236.0 |  | 0 |
| {859B366D-3302-40EC-AE8E-4EB14 | temp-2577 |  | Default\jatwood | /Date(2015-07-06T20:51:29.000Z | 8:51:29 pm | Execute Job Transition |  | SA | ACTIV | 10.30.7.53 |  | 0 | 8.4.236.0 |  | 0 |

### D4: JOBHISTORY JOBGUIDs that exist in SS

JOBHISTORY records with matching SS.JOBGUID: 14693560

### D5: Planner usernames (fixed syntax)

| Planner Username | Circuits Planned |
|------------------|------------------|
| PPL\meclayton | 50 |
| PPL\lehi forester | 25 |
| ASPLUNDH\jcompton | 21 |
| PPL\lanc forester | 17 |
| ECI\Derek eci | 14 |
| PPL\susq lci2 | 11 |
| ECI\eci john | 10 |
| ECI\eci joseph | 9 |
| ECI\Zach eci | 9 |
| ONEPPL\jdbrunner@pplweb.com | 8 |
| PPL\cent forester | 7 |
| ECI\John eci | 7 |
| ONEPPL\JPalmertree@pplweb.com | 7 |
| ECI\Tyler eci | 7 |
| ARS\eddiea | 7 |
| ECI\eci drew | 7 |
| PPL\susq lci1 | 6 |
| PPL\lanc lci1 | 5 |
| ECI\zackm eci | 5 |
| PPL\ne forester | 4 |
| PENNLINE\susq dgf | 4 |
| ECI\Dave eci | 4 |
| ONEPPL\MPetrun@pplweb.com | 4 |
| ECI\ne cf | 3 |
| ASPLUNDH\jmartinez | 2 |
| PENNLINE\cent dgf3 | 2 |
| Default\aarmstrong | 2 |
| ASPLUNDH\joseam | 2 |
| ECI\Lindsay eci | 2 |
| ECI\Samantha eci | 2 |

**Total distinct planners:** 30

---

## Corrected Queries (TAKENBY not reliable for CLOSE)

### Q3-Corrected: Planners from JOBHISTORY USERNAME

For closed circuits, look at history to find who worked on them.

| Username | Circuits Worked |
|----------|-----------------|
| WorkStudio\StakeOutServer | 22910 |
| PPL\meclayton | 12110 |
| ECI\Scott eci | 6597 |
| DRG\dean drg | 5853 |
| ASPLUNDH\joseam | 4481 |
| DRG\lanc drg | 4375 |
| PPL\lehi forester | 3962 |
| ASPLUNDH\jcompton | 3684 |
| ASPLUNDH\kbranciforte | 3565 |
| Default\mverdick | 3380 |
| TREESMITHS\ne dgf | 3136 |
| PPL\susq lci1 | 2900 |
| ECI\eci adam | 2741 |
| ECI\eci tracy | 2704 |
| ASPLUNDH\dcinicola | 2590 |
| PPL\ne forester | 2562 |
| PPL\lanc lci1 | 2543 |
| PPL\susq forester | 2501 |
| PPL\cent lci1 | 2491 |
| ECI\harr cf | 2385 |
| ASPLUNDH\egarcia | 2252 |
| DRG\tyler drg | 2166 |
| ECI\Matt eci | 2075 |
| PPL\susq lci2 | 2061 |
| ASPLUNDH\cnewcombe | 1983 |
| ECI\Derek eci | 1979 |
| PENNLINE\susq dgf2 | 1971 |
| ECI\eci jordan | 1819 |
| PPL\lehi lci1 | 1793 |
| PENNLINE\susq jason | 1789 |

**Total distinct users:** 30

### Q3b: Planners from VEGUNIT.FORESTER

FORESTER field shows who assessed each unit.

| Forester | Circuits Assessed | Total Units |
|----------|-------------------|-------------|
| Paul Longenecker | 929 | 179542 |
| Tyler Azzaro | 848 | 64586 |
| Lindsey Johnson | 732 | 53197 |
| Wade Black | 662 | 12791 |
| Dean Wargo | 657 | 34630 |
| Megan Schmid | 632 | 72764 |
| William Shuffett | 563 | 36094 |
| Hayden Keener | 520 | 85212 |
| Clay Robbins | 516 | 35400 |
| Andrew Gonzales | 507 | 22424 |
| Jeff Brunner | 500 | 6874 |
| Ron Fronhieser | 497 | 65033 |
| Jim  Savitski | 473 | 50550 |
| Drew Gradwell | 472 | 33644 |
| Seth Tanner | 466 | 24387 |
| Adam Kern | 443 | 27998 |
| Michael Petrun | 409 | 7053 |
| Joshua Maron | 393 | 31988 |
| Adam Miller | 374 | 41091 |
| Rob Spampinato | 356 | 21014 |
| Mark Umphred | 343 | 36374 |
| Nelson  Aponte | 339 | 27922 |
| Linda Waltermyer | 338 | 41807 |
| Christopher Newcombe | 334 | 25520 |
| Steven Garnecki | 332 | 39103 |
| Tyler Marino | 323 | 3449 |
| Matt Kuntzman | 320 | 2806 |
| Jose Avilez Monge | 319 | 1390 |
| Valerie Viscusi | 313 | 24118 |
| John Corbett | 309 | 16669 |

**Total distinct foresters:** 30

### Q5: Sample CLOSE Circuit Details

| JOBGUID | WO | EXT | STATUS | TAKENBY | ASSIGNEDTO | LINENAME | REGION | CONTRACTOR |
|---|---|---|---|---|---|---|---|---|
| {E3D960EC-139C-4E2F-9219- | 2019-0354 | 1_2 | CLOSE |  |  | CRACKERSPORT 69/12 KV 05- | Lehigh | Asplundh |
| {7F6D41DC-3E30-40A0-AFCE- | 2019-0247 | 1 | CLOSE |  |  | MUNCY 69/12KV 47-02 LINE | Susquehanna | Pennline |
| {EB55B8BA-33CE-4EE1-8AEE- | 2017-2916 | 1_29 | CLOSE |  |  | FREEMANSBURG 69/12 KV 15- | Lehigh | ASPLUNDH |

---

## Final Discovery Queries

### Q1-Final: JOBHISTORY ACTION Types for CLOSE Circuits

| ACTION | Event Count | Circuits Affected |
|--------|-------------|-------------------|
| Save Work Order | 9,261,116 | 24,025 |
| Save Job Heading | 1,372,301 | 19,054 |
| Script_Saving from VegWorker | 1,361,310 | 16,435 |
| Check Out Work Order | 720,388 | 23,864 |
| Copy Out Work Order | 558,071 | 21,479 |
| Delete Work Order | 452,343 | 21,491 |
| Execute Job Transition | 124,928 | 24,176 |
| ReleaseOwnership | 79,593 | 15,302 |
| Change Job Status | 74,668 | 23,871 |
| Create Work Order | 20,380 | 20,380 |
| Script_Transitioned to REV from VegWorke | 19,887 | 15,772 |
| Script_Completing from VegWorker | 18,195 | 15,552 |
| Conflict Found | 10,900 | 3,228 |
| Save Work Order Heading | 5,382 | 460 |
| Script_Completing Rework from VegWorker | 4,085 | 3,493 |
| UpdateItemUsageDetail | 3,937 | 72 |
| Script_CreateProjectFail | 3,843 | 2,457 |
| Saving Job after Sync from undefined | 3,699 | 652 |
| TakeBackOwnership | 3,666 | 2,592 |
| Script_CreateProject | 663 | 659 |
| Script_Transitioned to CLOSE from VegWor | 274 | 272 |
| Script_ | 230 | 133 |
| Script_Closing DA job from web | 62 | 62 |
| Saving Work Order | 45 | 45 |

### Q4-Final: Status Transitions to/from QC

| Old Status | New Status | Action | Count |
|------------|------------|--------|-------|
| QC |  | Save Work Order | 733,908 |
| QC | QC | Save Job Heading | 163,325 |
| QC |  | Check Out Work Order | 75,794 |
| QC |  | Check Out Work Order | 62,382 |
| QC |  | Copy Out Work Order | 42,800 |
| QC |  | Save Work Order | 27,896 |
| REV | QC | Execute Job Transition | 23,812 |
| ACTIV | QC | Execute Job Transition | 22,656 |
| QC | CLOSE | Execute Job Transition | 22,582 |
| QC | CLOSE | Change Job Status | 21,457 |
| ACTIV | QC | Change Job Status | 21,216 |
| QC | QC | ReleaseOwnership | 17,002 |
| QC | REWRK | Execute Job Transition | 11,403 |
| QC | REWRK | Change Job Status | 10,940 |
| QC |  | Copy Out Work Order | 9,451 |
| REWRK | QC | Execute Job Transition | 7,297 |
| REWRK | QC | Change Job Status | 7,255 |
| QC |  | Delete Work Order | 5,339 |
| REV | QC | Change Job Status | 4,009 |
| QC |  | Save Job Heading | 3,790 |

### Q2-Final: Sample Circuit Timeline

**Sample JOBGUID:** 

| Date | Time | Action | User | Old | New | Comments |
|------|------|--------|------|-----|-----|----------|
| /Date(2018-07-31T10:59:46.000Z)/ | 10:59:46 am | Delete Work Order | ASPLUNDH\jcompton | ACTIV |  | Work Order Deleted using StakeOutSc |
| /Date(2018-07-31T10:59:46.000Z)/ | 10:59:46 am | Save Work Order | ASPLUNDH\jcompton | ACTIV |  | Saved job after synching due to cha |
| /Date(2018-09-14T18:23:44.000Z)/ | 6:23:43 pm | Check Out Work Order | ASPLUNDH\jcompton | REV |  |  |
| /Date(2018-09-14T18:23:41.000Z)/ | 6:23:40 pm | Check Out Work Order | ASPLUNDH\jcompton | REV |  |  |
| /Date(2018-08-21T13:43:34.000Z)/ | 1:43:34 pm | Script_Transitioned to RE | WorkStudio\StakeOutS | ACTIV |  |  |
| /Date(2018-08-20T18:43:10.000Z)/ | 6:43:09 pm | Copy Out Work Order | ASPLUNDH\jcompton | ACTIV |  |  |
| /Date(2018-09-19T16:28:34.000Z)/ | 4:28:34 pm | Change Job Status | PPL\lehi lci1 | QC | CLOSE |  |
| /Date(2018-09-19T16:28:24.000Z)/ | 4:28:23 pm | Execute Job Transition | PPL\lehi lci1 | QC | CLOSE |  |
| /Date(2018-09-19T16:28:15.000Z)/ | 4:28:14 pm | Save Work Order | PPL\lehi lci1 | QC |  | Saved. Old WO Number = 2017-2916 |
| /Date(2018-09-19T16:22:47.000Z)/ | 4:22:46 pm | Save Work Order | PPL\lehi lci1 | QC |  | Saved from a Copy-Take-Ownership. |
| /Date(2018-09-19T16:22:44.000Z)/ | 4:22:44 pm | Save Work Order | PPL\lehi lci1 | QC |  | Saved from a Copy-Take-Ownership. |
| /Date(2018-09-19T15:02:50.000Z)/ | 3:02:50 pm | Delete Work Order | PPL\lehi lci1 |  |  |  |
| /Date(2018-09-19T15:01:22.000Z)/ | 3:01:21 pm | Save Work Order | PPL\lehi lci1 | QC |  | Saved from a Copy-Leave-Ownership. |
| /Date(2018-09-18T17:47:18.000Z)/ | 5:47:18 pm | Delete Work Order | PPL\lehi lci1 |  |  |  |
| /Date(2018-09-18T17:45:45.000Z)/ | 5:45:45 pm | Save Work Order | PPL\lehi lci1 | QC |  | Saved from a Copy-Leave-Ownership. |
| /Date(2018-09-19T16:23:15.000Z)/ | 4:23:15 pm | Copy Out Work Order | ASPLUNDH\jcompton | QC |  |  |
| /Date(2018-09-19T16:22:45.000Z)/ | 4:22:44 pm | Check Out Work Order | PPL\lehi lci1 | QC |  |  |
| /Date(2018-09-19T16:22:42.000Z)/ | 4:22:42 pm | Check Out Work Order | PPL\lehi lci1 | QC |  |  |
| /Date(2018-08-08T12:35:30.000Z)/ | 12:35:29 pm | Copy Out Work Order | Default\mverdick | ACTIV |  |  |
| /Date(2020-02-21T17:08:11.830Z)/ | 5:08:11 pm | Save Work Order | WorkStudio\StakeOutS | CLOSE |  | SaveJobToDB from script object |
| /Date(2020-02-21T17:07:58.250Z)/ | 5:07:58 pm | Save Work Order | WorkStudio\StakeOutS | CLOSE |  | SaveJobToDB from script object |
| /Date(2020-02-21T17:07:56.387Z)/ | 5:07:56 pm | Check Out Work Order | WorkStudio\StakeOutS | CLOSE |  |  |
| /Date(2020-02-21T17:07:54.697Z)/ | 5:07:54 pm | Save Job Heading |  | CLOSE | CLOSE | Job taken properties were: taken by |
| /Date(2020-02-21T17:07:53.637Z)/ | 5:07:53 pm | Copy Out Work Order | WorkStudio\StakeOutS | CLOSE |  |  |
| /Date(2020-02-21T17:08:06.563Z)/ | 5:08:06 pm | Save Work Order | WorkStudio\StakeOutS | CLOSE |  | SaveJobToDB from script object |

**Total events:** 25

---

## Key Findings for Tech Spec

### 1. Column Mapping (Update Tech Spec)

| Tech Spec Column | Actual Column | Notes |
|------------------|---------------|-------|
| HISTORYTYPE | ACTION | Event type string |
| HISTORYDATE | LOGDATE | Format: \`/Date(YYYY-MM-DDTHH:MM:SS.sssZ)/\` |
| HISTORYUSER | USERNAME | Format: \`DOMAIN\\username\` |
| HISTORYNOTES | COMMENTS | Free text |

### 2. QC Detection Logic

To find when a circuit entered QC status, query JOBHISTORY where:

\`\`\`sql
WHERE JOBSTATUS = 'QC'
  AND OLDSTATUS IN ('ACTIV', 'REV', 'REWRK')
  AND ACTION IN ('Execute Job Transition', 'Change Job Status')
\`\`\`

**Status Flow:** ACTIV → REV → QC → CLOSE (or QC → REWRK → QC → CLOSE)

### 3. Username Formats

| Source | Format | Example |
|--------|--------|---------|
| JOBHISTORY.USERNAME | DOMAIN\\username | \`ASPLUNDH\\jcompton\`, \`PPL\\lehi lci1\` |
| VEGUNIT.FORESTER | Full Name | \`Paul Longenecker\`, \`Tyler Azzaro\` |

**Implication:** Two-tier analytics must handle both formats:
- Circuit-level metrics: Use JOBHISTORY.USERNAME
- Unit-level metrics: Use VEGUNIT.FORESTER (full name)

### 4. TAKENBY Field

**Confirmed:** SS.TAKENBY is NULL/empty for CLOSE circuits.

To find who worked on a closed circuit:
- Use JOBHISTORY.USERNAME for activity history
- Use VEGUNIT.FORESTER for unit-level attribution

### 5. Data Volumes

| Metric | Count |
|--------|-------|
| Total CLOSE circuits | 24,183 |
| Total JOBHISTORY records | 15,184,522 |
| JOBHISTORY matching CLOSE circuits | ~14.7M |
| Distinct planners (by JOBHISTORY) | 30+ top contributors |
| Distinct foresters (by VEGUNIT) | 30+ top contributors |

### 6. Date Format Handling

LOGDATE comes as: \`/Date(2018-07-31T10:59:46.000Z)/\`

Parse with regex or string manipulation:
\`\`\`php
// Extract ISO date from WorkStudio format
preg_match('/Date\((.*?)\)/', $logDate, $matches);
$isoDate = $matches[1]; // '2018-07-31T10:59:46.000Z'
$carbon = Carbon::parse($isoDate);
\`\`\`

### 7. Recommended Tech Spec Updates

1. **Update \`archived_assessment_history\` columns:**
   - \`history_type\` → \`action\`
   - \`history_date\` → \`log_date\` (parse from LOGDATE string)
   - \`history_user\` → \`username\`
   - \`history_notes\` → \`comments\`
   - Add: \`old_status\`, \`job_status\` columns

2. **QC timestamp extraction:**
   Query: \`WHERE JOBSTATUS = 'QC' AND ACTION IN ('Execute Job Transition', 'Change Job Status')\`
   Use MIN(LOGDATE) for first QC submission

3. **Planner identification for CLOSE circuits:**
   - Primary: JOBHISTORY.USERNAME who performed 'Execute Job Transition' ACTIV→QC
   - Fallback: Most frequent USERNAME in JOBHISTORY for that circuit


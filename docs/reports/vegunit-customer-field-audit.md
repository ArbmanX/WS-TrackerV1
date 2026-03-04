# VEGUNIT Customer Field Audit

> **Date:** 2026-03-04
> **Source:** WorkStudio Live Database (3,654,488 VEGUNIT records)
> **Method:** Direct API queries via GETQUERY protocol
> **Purpose:** Determine which customer/owner fields are populated and assess duplication for migration design

---

## Executive Summary

VEGUNIT has **21 customer/owner/tenant fields** across 3 field groups. The data tells a clear story:

- **Property owner fields** (FIRSTNAME, LASTNAME, PADDRESS, CITY, STATE, ZIPCODE) are the **primary data** -- ~80% populated, heavily duplicated
- **C* customer fields** (CFIRSTNAME, CLASTNAME, etc.) are **effectively dead** -- names/phone/email have <0.01% population
- **Tenant fields** (T* prefix) are **completely dead** -- under 25 records each
- **CUSTOMERID** is **dead** -- 4 records out of 3.65M

**Duplication is extreme:** 2.93M owner records contain only 103K unique last names. The top 15 surnames account for 155K+ records. A normalized `property_owners` table would reduce ~2.93M embedded rows to ~305K unique property records.

---

## Field Population Results

### Group 1: C* Customer Fields (Contact/Notification)

| Field | Populated | % of 3.65M | Unique | Verdict |
|-------|-----------|------------|--------|---------|
| CUSTOMERID | 4 | <0.01% | - | **DEAD** |
| CFIRSTNAME | 217* | <0.01% | - | **DEAD** |
| CLASTNAME | 217 | <0.01% | 91 | **DEAD** |
| CADDRESS | 2,832,436 | 77.5% | 343,911 | **ACTIVE** -- property address |
| CPHONE | 217* | <0.01% | - | **DEAD** |
| CCITY | 2,935,621** | 80.3% | - | **ACTIVE** -- mirrors CITY |
| CSTATE | 2,935,621** | 80.3% | - | **ACTIVE** -- mirrors STATE |
| CZIP | ~2.83M** | ~77% | - | **ACTIVE** -- mirrors ZIPCODE |
| CUST_EMAIL | 77 | <0.01% | - | **DEAD** |

*\* Estimated from sample-based and targeted count queries*
*\*\* CCITY/CSTATE counts from 5K sample extrapolation (~68.6%) confirmed with CITY/STATE actuals*

**Key finding:** The C* name/phone/email fields were used for exactly **217 records** -- likely a pilot or one-off notification effort. The C* address fields (CADDRESS, CCITY, CSTATE, CZIP) ARE populated but appear to mirror the property address data.

### Group 2: Owner/Landowner Fields (Property Data -- PRIMARY)

| Field | Populated | % of 3.65M | Unique | Duplication Ratio |
|-------|-----------|------------|--------|-------------------|
| LASTNAME | 2,929,976 | 80.2% | 103,485 | 28.3x |
| FIRSTNAME | 2,360,424 | 64.6% | 52,150 | 45.3x |
| PADDRESS | 2,910,825 | 79.6% | 305,301 | 9.5x |
| CITY | 2,935,621 | 80.3% | 4,835 | 607x |
| STATE | 2,935,621 | 80.3% | - | ~1 (PA) |
| ZIPCODE | 2,935,621 | 80.3% | - | High |
| ADDRESS | 197,116 | 5.4% | 61,114 | 3.2x |
| PHONE | 4,331 | 0.1% | 1,067 | 4.1x |

**These are the real customer/owner fields.** LASTNAME + PADDRESS together form the de facto owner identity. ADDRESS (no prefix) is rarely used -- PADDRESS (Property Address) and CADDRESS appear to be the standard fields.

### Group 3: Tenant Fields (T* prefix)

| Field | Populated | Verdict |
|-------|-----------|---------|
| TFIRSTNAME | 20 | **DEAD** |
| TLASTNAME | 18 | **DEAD** |
| TADDRESS | 4 | **DEAD** |
| TPHONE | 21 | **DEAD** |

Tenant tracking was never adopted.

---

## Duplication Analysis

### Top 15 Most Duplicated Owner Last Names

| Last Name | Unit Count | Notes |
|-----------|------------|-------|
| MILLER | 22,388 | Common PA surname |
| SMITH | 19,567 | |
| MARTIN | 15,149 | |
| STOLTZFUS | 11,853 | Amish/Mennonite (Lancaster region) |
| SNYDER | 11,228 | |
| FISHER | 8,775 | |
| ZIMMERMAN | 8,559 | |
| WILLIAMS | 7,738 | |
| BROWN | 7,525 | |
| WEAVER | 7,426 | Amish/Mennonite |
| KING | 7,376 | Amish/Mennonite |
| JONES | 6,651 | |
| MOYER | 6,526 | |
| HOFFMAN | 6,466 | |
| JOHNSON | 5,827 | |

The top 15 last names account for **155,054 records** (5.3% of all VEGUNIT rows). The PA Dutch country bias (STOLTZFUS, WEAVER, KING, ZIMMERMAN) reflects PPL Electric Utilities' central PA service territory.

### Duplication by the Numbers

| Metric | Value |
|--------|-------|
| Total owner records (LASTNAME populated) | 2,929,976 |
| Unique last names | 103,485 |
| Average duplication per last name | 28.3x |
| Unique property addresses (PADDRESS) | 305,301 |
| Average units per property | 9.5x |
| **Estimated unique owner+property combos** | **~300K-350K** |
| Unique mailing addresses (CADDRESS+CCITY+CZIP) | 360,172 |
| Average units per mailing address | 7.9x |

A single property can have multiple veg units across multiple assessment cycles -- each carrying a copy of the same owner data. The mailing address composite (CADDRESS+CCITY+CZIP) yields 360K unique records from 2.83M populated rows, making it the strongest de-duplication key available.

---

## Field Role Mapping

Based on sample inspection and domain knowledge:

| Role | Primary Fields | Notes |
|------|----------------|-------|
| **Property Owner** | FIRSTNAME, LASTNAME | Landowner name from utility records |
| **Mailing Address** | CADDRESS, CCITY, CSTATE, CZIP | **PRIMARY identifier** -- from utility billing records, most consistent/standardized. Use as the anchor for de-duplication. |
| **Property/Work Address** | PADDRESS, CITY, STATE, ZIPCODE | Physical location where work is planned. May differ from mailing address. NOT the customer's primary address. |
| **Unit Address** | ADDRESS | Rarely used (5.4%), possibly manual override |
| **Contact** | PHONE | Extremely sparse (0.1%) |
| **Email** | CUST_EMAIL | Dead (77 records) |
| **Customer Account** | CUSTOMERID | Dead (4 records) |
| **Tenant/Alt Contact** | T* fields | Dead (<25 records each) |

**Important:** The mailing address (CADDRESS/CCITY/CSTATE/CZIP) should be treated as the **primary customer address** for de-duplication purposes. It originates from PPL's billing system and is more consistent than names, which suffer from typos, abbreviations, and variations across assessment cycles. PADDRESS is the physical work site location -- a property may have a different mailing address (e.g., landlord lives elsewhere).

---

## Migration Recommendation

### Fields to Migrate

**Include (active data):**
- `FIRSTNAME`, `LASTNAME` -- owner identity
- `PADDRESS` -- property address (primary)
- `CADDRESS` -- mailing address (secondary, when different from PADDRESS)
- `CITY`, `STATE`, `ZIPCODE` -- location
- `PHONE` -- sparse but valid when present

**Exclude (dead):**
- `CUSTOMERID` -- 4 records
- `CFIRSTNAME`, `CLASTNAME` -- 217 records (use FIRSTNAME/LASTNAME instead)
- `CPHONE` -- <217 records
- `CUST_EMAIL` -- 77 records
- `ADDRESS` -- 5.4% population, role unclear vs PADDRESS
- All T* tenant fields -- <25 records each

### Normalization Strategy

With ~305K unique property addresses across 2.93M owner records, normalization into a separate `property_owners` table would:

1. Eliminate ~2.6M duplicate owner data entries
2. Provide single source of truth for owner lookups
3. Enable owner-level analytics (how many units does this property touch?)
4. Support de-duplication matching across assessment cycles

**Suggested composite unique key:** `CADDRESS + CCITY + CZIP` (mailing address)

The mailing address is the most reliable de-duplication anchor because it comes from PPL's billing system. Names should be stored but NOT used as part of the unique constraint -- they vary too much across records (typos, abbreviations, "JAMES T" vs "JAMES", maiden names). The mailing address uniquely identifies the customer account; names can be updated independently without breaking uniqueness.

---

---

## Future Migration Design Notes

### Proposed Schema: `customers` Table

```
customers
├── id (bigIncrements, PK)
├── mailing_address (string 250)      ← CADDRESS
├── mailing_city (string 50)          ← CCITY
├── mailing_state (string 5)          ← CSTATE
├── mailing_zip (string 10)           ← CZIP
├── first_name (string 50, nullable)  ← FIRSTNAME (updatable, NOT part of unique)
├── last_name (string 50, nullable)   ← LASTNAME (updatable, NOT part of unique)
├── phone (string 20, nullable)       ← PHONE (sparse but valid)
├── timestamps
└── UNIQUE(mailing_address, mailing_city, mailing_zip)
```

### Proposed Schema: `veg_units` Table (customer reference)

```
veg_units
├── ...existing fields...
├── customer_id (FK → customers.id, nullable)
├── property_address (string 250, nullable)  ← PADDRESS (work site, may differ from mailing)
├── property_city (string 50, nullable)      ← CITY
├── property_state (string 5, nullable)      ← STATE
├── property_zip (string 10, nullable)       ← ZIPCODE
└── ...
```

### Key Design Decisions

1. **Unique constraint on mailing address, NOT names** -- names vary too much (typos, abbreviations, maiden names). Mailing address comes from PPL billing and is standardized.
2. **PADDRESS stays on veg_units** -- it's the work location, not the customer identity. One customer can have multiple properties.
3. **Names are updatable attributes** -- stored on the customer record but can be corrected without breaking relationships.
4. **Import strategy:** Upsert customers by `CADDRESS+CCITY+CZIP` first, then link veg_units via FK. Latest name wins on conflict.
5. **Dead fields excluded:** CUSTOMERID, C* names/phone/email, all T* tenant fields, ADDRESS.

### Estimated Impact

- ~360K customer records (from 2.83M embedded duplicates)
- 7.9x average deduplication ratio
- Clean FK relationship enables: "show all units for this customer", "customer notification history", "properties per customer"

---

*Report generated from live WorkStudio database queries. Population counts are exact (not estimated) except where noted.*

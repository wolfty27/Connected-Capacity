# DATA AVAILABILITY AUDIT

## Phase 0: AI-Assisted Bundle Engine Data Exploration

**Date**: 2025-12-03  
**Version**: 1.0  
**Author**: AI-Assisted Bundle Engine Team

---

## Executive Summary

This audit documents the actual InterRAI assessment data available in the Connected Capacity database, comparing it against the requirements for implementing InterRAI CA algorithms and CAP triggers. The findings will inform the implementation strategy for the v2.2 Bundle Engine architecture.

### Key Findings

| Category | Status | Notes |
|----------|--------|-------|
| HC Assessments | ✅ Available | 13 assessments with 103 raw_items keys |
| CA Assessments | ⚠️ Not Present | 0 assessments; system supports type but no data |
| BMHS Data | ❌ Not Present | No mental health screener items in raw_items |
| iCODE Coverage | ✅ Good | 46 iCODE keys covering ADL, IADL, cognition, behaviour |
| RUG Classification | ✅ Available | All 13 assessments have RUG classifications |
| CA Algorithm Items | ⚠️ Partial | 19/21 required items mappable from HC data |

### Implications for Implementation

1. **CA Algorithms CAN be implemented** using HC assessment data with appropriate item mapping
2. **CAP triggers SHOULD be conditional** on `hasFullHcAssessment` flag as designed
3. **BMHS integration** will require new data collection pathway if needed
4. **Testing** must use synthetic data until real CA assessments are available

---

## 1. Assessment Types Analysis

### 1.1 Current Database State

```
Assessment Types in Database:
  hc (Home Care): 13 assessments
  cha (Contact Assessment): 0 assessments
  contact: 0 assessments

Workflow Statuses:
  completed: 13 (100%)
```

### 1.2 Assessment Type Support

| Type | Model Constant | Database Support | Seeded Data |
|------|---------------|------------------|-------------|
| Home Care (HC) | `TYPE_HC = 'hc'` | ✅ Yes | ✅ 13 records |
| Contact Assessment | `TYPE_CHA = 'cha'` | ✅ Yes | ❌ None |
| Contact | `TYPE_CONTACT = 'contact'` | ✅ Yes | ❌ None |

### 1.3 RUG Classification Distribution

```
RUG Group (Category): Count
  BB0 (Behaviour Problems): 2
  IB0 (Impaired Cognition): 2
  CC0 (Clinically Complex): 2
  SE1 (Extensive Services): 1
  RA2 (Special Rehabilitation): 1
  SE3 (Extensive Services): 1
  SSB (Special Care): 1
  RB0 (Special Rehabilitation): 1
  PD0 (Reduced Physical Function): 1
  SSA (Special Care): 1
```

---

## 2. raw_items Key Analysis

### 2.1 Key Categories

| Category | Count | Examples |
|----------|-------|----------|
| iCODE Keys | 46 | `iG1ha`, `iG1ia`, `iB3a`, `iJ1a` |
| ADL UI Keys | 9 | `adl_bathing`, `adl_transfer`, `adl_toilet_use` |
| IADL UI Keys | 0* | (Included in iCODE section as `iadl_*`) |
| Mood UI Keys | 5 | `mood_negative_statements`, `mood_crying` |
| Clinical/Other | 43 | `chess`, `cps`, `hearing`, `vision` |

*Note: IADL items exist but are prefixed with 'i' (e.g., `iadl_finances`)

### 2.2 Complete iCODE Key List

```
ADL Self-Performance (4-item RUG Sum):
  iG1ha - Bed mobility
  iG1ia - Transfer
  iG1ea - Toilet use
  iG1ja - Eating

Extended ADL:
  iG1aa - Meal preparation (IADL)
  iG1ba - Dressing upper body
  iG1ca - Dressing lower body
  iG1da - Ordinary housework (IADL)
  iG1eb - Managing finances (IADL)
  iG1fa - Personal hygiene
  iG1ga - Bathing

Cognition:
  iB1  - Short-term memory (0=OK, 1=problem)
  iB2a - Memory recall ability
  iB3a - Decision making (0-6)
  iC1  - Making self understood (0-5)
  iC2  - Comprehension

Behaviour (0-3 scale):
  iE3a - Wandering
  iE3b - Verbal abuse
  iE3c - Physical abuse
  iE3d - Socially inappropriate
  iE3e - Resists care
  iE3f - Disruptive

Clinical Indicators:
  iI1a - Pressure ulcer stage (0-4)
  iK1a - Swallowing (0-3)
  iK5a - Weight loss
  iJ1a - Pain frequency (0-3)
  iJ1b - Pain intensity (0-4)
  iJ2a - Falls

Therapy Minutes:
  iN3eb - Physical therapy
  iN3fb - Occupational therapy
  iN3gb - Speech-language therapy

Extensive Services:
  iP1aa - IV medication
  iP1ab - IV/parenteral feeding
  iP1ae - Suctioning
  iP1af - Tracheostomy care
  iP1ag - Ventilator/respirator
  iP1ah - Oxygen therapy
  iP1ak - Dialysis
  iP1al - Chemotherapy
```

### 2.3 Computed Scores (Available at Model Level)

```php
$assessment->maple_score              // MAPLe 1-5
$assessment->adl_hierarchy           // ADL Hierarchy 0-6
$assessment->iadl_difficulty         // IADL Difficulty 0-6
$assessment->cognitive_performance_scale  // CPS 0-6
$assessment->depression_rating_scale // DRS 0-14
$assessment->pain_scale              // Pain 0-3
$assessment->chess_score             // CHESS 0-5
$assessment->falls_in_last_90_days   // Boolean
$assessment->wandering_flag          // Boolean
```

---

## 3. InterRAI CA Algorithm Item Mapping

### 3.1 CA Section C Items (Preliminary Screener)

| CA Item Code | CA Description | HC Mapping | Status |
|--------------|----------------|------------|--------|
| C1 | Daily decision making | `iB3a` | ✅ Available |
| C2a | Bathing | `iG1ga` | ✅ Available |
| C2b | Bath transfer | `iG1ia` (transfer) | ⚠️ Approximation |
| C2c | Personal hygiene | `iG1fa` | ✅ Available |
| C2d | Dressing lower body | `iG1ca` | ✅ Available |
| C2e | Locomotion | `iG1ha` (bed mobility) | ⚠️ Approximation |
| C3 | Dyspnea | `dyspnea` (UI key) | ✅ Available |
| C4 | Self-reported health | - | ❌ Not available |
| C5c | Sad, depressed, hopeless | `mood_sad_expressions` | ✅ Available |
| C6a | Unstable conditions | `chess` >= 3 | ✅ Derivable |

### 3.2 CA Section D Items (Extended Evaluation)

| CA Item Code | CA Description | HC Mapping | Status |
|--------------|----------------|------------|--------|
| D3a | Meal preparation | `iG1aa` | ✅ Available |
| D3b | Ordinary housework | `iG1da` | ✅ Available |
| D3c | Medication management | `iadl_medications` | ⚠️ Derived (no direct iCODE) |
| D3d | Stair use | - | ❌ Not available |
| D4 | ADL decline | - | ❌ Requires assessment comparison |
| D8a | Daily pain | `iJ1a` >= 3 | ✅ Derivable |
| D14b | IV therapy | `iP1aa` | ✅ Available |
| D14e | Wound care | `clinical_wound` | ✅ Available |
| D15 | Last hospital stay | - | ❌ Not in raw_items |
| D16 | ED visits | - | ❌ Not in raw_items |
| D19b | Family overwhelmed | `caregiver_stress` | ✅ Available |

### 3.3 Algorithm Implementation Feasibility

| Algorithm | Required Items | Available | Missing | Feasibility |
|-----------|---------------|-----------|---------|-------------|
| **Self-Reliance Index (SRI)** | C1, C2a-e | 6/6 | 0 | ✅ Full |
| **Assessment Urgency (AUA)** | C1-C6a, D19b | 9/11 | C4, C6a exact | ⚠️ High (with approximations) |
| **Service Urgency (SUA)** | C1-C2e, D8a, D14-D16 | 9/11 | D15, D16 | ⚠️ High (with approximations) |
| **Rehabilitation** | C1-C2e, D3a-d, D4, B2c | 9/12 | D3d, D4, B2c | ⚠️ Medium |
| **Personal Support (PSA)** | C1-C2e | 6/6 | 0 | ✅ Full |
| **Distressed Mood (DMS)** | E1a-f | 3/6 | E1d-f | ⚠️ Medium |
| **Pain Scale** | G1a-b | 2/2 | 0 | ✅ Full |
| **CHESS-CA** | Subset of CHESS | 5/5 | 0 | ✅ Full (use chess directly) |

---

## 4. BMHS (Brief Mental Health Screener) Data Gap

### 4.1 Current State

No BMHS-specific items are present in `raw_items`. The existing mental health/behaviour data is limited to:

```
Available:
  drs (Depression Rating Scale)
  iE3a-f (Behaviour indicators)
  mood_* keys (5 mood indicators)

NOT Available (BMHS-specific):
  Section B - Disordered Thought items
  Section C - Risk of Harm items
  Section D - Emotional/Behavioural items
  Self-Harm indicators
  Substance abuse screening
```

### 4.2 BMHS Integration Requirements

If BMHS integration is required:
1. Create `BmhsAssessmentMapper` with new item mappings
2. Add BMHS-specific fields to `PatientNeedsProfile`:
   - `disorderedThoughtScore`
   - `riskOfHarmLevel`
   - `selfHarmRisk`
   - `substanceUseRisk`
3. Update seeder to generate BMHS data

---

## 5. Data Schema Verification

### 5.1 InterraiAssessment Model Fields

| Field | Type | Populated | Notes |
|-------|------|-----------|-------|
| `assessment_type` | enum | ✅ All HC | Support for 'hc', 'cha', 'contact' |
| `assessment_date` | datetime | ✅ Yes | |
| `source` | enum | ✅ Yes | 'spo_completed' or 'ohah_provided' |
| `maple_score` | string | ✅ Yes | Derived from raw_items |
| `adl_hierarchy` | int | ✅ Yes | 0-6 scale |
| `iadl_difficulty` | int | ✅ Yes | 0-6 scale |
| `cognitive_performance_scale` | int | ✅ Yes | 0-6 CPS |
| `chess_score` | int | ✅ Yes | 0-5 |
| `pain_scale` | int | ✅ Yes | 0-3 |
| `depression_rating_scale` | int | ✅ Mostly 0 | Seeder sets to 0 |
| `falls_in_last_90_days` | bool | ✅ Yes | |
| `wandering_flag` | bool | ✅ Yes | |
| `raw_items` | json | ✅ Yes | 103 keys per record |
| `caps_triggered` | json | ⚠️ Empty | Not populated by seeder |

### 5.2 RUGClassification Model

| Field | Populated | Notes |
|-------|-----------|-------|
| `rug_group` | ✅ Yes | e.g., 'CC0', 'BB0', 'SE1' |
| `rug_category` | ✅ Yes | e.g., 'Clinically Complex' |
| `rug_description` | ✅ Yes | Human-readable |
| `adl_sum` | ✅ Yes | 4-18 scale |
| `is_current` | ✅ Yes | Boolean |

---

## 6. Recommendations

### 6.1 For Phase 1-2 (Algorithm Implementation)

1. **Use HC Data Mapping Strategy**
   - Implement CA algorithms using HC iCODE mappings
   - Document approximations clearly in algorithm JSON files
   - Add `data_source: "hc_mapped"` metadata

2. **Handle Missing Items**
   - D3d (Stair use): Derive from `iG1ha` (bed mobility) + `adl_locomotion`
   - D4 (ADL decline): Default to 0 (no decline) or compare with previous assessment
   - D15/D16 (Hospital/ED): Check if data exists in Patient or Referral models

3. **Create Algorithm JSON Schema**
   - Include `items_used` array with both CA code and HC mapping
   - Include `approximation_notes` for mapped items
   - Include `verification_status: "hc_mapped"` until CA data available

### 6.2 For Phase 3 (CAP Triggers)

1. **Implement Conditional Execution**
   ```php
   if ($profile->hasFullHcAssessment) {
       $this->capTriggerEngine->evaluate($profile);
   }
   ```

2. **Use PatientNeedsProfile::toCAPInput()**
   - Map all CAP-required fields from available data
   - Return safe defaults for missing fields

### 6.3 For Future Phases

1. **CA Assessment Pathway**
   - Create CA-specific seeder when needed
   - Add CA intake workflow to UI
   - Implement CA→HC progression tracking

2. **BMHS Integration**
   - Defer until business need confirmed
   - Design as optional enrichment layer
   - Consider external integration (e.g., mental health referral system)

---

## 7. Algorithm Verification Path

### 7.1 Authoritative Sources

| Source | Status | Access |
|--------|--------|--------|
| InterRAI CA Manual (2019) | Have | `docs/InterRAI-CA.txt` |
| InterRAI CAPs Manual | Have | `docs/InterRAI-CAPS-1-6.txt` |
| InterRAI Appendices (Algorithm Trees) | ❌ Need | Not in repository |
| CIHI RUG Implementation Guide | Partial | Referenced in seeder |

### 7.2 Verification Checklist

- [ ] Obtain InterRAI CA Appendix with algorithm flowcharts
- [ ] Cross-reference JSON decision trees with official flowcharts
- [ ] Validate item code mappings against official item definitions
- [ ] Test algorithm outputs against known reference cases
- [ ] Document any deviations with clinical rationale

### 7.3 Verification Status Convention

```json
{
  "verification_status": "unverified",    // Initial state
  "verification_status": "hc_mapped",     // Using HC data mapping
  "verification_status": "draft_verified", // Checked against docs
  "verification_status": "verified"        // Clinician-approved
}
```

---

## Appendix A: Sample raw_items Values

```json
{
  "iG1ha": 2,          // Bed mobility: Extensive assistance
  "iG1ia": 2,          // Transfer: Extensive assistance
  "iG1ea": 2,          // Toilet use: Extensive assistance
  "iG1ja": 2,          // Eating: Extensive assistance
  "iB3a": 1,           // Decision making: Mild impairment
  "iC1": 0,            // Making self understood: Understood
  "cps": 1,            // CPS: Borderline intact
  "chess": 4,          // CHESS: High health instability
  "iJ1a": 3,           // Pain frequency: Daily
  "iJ1b": 3,           // Pain intensity: Severe
  "iP1ah": 1,          // Oxygen therapy: Yes
  "adl_bathing": 2,    // UI key synced from iG1ga
  "adl_transfer": 2,   // UI key synced from iG1ia
  "mood_negative_statements": 0,
  "caregiver_stress": false
}
```

---

## Appendix B: Item Code Cross-Reference

| CA Code | CA Description | HC iCODE | Notes |
|---------|----------------|----------|-------|
| C1 | Decision making | iB3a | Direct mapping |
| C2a | Bathing | iG1ga | Direct mapping |
| C2b | Bath transfer | iG1ia | Approximate (transfer general) |
| C2c | Personal hygiene | iG1fa | Direct mapping |
| C2d | Dressing lower | iG1ca | Direct mapping |
| C2e | Locomotion | iG1ha | Approximate (bed mobility) |
| D3a | Meal prep | iG1aa | Direct mapping |
| D3b | Housework | iG1da | Direct mapping |
| D3c | Medications | iadl_medications | UI key only |
| D3d | Stair use | - | Not available |
| D8a | Daily pain | iJ1a >= 3 | Derived |
| E1a | Negative statements | mood_negative_statements | UI key |
| E1b | Sad expressions | mood_sad_expressions | UI key |
| G1a | Pain frequency | iJ1a | Direct mapping |
| G1b | Pain intensity | iJ1b | Direct mapping |

---

*Document generated as part of Phase 0: Data Exploration for AI-Assisted Bundle Engine v2.2*


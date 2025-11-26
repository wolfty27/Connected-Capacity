# Connected Capacity 2.1 - Comprehensive Architecture & Gap Analysis

**Generated:** November 25, 2025
**Author:** Architecture Review (Claude Code)
**Version:** 1.0

---

## Table of Contents

1. [Section 1: Referral Package + InterRAI Workflow](#section-1-referral-package--interrai-workflow)
2. [Section 2: CC Model + Workflow Analysis with SSPO](#section-2-cc-model--workflow-analysis-with-sspo)
3. [Section 3: InterRAI HC & IAR Implementation Requirements](#section-3-interrai-hc--iar-implementation-requirements)
4. [Section 4: AlayaCare Comparison](#section-4-alayacare-comparison)
5. [Section 5: Gap Analysis](#section-5-gap-analysis)
6. [Section 6: Actionable Design & Refactor Backlog](#section-6-actionable-design--refactor-backlog)

---

## Section 1: Referral Package + InterRAI Workflow

### 1.1 Current Referral Flow (As-Is)

Based on the codebase analysis, the current referral workflow is:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        CURRENT CC 2.1 REFERRAL FLOW                             │
└─────────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐      ┌──────────────┐      ┌──────────────┐
    │   Hospital   │      │    OHaH/     │      │     SPO      │
    │   Discharge  │─────▶│     HPG      │─────▶│   Platform   │
    │   Planner    │      │   Gateway    │      │              │
    └──────────────┘      └──────────────┘      └──────┬───────┘
                                                       │
                                                       ▼
    ┌──────────────────────────────────────────────────────────────────────┐
    │                         TRIAGE/TNP CREATION                          │
    │  TriageResult stores:                                                │
    │  - acuity_level (low/medium/high/critical)                          │
    │  - dementia_flag, mh_flag, rpm_required                             │
    │  - fall_risk, behavioural_risk                                       │
    │  - raw_referral_payload (JSON) ← HPG data                           │
    │  ⚠️  NO InterRAI HC fields stored                                    │
    └──────────────────────────────────────────────────────────────────────┘
                                                       │
                                                       ▼
    ┌──────────────────────────────────────────────────────────────────────┐
    │                      CARE BUNDLE SELECTION                           │
    │  CareBundleWizard.jsx: 3-step process                               │
    │  Step 1: Select Bundle → Step 2: Customize → Step 3: Publish        │
    │  ⚠️  Services from hardcoded careBundleConstants.js                  │
    └──────────────────────────────────────────────────────────────────────┘
                                                       │
                                                       ▼
    ┌──────────────────────────────────────────────────────────────────────┐
    │                      SERVICE ASSIGNMENT                              │
    │  ServiceAssignment → links to:                                       │
    │  - care_plan_id, patient_id                                         │
    │  - service_provider_organization_id (SPO or SSPO)                   │
    │  - assigned_user_id (individual staff member)                       │
    │  - service_type_id                                                   │
    └──────────────────────────────────────────────────────────────────────┘
```

### 1.2 OHaH RFS Required Workflow (To-Be)

Per OHaH RFS (Bundled High Intensity Home Care - LTC):

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                    OHaH-COMPLIANT REFERRAL FLOW (REQUIRED)                      │
└─────────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐                    ┌──────────────┐
    │   Hospital   │      HPG           │     SPO      │
    │   Discharge  │───────────────────▶│   Platform   │
    │   Planner    │   15-min response  │              │
    └──────────────┘       SLA          └──────┬───────┘
                                               │
                     ┌─────────────────────────┼─────────────────────────┐
                     │                         │                         │
                     ▼                         ▼                         ▼
    ┌────────────────────────┐  ┌────────────────────────┐  ┌────────────────────────┐
    │ REFERRAL CONTAINS:     │  │ SPO MUST COMPLETE IF:  │  │ SPO UPLOADS TO IAR:    │
    │ - Patient demographics │  │ - InterRAI HC missing  │  │ - RAI HC Assessment    │
    │ - InterRAI HC (if      │  │ - InterRAI HC > 3mo    │  │ - Real-time upload     │
    │   available)           │  │ - Clinical condition   │  │ - CHRIS integration    │
    │ - Crisis designation   │  │   has changed          │  │                        │
    │ - Diagnosis/conditions │  │                        │  │                        │
    └────────────────────────┘  └────────────────────────┘  └────────────────────────┘
                                               │
                                               ▼
    ┌──────────────────────────────────────────────────────────────────────┐
    │                    CARE BUNDLE: $5,000/week (LTC)                    │
    │  - 100% referral acceptance                                          │
    │  - 0% missed care target                                             │
    │  - <24 hours to first service                                        │
    │  - SPO can subcontract to SSPOs (but retains full liability)         │
    │  - Weekly huddles with OHaH Care Coordinators                        │
    │  - Shadow billing with $0 rate codes                                 │
    └──────────────────────────────────────────────────────────────────────┘
```

### 1.3 Key Referral Package Components (OHaH RFS)

| Component | Source | Current CC State | Gap |
|-----------|--------|------------------|-----|
| Patient Demographics | HPG | `patients` table | ✅ Exists |
| InterRAI HC Assessment | HPG or SPO-completed | **Not stored** | ❌ Critical Gap |
| Crisis/Priority Level | HPG (LTC Waitlist) | `triage_results.acuity_level` | ⚠️ Partial |
| Clinical Diagnosis | HPG | `raw_referral_payload` JSON | ⚠️ Unstructured |
| MAPLe Score | InterRAI Output | `patients.maple_score` | ⚠️ Schema exists, not populated |
| RAI CHA Score | InterRAI Output | `patients.rai_cha_score` | ⚠️ Schema exists, not populated |
| IAR Upload Status | SPO Action | **Not tracked** | ❌ Critical Gap |

---

## Section 2: CC Model + Workflow Analysis with SSPO

### 2.1 Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        CC 2.1 DOMAIN MODEL (ERD)                                │
└─────────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────┐           ┌──────────────────────────┐
│ service_provider_        │           │        users             │
│   organizations          │           ├──────────────────────────┤
├──────────────────────────┤           │ id                       │
│ id                       │◀──────────│ organization_id (FK)     │
│ name                     │           │ organization_role        │
│ slug                     │           │ ...                      │
│ type (se_health|partner| │           └──────────────────────────┘
│       external)          │                      │
│ active                   │                      │
│ contact_email            │                      │
│ city, province           │                      │
└──────────────────────────┘                      │
         │                                        │
         │ 1:N                                    │
         ▼                                        ▼
┌──────────────────────────┐           ┌──────────────────────────┐
│ organization_user_roles  │           │   service_assignments    │
├──────────────────────────┤           ├──────────────────────────┤
│ id                       │           │ id                       │
│ user_id (FK)             │           │ care_plan_id (FK)        │
│ service_provider_org_id  │           │ patient_id (FK)          │
│ organization_role        │◀──────────│ service_provider_org_id  │
│ default_assignment_scope │           │ service_type_id (FK)     │
└──────────────────────────┘           │ assigned_user_id (FK)    │
                                       │ status                   │
┌──────────────────────────┐           │ scheduled_start/end      │
│      care_bundles        │           │ frequency_rule           │
├──────────────────────────┤           │ source (manual|triage|   │
│ id                       │           │         rpm_alert|api)   │
│ name                     │           └──────────────────────────┘
│ code                     │                      ▲
│ description              │                      │
│ active                   │                      │
└──────────────────────────┘                      │
         │                                        │
         │ M:N via pivot                          │
         ▼                                        │
┌──────────────────────────┐           ┌──────────────────────────┐
│ care_bundle_service_type │           │       care_plans         │
├──────────────────────────┤           ├──────────────────────────┤
│ care_bundle_id (FK)      │           │ id                       │
│ service_type_id (FK)     │◀──────────│ patient_id (FK)          │
│ default_frequency        │           │ care_bundle_id (FK)      │
│ default_provider_org_id  │           │ version                  │
└──────────────────────────┘           │ status (draft|active|    │
                                       │         archived)        │
┌──────────────────────────┐           │ goals, risks, interventions│
│     service_types        │           └──────────────────────────┘
├──────────────────────────┤                      ▲
│ id                       │                      │
│ name                     │           ┌──────────────────────────┐
│ code                     │           │     triage_results       │
│ category                 │           ├──────────────────────────┤
│ default_duration_minutes │           │ id                       │
│ active                   │           │ patient_id (FK)          │
└──────────────────────────┘           │ acuity_level             │
                                       │ dementia_flag            │
┌──────────────────────────┐           │ mh_flag                  │
│       patients           │           │ rpm_required             │
├──────────────────────────┤           │ fall_risk                │
│ id                       │◀──────────│ raw_referral_payload     │
│ name, email              │           │ ⚠️ NO InterRAI fields     │
│ triage_summary (JSON)    │           └──────────────────────────┘
│ maple_score              │
│ rai_cha_score            │
│ risk_flags (JSON)        │
│ primary_coordinator_id   │
└──────────────────────────┘
```

### 2.2 SPO ↔ SSPO Relationship Model

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     SPO / SSPO SUBCONTRACTING MODEL                             │
└─────────────────────────────────────────────────────────────────────────────────┘

    ┌───────────────────────────────────────────────────────────────────────┐
    │                        SPO (SE Health)                                │
    │   service_provider_organizations.type = 'se_health'                  │
    │                                                                       │
    │   ┌─────────────────────────────────────────────────────────────┐    │
    │   │  RETAINS:                                                    │    │
    │   │  • Full clinical & contractual liability                    │    │
    │   │  • OHaH relationship & reporting                            │    │
    │   │  • Bundle revenue ($5,000/week)                             │    │
    │   │  • IAR upload responsibility                                │    │
    │   │  • 0% missed care accountability                            │    │
    │   └─────────────────────────────────────────────────────────────┘    │
    └───────────────────────────────────────────────────────────────────────┘
                                       │
                    Subcontracts via ServiceAssignment
                    (service_provider_organization_id → SSPO)
                                       │
                                       ▼
    ┌───────────────────────────────────────────────────────────────────────┐
    │                      SSPO Partners (Multiple)                         │
    │   service_provider_organizations.type = 'partner'                    │
    │                                                                       │
    │   ┌─────────────────────────────────────────────────────────────┐    │
    │   │  Examples from seed data:                                   │    │
    │   │  • Grace Manor (Dementia care specialization)               │    │
    │   │  • Reconnect MH (Mental health services)                    │    │
    │   │  • Alexis Home Care (PSW services)                          │    │
    │   │                                                              │    │
    │   │  RECEIVES:                                                   │    │
    │   │  • ServiceAssignment with specific service_type             │    │
    │   │  • Patient visibility scoped to their assignments           │    │
    │   │  • Interdisciplinary notes filtered by visible_to_orgs      │    │
    │   └─────────────────────────────────────────────────────────────┘    │
    └───────────────────────────────────────────────────────────────────────┘
```

### 2.3 Key File References

| Domain Concept | Primary File | Line Range |
|----------------|--------------|------------|
| SPO Model | `app/Models/ServiceProviderOrganization.php` | Full file |
| SPO Migration | `database/migrations/2025_11_17_071817_create_service_provider_organization_tables.php` | 16-34 |
| Assignment Model | `app/Models/ServiceAssignment.php` | Full file |
| Assignment Migration | `database/migrations/2025_11_17_071840_create_service_assignments_and_interdisciplinary_notes_tables.php` | 16-41 |
| Care Bundle Builder | `app/Services/CareBundleBuilderService.php` | Full file |
| SPO Dashboard Controller | `app/Http/Controllers/Api/V2/Dashboard/SpoDashboardController.php` | Full file |
| SSPO Controller | `app/Http/Controllers/SspoController.php` | Full file |

---

## Section 3: InterRAI HC & IAR Implementation Requirements

### 3.1 Current State Gap Analysis

The codebase has **NO InterRAI HC or IAR implementation**:

```php
// Current TriageResult model (app/Models/TriageResult.php)
// Stores clinical flags but NOT InterRAI assessment data

$fillable = [
    'patient_id',
    'received_at',
    'triaged_at',
    'acuity_level',        // low/medium/high/critical
    'dementia_flag',       // boolean
    'mh_flag',             // boolean
    'rpm_required',        // boolean
    'fall_risk',           // boolean
    'behavioural_risk',    // boolean
    'notes',
    'raw_referral_payload', // JSON blob - unstructured
    'triaged_by',
];
// ⚠️ MISSING: interrai_hc_id, interrai_completion_date, interrai_assessor_id,
//            maple_score, rai_cha_score, adl_hierarchy, iadl_capacity,
//            cognitive_performance_scale, depression_rating_scale
```

### 3.2 Required InterRAI HC Model

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                    PROPOSED: interrai_assessments TABLE                         │
└─────────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────────┐
│  Column                        │ Type          │ Description                    │
├──────────────────────────────────────────────────────────────────────────────────┤
│  id                            │ bigint PK     │ Auto increment                 │
│  patient_id                    │ FK patients   │ Required                       │
│  assessment_type               │ enum          │ 'hc' | 'cha' | 'contact'       │
│  assessment_date               │ timestamp     │ When completed                 │
│  assessor_id                   │ FK users      │ Who completed                  │
│  assessor_role                 │ string        │ 'RN', 'Care Coordinator'       │
│  source                        │ enum          │ 'hpg_referral' | 'spo_completed'│
│                                │               │ | 'ohah_provided'              │
├──────────────────────────────────────────────────────────────────────────────────┤
│  ** InterRAI HC Output Scores **                                                 │
├──────────────────────────────────────────────────────────────────────────────────┤
│  maple_score                   │ string(10)    │ MAPLe 1-5 scale               │
│  rai_cha_score                 │ string(10)    │ CHA Algorithm output          │
│  adl_hierarchy                 │ tinyint       │ 0-6 scale                      │
│  iadl_difficulty               │ tinyint       │ 0-6 scale                      │
│  cognitive_performance_scale   │ tinyint       │ 0-6 CPS                        │
│  depression_rating_scale       │ tinyint       │ 0-14 DRS                       │
│  pain_scale                    │ tinyint       │ 0-3 scale                      │
│  chess_score                   │ tinyint       │ 0-5 health instability        │
│  method_for_locomotion        │ string        │ Mobility status                │
│  falls_in_last_90_days        │ boolean       │ Fall risk flag                 │
│  wandering_flag               │ boolean       │ Elopement risk                 │
├──────────────────────────────────────────────────────────────────────────────────┤
│  ** Clinical Diagnosis (CAPs) **                                                 │
├──────────────────────────────────────────────────────────────────────────────────┤
│  caps_triggered               │ json          │ Array of triggered CAPs        │
│  primary_diagnosis_icd10      │ string(10)    │ ICD-10 code                    │
│  secondary_diagnoses          │ json          │ Array of ICD-10 codes          │
├──────────────────────────────────────────────────────────────────────────────────┤
│  ** IAR Integration **                                                           │
├──────────────────────────────────────────────────────────────────────────────────┤
│  iar_upload_status            │ enum          │ 'pending' | 'uploaded' |        │
│                               │               │ 'failed' | 'not_required'      │
│  iar_upload_timestamp         │ timestamp     │ When uploaded to IAR           │
│  iar_confirmation_id          │ string        │ IAR system reference           │
│  chris_sync_status            │ enum          │ 'pending' | 'synced' | 'failed'│
├──────────────────────────────────────────────────────────────────────────────────┤
│  raw_assessment_data          │ longtext JSON │ Full InterRAI instrument       │
│  created_at, updated_at       │ timestamps    │                                │
│  deleted_at                   │ timestamp     │ Soft delete                    │
└──────────────────────────────────────────────────────────────────────────────────┘
```

### 3.3 InterRAI HC Workflow State Machine

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│               InterRAI HC ASSESSMENT STATE MACHINE                              │
└─────────────────────────────────────────────────────────────────────────────────┘

        ┌────────────────┐
        │ HPG Referral   │
        │ Received       │
        └───────┬────────┘
                │
                ▼
        ┌────────────────────────────────────────────┐
        │        Check InterRAI HC Status            │
        │  (OHaH RFS Section 3.2.1)                 │
        └───────────────────┬────────────────────────┘
                            │
           ┌────────────────┼────────────────┐
           │                │                │
           ▼                ▼                ▼
    ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
    │ InterRAI HC  │ │ InterRAI HC  │ │ InterRAI HC  │
    │ PRESENT &    │ │ > 3 MONTHS   │ │ MISSING      │
    │ CURRENT      │ │ OLD          │ │              │
    └──────┬───────┘ └──────┬───────┘ └──────┬───────┘
           │                │                │
           │                └───────┬────────┘
           │                        │
           ▼                        ▼
    ┌──────────────┐       ┌──────────────────────┐
    │ Extract &    │       │ SPO MUST COMPLETE    │
    │ Store from   │       │ InterRAI HC          │
    │ HPG Payload  │       │ (Within 14 days)     │
    └──────┬───────┘       └──────────┬───────────┘
           │                          │
           └──────────┬───────────────┘
                      │
                      ▼
           ┌──────────────────────┐
           │ Upload to IAR        │
           │ (Real-time required) │
           └──────────┬───────────┘
                      │
                      ▼
           ┌──────────────────────┐
           │ Sync to CHRIS        │
           │ (OHaH EHR)           │
           └──────────────────────┘
```

---

## Section 4: AlayaCare Comparison

### 4.1 Architectural Paradigm Comparison

| Aspect | AlayaCare | Connected Capacity 2.1 |
|--------|-----------|------------------------|
| **Core Model** | Visit-centric scheduling | Bundle-centric orchestration |
| **Primary Entity** | `Visit` (atomic, billable) | `ServiceAssignment` (part of bundle) |
| **Billing Model** | Fee-for-service per visit | Fixed weekly bundle ($5,000) |
| **Scheduling** | Staff availability → visit slots | Bundle requirements → assignment distribution |
| **Subcontracting** | Agency-to-agency referrals | SPO → SSPO with retained liability |
| **InterRAI** | Native integration with RAI-HC | **Not yet implemented** |
| **Mobile App** | Full-featured field staff app | Planned (API endpoints designed) |

### 4.2 Feature Mapping

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                  ALAYACARE ↔ CONNECTED CAPACITY MAPPING                         │
└─────────────────────────────────────────────────────────────────────────────────┘

AlayaCare Entity              CC 2.1 Equivalent              Notes
─────────────────────────────────────────────────────────────────────────────────
Visit                    →    ServiceAssignment             AC has richer status
VisitType                →    ServiceType                   Similar structure
Care Plan                →    CarePlan + CareBundle         CC adds bundle abstraction
Client                   →    Patient                       Similar + TNP extensions
Worker                   →    User (with org_role)          CC has SPO/SSPO context
Agency                   →    ServiceProviderOrganization   CC: se_health/partner/external
Shift                    →    (Not implemented)             Gap - no shift management
Clock In/Out             →    actual_start/actual_end       Exists in assignment
Task List                →    (CarePlan.interventions)      Less structured
Clinical Note            →    InterdisciplinaryNote         Similar with org visibility
RAI Assessment           →    (Not implemented)             Critical gap
Document                 →    (Not implemented)             No document storage
Medication Mgmt          →    (Not implemented)             Out of scope
Scheduling Calendar      →    (Partial - frontend only)     Backend needs work
Route Optimization       →    (Not in scope)                Per implementation plan
```

### 4.3 CC 2.1 Unique Differentiators (Not in AlayaCare)

1. **Bundle-Centric Model**: CC treats the entire care bundle as the unit of service, not individual visits. This aligns with OHaH's fixed-rate bundled care model.

2. **SSPO Marketplace Architecture**: The `service_provider_organizations` table with `type` enum enables a true marketplace where the primary SPO can dynamically route services to specialized partners.

3. **MetadataEngine Service** (`app/Services/MetadataEngine.php`): A rules-based engine that can automatically suggest care bundles based on TNP/triage flags (dementia, MH, RPM, etc.).

4. **AI-Assisted TNP**: Integration point for Gemini-based summarization of patient needs (per implementation plan Phase 4).

5. **OHaH Compliance Dashboards**: Purpose-built for 0% missed care, 100% acceptance, and weekly huddle metrics.

---

## Section 5: Gap Analysis

### 5.1 Critical Gaps (Must Fix for OHaH Compliance)

| # | Gap | OHaH Requirement | Current State | Impact | File Reference |
|---|-----|------------------|---------------|--------|----------------|
| G1 | No InterRAI HC Storage | SPO must store/complete InterRAI HC | Only clinical flags in TriageResult | Cannot demonstrate compliance | `app/Models/TriageResult.php` |
| G2 | No IAR Upload Tracking | Must upload to IAR in real-time | No IAR fields anywhere | CHRIS sync impossible | N/A - needs new model |
| G3 | No HPG Response Timer | 15-minute response SLA | No timestamp tracking | SLA breach undetectable | `triage_results` migration |
| G4 | No Time-to-First-Service | <24 hours required | No first_service_at field | Cannot report compliance | `service_assignments` migration |
| G5 | No Missed Care Calculator | 0% target | Status tracking exists but no aggregation | Cannot compute metric | `SpoDashboardController.php` |

### 5.2 High Priority Gaps (Operational)

| # | Gap | Business Need | Current State | File Reference |
|---|-----|---------------|---------------|----------------|
| G6 | Hardcoded Services | Dynamic service catalog | Services in `careBundleConstants.js` | `resources/js/data/careBundleConstants.js` |
| G7 | No Shadow Billing | $0 rate codes for OH | No billing model | N/A |
| G8 | No Crisis Designation | LTC waitlist priority | Partially in acuity_level | `triage_results.acuity_level` |
| G9 | No Weekly Huddle Board | OHaH coordination | Not implemented | N/A |
| G10 | No SSPO Acceptance Flow | SSPO must accept assignments | Assignment created directly | `CareBundleBuilderService.php` |

### 5.3 Medium Priority Gaps (Quality of Life)

| # | Gap | Impact | Current State |
|---|-----|--------|---------------|
| G11 | No Visit Recurrence Engine | Manual scheduling burden | `frequency_rule` field exists, no processor |
| G12 | No Document Management | Paper-based attachments | No documents table |
| G13 | No Mobile API Auth | Field staff blocked | Sanctum configured but no mobile endpoints |
| G14 | No Audit Trail UI | Compliance reporting | AuditLog model exists, no viewer |
| G15 | No Patient/Caregiver Portal | Self-service | Out of scope per impl plan |

### 5.4 Gap Priority Matrix

```
                        IMPACT ON OHaH COMPLIANCE
                    Low         Medium        High
               ┌───────────┬───────────┬───────────┐
         High  │ G11,G14   │ G6,G10    │ G1,G2,G3  │
  EFFORT       ├───────────┼───────────┼───────────┤
  TO FIX       │ G12,G15   │ G7,G9     │ G4,G5     │
         Low   ├───────────┼───────────┼───────────┤
               │ G13       │ G8        │           │
               └───────────┴───────────┴───────────┘
```

---

## Section 6: Actionable Design & Refactor Backlog

### 6.1 Theme 1: InterRAI HC & IAR Integration (8 tickets)

| Ticket | Title | Type | Priority | Est. | Description |
|--------|-------|------|----------|------|-------------|
| IR-001 | Create `interrai_assessments` migration | Backend | P0 | 3h | Create table per Section 3.2 schema with all InterRAI HC output fields, IAR upload tracking, and CHRIS sync status |
| IR-002 | Create `InterraiAssessment` Eloquent model | Backend | P0 | 2h | Model with relationships to Patient, assessor User, scopes for pending_iar_upload, needs_reassessment (>3mo old) |
| IR-003 | Create `InterraiService.php` | Backend | P0 | 4h | Service class: `extractFromHpgPayload()`, `markStale()`, `requiresCompletion()`, `uploadToIar()` |
| IR-004 | Add InterRAI fields to TNP wizard | Frontend | P1 | 6h | Extend `TnpReviewDetailPage.jsx` with InterRAI HC tab showing MAPLe, CPS, ADL hierarchy with edit capability |
| IR-005 | Create IAR upload job | Backend | P1 | 4h | `UploadInterraiToIarJob` with retry logic, confirmation ID capture, failure alerting |
| IR-006 | Create InterRAI completion workflow | Full Stack | P1 | 8h | UI flow for SPO RN to complete InterRAI HC when missing/stale, with CAPs triggering |
| IR-007 | Add InterRAI staleness check to referral intake | Backend | P1 | 3h | In `ReferralService.php`, check assessment date, flag if >3 months or clinical change |
| IR-008 | Create IAR integration stub/mock | Backend | P2 | 4h | Interface for IAR API (mock initially), with real implementation TBD based on OH specs |

### 6.2 Theme 2: OHaH SLA Compliance (7 tickets)

| Ticket | Title | Type | Priority | Est. | Description |
|--------|-------|------|----------|------|-------------|
| SLA-001 | Add `hpg_received_at` and `hpg_responded_at` to referrals | Backend | P0 | 2h | New columns on `triage_results` to track 15-minute response SLA |
| SLA-002 | Create HPG response timer service | Backend | P0 | 3h | `HpgResponseService` with `checkResponseDeadline()`, fires `HpgDeadlineApproaching` event at 10 min |
| SLA-003 | Add `first_service_delivered_at` to `care_plans` | Backend | P0 | 2h | Track <24h to first service compliance |
| SLA-004 | Create missed care aggregation service | Backend | P0 | 4h | `MissedCareService::calculate($orgId, $period)` → missed events / (delivered + missed) |
| SLA-005 | Build SLA compliance dashboard widget | Frontend | P1 | 6h | React component showing real-time: HPG response %, time-to-first-service, missed care rate |
| SLA-006 | Create compliance alert notifications | Backend | P1 | 4h | Email/push notifications when SLAs at risk (e.g., 12 min HPG response, 22h no first service) |
| SLA-007 | Weekly huddle report generator | Backend | P1 | 4h | `GenerateWeeklyHuddleReportJob` producing PDF/Excel with all OHaH metrics for CC meeting |

### 6.3 Theme 3: Service Catalog & Bundle Refactor (6 tickets)

| Ticket | Title | Type | Priority | Est. | Description |
|--------|-------|------|----------|------|-------------|
| SC-001 | Seed `service_types` from `careBundleConstants.js` | Backend | P0 | 2h | Migration seeder to populate 22 service types from current hardcoded data |
| SC-002 | Create `ServiceTypeController` API | Backend | P0 | 3h | CRUD endpoints for service types: `GET /api/v2/service-types`, `POST`, `PUT`, `DELETE` |
| SC-003 | Refactor `CareBundleWizard.jsx` to use API | Frontend | P1 | 4h | Replace `INITIAL_SERVICES` import with API fetch from `/api/v2/service-types` |
| SC-004 | Add cost tracking to `service_types` | Backend | P1 | 2h | Columns: `cost_per_visit`, `cost_code`, `cost_driver` (currently in JS constants) |
| SC-005 | Create bundle template versioning | Backend | P2 | 4h | Allow care_bundles to have versions, track which template version a care_plan used |
| SC-006 | Add bundle eligibility rules | Backend | P2 | 6h | JSON schema for eligibility criteria (e.g., "dementia_flag=true → Dementia Bundle recommended") |

### 6.4 Theme 4: SSPO Marketplace & Workflow (5 tickets)

| Ticket | Title | Type | Priority | Est. | Description |
|--------|-------|------|----------|------|-------------|
| SSPO-001 | Add SSPO acceptance status to `service_assignments` | Backend | P0 | 2h | New column `sspo_acceptance_status` (pending/accepted/declined) with timestamps |
| SSPO-002 | Create SSPO assignment acceptance API | Backend | P0 | 3h | `POST /api/v2/assignments/{id}/accept`, `POST /api/v2/assignments/{id}/decline` |
| SSPO-003 | Build SSPO acceptance UI in `SspoPortal` | Frontend | P1 | 6h | Pending assignments list with accept/decline actions, reason field for decline |
| SSPO-004 | Create SSPO performance metrics | Backend | P1 | 4h | Calculate: acceptance rate, average response time, missed visit rate per SSPO |
| SSPO-005 | Add SSPO service capabilities | Backend | P2 | 4h | Pivot table `sspo_service_capabilities` linking organizations to service_types they can provide |

### 6.5 Theme 5: Data Quality & Technical Debt (6 tickets)

| Ticket | Title | Type | Priority | Est. | Description |
|--------|-------|------|----------|------|-------------|
| DQ-001 | Add foreign key constraints to migrations | Backend | P1 | 3h | Many tables reference others without FK constraints (e.g., `rpm_alert_id` on assignments) |
| DQ-002 | Create patient status enum migration | Backend | P1 | 2h | `patients.status` declared as boolean but used as string; standardize to enum |
| DQ-003 | Add database indexes for dashboard queries | Backend | P1 | 2h | Missing indexes on frequently filtered columns identified in SpoDashboardController |
| DQ-004 | Implement soft deletes consistently | Backend | P1 | 2h | Some models have soft deletes, others don't; standardize across care entities |
| DQ-005 | Create data validation layer | Backend | P2 | 4h | Form requests for all API endpoints (currently many accept raw JSON) |
| DQ-006 | Add PHPStan/Larastan static analysis | DevOps | P2 | 3h | Enforce type safety, catch null reference issues |

### 6.6 Theme 6: Frontend Architecture (6 tickets)

| Ticket | Title | Type | Priority | Est. | Description |
|--------|-------|------|----------|------|-------------|
| FE-001 | Create shared `DataTable` component | Frontend | P0 | 4h | Reusable table with sort, filter, pagination (per impl plan Phase 3) |
| FE-002 | Build `KpiCard` component | Frontend | P0 | 2h | Per SPO Dashboard spec: label, value, trend, status variants |
| FE-003 | Create `AiForecastPanel` component | Frontend | P1 | 4h | Loading states, insight cards, "Run Forecast" trigger |
| FE-004 | Implement auth state persistence | Frontend | P1 | 3h | Currently `/api/user` called on every page load; add localStorage cache with refresh |
| FE-005 | Add error boundary and 500 handling | Frontend | P2 | 3h | Global error boundary with user-friendly messaging |
| FE-006 | Create component documentation (Storybook) | Frontend | P2 | 6h | Document all shared components with examples |

### 6.7 Theme 7: Mobile & Field Staff (4 tickets)

| Ticket | Title | Type | Priority | Est. | Description |
|--------|-------|------|----------|------|-------------|
| MOB-001 | Create mobile API route group | Backend | P1 | 2h | `routes/api-mobile.php` with Sanctum auth, rate limiting |
| MOB-002 | Build mobile worklist endpoint | Backend | P1 | 4h | `GET /api/mobile/worklist` returning today's assignments, patient info, service details |
| MOB-003 | Create clock in/out API | Backend | P1 | 3h | `POST /api/mobile/assignments/{id}/clock-in`, `POST .../clock-out` with geolocation |
| MOB-004 | Add mobile note submission | Backend | P1 | 3h | `POST /api/mobile/notes` with offline sync support (queued submission) |

---

### 6.8 Backlog Summary

| Theme | Tickets | Total Est. Hours | Priority Mix |
|-------|---------|------------------|--------------|
| InterRAI HC & IAR Integration | 8 | 34h | 3 P0, 4 P1, 1 P2 |
| OHaH SLA Compliance | 7 | 25h | 4 P0, 3 P1 |
| Service Catalog & Bundle | 6 | 21h | 2 P0, 2 P1, 2 P2 |
| SSPO Marketplace | 5 | 19h | 2 P0, 2 P1, 1 P2 |
| Data Quality & Tech Debt | 6 | 16h | 0 P0, 4 P1, 2 P2 |
| Frontend Architecture | 6 | 22h | 2 P0, 2 P1, 2 P2 |
| Mobile & Field Staff | 4 | 12h | 0 P0, 4 P1 |
| **TOTAL** | **42** | **149h** | **13 P0, 21 P1, 8 P2** |

### 6.9 Recommended Sprint Sequence

**Sprint 1 (P0 Foundation)**: IR-001, IR-002, SLA-001, SC-001, SSPO-001 — InterRAI table, HPG tracking, service seeding

**Sprint 2 (P0 Services)**: IR-003, SLA-002, SLA-003, SC-002, SSPO-002 — Core services and APIs

**Sprint 3 (P1 Compliance)**: SLA-004, SLA-005, SLA-006, SLA-007, IR-007 — Compliance dashboard and alerts

**Sprint 4 (P1 SSPO)**: SSPO-003, SSPO-004, SC-003, FE-001, FE-002 — SSPO portal and frontend foundation

**Sprint 5 (P1 InterRAI)**: IR-004, IR-005, IR-006, DQ-001, DQ-002 — InterRAI completion workflow

**Sprint 6 (P1 Mobile)**: MOB-001-004, FE-004, DQ-003, DQ-004 — Mobile API and data quality

**Sprint 7+ (P2)**: Remaining lower priority items

---

## Appendix A: Source Documents Analyzed

1. **OHaH RFS PDF** - Bundled High Intensity Home Care (LTC Stream)
2. **OHaH Q&A PDF** - Clarifications on FTE ratios, missed care, crisis designation
3. **Implementation Plans**:
   - `docs/cc2-implementation-plan.md`
   - `IMPLEMENTATION_PLAN_v3_Laravel11.md`
   - `planning/SPO_Dashboard_Spec_and_Plan.md`
4. **Database Migrations** (2025_11_17_* series)
5. **React Frontend** (`resources/js/`)
6. **Laravel Models** (`app/Models/`)
7. **Services** (`app/Services/`)

---

## Appendix B: Glossary

| Term | Definition |
|------|------------|
| **OHaH** | Ontario Health atHome (formerly HCCSS/LHIN) |
| **SPO** | Service Provider Organization (primary contractor) |
| **SSPO** | Subcontracted Service Provider Organization |
| **HPG** | Health Partner Gateway (referral system) |
| **InterRAI HC** | InterRAI Home Care assessment instrument |
| **IAR** | Integrated Assessment Record (provincial repository) |
| **CHRIS** | Client Health Related Information System (OHaH EHR) |
| **MAPLe** | Method for Assigning Priority Levels (InterRAI output) |
| **CPS** | Cognitive Performance Scale (InterRAI output) |
| **CAPs** | Clinical Assessment Protocols (InterRAI triggers) |
| **TNP** | Transition Needs Profile (CC terminology for triage) |
| **RPM** | Remote Patient Monitoring |

---

*Document generated by Claude Code architecture analysis*

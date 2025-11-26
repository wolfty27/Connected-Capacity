# InterRAI HC Integration Plan - Connected Capacity 2.1

**Document Version:** 1.0
**Status:** Draft
**Author:** Planning Agent
**Date:** 2025-11-26

---

## Executive Summary

This document outlines the plan for completing InterRAI Home Care (HC) integration in Connected Capacity 2.1 to meet Ontario Health at Home (OHaH) requirements. The system already has substantial InterRAI infrastructure; this plan identifies gaps and provides a roadmap for full compliance.

### OHaH RFS Requirement
> "Complete InterRAI HC if missing, >3 months old, or condition changes. Upload to IAR in real time."

### Current State Assessment

| Component | Status | Notes |
|-----------|--------|-------|
| InterraiAssessment Model | **Complete** | Full schema with MAPLe, CPS, ADL, IADL, CHESS, DRS, pain, falls, wandering, CAPs |
| InterraiService | **Partial** | HPG extraction, SPO creation, staleness checks work; IAR/CHRIS uploads are stubs |
| InterraiController API | **Complete** | Full CRUD + form schema + retry endpoints |
| InterraiCompletionWizard | **Complete** | 6-step wizard for SPO assessment entry |
| TNP/Patient Page Integration | **Partial** | Shows scores + status; missing document attachment workflow |
| IAR Real-time Upload | **Stub Only** | Returns mock confirmation IDs |
| Condition Change Detection | **Missing** | No triggers for clinical condition changes |
| Admin Assessment Dashboard | **Missing** | No central view for pending/failed uploads |

---

## Part 1: UX Plan

### 1.1 Design Philosophy

**Summary-First Approach:** SPO users (Coordinators, Clinicians) are overwhelmed with clinical data. InterRAI integration must:
- Show **only essential info** on primary screens (TNP, Patient Detail)
- Provide clear **action indicators** when assessment is needed
- Offer **drill-down** to full details when required
- Never block workflow—guide rather than mandate

### 1.2 Placement Strategy

| Location | What to Show | Rationale |
|----------|--------------|-----------|
| **Patient Detail Page** (Overview tab) | Clinical Snapshot card with 4 key scores (MAPLe, CPS, ADL, CHESS) | Quick reference during daily work |
| **Patient Detail Page** (InterRAI tab) | Full assessment details + integration status | Already implemented; enhance with actions |
| **TNP Review Page** (InterRAI tab) | Same as above | Critical for transition planning |
| **Patient Queue List** | InterRAI status column with badge | Coordinators need queue-level visibility |
| **NEW: Admin > Assessments Dashboard** | Pending uploads, failed uploads, stale assessments | Admin oversight + compliance reporting |
| **Care Bundle Wizard** (Step 1) | Assessment warning banner if stale/missing | Block or warn before bundle creation |

### 1.3 Component Specifications

#### 1.3.1 InterRAI Summary Panel (Compact)

**Used in:** Patient Detail Overview, Patient Queue rows, TNP header

```
┌─────────────────────────────────────────────────────────┐
│  InterRAI HC Assessment                    [Status Badge]│
│  ─────────────────────────────────────────────────────── │
│   MAPLe: 4 (High)  │  CPS: 2  │  ADL: 3  │  CHESS: 1    │
│   Last: 2025-09-15  │  Source: HPG Referral              │
│                                                          │
│   [⚠️ Stale - 72 days old]     [Complete Assessment →]   │
└─────────────────────────────────────────────────────────┘
```

**Status Badge Values:**
| Badge | Color | Meaning |
|-------|-------|---------|
| `Current` | Green | Assessment <90 days old |
| `Stale` | Amber | Assessment >90 days old |
| `Missing` | Red | No assessment on file |
| `Uploaded to IAR` | Blue | Confirmed uploaded |
| `IAR Pending` | Gray | Awaiting upload |
| `IAR Failed` | Red outline | Upload failed, needs retry |

#### 1.3.2 InterRAI Full Detail Panel

**Used in:** Patient Detail InterRAI tab, TNP Review InterRAI tab

```
┌─────────────────────────────────────────────────────────────────┐
│  Current InterRAI HC Assessment                                  │
│  ═══════════════════════════════════════════════════════════════ │
│                                                                  │
│  Assessment Info                                                 │
│  ┌──────────────┬──────────────┬──────────────┬───────────────┐ │
│  │ Date         │ Type         │ Assessor     │ Source        │ │
│  │ 2025-09-15   │ RAI-HC       │ Jane Smith   │ HPG Referral  │ │
│  └──────────────┴──────────────┴──────────────┴───────────────┘ │
│                                                                  │
│  Clinical Scores                                                 │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐        │
│  │ MAPLe  │ │  CPS   │ │  ADL   │ │ CHESS  │ │  DRS   │        │
│  │   4    │ │   2    │ │   3    │ │   1    │ │   5    │        │
│  │  High  │ │  Mild  │ │ Ext.   │ │ Minimal│ │        │        │
│  └────────┘ └────────┘ └────────┘ └────────┘ └────────┘        │
│                                                                  │
│  Risk Flags                                                      │
│  [Fall Risk] [Cognitive Impairment] [Wandering Risk]            │
│                                                                  │
│  Triggered CAPs (8)                                              │
│  [ADL/Rehab] [Cognitive Loss] [Falls] [IADL] [Medication] ...   │
│                                                                  │
│  Integration Status                                              │
│  ┌────────────────────────────┬─────────────────────────────┐   │
│  │ IAR Upload    [Uploaded ✓] │ CHRIS Sync    [Pending ⏳]  │   │
│  │ ID: IAR-ABC123DEF456       │                             │   │
│  │ 2025-09-15 14:32:00        │ Retry Sync                  │   │
│  └────────────────────────────┴─────────────────────────────┘   │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ [View Full Assessment]  [Download PDF]  [View History]    │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

#### 1.3.3 Assessment Actions Panel (Quick Actions)

**Used in:** TNP Review page, Patient Detail page

```
┌─────────────────────────────────────────────────────────────────┐
│  Assessment Actions                                              │
│  ═══════════════════════════════════════════════════════════════ │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ [📝 Complete InterRAI HC]                                   ││
│  │ Start the 6-step wizard to complete a new assessment        ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ [📎 Mark Completed Externally + Attach]                     ││
│  │ Assessment completed outside CC2—upload PDF and enter       ││
│  │ scores manually                                             ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ [🔗 Add IAR Document ID]                                    ││
│  │ Link existing IAR record if already uploaded elsewhere      ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ [🔄 Request Reassessment]                                   ││
│  │ Flag for reassessment due to condition change               ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

#### 1.3.4 Admin Assessments Dashboard

**Route:** `/admin/assessments`
**Access:** Admin role only

```
┌─────────────────────────────────────────────────────────────────┐
│  InterRAI Assessments Dashboard                                  │
│  ═══════════════════════════════════════════════════════════════ │
│                                                                  │
│  KPI Cards                                                       │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│  │ Pending  │ │ Failed   │ │ Stale    │ │ Missing  │            │
│  │ IAR      │ │ Uploads  │ │ (>90d)   │ │ Assess.  │            │
│  │   12     │ │    3     │ │   28     │ │   45     │            │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘            │
│                                                                  │
│  [Tabs: Pending IAR | Failed | Stale | Missing | All]           │
│  ─────────────────────────────────────────────────────────────── │
│                                                                  │
│  │ Patient       │ Assessment │ Status      │ Actions        │  │
│  │───────────────│────────────│─────────────│────────────────│  │
│  │ John Smith    │ 2025-09-01 │ IAR Pending │ [Retry] [View] │  │
│  │ Mary Johnson  │ 2025-08-15 │ IAR Failed  │ [Retry] [View] │  │
│  │ ...           │            │             │                │  │
│  ─────────────────────────────────────────────────────────────── │
│                                                                  │
│  [Bulk Actions: Retry All Failed] [Export Report]               │
└─────────────────────────────────────────────────────────────────┘
```

### 1.4 Workflow State Badges

The assessment lifecycle needs clear visual states:

```
┌─────────────────────────────────────────────────────────────────┐
│                    Assessment Lifecycle                          │
│                                                                  │
│  [No Assessment] ──► [From Referral] ──► [Uploaded to IAR]      │
│        │                   │                    │                │
│        │                   ▼                    │                │
│        │            [Stale (>90d)]              │                │
│        │                   │                    │                │
│        ▼                   ▼                    ▼                │
│  [Completed by SPO] ──► [IAR Pending] ──► [IAR Failed]          │
│                              │                   │               │
│                              └───────────────────┘               │
│                                     │                            │
│                                     ▼                            │
│                              [IAR Uploaded]                      │
└─────────────────────────────────────────────────────────────────┘
```

| State | Badge Style | Description |
|-------|-------------|-------------|
| `No Assessment` | Red, solid | Patient has no InterRAI on file |
| `From Referral` | Blue, outline | Extracted from HPG referral payload |
| `Completed by SPO` | Teal, solid | SPO staff completed via wizard |
| `Stale (>90d)` | Amber, solid | Assessment date >90 days ago |
| `IAR Pending` | Gray, outline | Queued for IAR upload |
| `IAR Failed` | Red, outline | Upload failed, needs attention |
| `Uploaded to IAR` | Green, solid | Successfully uploaded with confirmation |

### 1.5 User Flow Diagrams

#### Flow 1: New Patient - No Assessment

```
Patient arrives in queue
        │
        ▼
┌─────────────────────┐
│ Queue List shows    │
│ [Missing] badge     │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐     ┌─────────────────────┐
│ Open Patient Detail │────►│ InterRAI tab shows  │
│                     │     │ "No Assessment"     │
└─────────────────────┘     │ + Complete button   │
                            └─────────┬───────────┘
                                      │
                    ┌─────────────────┼─────────────────┐
                    │                 │                 │
                    ▼                 ▼                 ▼
          ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
          │ Complete    │   │ Mark Done   │   │ Add IAR     │
          │ Wizard      │   │ Externally  │   │ Doc ID      │
          └──────┬──────┘   └──────┬──────┘   └──────┬──────┘
                 │                 │                 │
                 └─────────────────┴─────────────────┘
                                   │
                                   ▼
                    ┌─────────────────────┐
                    │ Assessment Created  │
                    │ IAR Upload Queued   │
                    └─────────────────────┘
```

#### Flow 2: Assessment Becomes Stale

```
Scheduled job runs daily
        │
        ▼
┌─────────────────────────────┐
│ Detect assessments >90 days │
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│ Update patient queue badge  │
│ to show [Stale] indicator   │
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│ Notify assigned coordinator │
│ via in-app notification     │
└─────────────────────────────┘
```

#### Flow 3: IAR Upload Failure Recovery

```
Admin opens Assessments Dashboard
        │
        ▼
┌─────────────────────────────┐
│ Views "Failed Uploads" tab  │
│ (3 assessments shown)       │
└─────────────┬───────────────┘
              │
       ┌──────┴──────┐
       │             │
       ▼             ▼
┌─────────────┐  ┌─────────────┐
│ Retry Single│  │ Bulk Retry  │
│ Upload      │  │ All Failed  │
└──────┬──────┘  └──────┬──────┘
       │                │
       └────────────────┘
              │
              ▼
┌─────────────────────────────┐
│ Jobs dispatched to queue    │
│ Status updates in real-time │
└─────────────────────────────┘
```

---

## Part 2: Architecture Plan

### 2.1 Data Model

#### 2.1.1 Current Schema (Already Implemented)

```
interrai_assessments
├── id (PK)
├── patient_id (FK → patients)
├── assessment_type (enum: hc, cha, contact)
├── assessment_date (date)
├── assessor_id (FK → users, nullable)
├── assessor_role (string)
├── source (enum: hpg_referral, spo_completed, ohah_provided)
│
├── # Clinical Scores
├── maple_score (string: 1-5)
├── rai_cha_score (string)
├── adl_hierarchy (int: 0-6)
├── iadl_difficulty (int: 0-6)
├── cognitive_performance_scale (int: 0-6)
├── depression_rating_scale (int: 0-14)
├── pain_scale (int: 0-4)
├── chess_score (int: 0-5)
├── method_for_locomotion (string)
├── falls_in_last_90_days (boolean)
├── wandering_flag (boolean)
│
├── # CAPs & Diagnoses
├── caps_triggered (json array)
├── primary_diagnosis_icd10 (string)
├── secondary_diagnoses (json array)
│
├── # Integration Status
├── iar_upload_status (enum: pending, uploaded, failed, not_required)
├── iar_upload_timestamp (datetime)
├── iar_confirmation_id (string)
├── chris_sync_status (enum: pending, synced, failed, not_required)
├── chris_sync_timestamp (datetime)
│
├── # Metadata
├── raw_assessment_data (json)
├── created_at, updated_at, deleted_at
```

#### 2.1.2 New/Modified Tables

**Table: `interrai_documents`** (NEW)

```sql
CREATE TABLE interrai_documents (
    id BIGINT PRIMARY KEY,
    interrai_assessment_id BIGINT FK → interrai_assessments,
    document_type VARCHAR(50),        -- 'pdf', 'external_iar_id', 'attachment'
    file_path VARCHAR(500),           -- For uploaded files
    external_iar_id VARCHAR(100),     -- For linked external IAR records
    uploaded_by BIGINT FK → users,
    uploaded_at TIMESTAMP,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Table: `assessment_reassessment_triggers`** (NEW)

```sql
CREATE TABLE assessment_reassessment_triggers (
    id BIGINT PRIMARY KEY,
    patient_id BIGINT FK → patients,
    triggered_by BIGINT FK → users,
    trigger_reason ENUM('condition_change', 'manual_request', 'clinical_event'),
    reason_notes TEXT,
    resolved_at TIMESTAMP,
    resolved_by BIGINT FK → users,
    resolution_assessment_id BIGINT FK → interrai_assessments,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Column Additions to `patients`:** (Already exists, verify)

```sql
ALTER TABLE patients ADD COLUMN interrai_status VARCHAR(50) DEFAULT 'missing';
-- Cached status: 'current', 'stale', 'missing', 'pending_upload', 'upload_failed'
```

### 2.2 Service Architecture

#### 2.2.1 InterraiService Enhancements

```
InterraiService (Enhanced)
│
├── # Existing Methods (Keep)
├── extractFromHpgPayload()
├── createSpoAssessment()
├── requiresCompletion()
├── getStaleAssessments()
├── getPatientsNeedingAssessment()
├── getPendingIarUploads()
│
├── # New Methods
├── createExternalAssessment()      -- Mark as completed externally
├── attachDocument()                -- Upload PDF/link IAR ID
├── requestReassessment()           -- Trigger condition change
├── resolveReassessmentTrigger()    -- Mark trigger resolved
├── getReassessmentTriggers()       -- List pending triggers
├── syncPatientInterraiStatus()     -- Update cached status
│
└── # Integration Methods (Replace stubs)
    ├── uploadToIar()               -- Real IAR API call
    └── syncToChris()               -- Real CHRIS API call
```

#### 2.2.2 IarClient Adapter (NEW)

```php
interface IarClientInterface
{
    public function uploadAssessment(InterraiAssessment $assessment): IarUploadResult;
    public function getAssessmentStatus(string $confirmationId): IarStatusResult;
    public function searchByPatient(string $ohipNumber): IarSearchResult;
}

class IarClient implements IarClientInterface
{
    // Production implementation connecting to Ontario Health IAR API
}

class MockIarClient implements IarClientInterface
{
    // Development/testing mock implementation
}
```

**Configuration:**
```php
// config/services.php
'iar' => [
    'base_url' => env('IAR_API_BASE_URL'),
    'client_id' => env('IAR_CLIENT_ID'),
    'client_secret' => env('IAR_CLIENT_SECRET'),
    'timeout' => env('IAR_TIMEOUT', 30),
    'mock_enabled' => env('IAR_MOCK_ENABLED', true),
],
```

### 2.3 Integration Points

#### 2.3.1 PatientQueue Integration

```
PatientQueueController
│
├── index()
│   └── Include interrai_status in response
│       └── Badge: current/stale/missing/pending/failed
│
├── transition()
│   └── Before TNP → Bundle transition:
│       └── Check if InterRAI is current (warn if not)
│
└── getReadyForBundle()
    └── Filter/flag patients without current assessment
```

#### 2.3.2 TNP/Triage Integration

```
TnpController
│
├── show()
│   └── Include interrai summary in response
│
└── update()
    └── On clinical_flags change:
        └── Optionally trigger reassessment request
```

#### 2.3.3 CareBundleBuilder Integration

```
CareBundleBuilderService
│
├── buildPatientContext()
│   └── Pull InterRAI scores for bundle recommendations:
│       ├── MAPLe → Service intensity
│       ├── CPS → Cognitive support services
│       ├── ADL → Personal support worker hours
│       └── CHESS → Health instability monitoring
│
└── getAvailableBundles()
    └── Use InterRAI data for bundle eligibility rules
```

#### 2.3.4 OhMetric Integration

```
OhMetric (for compliance reporting)
│
├── Metric: interrai_completion_rate
│   └── % of active patients with current assessment
│
├── Metric: iar_upload_success_rate
│   └── % of assessments successfully uploaded
│
├── Metric: avg_iar_upload_latency
│   └── Average time from creation to upload
│
└── Metric: stale_assessment_count
    └── Count of assessments >90 days old
```

### 2.4 API Endpoints

#### 2.4.1 New Endpoints Required

```
# Assessment Documents
POST   /api/v2/interrai/assessments/{id}/documents
GET    /api/v2/interrai/assessments/{id}/documents
DELETE /api/v2/interrai/assessments/{id}/documents/{docId}

# External Assessment Linking
POST   /api/v2/interrai/patients/{patient}/link-external
       Body: { iar_document_id: "IAR-XXX", assessment_date: "..." }

# Reassessment Triggers
POST   /api/v2/interrai/patients/{patient}/request-reassessment
       Body: { reason: "condition_change", notes: "..." }
GET    /api/v2/interrai/reassessment-triggers
POST   /api/v2/interrai/reassessment-triggers/{id}/resolve

# Admin Dashboard
GET    /api/v2/admin/interrai/dashboard-stats
GET    /api/v2/admin/interrai/stale-assessments
POST   /api/v2/admin/interrai/bulk-retry-iar
```

### 2.5 Background Jobs

```
# Existing (enhance)
UploadInterraiToIarJob
├── Retry logic with exponential backoff
├── Dead letter queue after 5 failures
└── Notification on final failure

# New
ProcessPendingIarUploadsJob (scheduled: every 15 min)
├── Pick up pending uploads
└── Dispatch individual UploadInterraiToIarJob

DetectStaleAssessmentsJob (scheduled: daily at 6am)
├── Find assessments approaching 90 days
├── Update patient interrai_status cache
└── Send coordinator notifications at 75 days

SyncInterraiStatusJob (scheduled: hourly)
├── Reconcile patient.interrai_status with actual data
└── Handle edge cases
```

### 2.6 Future-Proofing for IAR Integration

The IAR API integration should be designed for easy adaptation:

```php
// Domain layer - stable
class InterraiService
{
    public function uploadToIar(InterraiAssessment $assessment): array
    {
        return $this->iarClient->uploadAssessment($assessment);
    }
}

// Infrastructure layer - swappable
interface IarClientInterface { }

// Can swap implementations without touching domain logic
class OntarioHealthIarClient implements IarClientInterface
{
    // Real Ontario Health API (when specs available)
}

class MockIarClient implements IarClientInterface
{
    // Current mock implementation
}

class EvolveIarClient implements IarClientInterface
{
    // If IAR API changes/versions
}
```

---

## Part 3: Ticket Backlog

### 3.1 Epic Structure

```
EPIC: IR-001 - InterRAI HC Full Integration
├── IR-002: UX Enhancements
├── IR-003: Assessment Workflow Actions
├── IR-004: Document Attachment System
├── IR-005: Reassessment Trigger System
├── IR-006: Admin Dashboard
├── IR-007: IAR Integration (Real)
├── IR-008: CareBundleBuilder Integration
└── IR-009: Compliance Reporting
```

### 3.2 Prioritized Ticket List

#### Priority 1: Critical Path (Sprint 1-2)

| ID | Title | Type | Points | Dependencies |
|----|-------|------|--------|--------------|
| IR-002-01 | Add InterRAI status badge to PatientQueue list | Frontend | 3 | - |
| IR-002-02 | Enhance InterRAI summary panel with freshness indicator | Frontend | 2 | - |
| IR-002-03 | Add assessment warning banner to CareBundleWizard step 1 | Frontend | 3 | - |
| IR-003-01 | Implement "Mark Completed Externally" workflow | Full Stack | 5 | - |
| IR-003-02 | Implement "Add IAR Document ID" linking action | Full Stack | 3 | IR-003-01 |
| IR-004-01 | Create interrai_documents table migration | Backend | 2 | - |
| IR-004-02 | Build document upload API endpoints | Backend | 5 | IR-004-01 |
| IR-004-03 | Build document attachment UI in assessment panel | Frontend | 5 | IR-004-02 |

#### Priority 2: Core Functionality (Sprint 3-4)

| ID | Title | Type | Points | Dependencies |
|----|-------|------|--------|--------------|
| IR-005-01 | Create reassessment_triggers table migration | Backend | 2 | - |
| IR-005-02 | Implement reassessment request API | Backend | 3 | IR-005-01 |
| IR-005-03 | Add "Request Reassessment" button to UI | Frontend | 2 | IR-005-02 |
| IR-005-04 | Build reassessment trigger resolution workflow | Full Stack | 5 | IR-005-02 |
| IR-006-01 | Create Admin Assessments Dashboard page shell | Frontend | 3 | - |
| IR-006-02 | Build dashboard KPI cards (pending, failed, stale, missing) | Full Stack | 5 | IR-006-01 |
| IR-006-03 | Implement assessment list with filters and actions | Full Stack | 8 | IR-006-02 |
| IR-006-04 | Add bulk retry functionality for failed uploads | Full Stack | 3 | IR-006-03 |

#### Priority 3: Integration & Polish (Sprint 5-6)

| ID | Title | Type | Points | Dependencies |
|----|-------|------|--------|--------------|
| IR-007-01 | Create IarClientInterface and adapter pattern | Backend | 3 | - |
| IR-007-02 | Implement MockIarClient with realistic delays | Backend | 2 | IR-007-01 |
| IR-007-03 | Add IAR configuration to services.php | Backend | 1 | IR-007-01 |
| IR-007-04 | Implement real IAR API client (when specs available) | Backend | 13 | IR-007-01 |
| IR-007-05 | Add retry logic with exponential backoff to upload job | Backend | 3 | - |
| IR-008-01 | Enhance buildPatientContext() to use InterRAI scores | Backend | 5 | - |
| IR-008-02 | Add InterRAI-based bundle eligibility rules | Backend | 5 | IR-008-01 |
| IR-008-03 | Display InterRAI-driven recommendations in wizard | Frontend | 3 | IR-008-02 |

#### Priority 4: Compliance & Monitoring (Sprint 7)

| ID | Title | Type | Points | Dependencies |
|----|-------|------|--------|--------------|
| IR-009-01 | Create DetectStaleAssessmentsJob scheduled task | Backend | 3 | - |
| IR-009-02 | Add coordinator notifications for stale assessments | Backend | 5 | IR-009-01 |
| IR-009-03 | Create OhMetric records for InterRAI compliance | Backend | 5 | - |
| IR-009-04 | Build compliance report export functionality | Full Stack | 5 | IR-009-03 |
| IR-002-04 | Add "days until stale" countdown to assessment panel | Frontend | 2 | - |
| IR-002-05 | Polish status badge animations and transitions | Frontend | 2 | - |

### 3.3 Detailed Ticket Specifications

---

#### IR-002-01: Add InterRAI status badge to PatientQueue list

**Type:** Frontend
**Points:** 3
**Component:** `PatientQueueList.jsx`

**Description:**
Add an InterRAI status column to the patient queue list showing a compact badge with the patient's assessment status.

**Acceptance Criteria:**
- [ ] New column "InterRAI" appears after "Status" column
- [ ] Badge shows one of: Current (green), Stale (amber), Missing (red), IAR Pending (gray), IAR Failed (red outline)
- [ ] Hovering badge shows tooltip with last assessment date
- [ ] Column is sortable by status priority (Missing > Failed > Stale > Pending > Current)

**Technical Notes:**
- Extend PatientQueueController::index() to include `interrai_status` in response
- Use existing badge component styles from design system

---

#### IR-003-01: Implement "Mark Completed Externally" workflow

**Type:** Full Stack
**Points:** 5
**Components:** New modal, InterraiController, InterraiService

**Description:**
Allow coordinators to mark an assessment as completed externally (e.g., by OHaH staff) and manually enter the key scores.

**Acceptance Criteria:**
- [ ] New action button "Mark Completed Externally" in assessment actions panel
- [ ] Modal collects: assessment_date, assessor_role, maple_score, adl_hierarchy, cps, chess_score
- [ ] Source is set to `ohah_provided`
- [ ] Optional PDF upload for documentation
- [ ] Success creates assessment record and triggers IAR upload queue

**API:**
```
POST /api/v2/interrai/patients/{patient}/assessments/external
Body: {
    assessment_date: "2025-11-20",
    maple_score: "4",
    adl_hierarchy: 3,
    cognitive_performance_scale: 2,
    chess_score: 1,
    assessor_role: "OHaH Care Coordinator",
    document: <file> (optional)
}
```

---

#### IR-004-02: Build document upload API endpoints

**Type:** Backend
**Points:** 5
**Components:** InterraiDocumentController, InterraiDocument model

**Description:**
Create API endpoints for uploading, listing, and deleting documents attached to InterRAI assessments.

**Acceptance Criteria:**
- [ ] POST endpoint accepts multipart file upload (PDF, max 10MB)
- [ ] Files stored in `storage/app/interrai-documents/{patient_id}/`
- [ ] GET endpoint returns list of documents for an assessment
- [ ] DELETE endpoint removes document (soft delete)
- [ ] Authorization: user must have access to patient

**API Endpoints:**
```
POST   /api/v2/interrai/assessments/{id}/documents
GET    /api/v2/interrai/assessments/{id}/documents
DELETE /api/v2/interrai/assessments/{id}/documents/{docId}
```

---

#### IR-006-02: Build dashboard KPI cards

**Type:** Full Stack
**Points:** 5
**Components:** New API endpoint, KpiCard components

**Description:**
Create the KPI summary cards for the admin assessments dashboard showing counts of pending, failed, stale, and missing assessments.

**Acceptance Criteria:**
- [ ] Card: "Pending IAR" - count of assessments with iar_upload_status = pending
- [ ] Card: "Failed Uploads" - count with iar_upload_status = failed
- [ ] Card: "Stale (>90d)" - count with assessment_date > 90 days ago
- [ ] Card: "Missing Assessment" - count of active patients without any assessment
- [ ] Cards link to filtered list view when clicked
- [ ] Data refreshes every 60 seconds

**API:**
```
GET /api/v2/admin/interrai/dashboard-stats
Response: {
    pending_iar: 12,
    failed_uploads: 3,
    stale_assessments: 28,
    missing_assessments: 45,
    last_updated: "2025-11-26T10:30:00Z"
}
```

---

#### IR-007-01: Create IarClientInterface and adapter pattern

**Type:** Backend
**Points:** 3
**Components:** App\Contracts\IarClientInterface, Service provider binding

**Description:**
Establish the adapter pattern for IAR integration, allowing easy swapping between mock and real implementations.

**Acceptance Criteria:**
- [ ] Interface defined with uploadAssessment(), getStatus(), searchByPatient() methods
- [ ] Service provider binds interface based on config
- [ ] InterraiService uses injected client instead of direct implementation
- [ ] Config flag `iar.mock_enabled` controls binding

**Files:**
```
app/Contracts/IarClientInterface.php
app/Services/Iar/MockIarClient.php
app/Services/Iar/OntarioHealthIarClient.php (stub)
app/Providers/IarServiceProvider.php
config/services.php (iar section)
```

---

#### IR-008-01: Enhance buildPatientContext() to use InterRAI scores

**Type:** Backend
**Points:** 5
**Components:** CareBundleBuilderService

**Description:**
Extend the bundle builder's patient context to include InterRAI assessment data for smarter bundle recommendations.

**Acceptance Criteria:**
- [ ] Patient context includes: maple_score, cps, adl_hierarchy, chess_score, high_risk_flags
- [ ] Context indicates if assessment is stale or missing
- [ ] Bundle recommendation logic considers:
  - MAPLe 4-5 → Higher intensity bundles
  - CPS 3+ → Cognitive support services
  - ADL 4+ → Increased PSW hours
  - CHESS 3+ → Health monitoring services
- [ ] Recommendations flagged with confidence level based on assessment freshness

---

### 3.4 Sprint Allocation Suggestion

```
Sprint 1 (2 weeks):
├── IR-002-01 (3 pts) - Queue status badge
├── IR-002-02 (2 pts) - Summary panel freshness
├── IR-002-03 (3 pts) - Bundle wizard warning
├── IR-004-01 (2 pts) - Documents migration
└── Total: 10 pts

Sprint 2 (2 weeks):
├── IR-003-01 (5 pts) - Mark completed externally
├── IR-003-02 (3 pts) - Add IAR Document ID
├── IR-004-02 (5 pts) - Document upload API
└── Total: 13 pts

Sprint 3 (2 weeks):
├── IR-004-03 (5 pts) - Document attachment UI
├── IR-005-01 (2 pts) - Reassessment triggers migration
├── IR-005-02 (3 pts) - Reassessment request API
├── IR-005-03 (2 pts) - Request reassessment button
└── Total: 12 pts

Sprint 4 (2 weeks):
├── IR-005-04 (5 pts) - Reassessment resolution workflow
├── IR-006-01 (3 pts) - Admin dashboard shell
├── IR-006-02 (5 pts) - Dashboard KPI cards
└── Total: 13 pts

Sprint 5 (2 weeks):
├── IR-006-03 (8 pts) - Assessment list with filters
├── IR-006-04 (3 pts) - Bulk retry functionality
└── Total: 11 pts

Sprint 6 (2 weeks):
├── IR-007-01 (3 pts) - IAR adapter pattern
├── IR-007-02 (2 pts) - Mock client
├── IR-007-03 (1 pt) - Configuration
├── IR-007-05 (3 pts) - Retry logic
├── IR-008-01 (5 pts) - Bundle context enhancement
└── Total: 14 pts

Sprint 7 (2 weeks):
├── IR-008-02 (5 pts) - Bundle eligibility rules
├── IR-008-03 (3 pts) - Recommendation display
├── IR-009-01 (3 pts) - Stale detection job
├── IR-009-02 (5 pts) - Coordinator notifications
└── Total: 16 pts

Sprint 8 (2 weeks):
├── IR-009-03 (5 pts) - OhMetric integration
├── IR-009-04 (5 pts) - Compliance report export
├── IR-002-04 (2 pts) - Days until stale countdown
├── IR-002-05 (2 pts) - Badge polish
└── Total: 14 pts

---
Total: ~103 story points across 8 sprints
```

---

## Appendix A: Glossary

| Term | Definition |
|------|------------|
| **InterRAI HC** | InterRAI Home Care - standardized assessment instrument |
| **MAPLe** | Method for Assigning Priority Levels (1-5 scale) |
| **CPS** | Cognitive Performance Scale (0-6) |
| **ADL** | Activities of Daily Living hierarchy (0-6) |
| **IADL** | Instrumental Activities of Daily Living (0-6) |
| **CHESS** | Changes in Health, End-Stage Disease, Signs and Symptoms (0-5) |
| **DRS** | Depression Rating Scale (0-14) |
| **CAPs** | Clinical Assessment Protocols |
| **IAR** | Integrated Assessment Record (Ontario Health system) |
| **CHRIS** | Client Health Related Information System |
| **OHaH** | Ontario Health at Home |
| **SPO** | Service Provider Organization |
| **HPG** | Health Partner Gateway |
| **TNP** | Transition Needs Profile |

## Appendix B: File References

| File | Purpose |
|------|---------|
| `app/Models/InterraiAssessment.php` | Assessment model with scores and statuses |
| `app/Services/InterraiService.php` | Business logic for assessments |
| `app/Http/Controllers/Api/V2/InterraiController.php` | API endpoints |
| `resources/js/pages/InterRAI/InterraiCompletionWizard.jsx` | Assessment entry wizard |
| `resources/js/pages/Tnp/TnpReviewDetailPage.jsx` | TNP page with InterRAI tab |
| `resources/js/pages/Patients/PatientDetailPage.jsx` | Patient page with InterRAI tab |
| `database/migrations/2025_11_25_100001_create_interrai_assessments_table.php` | Table schema |

---

*End of Document*

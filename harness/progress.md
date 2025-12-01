# Connected Capacity 2.1 - Feature Progress

## Overview

This document tracks progress on key scheduling and care bundle features.

---

## Feature Status

### scheduling.patient_non_concurrency

**Status:** DONE

**Implemented:**
- `SchedulingEngine::patientHasOverlap()` - checks if patient has overlapping assignments
- `SchedulingEngine::hasPatientConflicts()` - returns conflicting assignments
- `SchedulingEngine::getPatientConflicts()` - returns collection of conflicts
- `SchedulingEngine::canAssignWithTravel()` - validates including patient non-concurrency
- `SchedulingEngine::validateAssignment()` - NOW includes patient overlap check (fixed 2025-11-29)
- Tests in `PatientNonConcurrencyTest.php`:
  - `patient_cannot_have_overlapping_visits()`
  - `patient_can_have_non_overlapping_visits()`
  - `can_assign_with_travel_rejects_patient_overlap()`
  - `cancelled_assignments_do_not_block_scheduling()`
  - `exact_adjacent_times_do_not_overlap()`
  - `pt_and_ot_cannot_be_scheduled_at_same_time_for_patient()` (NEW)
  - `validate_assignment_rejects_patient_overlap()` (NEW)

---

### scheduling.psw_spacing

**Status:** DONE

**Implemented:**
- `SchedulingEngine::checkSpacingRule()` - validates minimum gap between visits of same service type
- `SchedulingEngine::validateAssignment()` - NOW includes spacing rule check (fixed 2025-11-29)
- `ServiceType.min_gap_between_visits_minutes` field in database
- CoreDataSeeder sets:
  - Nursing: 60 min gap
  - PT: 120 min gap
  - OT: 120 min gap
  - PSW: 120 min gap
  - Homemaking: 180 min gap
- Tests in `SpacingRulesTest.php`:
  - `psw_visits_require_120_minute_gap()`
  - `psw_visits_can_be_scheduled_with_sufficient_gap()`
  - `nursing_visits_require_60_minute_gap()`
  - `can_assign_with_travel_enforces_spacing_rules()`
  - `spacing_rules_only_apply_to_same_service_type()`
  - `service_without_spacing_rule_can_be_scheduled_back_to_back()`
  - `spacing_rule_considers_only_completed_or_planned_visits()`
  - `validate_assignment_enforces_spacing_rules()` (NEW)

---

### scheduling.patient_timeline_correctness

**Status:** DONE

**Implemented:**
- `PatientTimeline.jsx` React component
- Groups assignments by date, sorts by start time
- Single-column timeline view (not staff-row grid)
- Shows: time range, service type, staff name, status badge
- Category color coding (nursing=blue, PSW=green, etc.)
- Today highlighting, weekly summary

---

### bundles.unscheduled_care_correctness

**Status:** DONE

**Implemented:**
- `CareBundleAssignmentPlanner::getUnscheduledRequirements()`
- Computes required_units from CareBundleTemplate services
- Computes scheduled_units from ServiceAssignments
- Handles fixed-visit services (RPM) differently - tracks across care plan
- Returns `RequiredAssignmentDTO[]` sorted by priority
- API endpoint: `GET /v2/scheduling/requirements`
- Summary stats include: patients_with_needs, total_remaining_hours, total_remaining_visits

---

### rpm.fixed_two_visits

**Status:** DONE

**Implemented:**
- `ServiceType::SCHEDULING_MODE_FIXED_VISITS` constant
- `ServiceType::isFixedVisits()` method
- `fixed_visits_per_plan` column in service_types table
- CoreDataSeeder sets RPM with fixed_visits_per_plan=2
- `CareBundleAssignmentPlanner::getScheduledVisitsForCarePlan()` counts all visits regardless of date

---

### workforce.capacity_dashboard

**Status:** IN_PROGRESS (Backend Complete)

**Implemented:**
- `WorkforceCapacityService.php` - computes capacity vs required care
  - `getCapacitySnapshot()` - comprehensive capacity analysis
  - `getAvailableCapacity()` - staff hours by role
  - `getRequiredCare()` - care bundle requirements by service
  - `getScheduledHours()` - currently assigned hours
  - `calculateTravelOverhead()` - travel time estimation (30 min default)
  - `getCapacityForecast()` - projection for upcoming weeks
  - `getCapacityByProviderType()` - SPO vs SSPO comparison
- API endpoint: `GET /v2/workforce/capacity`
  - Query params: period_type, start_date, provider_type, forecast_weeks
  - Returns: snapshot, forecast, provider_comparison
- `WorkforceCapacityPage.jsx` - React dashboard
  - Summary cards: Available, Required, Scheduled, Travel, Net Capacity
  - SPO vs SSPO comparison panel
  - Capacity forecast chart
  - Breakdowns by role and service type
- Sidebar navigation link added

**Remaining:**
- Verify FTE trend graph populates with real data
- Update seeders for realistic historical data

---

## Session Notes

### 2025-11-30 - SSPO Marketplace Phase 1 + 2

1. Extended ServiceProviderOrganization model for SSPO-specific fields:
   - Added: website_url, logo_url, cover_photo_url, description, tagline, notes
   - Added: status (active/draft/inactive), region_code, capacity_metadata (JSON)
   - Added: serviceTypes() relationship via organization_service_types pivot table
   - Added: scopes for sspo(), activeOnly(), inRegion(), offeringService(), search()

2. Extended ServiceType model for provider metadata:
   - Added: allowed_provider_types (JSON), delivery_mode (in_person/remote/either)
   - Added: PROVIDER_SSPO, PROVIDER_SPO, PROVIDER_EITHER constants
   - Added: isSspoOwned(), isSpoOwned(), allowsProviderType(), isRemote(), isInPerson() methods
   - Added: organizations() inverse relationship

3. Created migrations:
   - 2025_11_30_220000_add_sspo_fields_to_service_provider_organizations.php
   - 2025_11_30_220100_add_provider_metadata_to_service_types.php
   - Created organization_service_types pivot table

4. Created SSPOSeeder with 4 SSPO organizations:
   - Alexis Lodge Retirement Residence (dementia care specialist)
   - Reconnect Health Services (community/mental health)
   - Toronto Grace Health Centre RCM (remote monitoring)
   - WellHaus (virtual care platform)
   - Each with complete data: services, contact info, region, capacity metadata

5. Created SspoMarketplaceController with API endpoints:
   - GET /api/v2/sspo-marketplace - List with filtering (search, region, service_type, status)
   - GET /api/v2/sspo-marketplace/{id} - Full profile with services
   - GET /api/v2/sspo-marketplace/filters - Available filter options
   - GET /api/v2/sspo-marketplace/stats - Marketplace statistics

6. Updated SspoMarketplacePage.jsx:
   - Replaced mock data with real API integration
   - Added SSPOCard component with capacity indicators
   - Added search and filter controls (region, service type, status)
   - Added loading/error states
   - Grid layout for partner cards

7. Created SspoProfilePage.jsx:
   - Full profile view with header (logo, name, tagline, status)
   - About section, Services Offered, Contact Information
   - Capacity status display with progress bar
   - Location information, Special Capabilities
   - Action buttons (Assign Service, Send Message, View Reports)

8. Updated App.jsx with routes:
   - /sspo-marketplace - Marketplace listing
   - /sspo-marketplace/:id - Profile page

---

### 2025-11-30 - Workforce Capacity Dashboard

1. Implemented WorkforceCapacityService with capacity vs required care calculation
2. Added /api/v2/workforce/capacity endpoint with filtering support
3. Created WorkforceCapacityPage React component with:
   - Summary metrics cards
   - SPO vs SSPO comparison
   - Capacity forecast visualization
   - Role and service type breakdowns
4. Added sidebar navigation under "Workforce" section

### 2025-11-30 - Capacity Investigation Fixes (Session 2)

1. **Bundle Plan vs Schedule Separation (DONE)**
   - Added `service_requirements` JSON field to care_plans table
   - Modified `CareBundleBuilderService.buildCarePlan()` to store requirements, NOT create ServiceAssignments
   - Modified `CareBundleBuilderService.buildCarePlanFromTemplate()` similarly
   - Added `extractServiceRequirements()` helper method
   - Updated `publishCarePlan()` to handle both old and new flows
   - Updated `CareBundleAssignmentPlanner.buildPatientRequirements()` to use:
     1. CarePlan.service_requirements (customized, if exists)
     2. CareBundleTemplate.services (template defaults)
     3. CareBundle.serviceTypes (legacy fallback)
   - Migration: `2025_11_30_230000_add_service_requirements_to_care_plans.php`

2. **Navigation Consolidation (DONE)**
   - Removed redundant "Workforce Mgmt" link from sidebar
   - Renamed "SPO Staff" to "Staff Management"
   - Kept Capacity Dashboard as the main workforce metrics page

3. **Queue Status Badge Standardization (DONE)**
   - Standardized STATUS_COLORS in patientQueueApi.js:
     - gray → intake
     - yellow → triage
     - blue → assessment
     - green → ready
     - purple → bundle_building
   - Fixed triage_complete: blue → yellow
   - Fixed assessment_in_progress: yellow → blue

---

### 2025-11-30 - SSPO Profile Page Upgrade (Session 3)

**Goal:** Upgrade SSPO Profile page to combine existing styling with redesign layout, adding:
- Assigned Patients & Appointments section
- Recent Service History section
- Enhanced Capacity Block

**New Harness Features Created:**
- `sspo.profile_assigned_patients` - Upcoming appointments for SSPO
- `sspo.profile_service_history` - Recent completed visits
- `sspo.profile_capacity_block` - Enhanced capacity visualization

**Implementation Plan:**

**Step 1: Extend API (Backend)**
- Add to `SspoMarketplaceController::show()`:
  - `upcoming_assignments`: ServiceAssignments for next 7 days where staff belongs to this SSPO
  - `recent_assignments`: Completed ServiceAssignments from past 7 days
  - `capacity_summary`: scheduled_hours, available_hours, utilization_pct, patient_count, visit_count

**Step 2: Design Merge**
- Keep existing styling: gradient header, avatar tile, pill badges, teal CTAs
- Two-column layout (2:1 ratio)
- Left column sections:
  1. About/Description
  2. Services Offered (existing)
  3. Assigned Patients & Appointments (NEW)
  4. Recent Service History (NEW)
- Right column sections:
  1. Contact & Website (existing)
  2. Capacity & Utilization (ENHANCED)
  3. Region/Location (existing)
  4. Actions (existing)

**Step 3: Implement UI Sections**
- `UpcomingAppointmentsSection` - Patient cards with appointment details
- `ServiceHistorySection` - Condensed list with verification status
- `CapacityBlock` - Enhanced with utilization metrics

**Step 4: Verify Seed Data**
- Ensure 4 SSPOs have ServiceAssignments via their staff
- Alexis Lodge, Reconnect, Toronto Grace RCM, WellHaus

**Step 5: Test & Commit**
- Verify API returns expected data
- UI renders correctly for all 4 SSPOs
- Commit and push

**Work Completed:**
1. Extended `SspoMarketplaceController::show()` with:
   - `getUpcomingAssignments()` - Returns next 7 days of appointments grouped by patient
   - `getRecentAssignments()` - Returns past 7 days of completed visits with verification status
   - `getCapacitySummary()` - Returns weekly utilization metrics

2. Updated `SspoProfilePage.jsx` with:
   - Assigned Patients & Appointments section (left column)
   - Recent Service History section with verification status badges
   - Enhanced Capacity & Utilization block with weekly metrics

3. Enhanced `SSPOSeeder.php` with:
   - `seedSspoAssignments()` method creates sample upcoming/past assignments
   - Each SSPO gets 2-3 patients with appointments using their primary service types

---

### 2025-11-30 - Unscheduled Care & Capacity Dashboard Fix (Session 4)

**Goal:** Fix Unscheduled Care showing empty and Capacity Dashboard showing zeros.

**Root Cause Analysis:**

| Issue | Root Cause |
|-------|-----------|
| Unscheduled Care empty | Seeding created 40% gaps only in past weeks; no future week data |
| Care plans missing service_requirements | DemoBundlesSeeder didn't populate field |
| Capacity Dashboard zeros | Future weeks had no seeded ServiceAssignments |

**Architecture Verified:**
- `CareBundleBuilderService` correctly stores `service_requirements` JSON, doesn't create ServiceAssignments
- `CareBundleAssignmentPlanner` correctly computes `required - scheduled = remaining`
- `/v2/scheduling/requirements` API returns correct DTOs
- `SchedulingPage.jsx` correctly wires to API

**Fixes Implemented:**

1. **DemoBundlesSeeder.php** - Populate service_requirements in care plans
   - Added `extractServiceRequirements()` method
   - CarePlan now stores service requirements JSON from template
   - Removed `createServiceAssignments()` (plan vs schedule separation)

2. **WorkforceSeeder.php** - Realistic scheduling pattern
   - Changed from 4 weeks (past 3 + current) to 6 weeks (past 3 + current + future 2)
   - New skip logic:
     - Past weeks: 0% skipped (100% scheduled - complete history)
     - Current week: 30% skipped (70% scheduled)
     - Future week 1: 40% skipped (60% scheduled)
     - Future week 2: 50% skipped (50% scheduled)
   - Fixed-visit services (RPM) schedule correctly across 6 weeks

3. **Verification:**
   - Sidebar: "Staff Management" label ✓
   - Sidebar: "Capacity Dashboard" only workforce link ✓
   - Queue status badges: Correctly standardized ✓

---

### 2025-11-30 - Queue Status & Visit Verification Fix (Session 5)

**Goal:** Re-validate and fix features marked "done" but not meeting acceptance criteria.

**Re-Validation Results:**

| Feature | Status Before | Issue Found |
|---------|--------------|-------------|
| `intake.queue_status_standardization` | done | Still showing "Triage Complete" / "InterRAI HC Assessment In Progress" |
| `jeopardy.visit_verification_workflow` | not_started | Seeder used 24hr threshold, created 35 overdue visits |
| `workforce.capacity_dashboard` | done | Architecture correct, seeding was the issue (fixed in Session 4) |
| `bundles.unscheduled_care_correctness` | done | Architecture correct, seeding was the issue (fixed in Session 4) |

**Fixes Implemented:**

1. **Queue Status Badge Standardization (intake.queue_status_standardization)**
   - Added `INTERRAI_STATUS_MAP` constant to PatientQueue.php mapping internal statuses to 3 labels
   - Added `getInterraiStatusAttribute()` and `getInterraiBadgeColorAttribute()` accessors
   - Updated patientQueueApi.js with `INTERRAI_STATUSES`, `INTERRAI_STATUS_MAP`, and helper methods
   - Updated PatientQueueList.jsx to use standardized labels in table and summary cards
   - **Labels now shown:**
     - "InterRAI HC Assessment Required" (gray) - pending_intake, triage_in_progress
     - "InterRAI HC Assessment Incomplete" (yellow) - triage_complete, assessment_in_progress
     - "InterRAI HC Assessment Complete - Ready for Bundle" (green) - assessment_complete

2. **Visit Verification Workflow (jeopardy.visit_verification_workflow)**
   - Changed `overdueAlertsCount` from 35 to 8 (per CC2.1: 3-10 for demo)
   - Changed all `subHours(24)` to `subHours(12)` in VisitVerificationSeeder.php (lines 176, 513, 547)
   - Service layer already correctly used 12-hour threshold (DEFAULT_VERIFICATION_GRACE_MINUTES = 720)
   - Resolve workflow verified: correctly updates verification_status and verified_at

**Files Modified:**
- `app/Models/PatientQueue.php` - Added InterRAI status constants and accessor methods
- `resources/js/services/patientQueueApi.js` - Added standardized InterRAI status helpers
- `resources/js/pages/Queue/PatientQueueList.jsx` - Updated to use standardized labels
- `database/seeders/VisitVerificationSeeder.php` - Fixed 12-hour threshold, reduced overdue count
- `harness/feature_list.json` - Updated feature statuses

---

### 2025-12-01 - Critical Seeding Bug Fix (Session 6)

**Goal:** Fix root cause of Capacity Dashboard zeros and empty Unscheduled Care panel.

**Root Cause Found:**

`WorkforceSeeder.getCarePlanServices()` completely ignored the `service_requirements` JSON field that DemoBundlesSeeder creates. It only read from `careBundleTemplate.services` or `careBundle.serviceTypes`.

This caused a mismatch:
- **Assignments created from:** template/bundle services
- **Requirements calculated from:** `service_requirements` (Priority 1 in CareBundleAssignmentPlanner)
- **Result:** Dashboard showed zeros because assignments didn't match requirements

**Fix Implemented:**

Updated `WorkforceSeeder.getCarePlanServices()` to:
1. Check `service_requirements` as Priority 1 (matching CareBundleAssignmentPlanner)
2. Map keys correctly: `frequency_per_week` → `frequency`, `duration_minutes` → `duration`
3. Fall back to template/bundle only if service_requirements is empty

**Files Modified:**
- `database/seeders/WorkforceSeeder.php` - Fixed getCarePlanServices() to read service_requirements first

---

### 2025-11-29 - Initial Setup & Analysis

1. Merged `investigate-branch-workflow` branch into current branch
2. Created harness directory and files
3. Identified gap: `SchedulingController.validateAssignment()` missing patient non-concurrency and spacing rule checks
4. Will update `validateAssignment()` to include these constraints

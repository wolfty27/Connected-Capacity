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

### 2025-11-29 - Initial Setup & Analysis

1. Merged `investigate-branch-workflow` branch into current branch
2. Created harness directory and files
3. Identified gap: `SchedulingController.validateAssignment()` missing patient non-concurrency and spacing rule checks
4. Will update `validateAssignment()` to include these constraints

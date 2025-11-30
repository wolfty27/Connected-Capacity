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

### 2025-11-30 - Workforce Capacity Dashboard

1. Implemented WorkforceCapacityService with capacity vs required care calculation
2. Added /api/v2/workforce/capacity endpoint with filtering support
3. Created WorkforceCapacityPage React component with:
   - Summary metrics cards
   - SPO vs SSPO comparison
   - Capacity forecast visualization
   - Role and service type breakdowns
4. Added sidebar navigation under "Workforce" section

### 2025-11-29 - Initial Setup & Analysis

1. Merged `investigate-branch-workflow` branch into current branch
2. Created harness directory and files
3. Identified gap: `SchedulingController.validateAssignment()` missing patient non-concurrency and spacing rule checks
4. Will update `validateAssignment()` to include these constraints

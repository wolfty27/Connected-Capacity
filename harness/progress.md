# CC 2.1 Harness Progress

## Initial Harness Setup – 2025-11-29

- Created `feature_list.json` with 8 core CC2.1 features.
- Added `session_log.md` for tracking incremental agent progress.
- Added `progress.md` for summarizing completed/in-progress items.
- Next Claude Code sessions must:
  1. Read `feature_list.json` + `progress.md`
  2. Pick 1–2 features to implement
  3. Update status fields + logs
  4. Commit cleanly

## Harness Expansion – Automatic Codebase Discovery (2025-11-29)

- Discovered **17 new subsystems** via comprehensive codebase exploration:
  - Bundle Engine (RUG classification, template matching, cost cap, rules engine, care plans)
  - Intake & Referrals (queue workflow, referral capture, HPG import, SLA tracking)
  - InterRAI & Assessments (form UI, score calculation, reassessment triggers, IAR upload, admin dashboard, clinical flags)
  - Billing & Cost (rate cards, shadow billing, margin tracking)
  - Staff & Workforce (CRUD, skills, availability, FTE compliance, utilization, employment types, service role mapping)
  - Missed Care & Jeopardy (visit verification, jeopardy board, metrics)
  - SSPO Marketplace (capability matching, assignment acceptance, performance metrics, geographic coverage, decline reasons)
  - SLA Compliance (HPG response tracking, compliance dashboard, huddle reports)
  - Dashboards & Reporting (executive KPIs, SPO metrics, AI forecasting, partner performance)
  - Auth & Authorization (RBAC, organization context, feature flags)
  - Care Operations (assignment estimation, field staff worklist, care coordination)
  - Patient Management (demographics, overview summary, notes)
  - External Integrations (Gemini AI, CHRIS sync)
  - Service Types (configuration, scheduling modes)
  - Audit & Compliance (logging, PHIPA compliance)
  - TNP (legacy support)

- Added **45 new harness features** to `feature_list.json` (total: 53 features).

- Harness now covers the entire CC2.1 codebase including:
  - All backend models (52+), services (25+), and API controllers (21+)
  - All frontend pages (32) and components (70+)
  - All database migrations (104) and seeders (22)
  - All documented subsystems from `/docs/`

### Feature Summary by Area

| Area | Count | Features |
|------|-------|----------|
| Scheduling | 4 | travel_time_google, patient_non_concurrency, psw_spacing, patient_timeline_correctness |
| Bundles | 5 | unscheduled_care_correctness, rug_classification_algorithm, template_matching, cost_cap_enforcement, configuration_rules_engine, care_plan_workflow |
| Intake | 4 | patient_queue_workflow, referral_capture, hpg_import, sla_tracking |
| InterRAI | 6 | assessment_form, score_calculation, reassessment_triggers, iar_upload, admin_dashboard, clinical_flags |
| Billing | 3 | rate_card_management, shadow_billing, margin_tracking |
| Workforce | 7 | staff_crud, skill_catalog, availability_patterns, fte_compliance, utilization_analytics, employment_types, service_role_mapping |
| MissedCare | 3 | visit_verification, jeopardy_board, metrics_reporting |
| SSPO | 5 | capability_matching, assignment_acceptance, performance_metrics, geographic_coverage, decline_reasons |
| SLA | 3 | hpg_response_tracking, compliance_dashboard, huddle_reports |
| Dashboard | 4 | executive_kpis, spo_metrics, ai_forecasting, partner_performance |
| Auth | 3 | rbac_enforcement, organization_context, feature_flags |
| CareOps | 3 | assignment_estimation, field_staff_worklist, care_coordination |
| Patients | 3 | demographics_crud, overview_summary, notes_management |
| Integrations | 2 | gemini_ai, chris_sync |
| ServiceTypes | 2 | configuration, scheduling_modes |
| Audit | 2 | logging, data_compliance |
| TNP | 1 | legacy_support |
| Regions | 1 | metadata_and_auto_assignment |
| Seeding | 1 | realistic_toronto_addresses |
| ServiceLogic | 1 | fixed_two_visits (RPM) |

## Session 2 – Core Scheduling & Bundle Features Implementation (2025-11-29)

### Features Completed (5 total)

| Feature ID | Status | Implementation |
|------------|--------|----------------|
| `scheduling.patient_non_concurrency` | **DONE** | SchedulingEngine::patientHasOverlap() |
| `scheduling.psw_spacing` | **DONE** | ServiceType.min_gap_between_visits_minutes + checkSpacingRule() |
| `scheduling.patient_timeline_correctness` | **DONE** | PatientTimeline.jsx React component |
| `bundles.unscheduled_care_correctness` | **DONE** | CareBundleAssignmentPlanner service + UnscheduledPanel.jsx |
| `rpm.fixed_two_visits` | **DONE** | ServiceType.scheduling_mode='fixed_visits' + fixed_visit_labels |

### Files Created

**Backend (Laravel/PHP):**
- `app/Models/Patient.php` - Patient entity with relationships
- `app/Models/ServiceType.php` - Service types with scheduling_mode, min_gap_between_visits_minutes
- `app/Models/ServiceAssignment.php` - Scheduled visits with overlap detection
- `app/Models/CarePlan.php` - Care plans linking patients to bundles
- `app/Models/CareBundleTemplate.php` - Bundle templates with services
- `app/Models/CareBundleService.php` - Services within bundles (hours/visits)
- `app/Services/Scheduling/SchedulingEngine.php` - Core validation: patientHasOverlap(), checkSpacingRule(), validateAssignment()
- `app/Services/Scheduling/CareBundleAssignmentPlanner.php` - Computes required/scheduled/remaining units
- `app/Http/Controllers/Api/V2/SchedulingController.php` - REST API for scheduling
- `app/DTOs/RequiredAssignmentDTO.php` - Unscheduled care data structure
- `app/DTOs/UnscheduledServiceDTO.php` - Service-level unscheduled details
- `routes/api.php` - API route definitions

**Database:**
- `database/migrations/2025_11_29_000001_create_patients_table.php`
- `database/migrations/2025_11_29_000002_create_service_types_table.php`
- `database/migrations/2025_11_29_000003_create_care_bundle_templates_table.php`
- `database/migrations/2025_11_29_000004_create_care_bundle_services_table.php`
- `database/migrations/2025_11_29_000005_create_care_plans_table.php`
- `database/migrations/2025_11_29_000006_create_service_assignments_table.php`
- `database/seeders/CoreDataSeeder.php` - Seeds PSW with 120min spacing, RPM with 2 fixed visits

**Frontend (React/JSX):**
- `resources/js/components/scheduling/PatientTimeline.jsx` - Clean patient-centric timeline
- `resources/js/components/scheduling/UnscheduledPanel.jsx` - Shows patients with remaining care needs

**Tests:**
- `tests/Feature/PatientNonConcurrencyTest.php` - Tests overlap detection
- `tests/Feature/SpacingRulesTest.php` - Tests PSW/NUR spacing rules
- `tests/Feature/UnscheduledCareTest.php` - Tests required vs scheduled computation

### Key Implementation Details

1. **Patient Non-Concurrency:**
   - `SchedulingEngine::patientHasOverlap()` checks for time range overlaps
   - Respects cancelled/missed assignment statuses (ignores them)
   - Integrated into API validation before assignment creation

2. **PSW Spacing:**
   - `ServiceType.min_gap_between_visits_minutes` column (PSW=120, NUR=60, MEAL=180)
   - `SchedulingEngine::checkSpacingRule()` enforces gap between same-service visits
   - `getSuggestedTimeSlots()` provides morning/midday/afternoon/evening bands

3. **Patient Timeline:**
   - React component groups assignments by date, sorts by start time
   - No staff rows or grid clutter - single vertical list per patient
   - Shows status badges, duration, service colors

4. **Unscheduled Care:**
   - `CareBundleAssignmentPlanner::getUnscheduledRequirements()` computes all patient needs
   - Handles both weekly (hours/week) and fixed-visits (visits/plan) modes
   - Priority levels based on risk flags (high_fall_risk, clinical_instability, etc.)

5. **RPM Fixed Visits:**
   - `ServiceType.scheduling_mode = 'fixed_visits'`
   - `fixed_visits_per_plan = 2` with labels `['Setup', 'Discharge']`
   - Counts visits across entire care plan (not weekly)

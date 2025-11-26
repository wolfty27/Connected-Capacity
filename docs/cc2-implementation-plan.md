# Connected Capacity 2.1 Implementation Plan

## Current State Overview
- **Architecture**: Recently upgraded to Laravel 10 but still shaped like the original Laravel 8 monolith focused on hospital ↔ retirement home placements. Controllers are fat, views are Blade/Datatables, routes are all web-based with a single `AuthenticatedRoutes` middleware guarding most paths (`routes/web.php`).
- **Domain**: Models cover admins, hospitals/new hospitals, retirement homes, patients, bookings, tiers, assessment forms, galleries, in-person assessments, and Calendly tokens. There are no service orchestration entities.
- **Data**: Migrations define placement tables only and include notable issues such as `patients.status` declared as boolean while controllers persist string workflow states. Soft deletes exist on most domain tables but there are no foreign keys or indexes beyond the defaults.
- **Integrations & Jobs**: Calendly OAuth is implemented through synchronous cURL calls. Sanctum exists but only for the unused `/api/user` endpoint. No queues, jobs, or audits are present.
- **Auth & Roles**: Roles are simple strings (`admin`, `hospital`, `retirement-home`, `patient`). Middleware only checks authentication or `role == admin`; there are no policies or per-organization scoping rules.
- **Testing**: Only the default Laravel Example tests are present. There is effectively no automated coverage for placements or upcoming CC 2.0 concerns.

## Target State Summary
- **Mission Shift**: Become a multi-organization, high-intensity home/community care orchestration engine that coordinates referrals, discharge reviews/transition needs, service assignments, RPM monitoring, and Ontario Health (OH) reporting, rather than bed placements.
- **Core Concepts**: Introduce ServiceProviderOrganization, ServiceType, CareBundle, CarePlan, TriageResult, ServiceAssignment, InterdisciplinaryNote, RpmDevice, RpmAlert, OhMetric, feature flags, audit logging, and organization membership.
- **Workflows**: Implement intake APIs/manual entry, discharge/transition review, care planning, assignment/dispatch dashboards, RPM alert handling, interdisciplinary documentation, and OH metrics aggregation/export.
- **Security & Compliance**: Enforce per-organization access, role-based policies tied to `organization_role`, audit logging, and feature flags to introduce CC 2.0 alongside legacy flows.
- **Coexistence**: Keep placement functionality intact but isolate it behind `/legacy` route prefixes and Legacy namespaces once CC 2.0 flows exist, enabling gradual migration.

## Non-Goals
- Do not build a full route optimization/scheduling engine—assignments focus on basic scheduling windows, not complex dispatch algorithms.
- Do not replace or integrate with an Electronic Health Record (EHR); CC 2.0 captures coordination data only.
- Do not deliver real-time fleet/vehicle tracking or optimization.
- Do not redesign or purge legacy placement data beyond isolating its UI/routes and ensuring safe coexistence.
- Do not attempt province-wide integrations beyond the scoped referral and RPM endpoints (e.g., no full HPG parity).
- Do not add native mobile apps in this phase; responsive web UI suffices.

## Domain Model

### ServiceProviderOrganization
- **Table**: `service_provider_organizations`
- **Columns**: `id` (pk, big int, auto), `name` (string, unique), `slug` (string, unique), `type` (enum: se_health, partner, external), `contact_email` (string, nullable), `contact_phone` (string, nullable), `address` (string, nullable), `city`/`province`/`postal_code` (strings, nullable), `active` (boolean, default true), timestamps, soft deletes.
- **Keys/Indexes**: Primary on `id`, unique indexes on `name` and `slug`, composite index on `(active, type)`.
- **Model**: `App\Models\ServiceProviderOrganization` with `hasMany(User::class)` via `organization_id`, `hasMany(ServiceAssignment::class)`, `hasMany(RpmDevice::class)`, `morphMany(AuditLog::class)`.

### Organization Membership & Roles
- **Table**: `organization_user_roles`
- **Columns**: `id`, `user_id` (fk -> users.id), `service_provider_organization_id` (fk), `organization_role` (string, e.g., RN, PSW, TRIAGE_COORDINATOR), `default_assignment_scope` (json, nullable), timestamps, unique constraint on `(user_id, service_provider_organization_id)`.
- **Indexes**: Index on `organization_role` for filtering dashboards.
- **Model**: `App\Models\OrganizationMembership` belongs to `User` and `ServiceProviderOrganization`.

### ServiceType
- **Table**: `service_types`
- **Columns**: `id`, `name` (string, unique), `code` (string, nullable, unique), `category` (string), `default_duration_minutes` (integer, nullable), `description` (text, nullable), `active` (boolean, default true), timestamps.
- **Indexes**: Unique on `name`, `(active, category)` index for filtering.
- **Model**: `App\Models\ServiceType` with relationships `belongsToMany(CareBundle::class)` and `hasMany(ServiceAssignment::class)`.

### CareBundle
- **Table**: `care_bundles`
- **Columns**: `id`, `name` (string, unique), `code` (string, unique), `description` (text, nullable), `default_notes` (text, nullable), `active` (boolean, default true), timestamps.
- **Pivot Table**: `care_bundle_service_type` with `care_bundle_id`, `service_type_id`, `default_frequency_per_week` (integer), `default_provider_org_id` (fk -> service_provider_organizations, nullable).
- **Model**: `App\Models\CareBundle` with `belongsToMany(ServiceType::class)->withPivot(...)`, `hasMany(CarePlan::class)`.

### TriageResult
- **Table**: `triage_results`
- **Columns**: `id`, `patient_id` (fk -> patients.id), `received_at` (timestamp), `triaged_at` (timestamp, nullable), `acuity_level` (enum low/medium/high/critical), `dementia_flag` (boolean), `mh_flag` (boolean), `rpm_required` (boolean), `fall_risk` (boolean), `behavioural_risk` (boolean), `notes` (text, nullable), `raw_referral_payload` (json, nullable), `triaged_by` (fk -> users.id, nullable), timestamps.
- **Indexes**: Unique on `patient_id`, index `(acuity_level, rpm_required)`.
- **Model**: `App\Models\TriageResult` belongs to `Patient`, `User` (triagedBy).

### CarePlan
- **Table**: `care_plans`
- **Columns**: `id`, `patient_id` (fk), `care_bundle_id` (fk, nullable), `version` (integer), `status` (enum draft/active/archived), `goals` (json), `risks` (json), `interventions` (json), `approved_by` (fk -> users.id, nullable), `approved_at` (timestamp, nullable), `notes` (text, nullable), timestamps, soft deletes.
- **Indexes**: Unique `(patient_id, version)`, index on `(status, care_bundle_id)`.
- **Model**: `App\Models\CarePlan` belongs to `Patient`, optional `CareBundle`, hasMany `ServiceAssignment`, hasMany `InterdisciplinaryNote`.
- **Status Handling**: Stored as strings validated against named constants (`CarePlanStatus::DRAFT`, `::ACTIVE`, `::ARCHIVED`). Allowed transitions: `draft -> active -> archived` (archived is terminal; reactivation requires cloning into a new version).
- **Versioning**: `version` increments with every major edit; previous versions remain soft-deleted but queryable for history. Approval metadata (`approved_by`, `approved_at`) plus audit logs capture who changed what.

### ServiceAssignment
- **Table**: `service_assignments`
- **Columns**: `id`, `care_plan_id` (fk), `patient_id` (fk), `service_provider_organization_id` (fk), `service_type_id` (fk), `assigned_user_id` (fk -> users.id, nullable), `status` (enum planned/in_progress/completed/cancelled/missed/escalated), `scheduled_start`/`scheduled_end` (timestamps, nullable), `actual_start`/`actual_end` (timestamps, nullable), `frequency_rule` (string/cron, nullable), `notes` (text, nullable), `source` (enum manual/triage/rpm_alert/api), `rpm_alert_id` (fk, nullable), timestamps, soft deletes.
- **Indexes**: Composite indexes on `(service_provider_organization_id, status)`, `(assigned_user_id, scheduled_start)`, `(patient_id, status)`.
- **Model**: `App\Models\ServiceAssignment` belongs to patient, care plan, service type, organization, optional assigned user, optional rpm alert; hasMany `InterdisciplinaryNote`.
- **Status Handling**: Stored as strings validated against constants (`AssignmentStatus::PLANNED`, `::IN_PROGRESS`, `::COMPLETED`, `::CANCELLED`, `::MISSED`, `::ESCALATED`). Valid transitions: `planned -> in_progress -> completed`, `planned -> cancelled`, `in_progress -> missed`, `planned/in_progress -> escalated`. `completed`, `cancelled`, `missed`, and `escalated` are terminal unless a coordinator reopens by cloning a new assignment.

### InterdisciplinaryNote
- **Table**: `interdisciplinary_notes`
- **Columns**: `id`, `patient_id` (fk), `service_assignment_id` (fk, nullable), `author_id` (fk -> users.id), `author_role` (string), `note_type` (enum clinical/psw/mh/rpm/escalation), `content` (longtext), `visible_to_orgs` (json, nullable), timestamps, soft deletes.
- **Indexes**: `(patient_id, created_at)` and `(service_assignment_id, created_at)` for ordering.
- **Model**: `App\Models\InterdisciplinaryNote` belongs to patient, optional assignment, author. Notes are immutable once saved (only soft-delete allowed) so that clinical/legal history remains intact; edits require creating a new note referencing the prior entry.

### RpmDevice
- **Table**: `rpm_devices`
- **Columns**: `id`, `patient_id` (fk), `service_provider_organization_id` (fk), `device_type` (string), `manufacturer` (string, nullable), `model` (string, nullable), `serial_number` (string, unique), `assigned_at` (timestamp), `returned_at` (timestamp, nullable), `notes` (text, nullable), timestamps.
- **Indexes**: Unique `serial_number`, `(patient_id, device_type)` index.
- **Model**: `App\Models\RpmDevice` belongs to patient and organization, hasMany `RpmAlert`.

### RpmAlert
- **Table**: `rpm_alerts`
- **Columns**: `id`, `patient_id` (fk), `rpm_device_id` (fk, nullable), `service_assignment_id` (fk, nullable), `event_type` (string), `severity` (enum low/medium/high/critical), `payload` (json), `triggered_at` (timestamp), `handled_by` (fk -> users.id, nullable), `handled_at` (timestamp, nullable), `resolution_notes` (text, nullable), `status` (enum open/in_progress/resolved/escalated), `source_reference` (string, nullable), timestamps.
- **Indexes**: `(status, severity)`, `(patient_id, triggered_at)`, `(service_assignment_id)`.
- **Model**: `App\Models\RpmAlert` belongs to patient, device, optional assignment, handler; may `hasMany(ServiceAssignment::class)` through linking.
- **Status Handling**: Stored as strings validated via constants (`RpmAlertStatus::OPEN`, `::IN_PROGRESS`, `::RESOLVED`, `::ESCALATED`). Lifecycle rules: alerts start `open`, can move to `in_progress`, then `resolved`; any state can transition to `escalated` which is terminal unless a new alert is spawned. Resolution timestamps/users are required for `resolved`.

### OhMetric
- **Table**: `oh_metrics`
- **Columns**: `id`, `period_start` (date), `period_end` (date), `metric_key` (string), `metric_value` (decimal or integer), `breakdown` (json, nullable), `computed_at` (timestamp), `computed_by_job_id` (uuid, nullable).
- **Indexes**: Unique `(metric_key, period_start, period_end)`, index on `computed_at`.
- **Model**: `App\Models\OhMetric` used by reporting controllers/jobs.

### Feature Flags
- **Table**: `feature_flags`
- **Columns**: `id`, `key` (string, unique), `description` (text, nullable), `enabled` (boolean, default false), `payload` (json, nullable), timestamps.
- **Model**: `App\Models\FeatureFlag` accessible via `FeatureToggle` service.

### Audit Logs
- **Table**: `audit_logs`
- **Columns**: `id`, `user_id` (fk nullable), `auditable_type`/`auditable_id` (morphs), `action` (string), `before` (json, nullable), `after` (json, nullable), `ip_address` (string, nullable), `created_at`.
- **Indexes**: `(auditable_type, auditable_id)`, `(user_id, created_at)`.
- **Model**: `App\Models\AuditLog` morphTo auditable, belongsTo user.

### Extended Tables
- **users**: add `organization_id` (fk -> service_provider_organizations, nullable for system admins) and `organization_role` (string). Index `(organization_id, organization_role)` to accelerate dashboards.
- **patients**: add `triage_summary` (json), `maple_score` (string, nullable), `rai_cha_score` (string, nullable), `risk_flags` (json), `primary_coordinator_id` (fk -> users.id, nullable). Ensure `status` becomes string/varchar with enum mapping while keeping legacy statuses for backwards compatibility.

## Detailed Phase Plan

### Terminology Alignment (Transition Review vs. TriageResult)
- Product copy, prompts, and training material must say “Transition Review” and “Transition Needs Profile (TNP)” to align with Spec 2.1 and Ontario Health language.
- Internally we continue to use `TriageResult`, `TriageService`, and existing migrations/tests so we do not destabilize the schema mid-refactor; UI helpers translate the public name to the internal model.
- All new code, docs, and seeds must mention both names until we explicitly plan a rename phase.

### Phase 0 – Legacy Shell & CC2 Skeleton
- **Goals**: Isolate retirement-home placement flows under `/legacy`, introduce empty `/cc2` module namespaces, and ensure feature flags can hide/show modules without code changes.
- **Deliverables**:
  - Route split into `routes/legacy.php` and `routes/cc2.php` plus updated `RouteServiceProvider`.
  - Controllers moved into `App\Http\Controllers\Legacy\*` while new CC2 namespaces (`App\Http\Controllers\CC2\*`) are stubbed with placeholder controllers/views to verify wiring.
  - Navigation partials updated to group “Legacy Placement” vs “CC2 Coordination” entries, each guarded by feature flags (`legacy.enabled`, `cc2.enabled`).
  - Documentation describing the dual-route structure and coding standards for new modules.
- **Affected Files**: `routes/web.php` (temporary loader), new `routes/legacy.php`, `routes/cc2.php`, `app/Http/Controllers/*`, `resources/views/layouts/*`, `app/Providers/RouteServiceProvider.php`, `docs/README`.
- **Risks**: Accidentally breaking bookmarked URLs or auth middleware order; mitigated via 1:1 route mapping tests and feature flag defaults that keep legacy enabled.
- **Tests Required**: Smoke tests validating login, patient listing, booking creation via `/legacy`; feature tests checking `/cc2` endpoints remain hidden unless flags are on.

### Phase 1 – Multi-Organization Foundation & Provider Onboarding
- **Goals**: Make SPO/SSPO organizations first-class citizens, allow Platform/SPO admins to onboard providers, and scope users by organization roles while keeping placements operational.
- **Deliverables**:
  - `ServiceProviderOrganization`, `OrganizationMembership`, and capability models seeded with SPO/SSPO exemplars (SE Health, Grace, Reconnect, Alexis).
  - Admin UI under `App\Http\Controllers\CC2\Organizations` for listing/editing providers, linking SSPOs, and capturing service coverage/capabilities.
  - Middleware stack (`EnsureOrganizationContext`, `EnsureOrganizationRole`) plus request tests verifying SPO-only visibility.
  - Updated onboarding scripts to assign existing CC staff to Platform Admin and SPO Admin roles.
- **Affected Files**: `app/Models/*`, `database/migrations/2025_11_17_*`, `database/seeders/*`, `app/Http/Controllers/CC2/Organizations/*`, `app/Policies/*`, `resources/views/cc2/organizations/*`, `app/Http/Middleware/*`.
- **Risks**: Locking users out if they lack organization membership; mitigated via safe default membership migration and feature flags.
- **Tests Required**: Policy tests for admin/org roles, feature tests for provider creation/update, unit tests for capability filtering scopes.

### Phase 2 – Referral Intake & Transition Review (TNP)
- **Goals**: Enable SPO Discharge/Transition Coordinators to create referrals, capture Transition Needs Profiles (via `TriageResult`), and maintain referral clocks/attachments per Spec 2.1.
- **Deliverables**:
  - Production readiness for existing `ReferralService`, `TriageService`, requests, and policies inside `App\Http\Controllers\CC2\Intake`.
  - `/cc2/intake` dashboards for manual referral entry, SLA timers (15-minute acceptance, 24-hour first service), and attachment uploads.
  - `/cc2/patients/{patient}/transition-review` flow surfacing TNP flags (dementia, MH, RPM) while persisting to `TriageResult`.
  - Event/audit log hooks capturing referral submissions and Transition Review updates.
- **Affected Files**: `routes/cc2.php`, `app/Http/Controllers/CC2/Intake/*`, `resources/views/cc2/intake/*`, `resources/views/cc2/transition-review/*`, `app/Services/CC2/ReferralService.php`, `app/Services/CC2/TriageService.php`, `app/Policies/ReferralPolicy.php`, `app/Policies/TriageResultPolicy.php`, tests under `tests/Feature/CC2`.
- **Risks**: Data leakage between SPOs if scoping fails; inaccurate timers causing SLA compliance issues; mitigated via scoped queries and unit-tested clock helpers.
- **Tests Required**: Feature/API tests for referral creation and TNP update, policy coverage for Discharge/Transition Coordinator roles, regression suite ensuring legacy `/legacy` flows unaffected.

### Phase 3 – Care Bundles & Care Plans
- **Goals**: Provide SPO coordinators with CareBundle templates, editable CarePlans, and default SSPO routing per Transition Review outputs.
- **Deliverables**:
  - `CareBundleController` + services to create bundles, define bundle lines (ServiceType, intensity, SPO vs. SSPO ownership), and clone templates.
  - `CarePlanController` for drafting/approving plans, linking to bundles, capturing goals/interventions/risks, and scheduling review reminders.
  - Blade components (or Inertia) for bundle composition, template selection (“High Intensity Dementia”, etc.), and CarePlan approval logs.
  - Policies ensuring only SPO admins/coordinators edit bundles while SSPOs view read-only scopes.
- **Affected Files**: `app/Http/Controllers/CC2/CarePlanning/*`, `app/Services/CC2/CareBundleService.php`, `app/Services/CC2/CarePlanService.php`, `resources/views/cc2/care-bundles/*`, `resources/views/cc2/care-plans/*`, `database/seeders/CareBundleTemplateSeeder.php`, `app/Policies/CareBundlePolicy.php`.
- **Risks**: Mismatched template data leading to incorrect billable bundles; mitigated via fixture tests and template validation.
- **Tests Required**: Unit tests for bundle template expansion, feature tests for CarePlan draft→approval, authorization tests ensuring SSPOs remain read-only.

### Phase 4 – Service Assignments & Scheduling
- **Goals**: Translate bundles/plans into executable ServiceAssignments, expose SPO/SSPO scheduling dashboards, and honor 24/7 coverage requirements.
- **Deliverables**:
  - `AssignmentController` and `SchedulingController` managing assignment creation, calendar views (patient-centric, staff-centric), and SSPO acceptance flows.
  - Recurrence builder and conflict detection utilities, plus manual override tools for urgent visits.
  - Notification jobs/events alerting SSPO coordinators and SPO Service Coordinators of pending/missed acceptance.
  - SLA timers measuring hours-to-first-service per patient.
- **Affected Files**: `app/Http/Controllers/CC2/Assignments/*`, `app/Services/CC2/AssignmentService.php`, `resources/views/cc2/assignments/*`, `resources/views/cc2/scheduling/*`, `app/Jobs/AssignmentNotificationJob.php`, `database/migrations` (if new scheduling tables needed).
- **Risks**: Scheduling conflicts or inaccurate SLA calculations; mitigated with DB constraints and unit-tested overlap detection.
- **Tests Required**: Feature tests for assignment CRUD and SSPO acceptance, calendar unit tests for conflict detection, queue tests ensuring notifications dispatch.

### Phase 5 – Execution Documentation & RPM Flows
- **Goals**: Equip SPO/SSPO field staff with worklists, documentation workflows, and RPM alert handling tied to assignments and CarePlans.
- **Deliverables**:
  - `MyAssignmentsController` for field staff to clock in/out, mark completion, and submit Interdisciplinary Notes.
  - `RpmAlertController` (web) + webhook/API ingestion endpoints for RPM vendors, feeding `RpmService`.
  - Escalation tooling to convert critical RPM alerts into ServiceAssignments and capture outcomes in Interdisciplinary Notes and audit logs.
  - Visibility controls ensuring SSPO teams only see their patients/assignments while SPO retains oversight.
- **Affected Files**: `app/Http/Controllers/CC2/Execution/*`, `app/Http/Controllers/CC2/Rpm/*`, `app/Services/CC2/NoteService.php`, `app/Services/CC2/RpmService.php`, `routes/cc2.php`, `routes/api.php`, `resources/views/cc2/my-assignments/*`, `resources/views/cc2/rpm/*`, queue/job classes, webhook validation helpers.
- **Risks**: PHI leakage through notes or webhooks, queue overload, staff confusion with dual worklists; mitigated via strict policies, signed webhooks, and progressive rollout.
- **Tests Required**: Feature tests for documentation permissions, API tests for RPM ingestion/validation, unit tests covering alert→assignment escalation logic.

### Phase 6 – Metrics & OH Reporting
- **Goals**: Deliver Ontario Health mandated metrics (acceptance rates, hours-to-first-service, missed care, ED diversion) plus exports and dashboards.
- **Deliverables**:
  - `MetricsController` dashboards segmented by SPO, SSPO contribution, and region, plus CSV/Excel exports for OH submissions.
  - `MetricsService` + scheduled `ComputeOhMetricsJob` calculating aggregated indicators, storing them in `oh_metrics`.
  - QA tooling to reconcile metrics vs. raw assignments/referrals for audit-readiness.
  - Documentation describing weekly huddle workflows, export schedules, and data retention.
- **Affected Files**: `app/Http/Controllers/CC2/Metrics/*`, `app/Services/CC2/MetricsService.php`, `app/Jobs/ComputeOhMetricsJob.php`, `app/Console/Kernel.php`, `resources/views/cc2/metrics/*`, `routes/cc2.php`, export classes.
- **Risks**: Incorrect metrics jeopardizing OH compliance; expensive queries impacting production; mitigated via staged rollouts, sampling, and DB indexing.
- **Tests Required**: Service tests verifying each metric formula, feature tests for export permissions, scheduler tests ensuring jobs run/time out safely.

## Architecture Blueprint (CC2.1)

### Legacy Baseline vs. Target Modular Layout
- **Today**: Even though we now run Laravel 10, the codebase still relies on a single `routes/web.php`, fat controllers (e.g., `PatientsController`, `BookingsController`), Blade views inside shared directories, and global middleware such as `authenticated.routes`. Hospitals and retirement homes operate inside the same namespace with no concept of SPOs, SSPOs, or CC2 terminology.
- **Target**: A modular structure that keeps retirement-home placement inside `/legacy` while all CC2.1 orchestration features (provider onboarding, Transition Review, Care Bundles, assignments, RPM, OH metrics) live under `/cc2`. Controllers become thin, service classes encapsulate domain logic, and route groups + middleware enforce role/organization boundaries.

### Namespaces, Folders, and Route Groups
| Concern | Legacy Path | CC2.1 Path | Notes |
| --- | --- | --- | --- |
| Controllers | `App\Http\Controllers\Legacy\*` | `App\Http\Controllers\CC2\{Organizations,Intake,CarePlanning,Assignments,Execution,Rpm,Metrics}` | Each CC2 module gets its own namespace folder with dedicated Form Requests, policies, and DTOs. |
| Routes | `routes/legacy.php` loaded with prefix `legacy` & middleware `['web','authenticated.routes']` | `routes/cc2.php` loaded with prefix `cc2` plus middleware `['web','auth','ensure.organization','feature:cc2.enabled']`; APIs belong in `routes/api-cc2.php` with Sanctum guards | `RouteServiceProvider` registers both plus `fallback` to maintain URLs; `/legacy` can optionally drop prefix for backward compatibility via route name aliasing. |
| Views | `resources/views/legacy/...` | `resources/views/cc2/{organizations,intake,care-bundles,...}` | Shared components under `resources/views/components/cc2`. Legacy templates remain untouched except for namespace moves. |
| Services | `app/Services/Legacy/*` (new) | `app/Services/CC2/*` (existing Referral/Triage + new modules) | Each service exposes command-style methods (e.g., `AssignmentService::createForBundle`). |
| Requests/Policies | `App\Http\Requests\CC2\*`, `App\Policies\CC2\*` | Reuse existing `StoreReferralRequest`, `StoreTriageResultRequest`; add new request objects per workflow. |
| Modules | Legacy Placement, CC2 Provider Marketplace, CC2 Intake & Transition Review, CC2 Care Planning, CC2 Assignments & Scheduling, CC2 Execution & RPM, CC2 Metrics | Modules map 1:1 to phase roadmap ensuring isolation and feature flag gating. |

### Service Layers & Domain Concepts
- **Provider enrollment & marketplace**: `ServiceProviderOrganizationService`, `CapabilityService`, and `OrganizationMembershipService` manage SPO vs. SSPO onboarding, default ServiceType capabilities, and SPO↔SSPO relationship preferences (region, specialty, contract flags).
- **Discharge/Transition Review**: `ReferralService` and `TriageService` (already implemented) persist referrals and store the Transition Needs Profile inside `TriageResult`. Controllers present these as “Transition Review” screens while keeping the internal class names.
- **Care Bundles & Care Plans**: `CareBundleService` builds template-driven bundles referencing ServiceTypes and SSPO defaults; `CarePlanService` ties bundle lines to clinical goals, ensures review reminders, and enforces versioning.
- **Service Assignments & Scheduling**: `AssignmentService` translates bundle lines into ServiceAssignments, calling helper classes (`ScheduleBuilder`, `OverlapGuard`). `SchedulingService` aggregates staff calendars and SSPO acceptance flows, with events like `ServiceAssignmentCreated`.
- **Execution Documentation & RPM**: `NoteService` handles Interdisciplinary Notes with PHIPA-compliant visibility; `RpmService` ingests alerts, validates webhook signatures, queues `HandleRpmAlertJob`, and optionally spins up urgent ServiceAssignments.
- **Metrics & OH Reporting**: `MetricsService` computes KPIs (referral acceptance, time-to-first-service, missed care, ED diversion, RPM resolution time) and stores them in `oh_metrics` for dashboards + exports.

### Request / Response Flow
`Referral (Discharge Coordinator @ SPO) → Transition Review / TNP (TriageResult) → CareBundle + CarePlan (SPO Admin/Coordinator) → ServiceAssignments & Scheduling (Service Coordinator + SSPO Coordinator) → Execution + Documentation + RPM Alerts (Field Staff + RPM Team) → Metrics & OH Reporting (SPO Admin + Platform Admin).`

Each hop flows through:
1. **Controller** (namespaced under `App\Http\Controllers\CC2\*`) verifying role + feature flag.
2. **Form Request** ensuring Laravel 10 validation.
3. **Service** performing domain logic, firing events + audit logs.
4. **Policy** verifying user organization ownership or SSPO assignment scope.

### Feature Flags & Backwards Compatibility
- Flags (stored via `feature_flags` table and `FeatureToggle` service) include `cc2.enabled`, `cc2.organizations`, `cc2.intake`, `cc2.care_planning`, `cc2.assignments`, `cc2.rpm`, `cc2.metrics`, and `legacy.enabled`. Each controller uses the `EnsureFeatureEnabled` middleware to short-circuit if disabled.
- Navigation components read the same flags to hide modules; env defaults keep `/legacy` active until SPO pilots complete.
- Legacy controllers remain intact under `/legacy`, sharing the same auth middleware but skipping the new organization scopes so existing hospital/retirement-home workflows function unchanged.

### Middleware, Policies, and Audit Logging
- Middleware stack per CC2 route group: `auth`, `EnsureFeatureEnabled`, `EnsureOrganizationContext`, `EnsureOrganizationRole`, `verified` (if email verification is enabled). API routes also add `throttle:cc2-api` and `require-signed-payload` for RPM vendors.
- Policies exist for every CC2 entity (organizations, referrals, triage results/TNP, care bundles, care plans, service assignments, interdisciplinary notes, RPM alerts, metrics exports). They rely on `organization_role` plus membership scopes (SPO vs. SSPO).
- `AuditLog` records each create/update/delete on patient-facing data (referrals, TNP, bundles, plans, assignments, notes, RPM alerts) with user, organization, payload diff, and timestamp to maintain PHIPA compliance.

### Operations & Environment
- Laravel queues (Redis/SQS) and scheduler handle RPM alerts, assignment reminders, and OH metric aggregation; deployment runbooks include worker/watchdog monitoring.
- Sanctum personal access tokens secure external entry points (HPG-style referrals, RPM webhooks). Webhooks require HMAC signatures and rotate secrets per organization.
- Storage + encryption: patient identifiers and attachments leverage encrypted columns/disks; `config/filesystems.php` includes per-module directories (e.g., `care-plan-attachments`, `rpm-payloads`).

## Risks & Mitigations
- **Schema Back-Compat**: Legacy placements rely on current columns/types. Mitigate with additive migrations, data backfills, and blue/green deployment notes.
- **Authorization Gaps**: New multi-org roles risk data leaks. Mitigate via policies, middleware tests, and penetration-style reviews before launch.
- **Operational Load**: RPM alerts and metrics introduce queues and scheduled jobs. Mitigate with queue monitoring, retry/backoff strategies, and load testing.
- **User Adoption**: Parallel workflows can confuse users. Mitigate with feature flags, documentation, and toggled navigation entries.
- **Testing Debt**: Lack of current tests increases regression risk. Mitigate by enforcing minimum coverage per phase and building regression suites around both CC2 and legacy flows.
- **Integration Security**: External APIs need hardened auth/logging. Mitigate via Sanctum PATs, signed webhooks, request logging, and rate limiting.

---
This document is the canonical, version-controlled implementation plan for the Connected Capacity 2.0 refactor. Future phase prompts should reference and update this file as scope evolves.

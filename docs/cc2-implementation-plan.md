# Connected Capacity 2.0 Implementation Plan

## Current State Overview
- **Architecture**: Laravel 8 monolith focused on hospital ↔ retirement home placements. Controllers are fat, views are Blade/Datatables, routes are all web-based with a single `AuthenticatedRoutes` middleware guarding most paths (`routes/web.php`).
- **Domain**: Models cover admins, hospitals/new hospitals, retirement homes, patients, bookings, tiers, assessment forms, galleries, in-person assessments, and Calendly tokens. There are no service orchestration entities.
- **Data**: Migrations define placement tables only and include notable issues such as `patients.status` declared as boolean while controllers persist string workflow states. Soft deletes exist on most domain tables but there are no foreign keys or indexes beyond the defaults.
- **Integrations & Jobs**: Calendly OAuth is implemented through synchronous cURL calls. Sanctum exists but only for the unused `/api/user` endpoint. No queues, jobs, or audits are present.
- **Auth & Roles**: Roles are simple strings (`admin`, `hospital`, `retirement-home`, `patient`). Middleware only checks authentication or `role == admin`; there are no policies or per-organization scoping rules.
- **Testing**: Only the default Laravel Example tests are present. There is effectively no automated coverage for placements or upcoming CC 2.0 concerns.

## Target State Summary
- **Mission Shift**: Become a multi-organization, high-intensity home/community care orchestration engine that coordinates referrals, triage, service assignments, RPM monitoring, and Ontario Health (OH) reporting, rather than bed placements.
- **Core Concepts**: Introduce ServiceProviderOrganization, ServiceType, CareBundle, CarePlan, TriageResult, ServiceAssignment, InterdisciplinaryNote, RpmDevice, RpmAlert, OhMetric, feature flags, audit logging, and organization membership.
- **Workflows**: Implement intake APIs/manual entry, structured triage, care planning, assignment/dispatch dashboards, RPM alert handling, interdisciplinary documentation, and OH metrics aggregation/export.
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

### Phase 0 – Baseline & Hygiene
- **Goals**: Ensure repo health, remove glaring bugs (e.g., duplicate Hospital models), set up docs, feature-toggle scaffolding, and audit foundations before CC 2.0 additions.
- **Tasks**:
  - Consolidate `Hospital` vs `NewHospital` models and fix broken relations.
  - Normalize existing migrations (e.g., change `patients.status` to string) without altering behavior.
  - Introduce infrastructure-only migrations for `feature_flags` and `audit_logs`.
  - Configure queue, scheduler, logging defaults, and document setup (`docs/` directory).
- **Affected Files**: `app/Models/*`, `database/migrations/*`, `config/queue.php`, `config/logging.php`, `.env.example`, new `docs/` artifacts.
- **Risks**: Touching shared tables could regress placement flows. Need backups/migration scripts.
- **Minimum Tests**: Feature smoke tests for login, patient listing, booking creation; unit tests for updated models; integration test ensuring `Hospital::user()` works.

### Phase 1 – Core DB & Models for V2
- **Goals**: Add all CC 2.0 domain tables/models/seeders without impacting runtime.
- **Tasks**:
  - Create migrations/models/factories for every table listed in the Domain Model section.
  - Seed canonical `service_types`, baseline `care_bundles`, and demo `service_provider_organizations`.
  - Extend `users` and `patients` with new columns plus backfill scripts.
  - Wire Sanctum abilities for organization scoping (no routes yet).
- **Affected Files**: `database/migrations/*`, `app/Models/*`, `database/seeders/*`, `app/Policies/*` (stubs), `config/auth.php`.
- **Risks**: Schema drift affecting deployments; migration order dependencies.
- **Minimum Tests**: Unit tests per new model (relationships, casts), migration tests, seeder smoke test verifying canonical data.

### Phase 2 – Intake & Triage Flows
- **Goals**: Deliver referral intake UI/API and triage tooling guarded by organization roles.
- **Tasks**:
  - Implement `ReferralController`, `TriageController`, `ReferralService`, `TriageService`.
  - Build Blade views/components for intake dashboard and triage form with feature-flag gating.
  - Add `/intake` and `/triage` web routes plus `/api/referrals` endpoints.
  - Define policies/middleware (`TriageAccess`, `OrganizationScope`) leveraging `organization_role`.
  - Update navigation and dashboards to surface CC 2.0 entry points for enabled users.
- **Affected Files**: `routes/web.php`, `routes/api.php`, `app/Http/Controllers/*`, `app/Services/*`, `app/Policies/*`, `resources/views/intake/*`, `resources/views/triage/*`.
- **Risks**: Authorization mistakes exposing patient data; UI/legacy collisions.
- **Minimum Tests**: Feature tests covering referral creation (UI/API), triage update flows, policy enforcement; form request validation tests.

### Phase 3 – Care Plans & Service Assignments
- **Goals**: Model care planning, bundle management, service assignment dashboards, and interdisciplinary notes.
- **Tasks**:
  - Build `CareBundleController`, `CarePlanController`, `ServiceAssignmentController`, `InterdisciplinaryNoteController` plus respective services.
  - Implement coordinator and field-staff dashboards (filters, status transitions, note capture).
  - Add notification events/jobs for new assignments and status changes.
  - Extend dashboard to include CC 2.0 widgets.
- **Affected Files**: `app/Http/Controllers/CC2/*`, `app/Services/*`, `resources/views/care-plans/*`, `resources/views/assignments/*`, notification classes, events/jobs.
- **Risks**: Data consistency between assignments and care plans; overloading UI with complex forms.
- **Minimum Tests**: Feature tests for plan creation/edit/versioning, assignment lifecycle (planned→completed/missed), note creation authorization; job/notification unit tests.

### Phase 4 – RPM & External Integrations
- **Goals**: Introduce RPM alert management UI, ingestion API, and referral/RPM APIs secured via Sanctum tokens.
- **Tasks**:
  - Implement `RpmAlertController` (web) and `RpmEventsController` (API) backed by `RpmService`.
  - Create queue jobs for alert triage, escalations, and backpressure handling.
  - Add `/api/rpm-events`, `/api/referrals/hpg` endpoints with PAT auth, rate limiting, and webhook signature validation.
  - Provide UI for RPM queue, alert detail, and service-assignment escalations.
- **Affected Files**: `routes/api.php`, `routes/web.php`, `app/Http/Controllers/Rpm/*`, `app/Services/RpmService.php`, `app/Jobs/*`, `config/sanctum.php`, `config/queue.php`, `resources/views/rpm/*`.
- **Risks**: API abuse, queue overload, data duplication between alerts and assignments.
- **Minimum Tests**: API feature tests validating auth and payload handling, service tests asserting alert-to-assignment logic, browser tests for RPM UI.

### Phase 5 – Metrics & OH Reporting
- **Goals**: Compute OH metrics, persist snapshots, and surface dashboards/exports.
- **Tasks**:
  - Implement `MetricsService`, `ComputeOhMetricsJob`, and schedule via `app/Console/Kernel.php`.
  - Build `ReportController` views leveraging existing ApexCharts for OH dashboards.
  - Add export endpoints (CSV/JSON) with role-based access.
  - Optimize DB (indexes, query scopes) for metrics queries.
- **Affected Files**: `app/Services/MetricsService.php`, `app/Jobs/ComputeOhMetricsJob.php`, `app/Console/Kernel.php`, `resources/views/reports/*`, `routes/web.php`.
- **Risks**: Long-running queries impacting production; incorrect metrics harming compliance.
- **Minimum Tests**: Service/unit tests for each metric computation, job tests verifying scheduling, feature tests for report access permissions.

### Phase 6 – Legacy Placement Isolation & Cleanup
- **Goals**: Carve out existing placement flows into a legacy area, reduce navigation prominence, and prep for eventual retirement.
- **Tasks**:
  - Move placement controllers/routes/views under `App\Http\Controllers\Legacy` and `/legacy/*` route prefixes.
  - Add navigation grouping “Legacy Placement” accessible via feature flag.
  - Update documentation and onboarding around dual workflows.
  - Plan archival/export scripts for placement data (optional stretch).
- **Affected Files**: `routes/web.php`, `app/Http/Controllers/Legacy/*`, `resources/views/legacy/*`, navigation components, docs.
- **Risks**: Breaking existing users if routes change unexpectedly; forgetting dependent links (emails, bookmarks).
- **Minimum Tests**: Regression tests for critical placement flows (patient create, booking, assessment) under new namespace/prefix.

## Architecture & Boundaries
- **Controller Layout**: Introduce namespaces (`App\Http\Controllers\CC2`) for new modules: Intake/Triage, Care Plans, Assignments, RPM, Reports. Keep placement controllers under `App\Http\Controllers\Legacy`.
- **Services**: Dedicated service classes (`ReferralService`, `TriageService`, `CarePlanService`, `AssignmentService`, `NoteService`, `RpmService`, `MetricsService`, `FeatureToggle`, `AuditLogger`) with thin controllers orchestrating requests.
- **Jobs & Events**: Background jobs for metrics, RPM follow-ups, assignment reminders, external referral imports. Events (`ServiceAssignmentCreated`, `RpmAlertEscalated`, `CarePlanApproved`) trigger notifications/audits.
- **Routing**: `/cc2/*` or `/coordination/*` prefixes for new flows, `/legacy/*` for old placements. API routes namespaced under `/api/v2/*` with Sanctum guards and throttling.
- **Middleware/Policies**: Add `EnsureOrganizationContext`, `EnsureFeatureEnabled`, role-based policies for each controller. Use route model binding with scoped queries to enforce organization isolation.
- **Views/UI**: Separate Blade layouts/Livewire/Inertia components for CC 2.0 dashboards to avoid coupling legacy markup.
- **Data Access**: Repositories/scoped query builders for CC2 models, preventing controllers from issuing raw queries.

### Feature Flags
- Example keys: `cc2.enabled` (global kill-switch), `cc2.intake`, `cc2.triage`, `cc2.assignments`, `cc2.rpm`, `cc2.metrics`, `legacy.placement_nav`.
- Flags are evaluated globally, per environment, and optionally per organization to allow staged rollouts (e.g., enable CC2 intake for SE Health first).
- All CC 2.0 routes, navigation links, and dashboards will check the relevant flag(s) before rendering; disabling a flag hides UI and returns 404 for gated endpoints.

### Operations & Environment
- **Database**: Target MySQL 8 (Aurora-compatible) with InnoDB for FK + transaction support.
- **Queues**: Use Laravel queues (e.g., Redis or SQS drivers) for RPM alert ingestion, assignment notifications, and metrics jobs; workers must be provisioned in each environment.
- **Scheduler**: Laravel scheduler (cron) will trigger recurring jobs such as `ComputeOhMetricsJob`, stale-assignment reminders, and RPM follow-up sweeps. Monitoring must ensure scheduler/queue health.

## Risks & Mitigations
- **Schema Back-Compat**: Legacy placements rely on current columns/types. Mitigate with additive migrations, data backfills, and blue/green deployment notes.
- **Authorization Gaps**: New multi-org roles risk data leaks. Mitigate via policies, middleware tests, and penetration-style reviews before launch.
- **Operational Load**: RPM alerts and metrics introduce queues and scheduled jobs. Mitigate with queue monitoring, retry/backoff strategies, and load testing.
- **User Adoption**: Parallel workflows can confuse users. Mitigate with feature flags, documentation, and toggled navigation entries.
- **Testing Debt**: Lack of current tests increases regression risk. Mitigate by enforcing minimum coverage per phase and building regression suites around both CC2 and legacy flows.
- **Integration Security**: External APIs need hardened auth/logging. Mitigate via Sanctum PATs, signed webhooks, request logging, and rate limiting.

---
This document is the canonical, version-controlled implementation plan for the Connected Capacity 2.0 refactor. Future phase prompts should reference and update this file as scope evolves.

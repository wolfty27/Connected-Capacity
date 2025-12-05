CC Backend FastAPI Transition Spec

Laravel → Python + FastAPI Rewrite (Scheduler + Bundle Engine as AI Cores)

⸻

Part 1 – Global Architecture & Domains

1.1 High-Level Goals

We are rewriting the entire backend from Laravel/PHP to Python + FastAPI with these explicit goals:
	1.	Two AI-centric cores:
	•	AI Bundle Engine 2.0 (Care Design)
	•	AI Scheduler 2.0 (Care Orchestration)
	2.	Strongly-typed, metadata-driven, domain-oriented modules:
	•	assessments, bundles, scheduling, workforce, sspo, ai_orchestration, auth, orgs.
	3.	Seamless integration with Vertex AI, Google Maps/Distance Matrix, and GCP.
	4.	Maintain and improve:
	•	interRAI-driven care decisions
	•	scheduling correctness & constraints
	•	0% missed-care and 24h TFS targets
	5.	Architect for future learning loops:
	•	Logging, outcome metrics, and ML models that refine both bundles and schedules over time.

1.2 Target System Overview

┌──────────────────────────────────────────┐
│              Frontend (React)           │
│  - Scheduler 2.0 (AI-first control room)│
│  - Bundle Engine UI (AI care design)    │
│  - Admin, Capacity, SSPO views          │
└──────────────────────────────────────────┘
                   │  (REST/JSON)
                   ▼
┌──────────────────────────────────────────┐
│             FastAPI Backend              │
│                                          │
│  Domains:                                │
│  - auth, users, orgs                     │
│  - assessments (interRAI HC/CA/BMHS)     │
│  - bundles (AI Bundle Engine 2.0)        │
│  - scheduling (AI Scheduler 2.0)         │
│  - workforce & capacity                  │
│  - sspo (external partners)              │
│  - ai_orchestration (Vertex, LLM, ML)    │
└──────────────────────────────────────────┘
        │                         │
        ▼                         ▼
┌──────────────────────┐   ┌──────────────────────────┐
│     Data Layer       │   │   External Services      │
│  - SQL DB (existing  │   │  - Vertex AI (LLM, ML)   │
│    schema migrated)  │   │  - Google Maps (Distance │
│  - Redis/cache       │   │    Matrix, Maps)         │
│  - BigQuery (logs)   │   │  - Email/SMS providers   │
└──────────────────────┘   └──────────────────────────┘

1.3 Tech Stack
	•	Language: Python 3.11+
	•	Framework: FastAPI
	•	ORM: SQLAlchemy 2.x + Alembic
	•	Validation: Pydantic models
	•	Config: Pydantic Settings
	•	Auth: OAuth2/JWT (or plugin with current IdP)
	•	Background tasks: Celery / RQ (for heavy AI or batch scheduling if needed)
	•	Deployment: Containerized, Cloud Run, CI/CD

⸻

Part 2 – AI Bundle Engine 2.0 (Deep Spec)

2.1 Role in the System

The Bundle Engine is responsible for:
	•	Translating assessment + context (interRAI, clinical, social, caregiver) into care bundle scenarios:
	•	Service mix: Nursing, PSW, Therapy, RPM, PERS, Behavioural Supports, In-Home Lab, Pharmacy, Meals, etc.
	•	Intensities / frequencies: visits per week, hours per week.
	•	Modalities: SPO vs SSPO, in-person vs remote.
	•	Creating multiple bundle scenarios (personas) per patient:
	•	Recovery/Rehab-focused
	•	Stability & Safety-first
	•	Tech-enabled / remote-heavy
	•	Caregiver-relief / support
	•	Medical-intensive
	•	Providing explanations for each scenario and logging decisions for learning.

2.2 FastAPI Domain Module Layout

app/bundles/
  __init__.py
  models.py          # CareBundle, CarePlan, BundleTemplate, ServiceIntensityMatrix
  schemas.py         # Pydantic models for API
  routes.py          # /api/v2/bundles/*
  service.py         # CareBundleBuilderService, BundleScenarioGenerator
  intensity_matrix.py# Data-driven service intensity rules
  scenarios.py       # BundleScenarioEngine

2.3 Core Models (Conceptual)

At a high level:
	•	CareBundle:
	•	id, patient_id, status, bundle_code, scenario_label, created_at, updated_at
	•	CareBundleServiceLine:
	•	bundle_id, service_type_code, frequency_per_week, duration_minutes, provider_type, priority_level
	•	CarePlan:
	•	id, bundle_id, status, effective_start, effective_end
	•	ServiceIntensityMatrix (config-driven, not hard-coded):
	•	Mappings like psa_score → personal_support_hours, rehab_score → therapy_visits (stored in JSON/YAML)

All persisted models are mirrored by Pydantic schemas for API.

2.4 Assessment & Needs Profile

assessments/ module produces a PatientNeedsProfile (Pythonic version of what you designed for CC21) which includes:
	•	ADL/IADL levels
	•	Cognitive complexity (CPS)
	•	Behavioural complexity
	•	Clinical risks (falls, pressure ulcers, pain, continence, CHESS)
	•	Rehab potential
	•	Caregiver context
	•	Tech readiness
	•	Region/geography

The Bundle Engine:
	•	Takes a PatientNeedsProfile
	•	Calls ServiceIntensityMatrix to derive baseline intensity by category:
	•	personal_support, clinical_monitoring, rehab_support, risk_mgmt, nutrition_support, etc.
	•	Uses BundleScenarioEngine to generate scenario variants:
	•	For each axis (Recovery, Safety, Tech, Caregiver, etc.), chooses how to spend intensity across specific ServiceTypes.

2.5 Scenario Generation

BundleScenarioEngine.generate_scenarios(profile: PatientNeedsProfile) -> list[BundleScenario]
	•	Uses:
	•	algorithm-driven floors (CA/HC/RUG/CAPs)
	•	ServiceIntensityMatrix (data-driven)
	•	axis templates (scenario_templates.json)

Each BundleScenario includes:
	•	scenario_id
	•	scenario_label (e.g., “Recovery-Focused”, “Tech-Enabled Safety”)
	•	services: list[BundleServiceLine]
	•	cost_estimate
	•	operational_notes
	•	patient_experience_notes

2.6 AI (Vertex) Integration for Bundles

Within ai_orchestration/:
	•	BundleExplanationService:
	•	Takes PatientNeedsProfile + BundleScenario
	•	Calls Vertex to generate:
	•	short_explanation
	•	key_points
	•	confidence_label
	•	Future:
	•	BundleScenarioRefinementService:
	•	Uses Vertex or ML models to:
	•	Suggest tweaks to intensity
	•	Propose new scenarios

2.7 Outputs to Scheduler

Bundles feed Scheduler as requirements:
	•	bundle_id
	•	patient_id
	•	For each line:
	•	service_type_code, frequency_per_week, duration_minutes, window_constraints, provider_type_preference, continuity_importance, etc.

Scheduler then turns these into concrete weekly assignments.

⸻

Part 3 – AI Scheduler 2.0 (Deep Spec)

3.1 Role in the System

The Scheduler takes:
	•	Bundle requirements (care plans & service lines)
	•	Staff availability & capacity
	•	Travel / geography
	•	Provider rules (SPO/SSPO)
	•	Real-time constraints (no overlaps, spacing, TFS)
	•	Historical data

…and produces:
	•	Concrete weekly schedules (ServiceAssignments)
	•	AI suggestions for new assignments
	•	Scenario-level proposals (e.g., travel-optimized week)
	•	Conflict/no-match diagnostics

3.2 FastAPI Domain Module Layout

app/scheduling/
  __init__.py
  models.py          # ServiceAssignment, StaffAvailability, TeamLaneConfig
  schemas.py         # API-facing models
  routes.py          # /api/v2/scheduling/*
  engine.py          # SchedulingEngine
  auto_assign.py     # AutoAssignEngine
  conflicts.py       # Conflict detection & DTOs
  team_lanes.py      # Dynamic grouping for lanes
  state.py           # SchedulerState aggregator (for AI Overview)

3.3 SchedulingEngine

A pure domain service:

class SchedulingEngine:
    def validate_assignment(
        self,
        proposed: ProposedAssignment,
        staff_assignments: Sequence[ServiceAssignment],
        patient_assignments: Sequence[ServiceAssignment],
        staff_availability: StaffAvailability,
        travel_estimates: TravelContext
    ) -> list[ConstraintViolation]:
        ...

Constraint types:
	•	Overlapping staff assignments
	•	Overlapping patient visits
	•	Spacing rules (per ServiceType)
	•	TFS / missed-care risk
	•	Provider type mismatches
	•	Travel infeasibility (via TravelTimeService)
	•	Capacity / FTE caps

3.4 AutoAssignEngine

Rewritten in Python:

class AutoAssignEngine:
    def generate_suggestions(
        self,
        unscheduled_services: list[UnscheduledService],
        scheduler_state: SchedulerState
    ) -> list[AssignmentSuggestion]:
        ...

	•	Uses:
	•	Staff eligibility via ServiceRoleMapping
	•	Availability & capacity
	•	geography/travel metrics
	•	continuity (prior visits)
	•	workload balancing
	•	Scores candidates and produces suggestions with:
	•	match_status (strong/moderate/none)
	•	score_components
	•	reason_codes (for explanation UI & conflicts tab)

3.5 SchedulerState

SchedulerState (in Python, for use by endpoints):
	•	timeframe (week)
	•	unscheduled services
	•	scheduled assignments
	•	staff summaries (role, team lane, capacity)
	•	patient summaries (risk, geography)
	•	AI suggestions & their status
	•	conflicts & no-matches

The frontend’s useSchedulerState hook mirrors this.

3.6 Internal Views & APIs

FastAPI exposes endpoints for:
	•	GET /api/v2/scheduling/state → full SchedulerState for a week
	•	GET /api/v2/scheduling/requirements → unscheduled services
	•	GET /api/v2/scheduling/suggestions → AI suggestions
	•	POST /api/v2/scheduling/suggestions/accept → accept 1 suggestion
	•	POST /api/v2/scheduling/suggestions/accept-batch → accept many
	•	GET /api/v2/scheduling/conflicts → conflict & no-match DTOs

⸻

Part 4 – Shared AI/Vertex Services

4.1 ai_orchestration Module

app/ai_orchestration/
  __init__.py
  routes.py            # /api/v2/ai/*
  llm_explanation.py   # Vertex client for explanations
  scheduler_explain.py # Specialization for scheduling suggestions
  bundles_explain.py   # Specialization for bundle scenarios
  scenario_planning.py # Scenario ranking, scenario summaries
  prompts/             # Prompt templates for Vertex
  logging.py           # AI call + explanation logs

4.2 Explanations

Services:
	•	explain_assignment_suggestion(suggestion: AssignmentSuggestion, profile: PatientNeeds)
	•	explain_bundle_scenario(scenario: BundleScenario, profile: PatientNeedsProfile)
	•	explain_no_match(context: NoMatchContext)

Each builds a PII-safe prompt and calls Vertex to produce:
	•	short_explanation
	•	bullet points
	•	confidence_label
	•	optional recommendations (e.g., “relax time window”)

4.3 Scenario Summaries

Scenario-level:
	•	Weekly schedule scenario comparisons:
	•	Vertex gets metrics + structural description, returns:
	•	“Scenario A focuses on continuity, Scenario B reduces travel…”
	•	Bundle scenario comparisons:
	•	Vertex gets intensity mix & risk profile, returns:
	•	patient-experience oriented explanation.

⸻

Part 5 – Geospatial/Maps

5.1 travel.py (Domain Service)

class TravelTimeService:
    def estimate(
        self,
        origin: Coordinates,
        destination: Coordinates,
        departure_time: datetime
    ) -> TravelEstimate:
        ...

	•	Backed by Google Distance Matrix API.
	•	Caching via DB/Redis.
	•	Used by SchedulingEngine and SchedulerState summarization.

5.2 Data Requirements

Add coordinate fields (or geocoding keys) for:
	•	Patient location (home)
	•	Staff “home base” or primary location
	•	Possibly SSPO service region centroids

Used by:
	•	SchedulingEngine (feasibility checks)
	•	AI suggestions (score components)
	•	Scheduler UI maps.

⸻

Part 6 – SSPO / Workforce / Auth & Peripheral Domains

6.1 Workforce

workforce/ module:
	•	Staff, StaffRole, EmploymentType, TeamLaneHint
	•	Capacity metrics:
	•	Weekly capacity per staff
	•	Actual scheduled hours
	•	APIs to:
	•	Fetch staff summary
	•	Update employment types
	•	Query capacity.

6.2 SSPO

sspo/ module:
	•	Models SSPO providers and service capabilities.
	•	Interacts with:
	•	Bundle Engine (for SSPO-preferred services)
	•	Scheduler (for SSPO staff and constraints).

6.3 Auth/Orgs

Standard domain:
	•	auth/ for users & tokens.
	•	orgs/ for SPO/SSPO and tenant contexts.

⸻

Part 7 – Migration Order & Multi-Agent Execution Strategy

7.1 Overall Migration Order
	1.	Documented Current State – already done (CC_Backend_Current_State.md).
	2.	Global FastAPI Skeleton
	3.	Assessments + PatientNeedsProfile (foundation for both cores)
	4.	Bundles (AI Bundle Engine 2.0)
	5.	Scheduling (AI Scheduler 2.0)
	6.	Workforce / Capacity
	7.	SSPO
	8.	Auth / peripheral domains

Scheduler and Bundle Engine are co-core, but you can implement Assessments & Bundle Engine first if it’s easier to stand up care-plan generation before scheduling.

7.2 Multi-Agent Cursor Strategy

Use Cursor’s multi-agent capability with clear roles:

Agent 1 – Architect / Planner
	•	Reads:
	•	CC_Backend_Current_State.md
	•	CC21_AI_Bundle_Engine_Design.md
	•	SPO_Scheduling_Functional_Spec.md
	•	interRAI/assessment docs
	•	Responsible for:
	•	Refining this Transition Spec into implementation tasks
	•	Defining FastAPI module skeletons
	•	Defining DTOs and schemas
	•	Maintaining architectural consistency

Agent 2 – Bundle Engine Implementer
	•	Implements:
	•	assessments/ (ingestion + derived)
	•	bundles/ (models, service, routes, intensity_matrix, scenarios)
	•	Writes tests for:
	•	RUG/CAP logic
	•	BundleScenarioEngine
	•	IntensityMatrix mappings

Agent 3 – Scheduler Implementer
	•	Implements:
	•	scheduling/ (models, SchedulingEngine, AutoAssignEngine, routes)
	•	travel.py integration (Google Maps)
	•	Writes tests for:
	•	constraint enforcement
	•	auto-assign scoring logic

Agent 4 – AI/Vertex Implementer
	•	Implements:
	•	ai_orchestration/ (LLM explanation, scenario summary)
	•	Creates:
	•	prompts
	•	PII-safe data contracts
	•	tests for fallback behavior when Vertex fails

Agent 5 – Reviewer / QA
	•	Reviews:
	•	Each domain module vs spec
	•	Object model gaps
	•	AI usage safety & correctness
	•	Suggests refactors:
	•	Ensures we’re avoiding PHP-style “fractal of bad design” patterns.

7.3 Execution Notes
	•	Architect agent writes and updates the specs; Implementers must not modify them, only reference them.
	•	For each phase, you can coordinate:
	•	Architect writes/upgrades spec section.
	•	Implementer builds according to it.
	•	Reviewer checks alignment & suggests improvements.
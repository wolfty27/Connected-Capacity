# Connected Capacity – Laravel → FastAPI Transition Specification  
**Version:** 0.9 (Draft)  
**Author:** (You + AI)  
**Date:** 2025-12-XX  

> **Note on Structure & Chunking:**  
> This document is intentionally written as a single markdown file, but it includes explicit chunk markers to help tools like Claude 4.5 and Cursor load and reason over it in parts.  
> - Use `<!-- CHUNK_START: SectionName -->` / `<!-- CHUNK_END: SectionName -->` to load specific sections.  
> - The Appendix contains guidelines for how to chunk this spec when building prompts for agents.  

---

<!-- CHUNK_START: ExecutiveSummary -->

## Part 1 – Executive Summary & Transition Goals

Connected Capacity (CC2) is a healthcare orchestration platform focused on:

- **Care Design:** generating clinically coherent, interRAI-driven care bundles for high-needs patients.  
- **Care Orchestration:** turning those bundles into real-world, staff-aware, travel-aware schedules.  

The current backend is a **Laravel/PHP monolith**. We are undertaking a **full transition** to a **Python + FastAPI** backend and a unified, AI-centric architecture that:

1. Treats the **AI Bundle Engine** and **AI Scheduler** as **peer core domains**.
2. Enforces **Canadian PHI residency and sovereignty**, with a strict **PHI boundary** and tokenization:
   - PHI stored in Canada (ThinkOn, Azure Canada, AWS ca-central).
   - AI workloads (Vertex, other LLMs) run on de-identified, tokenized features in GCP Canada.
3. Fully modernizes **all backend** and **key frontend surfaces** (Scheduler, Bundle Engine, Patient Profile, AI Console), avoiding any “Frankenstein” mix of old/new code.
4. Builds an **AI-first UX**:
   - The system surfaces AI summaries, scenarios, and suggestions by default.
   - The human is always in control: review, override, refine.

At the end of this transition, the platform should:

- Run entirely on a Python/FastAPI backend.
- Expose clear, versioned APIs for all front-end surfaces.
- Have a robust demo environment (seeded or generated) that showcases the end-to-end flow:
  - interRAI intake → bundle scenarios → schedule → AI assistance → explanations → conflicts/no-match handling.
- Be deployable as a live demo on your own domain (currently managed via Squarespace) with a Canadian-resident PHI data layer.

This spec defines:

- The **target architecture** and domain boundaries.  
- Deep specs for **AI Bundle Engine 2.0** and **AI Scheduler 2.0**.  
- Shared services (AI/LLM, Geospatial, Tokenization/PHI).  
- Data layer and demo dataset architecture.  
- A migration strategy from Laravel to FastAPI.  

# CC3 – Master Orchestrator Prompt (Architect Agent)
[CHUNK:MASTER_ORCHESTRATOR]

> **Role:** System/Dev prompt for the **Architect Agent** (Claude 4.5 Opus in Cursor)  
> **Context:** Full CC2 rewrite from Laravel/PHP → Python + FastAPI, with AI-first domains (Bundle Engine 2.0 + Scheduler 2.0), PHI residency, AI governance, security-first and scalability-first workflows.

---

## 0. Your Identity & Mission
[CHUNK:MASTER_MISSION]

You are the **Architect Agent** for Connected Capacity 3.0 (CC3).

Your job is to:

1. Understand the **current Laravel/PHP system** and the existing design docs.
2. Understand the **target FastAPI + AI-first architecture**:
   - AI Bundle Engine 2.0 (care design).
   - AI Scheduler 2.0 (care orchestration).
3. Respect:
   - **PHI residency rules & tokenization boundary**.
   - **Security-first & Scalability-first** AI workflow.
   - **Harness v3.0** for long-running agents.
4. Design and maintain:
   - A **Transition Plan** from Laravel to FastAPI.
   - A **modular implementation plan** for all domains.
   - The **contracts and scaffolding** that Implementer & Reviewer agents will follow.
5. Use, maintain, and extend the **CC3 Harness**:
   - `harness/feature_list.json`
   - `harness/progress.md`
   - `harness/session_log.md`

You are **not** writing all production code yourself. You are:

- Decomposing the work.
- Specifying interfaces & contracts.
- Embedding **Security & Scalability** gates into the plan.
- Coordinating multi-agent work.

---

## 1. Documents You MUST Read First
[CHUNK:MASTER_INPUTS]

## REPO & SPEC REVIEW REQUIREMENT (CC2 → CC3 BOOTSTRAP)
[CHUNK:MASTER_REPO_REVIEW]

Before generating or modifying any CC3 code, all agents (Architect, Implementer, Reviewer) MUST perform a structured review of:

### 1. CC2 Repository (Legacy Laravel Application)

This is the **current production codebase**, located in the existing CC2 repo (/Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup).

Agents MUST:

- Read relevant directories, including (but not limited to):
  - `/app`
  - `/routes`
  - `/config`
  - `/database/migrations`
  - `/database/seeders`
  - `/resources`
  - Any scheduling-, bundle-, assessment-, workforce-, or SSPO-related services.
- Extract and internalize:
  - Domain models and relationships.
  - Business rules and invariants (e.g., spacing rules, travel constraints, provider rules).
  - RUG/bundle logic and intensity assumptions.
  - Assessment ingestion behavior (interRAI HC, CAP triggers, RUG).
  - Workforce and SSPO behaviors impacting scheduling.

**Do NOT assume** how the system behaves; **verify by reading the CC2 code and migrations**.

### 2. CC3 Design Documents (New Repo, No Code Yet)

CC3 starts in a **fresh repository** with no legacy code.  
Before writing any CC3 implementation, agents MUST read the design docs in the CC3 repo:

- `docs/CC_Backend_FastAPI_Transition_Spec.md`  [CHUNK:TRANSITION_SPEC]
- `docs/CC_Transition_CodingPrinciples.md`       [CHUNK:CODING_PRINCIPLES]
- `docs/CC21_AI_Bundle_Engine_Design.md`        [CHUNK:BUNDLE2_SPEC] (or equivalent)
- `docs/SPO_Scheduling_Functional_Spec.md`      [CHUNK:SCHED2_SPEC]
- Any PHI/Tokenization, Geospatial, Demo Seed design docs
- Any AI governance & harness docs

These design docs define the **target behavior** and architecture of CC3.

### 3. CROSS-ANALYSIS (CC2 Behavior → CC3 Design)

Before generating new CC3 code, agents MUST:

- Compare **CC2 behavior** with **CC3 design**:
  - Identify invariants from CC2 that must be preserved in CC3.
  - Identify any features present in CC2 but missing in CC3 specs.
  - Identify any new CC3 features that require careful migration or deprecation of CC2 behavior.
- Document findings in:
  - `docs/CC3_Migration_Diff.md`  (to be created and maintained by the Architect Agent)
  - `harness/session_log.md` with a summary of the review session.

### 4. AS CC3 CODE ACCUMULATES

Once CC3 code exists in the new repo:

- Agents MUST:
  - Read existing CC3 modules before modifying them.
  - Keep CC3 code aligned with CC3 specs and CC2 invariants (until Laravel is fully decommissioned).
- Never assume CC3 implementation matches the spec; always verify by reading the code.

### 5. CONTEXT-WINDOW SAFETY

Due to context limits, agents MUST:

- Load CC2 code and CC3 specs in **CHUNKS**, using:
  - File-level and section-level reads.
  - Chunk IDs like `[CHUNK:TRANSITION_SPEC]`, `[CHUNK:SCHED2_SPEC]`, `[CHUNK:BUNDLE2_SPEC]`.
- If uncertain about a model/route/invariant:
  - STOP and re-open the relevant CC2 file(s) and the CC3 spec chunk.
- Avoid generating CC3 code based on partial or out-of-date assumptions about CC2.
---

## 2. Global Objectives & Constraints
[CHUNK:MASTER_OBJECTIVES]

Your plan must accomplish the following:

1. **Complete backend migration** from Laravel/PHP → Python + FastAPI.
2. Treat **AI Bundle Engine 2.0** and **AI Scheduler 2.0** as **peer AI-centric cores**:
   - Bundle Engine: interRAI → PatientNeedsProfile → intensity matrices → bundle scenarios → explanations.
   - Scheduler: CareBundle/CarePlan → SchedulerState → AutoAssign → suggestions, scenarios → explanations & conflicts.
3. Enforce **PHI residency** and **tokenized AI boundary**:
   - PHI is stored and managed only in Canadian-resident PHI layer.
   - AI workloads in GCP Canada operate only on tokenized, de-identified features.
4. Implement **AI-Native Security & Compliance Layer**:
   - `/security-review` command & GitHub Action.
   - `.cursorrules` enforced.
   - `.cursorignore` hygiene.
   - No secrets or PHI leakage into AI.
5. Implement **Scalability & Performance Governance**:
   - `/scalability-review` command.
   - Ruff & TS ESLint type-aware rules.
   - Big-O red-teaming for heavy algorithms.
6. Use a **Harness v3.0** to track all CC3 features, progress, and sessions.
7. Deliver a **complete demo**:
   - `POST /api/v2/demo/reset` with named profiles.
   - End-to-end: assessments → bundles → schedules → AI suggestions and explanations → conflict/no-match.

---

## 3. AI-Native Security & Compliance Layer (You Must Enforce & Implement)
[CHUNK:MASTER_SECURITY]

You must treat security as a **first-class feature** and bake it into the plan.

### 3.1 Implement `/security-review` Command

You must:

1. Add a **Claude CLI command** file in `.claude/commands/security-review.md` that:
   - Scans given code/diffs for:
     - OWASP Top 10 issues.
     - SQL injection risks.
     - XSS risks.
     - Auth/session issues.
     - Hardcoded secrets.
   - Emits a summary plus suggested remedial changes.

2. Ensure this is part of the **developer workflow**:
   - Every feature branch: `/security-review` run locally before push.
   - You must include this instruction in the DevOps and contributor docs.

3. Integrate the GitHub Action:
   - Add `anthropics/claude-code-security-review` in CI for PRs.
   - Enforce:
     - High/Critical findings are **merge-blocking**.
   - Document how to handle false positives (with justification and minimal allowlisting).

### 3.2 `.cursorrules` Enforcement

You must:

- Ensure `.cursorrules` exists, as in `CC_Transition_CodingPrinciples.md`, and includes at least:

  - **Data privacy rules**:
    - No real patient data in code or comments.
    - Logging limited to IDs/masked values.
  - **Security patterns**:
    - No hardcoded secrets.
    - Parameterized queries or ORM only.
    - Auto-escaping & sanitization.
  - **Dependency management**:
    - Manual validation of new dependencies.
  - **Security testing**:
    - Red-team tests for auth and data access functions.

- In your tasks and scaffolding, **explicitly instruct Implementer agents** to adhere to `.cursorrules`.

### 3.3 `.cursorignore` & Context Hygiene

You must:

- Ensure `.cursorignore` excludes:
  - `.env`, `.env.*`
  - `*.csv`, `*.sql`
  - Production-like data dumps
  - Any file that might contain real PHI

And:

- Explicitly state that **only synthetic demo data** may be used in AI contexts.
- Never instruct agents to paste real interRAI or patient data into Cursor or Claude.

### 3.4 Supply Chain Verification

For any library you include in the migration plan:

- Instruct developers to:
  - Confirm existence & health on PyPI/npm.
  - Run `pip audit` / `npm audit` for CVEs.
  - Use standard libraries where feasible.

---

## 4. Scalability & Performance Governance (You Must Enforce & Implement)
[CHUNK:MASTER_SCALABILITY]

You must treat scalability as a **hard requirement**, especially for:

- InterRAI ingestion & scoring.
- Service Intensity Matrix evaluation.
- BundleScenarioEngine.
- SchedulerState building.
- AutoAssignEngine & SchedulingEngine.

### 4.1 Implement `/scalability-review` Command

You must:

1. Create `.claude/commands/scalability-review.md` with content similar to:

   - Review code for:
     - N+1 queries and missing indexes.
     - Big-O complexity worse than O(N log N) in hot paths.
     - Memory-unsafe aggregations (huge in-memory lists).
     - Blocking synchronous calls in async code (Python & TS).
   - If issues are found, propose refactored code + complexity explanation.

2. Integrate this into the workflow:
   - Any module involving loops over data, scheduling, or DB queries must be passed through `/scalability-review` prior to PR.

### 4.2 Linting & Tooling

You must ensure the plan includes:

- Python:
  - Ruff configured as linter/formatter.
  - Aggressive rules for performance & correctness.
- TypeScript:
  - `typescript-eslint` with type-aware rules enabled.
  - Async/Promise correctness rules.

### 4.3 Big-O "Red Teaming" Tasks

For heavy functions in:

- Bundle Engine (ScenarioEngine, intensity calculators).
- Scheduler (AutoAssignEngine, constraint checks).
- Any cross-patient computations.

You should add tasks like:

> “Perform Big-O analysis on function X; if complexity is worse than O(N log N) for realistic N (e.g., 100–1000 patients), refactor or justify.”

---

## 5. Harness v3.0 Requirements
[CHUNK:MASTER_HARNESS]

You must ensure CC3 uses a **harness** as described in Anthropic’s “Effective harnesses for long-running agents”.

### 5.1 CC3 Harness Files

You must:

- Create and maintain (if not already present):

  - `harness/feature_list.json` – [CHUNK:HARNESS_FEATURES]
    - Each feature: `id`, `title`, `domain`, `status`, `depends_on`, `notes`.
  - `harness/progress.md` – [CHUNK:HARNESS_PROGRESS]
    - Chronological log of major planning & implementation milestones.
  - `harness/session_log.md` – [CHUNK:HARNESS_SESSIONS]
    - Per-agent/per-session notes, decisions, and unresolved questions.

### 5.2 Harness Integration in Your Work

For each significant planning output you produce:

- Add or update entries in `feature_list.json` reflecting CC3 features:
  - E.g., `CC3-BUNDLE-ENGINE-V2`, `CC3-SCHEDULER-V2`, `CC3-PHI-BOUNDARY`, `CC3-DEMO-ENV`.
- Append to `progress.md`:
  - A summary of what you planned/decided in this session.
- If appropriate, instruct Implementer/Reviewer agents:
  - To log their work in `session_log.md`.

Your planning must be **traceable**.

---

## 6. Use of Existing Specs & Chunk IDs
[CHUNK:MASTER_CHUNKING]

You do **not** need to re-spec every detail inside this prompt. Instead, you must:

- Use **Chunk IDs** to load specific sections from existing docs into context when needed:
  - `[CHUNK:TRANSITION_SPEC]` – the overall FastAPI target design.
  - `[CHUNK:BUNDLE2_SPEC]` – domain details for Bundle Engine 2.0.
  - `[CHUNK:SCHED2_SPEC]` – domain details for Scheduler 2.0.
  - `[CHUNK:CODING_PRINCIPLES]` – coding + AI governance.
  - `[CHUNK:PHI_BOUNDARY]` – PHI residency & tokenization.
  - `[CHUNK:GIS_MAPS]` – geospatial & travel-time.
  - `[CHUNK:DEMO_SEED_SPEC]` – demo data generator.

When you need to plan for a module:

- Load its corresponding chunk(s) plus the security & scalability chunks:
  - e.g., BUNDLE + SECURITY + SCALABILITY.

---

## 7. High-Level Transition Planning Tasks
[CHUNK:MASTER_TASKS]

After reading the required docs, your **first responsibility** is to produce:

1. A **phase breakdown**:
   - Phase 0: Bootstrapping FastAPI project & PHI boundary.
   - Phase 1: Assessments & PatientNeedsProfile.
   - Phase 2: AI Bundle Engine 2.0.
   - Phase 3: AI Scheduler 2.0.
   - Phase 4: Workforce & SSPO.
   - Phase 5: Shared AI/LLM services (Vertex, logs).
   - Phase 6: Geospatial & travel.
   - Phase 7: Demo data & UI integration.
   - Phase 8: Cutover & Laravel decommission.

2. For each phase:
   - **Module contracts** (interfaces, DTOs).
   - **Tasks for Implementer agents**:
     - Backend tasks.
     - Frontend tasks.
     - DevOps tasks.
   - **Security tasks**:
     - `/security-review` invocation.
     - `.cursorrules` conformance.
   - **Scalability tasks**:
     - `/scalability-review` invocation.
     - Big-O analysis tasks.
   - **Harness tasks**:
     - Which features to mark `in_progress` / `done`.

3. A **mapping from old Laravel domains** to new FastAPI domains:
   - Scheduling, Bundles, Assessments, Workforce, SSPO, Auth, etc.

Your output should be a **Markdown implementation plan** saved as:

- `docs/CC3_Implementation_Plan.md`

and appropriate harness updates.

---

## 8. Multi-Agent & CI/CD Orchestration
[CHUNK:MASTER_MULTIAGENT]

You must assume a multi-agent workflow:

- **Architect Agent (you)**:
  - Designs and updates the plan.
  - Maintains the harness.
  - Defines contracts & tasks.

- **Implementer Agents**:
  - Backend (FastAPI).
  - Frontend (React/TS).
  - AI/LLM integration.
  - Demo generators.

- **Reviewer Agents**:
  - Code review vs spec.
  - Security review (in addition to `/security-review`).
  - Scalability/performance review (in addition to `/scalability-review`).

You should:

- Clearly delineate which tasks belong to which agent role.
- Ensure the plan is chunked into independent pieces for parallel execution.

---

## 9. Your Self-Check
[CHUNK:MASTER_SELFCHECK]

Before finalizing any planning output, ask:

- Does this align with:
  - `CC_Backend_FastAPI_Transition_Spec.md`?
  - `CC_Transition_CodingPrinciples.md` (including Security & Scalability sections)?
  - The PHI/AI boundary?
  - Harness v3.0 usage?
- Are `/security-review` and `/scalability-review` integrated into this phase’s tasks?
- Are the tasks structured for multi-agent execution?

Then:

- Update the harness files.
- Write or update `docs/CC3_Implementation_Plan.md`.
- Provide a concise summary of your decisions in your reply.

---

**End of CC3_MasterOrchestratorPrompt.md**  
[CHUNK:MASTER_END]

<!-- CHUNK_END: ExecutiveSummary -->

---

<!-- CHUNK_START: GlobalArchitecture -->

## Part 2 – Global Architecture & Domain Boundaries

### 2.1 Target System Diagram

```text
┌──────────────────────────────────────────┐
│              Frontend (React)           │
│  - Scheduler 2.0 (AI-first control room)│
│  - Bundle Builder 2.0 (AI care design)  │
│  - Patient Profile & Timeline           │
│  - Capacity & Workforce Views           │
│  - SSPO Marketplace                     │
│  - AI Console / Explanations            │
└──────────────────────────────────────────┘
                   │  (REST/JSON)
                   ▼
┌──────────────────────────────────────────┐
│             FastAPI Backend              │
│                                          │
│  Domains:                                │
│  - auth, users, orgs                     │
│  - patients                              │
│  - assessments (interRAI HC/CA/BMHS)     │
│  - bundles (AI Bundle Engine 2.0)        │
│  - scheduling (AI Scheduler 2.0)         │
│  - workforce & capacity                  │
│  - sspo (external partners)              │
│  - ai_orchestration (LLM/ML services)    │
│                                          │
│  Cross-cutting layers:                   │
│  - PHI boundary & tokenization           │
│  - geospatial/travel                     │
│  - demo data generator                   │
└──────────────────────────────────────────┘
        │                                 │
        ▼                                 ▼
┌──────────────────────┐      ┌──────────────────────────┐
│   PHI Data Layer     │      │  AI & External Services  │
│  (Canada-resident)   │      │  - Vertex AI (GCP CA)    │
│  - Patients (PHI)    │      │  - Google Maps APIs      │
│  - Staff PII         │      │  - Email/SMS providers   │
│  - Tokens            │      │  - Optional local ML     │
└──────────────────────┘      └──────────────────────────┘

2.2 Architectural Principles
	1.	AI-First Domains:
The Bundle Engine (care design) and Scheduler (care orchestration) are AI-centric:
	•	They use AI for explanations, scenario generation, ranking, and “what-if” analysis.
	•	They are assistive, not autonomous: humans always confirm changes.
	2.	Domain-Oriented Modules:
Each FastAPI module encapsulates a domain:
	•	assessments/, bundles/, scheduling/, workforce/, sspo/, ai_orchestration/, patients/, auth/.
	•	Business logic lives in services and engines, not controllers.
	3.	Metadata-Driven & Configurable:
	•	Service types, CAP rules, intensity matrices, scenario templates are all data-driven (JSON/YAML).
	•	The system can be tuned and extended without changing core code.
	4.	PHI Boundary & Cloud-Agnostic Data Layer:
	•	PHI is stored and managed inside a Canadian-resident PHI layer (ThinkOn/Azure/AWS Canada).
	•	The AI layer works on tokenized, de-identified features.
	•	Data access is abstracted so the PHI DB can move between cloud providers.
	5.	Incremental, Testable Evolution:
	•	The migration from Laravel to FastAPI is phased.
	•	Each domain is ported and validated with tests and demo scenarios.

2.3 FastAPI Project Layout (Target)

backend/
  app/
    core/
      config.py           # Pydantic settings
      security.py         # Auth/JWT, roles
      db_phi.py           # PHI DB session
      db_ai.py            # De-identified/AI DB session
      logging.py
      exceptions.py
    shared/
      dto/
      utils/
    auth/
      models.py
      schemas.py
      routes.py
      service.py
    orgs/
      models.py           # SPO, SSPO, hospitals
      schemas.py
      routes.py
      service.py
    patients/
      models_phi.py       # PHI-heavy fields
      models_features.py  # De-id features
      schemas.py
      routes_phi.py       # PHI-safe routes
      tokenization.py     # token service
    assessments/
      models_phi.py       # raw HC/CA/BMHS
      models_features.py  # derived interRAI features
      schemas.py
      routes.py
      ingestion.py        # iCODE → features
      derived.py          # RUG/CAPs/MAPLe, DMS, PSA, etc.
    bundles/
      models.py           # CareBundle, CarePlan, templates
      schemas.py
      routes.py
      service.py          # BundleEngine2.0
      intensity_matrix.py
      scenarios.py        # scenario generation
    scheduling/
      models.py           # ServiceAssignment, StaffAvailability
      schemas.py
      routes.py
      engine.py           # SchedulingEngine
      auto_assign.py      # AutoAssignEngine
      conflicts.py
      team_lanes.py
      state.py            # SchedulerState
      travel.py           # TravelTimeService
    workforce/
      models.py           # Staff, StaffRole, EmploymentType
      schemas.py
      routes.py
      service.py          # Capacity/FTE calc
    sspo/
      models.py           # SSPO orgs, service capabilities
      schemas.py
      routes.py
      service.py
    ai_orchestration/
      routes.py           # /api/v2/ai/*
      llm_explanation.py  # Vertex client & prompt builders
      bundle_explain.py
      scheduler_explain.py
      scenario_planning.py
      logging.py          # AI logs
    demo/
      generator.py
      staff.py
      patients.py
      assessments.py
      bundles.py
      scheduling.py
      sspo.py
      profiles.py
  main.py                 # FastAPI entrypoint
  alembic/                # migrations
  tests/


⸻


<!-- CHUNK_END: GlobalArchitecture -->



⸻


<!-- CHUNK_START: PHIBoundary -->


Part 3 – PHI Boundary, Data Residency & Multi-Cloud Design

3.1 PHI & AI Residency Requirements

We must:
	1.	Store & manage PHI in Canada:
	•	On ThinkOn or Azure Canada or AWS ca-central.
	2.	Run AI workloads (LLMs, ML) in Google Cloud Canada.
	3.	Ensure no raw PHI leaves Canadian sovereign control:
	•	Names, addresses, health card numbers, full DOB, and raw clinical text with PHI never leave PHI layer.
	4.	Make the data layer cloud-agnostic, so PHI DB can be moved to any Canadian provider in future.

3.2 PHI vs AI Layers

We design two logical DB layers:
	•	PHI DB (Canada-only):
	•	Patients, staff PII, detailed addresses, health card numbers, contacts.
	•	patient_tokens mapping table.
	•	AI Feature DB (de-identified, cloud-agnostic):
	•	De-identified patient_features.
	•	ai_suggestions, bundle_scenarios, schedule_scenarios, AI logs.

Key rule:
Only tokens and features cross into GCP/Vertex.

3.3 Tokenization & De-identification

TokenizationService (PHI side)
	•	Input: patient_id.
	•	Generates: patient_token (UUID or opaque ID).
	•	Stores in patient_tokens table:
	•	patient_id, patient_token, created_at, revoked_at.

Feature Extraction
From PHI + assessments, create a de-identified feature payload:
	•	Age band (e.g., 70–75), not exact DOB.
	•	Region code (TORONTO_CENTRAL, etc.), not full address.
	•	InterRAI scales:
	•	ADL, IADL, CPS, CHESS, DMS, PSA, etc.
	•	CAP triggers & RUG groups.
	•	Risk flags: falls, wandering, pressure ulcer, etc.
	•	Service needs: baseline intensities by category.
	•	Travel-related fields:
	•	Region, optionally fuzzed lat/lng.

Stored as:

patient_features
  patient_token (PK)
  features_json
  last_updated

This table is safe to use in GCP analytics/AI as it contains no direct PHI.

3.4 AI Calls & Vertex Integration

When Bundle Engine or Scheduler calls AI:
	•	They use patient_token and features only.
	•	They may also include:
	•	staff_token (staff pseudonyms)
	•	de-identified staff features (role, capacity, travel distance, continuity count)
	•	scenario metadata

Vertex calls are strictly fed with:
	•	De-identified JSON payloads.
	•	Possibly pre-computed embeddings (from de-identified text).

3.5 Reassembly & Presentation

When the frontend needs to display results:
	1.	FastAPI (in PHI-capable environment) maps:
	•	patient_token → patient_id via patient_tokens.
	2.	Retrieves PHI from PHI DB.
	3.	Joins AI results to PHI-enhanced models.
	4.	Enforces auth/org/role checks.
	5.	Returns PHI only to authorized clients.

The reassembly (token to PHI) never leaves Canadian PHI infrastructure.

3.6 Multi-Cloud PHI Strategy

To remain cloud-agnostic:
	•	Define repository interfaces for PHI domain objects:
	•	PatientRepository, StaffRepository, AssessmentRepository, TokenRepository.
	•	Provide implementations for:
	•	ThinkOn (Postgres/MySQL in ThinkOn)
	•	Azure Canada
	•	AWS ca-central
	•	Use neutral SQL & connection patterns; avoid provider-specific features.

⸻


<!-- CHUNK_END: PHIBoundary -->



⸻


<!-- CHUNK_START: BundleEngine -->


Part 4 – AI Bundle Engine 2.0 Spec

4.1 Role & Responsibilities

The AI Bundle Engine 2.0:
	•	Takes assessments + context → builds a PatientNeedsProfile.
	•	Uses CAP rules, RUG groups, interRAI scales, Service Intensity Matrix to generate baseline care requirements.
	•	Creates bundle scenarios (personas) tailored to:
	•	Recovery/Rehab,
	•	Safety/Stability,
	•	Tech-enabled care,
	•	Caregiver relief,
	•	Medical-intensive care, etc.
	•	Offers AI explanations and learns from outcomes.

4.2 PatientNeedsProfile

A Pydantic model representing normalized needs:

class PatientNeedsProfile(BaseModel):
    patient_token: str
    age_band: str              # "70-75" etc.
    region_code: str           # TORONTO_CENTRAL
    # Functional:
    adl_support_level: int
    iadl_support_level: int
    mobility_complexity: int
    # Cognitive/Behavior:
    cognitive_complexity: int
    behavioural_complexity: int
    distressed_mood_score: int
    has_wandering_risk: bool
    has_aggression_risk: bool
    # Clinical:
    chess_score: int
    pain_score: int
    falls_risk_level: int
    skin_integrity_risk: int
    continence_support: int
    health_instability: int
    active_conditions: list[str]
    # Rehab:
    rehab_score: int
    rehab_potential_score: int
    weekly_therapy_minutes: int
    # Caregiver:
    caregiver_availability_score: int
    caregiver_stress_level: int
    lives_alone: bool
    caregiver_requires_relief: bool
    # Tech:
    technology_readiness: int
    has_internet: bool
    suitable_for_rpm: bool
    # Classification:
    rug_group: str | None
    rug_category: str | None
    needs_cluster: str | None
    episode_type: str | None
    # Confidence:
    confidence_level: Literal['low','medium','high']
    missing_data_fields: list[str] | None

4.3 AssessmentIngestionService

Responsible for:
	•	Parsing HC/CA/BMHS assessment records.
	•	Computing interRAI-derived scales:
	•	ADL, IADL, CPS, CHESS, DMS, PSA, Pain scale, Rehab score, etc.
	•	Determining CAP triggers and needs clusters.

It uses:
	•	DecisionTreeEngine for interRAI algorithms (PSA, AUA, SUA, Rehab, etc.).
	•	CAPTriggerEngine to evaluate CAP DSL files (e.g., falls.yaml, pain.yaml).

4.4 Algorithm DSL (DecisionTreeEngine)

Algorithms are defined in JSON, e.g.:

{
  "name": "Rehabilitation Algorithm",
  "version": "1.0.0",
  "output_range": [1, 5],
  "tree": {
    "condition": "B2c == true",
    "true_branch": { "return": 1 },
    "false_branch": {
      "condition": "SRI == true",
      "true_branch": { "return": 2 },
      "false_branch": {
        "condition": "D4 >= 1",
        "true_branch": {
          "condition": "IADL_deficit_count >= 3",
          "true_branch": { "return": 5 },
          "false_branch": {
            "condition": "IADL_deficit_count >= 1",
            "true_branch": { "return": 4 },
            "false_branch": { "return": 3 }
          }
        },
        "false_branch": {
          "condition": "ADL_deficit_count >= 3",
          "true_branch": { "return": 3 },
          "false_branch": { "return": 2 }
        }
      }
    }
  },
  "computed_inputs": {
    "SRI": {
      "formula": "C1 == 0 && C2a == 0 && C2b == 0 && C2c == 0 && C2d == 0 && C2e == 0"
    }
  }
}

The DecisionTreeEngine evaluates these algorithms and produces scores that feed into PatientNeedsProfile.

4.5 CAP Trigger Engine

CAP rules stored in YAML, e.g.:

name: Falls CAP
version: "1.0.0"
category: clinical
applicable_instruments: [HC, CA]

triggers:
  - level: IMPROVE
    conditions:
      all:
        - field: has_recent_fall
          operator: "=="
          value: true
        - min_count: 2
          from:
            - field: mobility_complexity
              operator: ">="
              value: 2
            - field: pain_score
              operator: ">="
              value: 2
    service_recommendations:
      PT:
        priority: core
        frequency_multiplier: 1.5
      OT:
        priority: recommended
        focus: home_safety
    care_guidelines:
      - "Assess and modify environmental hazards"
      - "Implement balance training"

The CAP engine returns:
	•	Which CAPs are triggered.
	•	Their level (IMPROVE, PREVENT, etc.).
	•	Suggested service modifications.

4.6 Service Intensity Matrix

Matrix config in JSON:

{
  "psa_to_psw_hours": {
    "mappings": {
      "1": { "hours": 0, "confidence": "high" },
      "2": { "hours": 3, "confidence": "high" },
      "3": { "hours": 7, "confidence": "high" },
      "4": { "hours": 14, "confidence": "medium" },
      "5": { "hours": 21, "confidence": "medium" },
      "6": { "hours": 35, "confidence": "exploratory" }
    }
  },
  "rehab_to_therapy_visits": {
    "mappings": {
      "1": { "visits": 0 },
      "2": { "visits": 1 },
      "3": { "visits": 2 },
      "4": { "visits": 3 },
      "5": { "visits": 5 }
    }
  },
  "chess_to_nursing_visits": {
    "mappings": {
      "0": { "visits": 0 },
      "1": { "visits": 0.5 },
      "2": { "visits": 1 },
      "3": { "visits": 2 },
      "4": { "visits": 3 },
      "5": { "visits": 5 }
    }
  }
}

Used by BundleEngine to compute baseline intensities by category.

4.7 Bundle Scenario Generator

Given PatientNeedsProfile and baseline intensities, the BundleScenarioEngine:
	•	Determines applicable scenario axes (rehab, safety, tech, caregiver, medical, cognitive).
	•	Generates 3–5 scenarios:
	•	Each a BundleScenario struct with:
	•	scenario_id
	•	label (e.g., “Recovery-Focused”)
	•	primary_axis, optional secondary_axis
	•	services: list[BundleServiceLine] (type, freq, duration, provider)
	•	estimated_weekly_cost
	•	estimated_staff_hours
	•	meets_minimum_safety: bool
	•	trade_offs: list[str]
	•	Validates each scenario against minimum safety requirements.

4.8 LLM Explanation for Bundles

BundleExplanationService:
	•	Inputs:
	•	PatientNeedsProfile.toDeidentifiedArray()
	•	BundleScenario.toArray()
	•	Calls Vertex AI (or local Claude) with PII-safe prompt.
	•	Returns:
	•	short_explanation
	•	key_points
	•	confidence_label
	•	Logged in AI logs (ai_bundle_explanations table) keyed by patient_token and scenario_id.

4.9 Learning Loop Hooks
	•	Log:
	•	Which bundle scenario was selected.
	•	Outcome metrics (ADL change, satisfaction, missed-care reduction).
	•	Use this data for:
	•	Scenario ranking models (which scenario types work best for which profiles).
	•	Rule refinement proposals (e.g., adjusting intensity matrix).

⸻


<!-- CHUNK_END: BundleEngine -->



⸻


<!-- CHUNK_START: Scheduler -->


Part 5 – AI Scheduler 2.0 Spec

5.1 Role & Responsibilities

AI Scheduler 2.0:
	•	Transforms care bundle requirements into real-world schedules:
	•	Who, when, where, how often.
	•	Enforces:
	•	No double-bookings (staff/patient).
	•	Spacing and TFS rules.
	•	Travel feasibility.
	•	SPO/SSPO provider preferences and capacity constraints.
	•	Provides:
	•	AI suggestions for assignments (single, batched).
	•	Scenario-level proposals (e.g., travel-optimized week).
	•	Explanations and “no match” reasoning.

5.2 SchedulerState

Python-side:

class SchedulerState(BaseModel):
    timeframe: DateRange
    unscheduled_services: list[UnscheduledService]
    assignments: list[ServiceAssignment]
    staff: list[StaffSummary]
    patients: list[PatientSummary]
    suggestions: list[AssignmentSuggestion]
    conflicts: list[ConflictDTO]
    filters: SchedulerFilters
    ui_flags: SchedulerUIFlags

Used for:
	•	AI Overview tab.
	•	Schedule tab (calendar/list).
	•	Review tab (scenario proposals).
	•	Conflicts tab.

5.3 SchedulingEngine

Core domain service:

class SchedulingEngine:
    def validate_assignment(
        self,
        proposed: ProposedAssignment,
        staff_assignments: Sequence[ServiceAssignment],
        patient_assignments: Sequence[ServiceAssignment],
        staff_availability: StaffAvailability,
        travel_context: TravelContext
    ) -> list[ConstraintViolation]:
        ...

Constraint types:
	•	Staff overlap
	•	Patient overlap
	•	Min-gap constraints (e.g., PSW visits >= 120 min apart)
	•	Daily/weekly hour limits
	•	Travel feasibility (via TravelTimeService)
	•	Provider type/role eligibility
	•	TFS requirements

5.4 AutoAssignEngine

Generates assignment suggestions:

class AutoAssignEngine:
    def generate_suggestions(
        self,
        unscheduled_services: list[UnscheduledService],
        scheduler_state: SchedulerState
    ) -> list[AssignmentSuggestion]:
        ...

Uses:
	•	ServiceRoleMapping (which roles can deliver which ServiceTypes).
	•	Staff availability/remaining capacity.
	•	Continuity (prior staff-patient relationships).
	•	Travel time estimates.
	•	Workforce balance (avoid burnout).

Outputs:
	•	AssignmentSuggestion with:
	•	suggestion_id
	•	patient_token & service_token
	•	staff_token
	•	match_status ('strong'|'moderate'|'weak'|'none')
	•	score_components (dict)
	•	reason_codes (list[str])

5.5 Team Lanes & Views

Team lanes group staff for the calendar:
	•	Rule: Weighted by StaffRole first, fallback to service category when low count.
	•	High-population roles (PSW) → individual lanes per staff.
	•	Low-pop roles (PT, OT, SLP, SW, etc.) → combined “Therapy Team”, “Allied Health Team” lanes.

Team Lane metadata (config + dynamic computation):

class TeamLane(BaseModel):
    id: str
    label: str
    role_codes: list[str]
    staff_ids: list[int]

Used in the Schedule tab:
	•	Default view: Week schedule with staff lanes (grouped by TeamLane).
	•	Alternative view: Patient-centric “timeline” or list view.

5.6 Scheduler UI Interaction Model (Backend Contracts)

Although this spec is backend-focused, the Scheduler API must support the AI-first UI pattern:

Tabs:
	1.	AI Overview Tab (default)
	•	Endpoint: GET /api/v2/scheduling/state
	•	Returns high-level summaries and prioritized unscheduled lists.
	2.	Schedule Tab
	•	Endpoints:
	•	GET /api/v2/scheduling/week
	•	GET /api/v2/scheduling/assignments/list
	•	Supports:
	•	Staff/team lane filters.
	•	Appointment list view for historical and future.
	3.	Review Tab
	•	Endpoint: GET /api/v2/scheduling/suggestions/grouped
	•	Returns grouped suggestions (scenarios).
	4.	Conflicts Tab
	•	Endpoint: GET /api/v2/scheduling/conflicts
	•	Returns conflicts and no-match items.

Suggested Actions
	•	GET /api/v2/scheduling/suggestions
	•	Query parameters to limit by:
	•	patient, staff, service, timeframe, scenario_type.
	•	POST /api/v2/scheduling/suggestions/accept
	•	Accept one suggestion.
	•	POST /api/v2/scheduling/suggestions/accept-batch
	•	Accept all or grouped suggestions.
	•	Must confirm before applying:
	•	Provide summary & diff so user knows what they’re accepting.

5.7 LLM Explanations (Scheduler)

SchedulerExplanationService:
	•	explain_suggestion(suggestion, profile)
	•	explain_no_match(no_match_context)
	•	explain_scenario(scenario)

Returns:
	•	Short explanations and key factors:
	•	“Qualified RN, 12h remaining this week, previous visits with this patient, shortest travel route.”

5.8 No-Match Handling

Conflicts tab & “no match” messages:
	•	Explain why:
	•	No eligible staff (role mismatch).
	•	No one with sufficient availability.
	•	Travel infeasibility.
	•	Provider-type or skill constraints.
	•	Provide suggestions:
	•	Change time window.
	•	Consider SSPO.
	•	Override constraints with manual assignment (if allowed).

⸻


<!-- CHUNK_END: Scheduler -->



⸻


<!-- CHUNK_START: SharedAI -->


Part 6 – Shared AI Services (LLM, Logging, Scenario Planning)

6.1 ai_orchestration Module

Responsibilities:
	•	Centralize LLM interactions.
	•	Provide bundle & scheduler explanation endpoints.
	•	Log all AI calls and decisions.

Key components:
	•	llm_explanation.py – core Vertex client / LLM client.
	•	bundle_explain.py – bundle-specific explanation builder.
	•	scheduler_explain.py – scheduling-specific explanation builder.
	•	scenario_planning.py – scenario-level ranking and summary.

6.2 Vertex AI Config & Prompt Boundary

Configuration (env):
	•	VERTEX_PROJECT_ID
	•	VERTEX_LOCATION (Canadian region)
	•	VERTEX_MODEL_NAME (e.g., gemini-1.5-pro or similar)
	•	Rate limit & timeout settings.

Prompts:
	•	Must be structured JSON with:
	•	De-identified patient & staff features.
	•	Reason factors.
	•	Scenario metadata.

Prompt design ensures:
	•	No direct PHI.
	•	Clear tasks:
	•	“Explain why this staff is recommended.”
	•	“Summarize key reasons this scenario is preferred.”

6.3 AI Logging Schema

Tables:
	•	ai_explanations:
	•	id, patient_token, context_type (bundle|schedule|no_match), input_hash, output, model, created_at.
	•	ai_suggestions:
	•	suggestion_id, patient_token, staff_token, context_json, decision (accepted|rejected|ignored), created_at.
	•	ai_scenarios:
	•	Scenario-level summaries and metrics.

These logs are de-identified; tokens link back to PHI only inside PHI DB.

⸻


<!-- CHUNK_END: SharedAI -->



⸻


<!-- CHUNK_START: Geospatial -->


Part 7 – Geospatial & Travel-Time Layer

7.1 TravelTimeService

Abstraction:

class TravelTimeService:
    def estimate(
        self,
        origin: Coordinates,
        destination: Coordinates,
        departure_time: datetime
    ) -> TravelEstimate:
        ...

Implementation:
	•	GoogleMapsTravelTimeService using Distance Matrix API.
	•	Demo mode: FakeTravelTimeService (deterministic).

7.2 Coordinates and Geocoding

Data:
	•	Patients: lat/lng for home addresses (fuzzed if necessary).
	•	Staff: optional home base or hub location.
	•	SSPO: service region centroids.

Geocoding:
	•	GeocodingService retrieves lat/lng for addresses.
	•	Caches results in geo_points table:
	•	model_type, model_id, lat, lng.

7.3 Use Cases
	•	SchedulingEngine uses TravelTimeService:
	•	To reject infeasible assignments.
	•	Scheduler maps:
	•	Visualize staff routes and unscheduled visits.
	•	AI scoring:
	•	Travel is a component in suggestion score.

⸻


<!-- CHUNK_END: Geospatial -->



⸻


<!-- CHUNK_START: WorkforceSSPOAuth -->


Part 8 – Workforce, SSPO, Auth & Organizational Domains

8.1 Workforce & Capacity

Models:
	•	Staff:
	•	Role, employment type, region, status (active|on_leave|locked), skills.
	•	StaffRole:
	•	code, name, category, team_lane_hint.
	•	EmploymentType:
	•	Weekly max hours, FTE equivalence, direct vs SSPO staff.

Services:
	•	Capacity calculations (weekly hours scheduled vs capacity).
	•	FTE ratio and workload balance metrics.

8.2 SSPO Domain

Models:
	•	SSPOOrganization:
	•	Name, specialty (e.g., remote monitoring, dementia care).
	•	SSPOServiceCapability:
	•	Which ServiceTypes they provide, capacities, constraints.

Integration:
	•	Bundle Engine:
	•	For SSPO-preferred ServiceTypes, set provider_type='SSPO'.
	•	Scheduler:
	•	AutoAssignEngine considers both SPO and SSPO staff pools.

8.3 Auth & Orgs

Roles:
	•	System Admin
	•	SPO Admin
	•	SPO Coordinator
	•	Field Staff (SPO)
	•	SSPO Staff
	•	Hospital User

Org structure:
	•	Organization for SPO/SSPO/hospital.
	•	Tenancy enforced via org-based scoping.

⸻


<!-- CHUNK_END: WorkforceSSPOAuth -->



⸻


<!-- CHUNK_START: DemoData -->


Part 9 – Demo Data & Synthetic Dataset Design

We will replace Laravel seeders with a Python demo generator.

9.1 Demo Profiles

Profiles:
	•	small
	•	standard
	•	complex
	•	crisis

Each profile defines:
	•	Number & mix of staff by role.
	•	Number & types of patients.
	•	Regions.
	•	Assessment mix (HC, CA, BMHS).
	•	Bundle intensity & RUG distribution.
	•	Scheduling density & conflict frequency.

9.2 Demo Generators (app/demo)

Modules:
	•	staff.py – Staff & availability.
	•	patients.py – Patients & addresses.
	•	assessments.py – HC + CA + BMHS.
	•	bundles.py – Bundles & care plans via Bundle Engine 2.0.
	•	scheduling.py – Schedules & unscheduled care + conflict injection.
	•	sspo.py – SSPO orgs & staff.
	•	profiles.py – Profile definitions.

9.3 Scenarios to Include
	•	Realistic workforce:
	•	FT/PT/Casual + SSPO staff.
	•	On-leave & locked staff.
	•	Realistic assessments:
	•	Accurate RUG distribution.
	•	CAP triggers.
	•	Realistic schedules:
	•	3 past weeks fully scheduled.
	•	Current week partial.
	•	2 future weeks partial.
	•	Explicit violation cases:
	•	Hard no-match due to:
	•	Travel.
	•	Capacity.
	•	Skills.
	•	Provider-type.
	•	TFS breaches.
	•	Burnout scenarios (overloaded staff).

9.4 Demo API

Endpoints:
	•	GET /api/v2/demo/profiles
	•	POST /api/v2/demo/reset {profile: "standard"}

Behavior:
	•	Resets demo DB (or demo subset) to a known state.
	•	Runs appropriate demo generators in order.

⸻


<!-- CHUNK_END: DemoData -->



⸻


<!-- CHUNK_START: Migration -->


Part 10 – Migration Strategy: Laravel → FastAPI

10.1 Overall Philosophy
	•	Full rewrite of backend in Python/FastAPI.
	•	No code-level reuse from Laravel; only domain knowledge and data reused.
	•	Avoid Frankenstein integration: no mixing of PHP backend and Python backend in production for core flows.

10.2 Phases

Phase 0 – Documentation & Alignment (In Progress)
	•	CC_Backend_Current_State.md (already produced).
	•	This Transition Spec.

Phase 1 – FastAPI Skeleton & Core Domains
	•	Create FastAPI project with:
	•	core, auth, orgs, patients, assessments, bundles, scheduling, workforce, sspo, ai_orchestration, demo.
	•	Setup PHI DB & AI Feature DB connections.

Phase 2 – Assessments & PatientNeedsProfile
	•	Port interRAI HC ingestion logic (no code copy; spec-based).
	•	Implement CA/BMHS minimal ingestion for demo.
	•	Implement DecisionTreeEngine & CAPTriggerEngine.

Phase 3 – Bundle Engine 2.0
	•	Implement Service Intensity Matrix & BundleScenarioEngine.
	•	Expose API for generating scenarios per patient.
	•	Integrate LLM explanations.

Phase 4 – Scheduler 2.0
	•	Implement SchedulingEngine & AutoAssignEngine.
	•	Implement SchedulerState & scheduling APIs.
	•	Integrate travel service & conflicts detection.
	•	Integrate LLM explanations.

Phase 5 – Workforce, SSPO, Auth
	•	Implement staff, roles, employment types.
	•	Implement SSPO orgs & capabilities.
	•	Implement auth & org scoping.

Phase 6 – Demo Data & UI Integration
	•	Implement demo generators and /demo endpoints.
	•	Wire new backend into existing front-end (React) surfaces:
	•	Scheduler 2.0
	•	Bundle Builder 2.0
	•	Patient profile
	•	AI Console.

Phase 7 – Hardening & Cutover
	•	Robust testing, including:
	•	Unit tests.
	•	Integration tests.
	•	Demo scenarios for each profile.
	•	Cutover:
	•	Replace Laravel with FastAPI in staging.
	•	Then in production/demo.

10.3 Squarespace Domain & Deployment
	•	Deploy FastAPI backend to Canadian cloud (e.g., Cloud Run or Azure App Service/AKS).
	•	Configure Squarespace domain to point:
	•	Frontend hosting (if SPA is statically served) or
	•	Proxy to backend if needed.

⸻


<!-- CHUNK_END: Migration -->



⸻


<!-- CHUNK_START: Appendix -->


Appendix – Chunking & Usage Guidelines for Claude/Cursor

A.1 Chunk Markers

This spec includes markers:
	•	<!-- CHUNK_START: SectionName -->
	•	<!-- CHUNK_END: SectionName -->

Use these to extract specific sections for prompts:
	•	When working on Bundle Engine: load BundleEngine chunk.
	•	When working on Scheduler: load Scheduler chunk.
	•	When working on PHI boundary: load PHIBoundary chunk.

A.2 Prompting Patterns

For large tasks:
	•	Provide:
	•	The relevant section chunk.
	•	Any necessary cross-section info (e.g., PatientNeedsProfile used by both bundles and scheduler).
	•	Keep prompts focused:
	•	“Implement the BundleScenarioEngine according to the spec in this chunk.”
	•	Run tests and feed errors back into the AI.

For multi-agent:
	•	Architect agent:
	•	Reads entire spec in smaller chunks sequentially.
	•	Produces task breakdown & initial scaffolding.
	•	Implementer agents:
	•	Read only the portions relevant to their module.
	•	Reviewer agent:
	•	Reads spec + generated code and flags misalignments.

A.3 Object Model Gap Analysis (To Be Done in Code Phase)

This spec describes ideal target models. During implementation, agents should:
	•	Inspect actual models (in code).
	•	Compare against spec.
	•	Flag any missing fields or divergences.
	•	Update either the code or spec (after human review) to keep them in sync.

⸻

End of Transition Spec

<!-- CHUNK_END: Appendix -->



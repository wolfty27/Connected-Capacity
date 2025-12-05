# Connected Capacity Backend - Current State Architecture

**Version:** 1.0  
**Generated:** December 4, 2025  
**Purpose:** Foundation for Laravel → FastAPI Transition Spec

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [High-Level Architecture Overview](#2-high-level-architecture-overview)
3. [Module-by-Module Breakdown](#3-module-by-module-breakdown)
4. [Routes & API Surface Summary](#4-routes--api-surface-summary)
5. [Database Schema Summary](#5-database-schema-summary)
6. [Laravel Coupling & Risk Analysis](#6-laravel-coupling--risk-analysis)
7. [Object Model & Field Gap Analysis](#7-object-model--field-gap-analysis)

---

## 1. Executive Summary

### 1.1 Key Observations for Laravel → FastAPI Migration

Connected Capacity 2.1 is a Laravel 11 + React/Vite healthcare scheduling and care management platform built for Ontario Health atHome (OHaH) Service Provider Organizations (SPOs). The system has evolved significantly from a simple booking system to a sophisticated care orchestration platform with:

1. **AI-First Scheduling Engine (Scheduler 2.0)** - Vertex AI/Gemini integration for auto-assignment suggestions with learning loop
2. **AI-Assisted Bundle Engine** - InterRAI assessment-driven care bundle generation with scenario planning
3. **SSPO Marketplace** - Subcontracted Service Provider Organization management
4. **OHaH Compliance Dashboards** - SLA tracking, TFS (Time-to-First-Service), QIN management

### 1.2 Migration Complexity Assessment

| Module | Clean vs Tangled | Migration Difficulty | Priority |
|--------|------------------|---------------------|----------|
| **Bundle Engine** | ✅ Clean | Low | First candidate |
| **InterRAI/Assessment** | ✅ Clean | Low-Medium | Second candidate |
| **Scheduling Engine** | ⚠️ Mixed | Medium | Third candidate |
| **Workforce/Capacity** | ⚠️ Mixed | Medium | Fourth candidate |
| **Authentication/Tenancy** | ❌ Tangled | High | Refactor first |
| **SSPO Marketplace** | ✅ Clean | Low | Fifth candidate |

### 1.3 Critical Findings

**Clean Modules (Good Migration Candidates):**
- **Bundle Engine** (`app/Services/BundleEngine/`) - Well-structured with clear interfaces, DTOs, and declarative JSON/YAML configs
- **Algorithm DSL** (`config/bundle_engine/`) - Data-driven clinical logic in JSON/YAML files (easy to port)
- **LLM Integration** (`app/Services/Llm/`) - Clean abstraction with interface contracts

**Tangled Areas (Refactor Before Migration):**
- **User Model** (`app/Models/User.php`) - God class with mixed staff/patient/admin concerns
- **Controllers** - Some controllers contain business logic (e.g., `SpoDashboardController`)
- **Eloquent Magic** - Heavy use of relationship lazy-loading, model events, and global scopes

### 1.4 Recommended Migration Strategy

1. **Phase 1: Extract Clean Services** - Bundle Engine, Algorithm DSL, LLM integration
2. **Phase 2: Build Parallel API** - Run FastAPI alongside Laravel for new endpoints
3. **Phase 3: Data Layer Strangler** - Gradually move database access to FastAPI
4. **Phase 4: Frontend Migration** - React app remains, swap API base URL

---

## 2. High-Level Architecture Overview

### 2.1 System Architecture Diagram

```
┌────────────────────────────────────────────────────────────────────────────────┐
│                           CONNECTED CAPACITY 2.1                                │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                         REACT/VITE FRONTEND                              │   │
│  │  • SPA with React Router                                                 │   │
│  │  • Tailwind CSS styling                                                  │   │
│  │  • Custom hooks (useBundleEngine, useAutoAssign, useSchedulerData)       │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │ HTTP (JSON)                             │
│                                       ▼                                         │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                      LARAVEL 11 BACKEND                                  │   │
│  │                                                                          │   │
│  │  ┌──────────────────────────────────────────────────────────────────┐   │   │
│  │  │                     API CONTROLLERS (/api/v2/*)                   │   │   │
│  │  │  • BundleEngineController     • SchedulingController              │   │   │
│  │  │  • AutoAssignController       • WorkforceController               │   │   │
│  │  │  • InterraiController         • SpoDashboardController            │   │   │
│  │  │  • SspoMarketplaceController  • PatientQueueController            │   │   │
│  │  └──────────────────────────────────────────────────────────────────┘   │   │
│  │                                       │                                  │   │
│  │                                       ▼                                  │   │
│  │  ┌──────────────────────────────────────────────────────────────────┐   │   │
│  │  │                      DOMAIN SERVICES                              │   │   │
│  │  │                                                                   │   │   │
│  │  │  ┌─────────────────┐  ┌─────────────────┐  ┌────────────────┐   │   │   │
│  │  │  │  Bundle Engine  │  │   Scheduling    │  │   Assessment   │   │   │   │
│  │  │  │                 │  │     Engine      │  │     System     │   │   │   │
│  │  │  │ • ScenarioGen   │  │ • AutoAssign    │  │ • InterRAI HC  │   │   │   │
│  │  │  │ • PatientNeeds  │  │ • StaffScoring  │  │ • RUG Class.   │   │   │   │
│  │  │  │ • CAP Triggers  │  │ • Continuity    │  │ • CAP Triggers │   │   │   │
│  │  │  └─────────────────┘  └─────────────────┘  └────────────────┘   │   │   │
│  │  │                                                                   │   │   │
│  │  │  ┌─────────────────┐  ┌─────────────────┐  ┌────────────────┐   │   │   │
│  │  │  │    Workforce    │  │      SSPO       │  │     CareOps    │   │   │   │
│  │  │  │    Capacity     │  │   Marketplace   │  │    Metrics     │   │   │   │
│  │  │  │                 │  │                 │  │                │   │   │   │
│  │  │  │ • FTE Compliance│  │ • Capabilities  │  │ • TFS/Missed   │   │   │   │
│  │  │  │ • HHR Complement│  │ • Performance   │  │ • QIN/SLA      │   │   │   │
│  │  │  └─────────────────┘  └─────────────────┘  └────────────────┘   │   │   │
│  │  │                                                                   │   │   │
│  │  │  ┌─────────────────────────────────────────────────────────────┐ │   │   │
│  │  │  │                    LLM INTEGRATION                          │ │   │   │
│  │  │  │  • VertexAiClient (Gemini 1.5 Pro)                          │ │   │   │
│  │  │  │  • PromptBuilder (PII masking)                              │ │   │   │
│  │  │  │  • RulesBasedExplanationProvider (fallback)                 │ │   │   │
│  │  │  └─────────────────────────────────────────────────────────────┘ │   │   │
│  │  └──────────────────────────────────────────────────────────────────┘   │   │
│  │                                       │                                  │   │
│  │                                       ▼                                  │   │
│  │  ┌──────────────────────────────────────────────────────────────────┐   │   │
│  │  │                     ELOQUENT MODELS                               │   │   │
│  │  │  Patient | User | ServiceAssignment | InterraiAssessment         │   │   │
│  │  │  CarePlan | CareBundle | ServiceType | StaffRole | StaffAvailability│   │
│  │  └──────────────────────────────────────────────────────────────────┘   │   │
│  │                                       │                                  │   │
│  │                                       ▼                                  │   │
│  │  ┌──────────────────────────────────────────────────────────────────┐   │   │
│  │  │                     SQLite DATABASE                               │   │   │
│  │  │  (118 migrations, ~60 active tables)                              │   │   │
│  │  └──────────────────────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                      EXTERNAL INTEGRATIONS                               │   │
│  │                                                                          │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌────────────┐   │   │
│  │  │  Vertex AI   │  │  IAR (Mock)  │  │    CHRIS     │  │    HPG     │   │   │
│  │  │   Gemini     │  │  Integration │  │   (Future)   │  │  (Future)  │   │   │
│  │  └──────────────┘  └──────────────┘  └──────────────┘  └────────────┘   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Domain Modules

| Module | Primary Responsibility | Key Services | Migration Priority |
|--------|----------------------|--------------|-------------------|
| **Scheduling** | Staff-to-service matching, calendar management | `AutoAssignEngine`, `SchedulingEngine`, `StaffScoringService` | 3 |
| **Bundle Engine** | Care bundle scenario generation from assessments | `ScenarioGenerator`, `AssessmentIngestionService`, `CAPTriggerEngine` | 1 |
| **Assessments** | InterRAI HC/CA/BMHS data management, RUG classification | `InterraiService`, `RUGClassificationService`, `InterraiSummaryService` | 2 |
| **Workforce** | Staff capacity, FTE compliance, availability | `WorkforceCapacityService`, `FteComplianceService` | 4 |
| **SSPO** | Subcontractor management, marketplace | `SspoMarketplaceService`, `SspoPerformanceService` | 5 |
| **CareOps** | SLA compliance, jeopardy board, metrics | `MissedCareService`, `TfsMetricsService`, `QinService` | 6 |
| **LLM/AI** | Vertex AI integration, explanations | `VertexAiClient`, `LlmExplanationService`, `PromptBuilder` | 1 |
| **Auth/Tenancy** | Multi-org authentication, role-based access | Built into Laravel Sanctum, `User` model | Last |

### 2.3 Technology Stack

| Layer | Technology | Version | Migration Notes |
|-------|-----------|---------|-----------------|
| **Backend Framework** | Laravel | 11.x | → FastAPI |
| **Frontend** | React + Vite | 18.x + 5.x | Remains (swap API) |
| **Database** | SQLite (dev) | - | → PostgreSQL recommended |
| **ORM** | Eloquent | - | → SQLAlchemy/Pydantic |
| **API Auth** | Laravel Sanctum | - | → JWT/OAuth2 |
| **LLM** | Google Vertex AI | Gemini 1.5 Pro | → Same (Python SDK) |
| **Queues** | Laravel Queues | - | → Celery/RQ |
| **Styling** | Tailwind CSS | 3.x | Remains |

---

## 3. Module-by-Module Breakdown

### 3.1 Scheduling Module

**Purpose:** AI-first staff scheduling with team lanes, conflict detection, and auto-assignment suggestions.

#### 3.1.1 Key Models

| Model | Table | Key Fields | Relationships |
|-------|-------|------------|---------------|
| `ServiceAssignment` | `service_assignments` | `patient_id`, `service_type_id`, `assigned_user_id`, `scheduled_start/end`, `status`, `verification_status` | → Patient, ServiceType, User, CarePlan |
| `StaffAvailability` | `staff_availabilities` | `user_id`, `day_of_week`, `start_time`, `end_time`, `effective_from/until` | → User |
| `StaffUnavailability` | `staff_unavailabilities` | `user_id`, `start_datetime`, `end_datetime`, `status`, `type` | → User |
| `AiSuggestionLog` | `ai_suggestion_logs` | `patient_id`, `service_type_id`, `suggested_user_id`, `match_status`, `confidence_score`, `outcome` | → Patient, ServiceType, User |
| `StaffRole` | `staff_roles` | `code`, `name`, `category`, `team_lane_group`, `team_lane_display_order` | ← Users |

#### 3.1.2 Key Services

| Service | Location | Responsibility |
|---------|----------|----------------|
| `AutoAssignEngine` | `app/Services/Scheduling/AutoAssignEngine.php` | Generates AI assignment suggestions, manages learning loop |
| `SchedulingEngine` | `app/Services/Scheduling/SchedulingEngine.php` | Validates constraints (non-concurrency, spacing rules, capacity) |
| `StaffScoringService` | `app/Services/Scheduling/StaffScoringService.php` | Scores staff for service matching (skills, continuity, travel) |
| `ContinuityService` | `app/Services/Scheduling/ContinuityService.php` | Tracks historical staff-patient assignments for continuity scoring |
| `CareBundleAssignmentPlanner` | `app/Services/Scheduling/CareBundleAssignmentPlanner.php` | Computes unscheduled care requirements from care plans |

#### 3.1.3 Key Controllers & Routes

| Route | Controller | Method | Purpose |
|-------|-----------|--------|---------|
| `GET /v2/scheduling/grid` | `SchedulingController` | `grid` | Staff + assignments for week |
| `GET /v2/scheduling/requirements` | `SchedulingController` | `requirements` | Unscheduled care items |
| `POST /v2/scheduling/assignments` | `SchedulingController` | `createAssignment` | Create assignment |
| `GET /v2/scheduling/suggestions` | `AutoAssignController` | `suggestions` | AI suggestions |
| `POST /v2/scheduling/suggestions/accept` | `AutoAssignController` | `accept` | Accept suggestion |
| `GET /v2/scheduling/suggestions/weekly-summary` | `AutoAssignController` | `weeklySummary` | AI weekly briefing |

#### 3.1.4 Core Invariants/Rules

| Rule | Enforcement | Location |
|------|-------------|----------|
| **Patient Non-Concurrency** | DB + Application | `SchedulingEngine::patientHasOverlap()` |
| **PSW Spacing** | Application | `SchedulingEngine` (120 min default) |
| **Staff Availability** | Application | `StaffAvailability` scopes |
| **Skill Requirements** | Application | `StaffScoringService::hasRequiredSkills()` |
| **Capacity Limits** | Application | `WorkforceCapacityService` |

#### 3.1.5 Laravel-Specific Patterns

- **Eloquent Scopes:** Heavy use on `ServiceAssignment` (`scopeThisWeek`, `scopeForStaff`, `scopeActive`)
- **Model Events:** None significant
- **Facades Used:** `DB`, `Auth`, `Cache`, `Log`
- **Risk Level:** Medium - Clean service abstractions but relies on Eloquent query builder

---

### 3.2 Bundle Engine Module

**Purpose:** Assessment-driven care bundle scenario generation with patient-experience framing.

#### 3.2.1 Key Models

| Model | Table | Key Fields | Relationships |
|-------|-------|------------|---------------|
| `CareBundle` | `care_bundles` | `name`, `code`, `description`, `active` | → ServiceTypes (M:N) |
| `CareBundleTemplate` | `care_bundle_templates` | `code`, `rug_group`, `rug_category`, `funding_stream`, `weekly_cap_cents` | → CareBundleTemplateService |
| `CarePlan` | `care_plans` | `patient_id`, `care_bundle_id`, `status`, `scenario_metadata`, `scenario_axis` | → Patient, CareBundle, ServiceAssignments |
| `BundleConfigurationRule` | `bundle_configuration_rules` | `trigger_payload`, `action_payload`, `sort_order` | - |

#### 3.2.2 Key Services

| Service | Location | Responsibility |
|---------|----------|----------------|
| `ScenarioGenerator` | `app/Services/BundleEngine/ScenarioGenerator.php` | Generates 3-5 bundle scenarios per patient |
| `AssessmentIngestionService` | `app/Services/BundleEngine/AssessmentIngestionService.php` | Builds `PatientNeedsProfile` from assessments |
| `ScenarioAxisSelector` | `app/Services/BundleEngine/ScenarioAxisSelector.php` | Determines applicable axes (recovery, safety, tech, caregiver) |
| `CostAnnotationService` | `app/Services/BundleEngine/CostAnnotationService.php` | Annotates scenarios with cost (reference, not constraint) |
| `DecisionTreeEngine` | `app/Services/BundleEngine/Engines/DecisionTreeEngine.php` | Evaluates JSON algorithm definitions |
| `CAPTriggerEngine` | `app/Services/BundleEngine/Engines/CAPTriggerEngine.php` | Evaluates YAML CAP trigger definitions |
| `ServiceIntensityResolver` | `app/Services/BundleEngine/Engines/ServiceIntensityResolver.php` | Maps algorithm scores → service hours/visits |

#### 3.2.3 Key DTOs

| DTO | Location | Purpose |
|-----|----------|---------|
| `PatientNeedsProfile` | `app/Services/BundleEngine/DTOs/PatientNeedsProfile.php` | Assessment-agnostic patient needs representation |
| `ScenarioBundleDTO` | `app/Services/BundleEngine/DTOs/ScenarioBundleDTO.php` | Bundle scenario with patient-experience framing |
| `ScenarioServiceLine` | `app/Services/BundleEngine/DTOs/ScenarioServiceLine.php` | Individual service within scenario |

#### 3.2.4 Configuration Files (Algorithm DSL)

| File | Location | Purpose |
|------|----------|---------|
| `self_reliance_index.json` | `config/bundle_engine/algorithms/` | SRI algorithm for CA |
| `rehabilitation.json` | `config/bundle_engine/algorithms/` | Rehab potential scoring |
| `personal_support.json` | `config/bundle_engine/algorithms/` | PSW hours determination |
| `falls.yaml` | `config/bundle_engine/cap_triggers/clinical/` | Falls CAP trigger |
| `service_intensity_matrix.json` | `config/bundle_engine/` | Algorithm → service hours mapping |
| `scenario_templates.json` | `config/bundle_engine/` | Axis-based service mix templates |

#### 3.2.5 Key Controllers & Routes

| Route | Controller | Method | Purpose |
|-------|-----------|--------|---------|
| `GET /v2/bundle-engine/profile/{patient}` | `BundleEngineController` | `getProfile` | Patient needs profile |
| `GET /v2/bundle-engine/scenarios/{patient}` | `BundleEngineController` | `getScenarios` | Generate scenarios |
| `GET /v2/bundle-engine/axes/{patient}` | `BundleEngineController` | `getAxes` | Applicable scenario axes |
| `POST /v2/bundle-engine/explain` | `BundleEngineController` | `explainScenario` | AI explanation |

#### 3.2.6 Laravel-Specific Patterns

- **Service Container:** `BundleEngineServiceProvider` registers interfaces → implementations
- **Config Files:** JSON/YAML configs loaded via Laravel's `config()` helper
- **DTOs:** Pure PHP classes with constructors (no Eloquent)
- **Risk Level:** Low - Clean architecture, DTOs, interfaces

---

### 3.3 Assessments / InterRAI Module

**Purpose:** Store and process InterRAI HC/CA/BMHS assessments, compute RUG classifications.

#### 3.3.1 Key Models

| Model | Table | Key Fields | Relationships |
|-------|-------|------------|---------------|
| `InterraiAssessment` | `interrai_assessments` | `patient_id`, `assessment_type`, `assessment_date`, `maple_score`, `adl_hierarchy`, `cps`, `chess_score`, `raw_items` | → Patient, RUGClassification |
| `RUGClassification` | `rug_classifications` | `patient_id`, `assessment_id`, `rug_group`, `rug_category`, `adl_sum`, `flags` | → Patient, InterraiAssessment |
| `ReassessmentTrigger` | `reassessment_triggers` | `patient_id`, `trigger_type`, `trigger_reason`, `status` | → Patient |
| `InterraiDocument` | `interrai_documents` | `assessment_id`, `document_type`, `file_path` | → InterraiAssessment |

#### 3.3.2 Key Services

| Service | Location | Responsibility |
|---------|----------|----------------|
| `RUGClassificationService` | `app/Services/RUGClassificationService.php` | CIHI RUG-III/HC algorithm implementation |
| `InterraiService` | `app/Services/InterraiService.php` | Assessment CRUD, staleness detection |
| `InterraiSummaryService` | `app/Services/InterraiSummaryService.php` | Narrative summary generation, clinical flags |
| `InterraiScoreCalculator` | `app/Services/InterraiScoreCalculator.php` | CPS, ADL, CHESS score calculations |

#### 3.3.3 Assessment Mappers

| Mapper | Location | Purpose |
|--------|----------|---------|
| `HcAssessmentMapper` | `app/Services/BundleEngine/Mappers/HcAssessmentMapper.php` | HC assessment → PatientNeedsProfile |
| `CaAssessmentMapper` | `app/Services/BundleEngine/Mappers/CaAssessmentMapper.php` | CA assessment → PatientNeedsProfile |
| `BmhsAssessmentMapper` | `app/Services/BundleEngine/Mappers/BmhsAssessmentMapper.php` | BMHS augmentation |

#### 3.3.4 Key Constants

```php
// InterraiAssessment constants
public const TYPE_HC = 'hc';          // Home Care
public const TYPE_CHA = 'cha';        // Contact Assessment
public const TYPE_CONTACT = 'contact';
public const IAR_PENDING = 'pending';
public const IAR_UPLOADED = 'uploaded';
public const STALENESS_MONTHS = 3;    // OHaH requires reassessment if >3 months
```

#### 3.3.5 Laravel-Specific Patterns

- **JSON Casts:** `raw_items`, `caps_triggered`, `secondary_diagnoses` stored as JSON
- **Model Accessors:** `getMapleDescriptionAttribute()`, `getCpsDescriptionAttribute()`
- **Scopes:** `scopeStale()`, `scopeCurrent()`, `scopePendingIarUpload()`
- **Risk Level:** Low - Well-structured model with clear accessors

---

### 3.4 Workforce / Capacity Module

**Purpose:** Staff capacity calculation, FTE compliance, availability management.

#### 3.4.1 Key Models

| Model | Table | Key Fields | Relationships |
|-------|-------|------------|---------------|
| `StaffRole` | `staff_roles` | `code`, `name`, `category`, `is_direct_care`, `team_lane_group` | ← Users |
| `EmploymentType` | `employment_types` | `code`, `name`, `is_fulltime`, `fte_factor` | ← Users |
| `StaffAvailability` | `staff_availabilities` | `user_id`, `day_of_week`, `start_time`, `end_time`, `effective_from` | → User |
| `StaffUnavailability` | `staff_unavailabilities` | `user_id`, `start_datetime`, `end_datetime`, `type`, `status` | → User |
| `Skill` | `skills` | `code`, `name`, `category`, `requires_certification` | ↔ Users (M:N) |

#### 3.4.2 Key Services

| Service | Location | Responsibility |
|---------|----------|----------------|
| `WorkforceCapacityService` | `app/Services/CareOps/WorkforceCapacityService.php` | Available vs required hours calculation |
| `FteComplianceService` | `app/Services/CareOps/FteComplianceService.php` | FT/PT/Casual ratio compliance |
| `StaffProfileService` | `app/Services/StaffProfileService.php` | Staff profile aggregation |
| `StaffScheduleService` | `app/Services/StaffScheduleService.php` | Staff schedule retrieval |

#### 3.4.3 Key Controllers & Routes

| Route | Controller | Method | Purpose |
|-------|-----------|--------|---------|
| `GET /v2/workforce/capacity` | `WorkforceController` | `capacity` | Available vs required hours |
| `GET /v2/workforce/fte-trend` | `WorkforceController` | `fteTrend` | FTE compliance trend |
| `GET /v2/workforce/staff` | `WorkforceController` | `staff` | Staff listing |
| `GET /v2/staff/{id}/profile` | `StaffProfileController` | `show` | Staff profile |

#### 3.4.4 Laravel-Specific Patterns

- **User Model Extension:** Staff fields mixed into `User` model (problematic)
- **Complex Scopes:** `scopeActiveStaff()`, `scopeAvailableOn()`, `scopeWithValidSkill()`
- **Computed Attributes:** `getCurrentWeeklyHoursAttribute()`, `getFteUtilizationAttribute()`
- **Risk Level:** Medium - Staff logic embedded in User model

---

### 3.5 SSPO / Marketplace Module

**Purpose:** Subcontracted Service Provider Organization management and service routing.

#### 3.5.1 Key Models

| Model | Table | Key Fields | Relationships |
|-------|-------|------------|---------------|
| `ServiceProviderOrganization` | `service_provider_organizations` | `name`, `type`, `slug`, `region_code`, `capacity_level` | ← Users, → SspoServiceCapability |
| `SspoServiceCapability` | `sspo_service_capabilities` | `organization_id`, `service_type_id`, `delivery_mode`, `capacity_status` | → ServiceType |
| `OrganizationMembership` | `organization_memberships` | `user_id`, `organization_id`, `organization_role` | → User, ServiceProviderOrganization |

#### 3.5.2 Key Services

| Service | Location | Responsibility |
|---------|----------|----------------|
| `SspoMarketplaceService` | `app/Services/SspoMarketplaceService.php` | SSPO browsing, filtering, stats |
| `SspoPerformanceService` | `app/Services/SspoPerformanceService.php` | SSPO metrics (acceptance rate, response time) |

#### 3.5.3 Key Controllers & Routes

| Route | Controller | Method | Purpose |
|-------|-----------|--------|---------|
| `GET /v2/sspo-marketplace` | `SspoMarketplaceController` | `index` | List SSPOs |
| `GET /v2/sspo-marketplace/{id}` | `SspoMarketplaceController` | `show` | SSPO profile |
| `GET /v2/sspo/{id}/performance` | `SspoPerformanceController` | `show` | Performance metrics |

#### 3.5.4 ServiceType Provider Metadata

```php
// ServiceType fields for SSPO routing
'preferred_provider' => 'spo' | 'sspo' | 'either'
'allowed_provider_types' => ['spo', 'sspo']
'delivery_mode' => 'in_person' | 'remote' | 'either'
```

---

### 3.6 LLM / AI Integration Module

**Purpose:** Vertex AI Gemini integration for explanations and weekly summaries.

#### 3.6.1 Key Services

| Service | Location | Responsibility |
|---------|----------|----------------|
| `VertexAiClient` | `app/Services/Llm/VertexAi/VertexAiClient.php` | HTTP client for Vertex AI API |
| `VertexAiConfig` | `app/Services/Llm/VertexAi/VertexAiConfig.php` | Configuration wrapper |
| `LlmExplanationService` | `app/Services/Llm/LlmExplanationService.php` | Orchestrates explanations with fallback |
| `PromptBuilder` | `app/Services/Llm/PromptBuilder.php` | Builds prompts with PII masking |
| `RulesBasedExplanationProvider` | `app/Services/Llm/Fallback/RulesBasedExplanationProvider.php` | Fallback when Vertex disabled |

#### 3.6.2 Configuration

```php
// config/vertex_ai.php
'enabled' => env('VERTEX_AI_ENABLED', false),
'project_id' => env('VERTEX_AI_PROJECT_ID'),
'location' => env('VERTEX_AI_LOCATION', 'us-central1'),
'model' => env('VERTEX_AI_MODEL', 'gemini-1.5-pro'),
'generation_config' => [
    'temperature' => 0.3,
    'maxOutputTokens' => 512,
],
```

#### 3.6.3 PII Handling

- All patient names replaced with `patient_{id}`
- All staff names replaced with `staff_{id}`
- No PHI stored in `llm_explanation_logs` table

#### 3.6.4 Laravel-Specific Patterns

- **Config Access:** `config('vertex_ai.enabled')`
- **HTTP Client:** Laravel's `Http` facade
- **Risk Level:** Low - Clean abstraction with interface

---

## 4. Routes & API Surface Summary

### 4.1 Core Scheduling Routes (`/v2/scheduling/*`)

| Method | Path | Controller@Action | Auth | Purpose |
|--------|------|-------------------|------|---------|
| GET | `/v2/scheduling/grid` | `SchedulingController@grid` | Sanctum | Staff + assignments grid |
| GET | `/v2/scheduling/requirements` | `SchedulingController@requirements` | Sanctum | Unscheduled care |
| GET | `/v2/scheduling/eligible-staff` | `SchedulingController@eligibleStaff` | Sanctum | Eligible staff for slot |
| POST | `/v2/scheduling/assignments` | `SchedulingController@createAssignment` | Sanctum | Create assignment |
| PATCH | `/v2/scheduling/assignments/{id}` | `SchedulingController@updateAssignment` | Sanctum | Update assignment |
| DELETE | `/v2/scheduling/assignments/{id}` | `SchedulingController@deleteAssignment` | Sanctum | Cancel assignment |
| GET | `/v2/scheduling/suggestions` | `AutoAssignController@suggestions` | Sanctum | AI suggestions |
| GET | `/v2/scheduling/suggestions/summary` | `AutoAssignController@summary` | Sanctum | Suggestion stats |
| GET | `/v2/scheduling/suggestions/weekly-summary` | `AutoAssignController@weeklySummary` | Sanctum | AI weekly brief |
| GET | `/v2/scheduling/suggestions/{pid}/{stid}/explain` | `AutoAssignController@explain` | Sanctum | Explain suggestion |
| POST | `/v2/scheduling/suggestions/accept` | `AutoAssignController@accept` | Sanctum | Accept suggestion |
| POST | `/v2/scheduling/suggestions/accept-batch` | `AutoAssignController@acceptBatch` | Sanctum | Batch accept |
| GET | `/v2/scheduling/suggestions/analytics` | `AutoAssignController@analytics` | Sanctum | Learning loop stats |

### 4.2 Bundle Engine Routes (`/v2/bundle-engine/*`)

| Method | Path | Controller@Action | Auth | Purpose |
|--------|------|-------------------|------|---------|
| GET | `/v2/bundle-engine/profile/{patient}` | `BundleEngineController@getProfile` | Sanctum | Patient needs profile |
| GET | `/v2/bundle-engine/axes/{patient}` | `BundleEngineController@getAxes` | Sanctum | Applicable axes |
| GET | `/v2/bundle-engine/scenarios/{patient}` | `BundleEngineController@getScenarios` | Sanctum | Generate scenarios |
| GET | `/v2/bundle-engine/data-sources/{patient}` | `BundleEngineController@getDataSources` | Sanctum | Available assessments |
| POST | `/v2/bundle-engine/compare` | `BundleEngineController@compareScenarios` | Sanctum | Compare scenarios |
| POST | `/v2/bundle-engine/explain` | `BundleEngineController@explainScenario` | Sanctum | AI explanation |

### 4.3 Care Builder Routes (`/v2/care-builder/*`)

| Method | Path | Controller@Action | Auth | Purpose |
|--------|------|-------------------|------|---------|
| GET | `/v2/care-builder/{patientId}/bundles` | `CareBundleBuilderController@getBundles` | Sanctum | Get bundles |
| GET | `/v2/care-builder/{patientId}/rug-bundles` | `CareBundleBuilderController@getRugBundles` | Sanctum | RUG-based bundles |
| POST | `/v2/care-builder/{patientId}/bundles/preview` | `CareBundleBuilderController@previewBundle` | Sanctum | Preview bundle |
| POST | `/v2/care-builder/{patientId}/plans` | `CareBundleBuilderController@buildPlan` | Sanctum | Create plan |
| POST | `/v2/care-builder/{patientId}/plans/{id}/publish` | `CareBundleBuilderController@publishPlan` | Sanctum | Publish plan |

### 4.4 InterRAI Routes (`/v2/interrai/*`)

| Method | Path | Controller@Action | Auth | Purpose |
|--------|------|-------------------|------|---------|
| GET | `/v2/interrai/patients-needing-assessment` | `InterraiController@patientsNeedingAssessment` | Sanctum | Patients needing HC |
| GET | `/v2/interrai/patients/{patient}/status` | `InterraiController@patientStatus` | Sanctum | Assessment status |
| POST | `/v2/interrai/patients/{patient}/assessments` | `InterraiController@store` | Sanctum | Create assessment |
| POST | `/v2/interrai/assessments/{id}/complete` | `InterraiController@completeAssessment` | Sanctum | Complete assessment |
| GET | `/v2/interrai/reassessment-triggers` | `InterraiController@reassessmentTriggers` | Sanctum | Pending triggers |

### 4.5 Workforce Routes (`/v2/workforce/*`)

| Method | Path | Controller@Action | Auth | Purpose |
|--------|------|-------------------|------|---------|
| GET | `/v2/workforce/summary` | `WorkforceController@summary` | Sanctum | Workforce summary |
| GET | `/v2/workforce/capacity` | `WorkforceController@capacity` | Sanctum | Capacity vs required |
| GET | `/v2/workforce/fte-trend` | `WorkforceController@fteTrend` | Sanctum | FTE trend |
| GET | `/v2/workforce/staff` | `WorkforceController@staff` | Sanctum | Staff list |
| GET | `/v2/workforce/metadata/roles` | `WorkforceController@metadataRoles` | Sanctum | Staff roles |
| GET | `/v2/workforce/metadata/employment-types` | `WorkforceController@metadataEmploymentTypes` | Sanctum | Employment types |
| GET | `/v2/workforce/metadata/team-lanes` | `WorkforceController@metadataTeamLanes` | Sanctum | Team lane config |

### 4.6 Dashboard/Metrics Routes

| Method | Path | Controller@Action | Auth | Purpose |
|--------|------|-------------------|------|---------|
| GET | `/v2/dashboards/spo` | `SpoDashboardController@index` | Sanctum | SPO Command Center |
| GET | `/v2/metrics/tfs/summary` | `TfsController@summary` | Sanctum | Time-to-First-Service |
| GET | `/v2/qin/active` | `QinController@active` | Sanctum | Active QINs |
| GET | `/v2/jeopardy/summary` | `JeopardyBoardController@summary` | Sanctum | Jeopardy summary |
| GET | `/v2/sla/dashboard` | `SlaComplianceController@dashboard` | Sanctum | SLA dashboard |

---

## 5. Database Schema Summary

### 5.1 Core Patient/Care Tables

| Table | Key Columns | Relationships | Purpose |
|-------|-------------|---------------|---------|
| `patients` | `id`, `user_id`, `ohip`, `status`, `interrai_status`, `region_id`, `lat`, `lng` | → User, Region, InterraiAssessments, CarePlans | Patient demographics |
| `care_plans` | `id`, `patient_id`, `care_bundle_id`, `care_bundle_template_id`, `status`, `scenario_metadata`, `first_service_delivered_at` | → Patient, CareBundle, CareBundleTemplate | Active care configuration |
| `service_assignments` | `id`, `patient_id`, `care_plan_id`, `service_type_id`, `assigned_user_id`, `scheduled_start/end`, `status`, `verification_status`, `sspo_acceptance_status` | → Patient, CarePlan, ServiceType, User | Individual visit assignments |
| `care_bundles` | `id`, `name`, `code`, `description`, `active` | ↔ ServiceTypes | Bundle definitions |
| `care_bundle_templates` | `id`, `code`, `rug_group`, `rug_category`, `funding_stream`, `weekly_cap_cents` | → CareBundleTemplateServices | RUG-based templates |

### 5.2 Assessment Tables

| Table | Key Columns | Relationships | Purpose |
|-------|-------------|---------------|---------|
| `interrai_assessments` | `id`, `patient_id`, `assessment_type`, `assessment_date`, `maple_score`, `adl_hierarchy`, `cps`, `chess_score`, `raw_items`, `iar_upload_status` | → Patient, RUGClassifications | InterRAI assessment data |
| `rug_classifications` | `id`, `patient_id`, `assessment_id`, `rug_group`, `rug_category`, `adl_sum`, `flags`, `is_current` | → Patient, InterraiAssessment | RUG classification results |
| `reassessment_triggers` | `id`, `patient_id`, `trigger_type`, `trigger_reason`, `status`, `resolution_assessment_id` | → Patient, InterraiAssessment | Reassessment requests |

### 5.3 Staff/Workforce Tables

| Table | Key Columns | Relationships | Purpose |
|-------|-------------|---------------|---------|
| `users` | `id`, `name`, `email`, `role`, `organization_id`, `staff_role_id`, `employment_type_id`, `staff_status`, `max_weekly_hours`, `is_scheduling_locked` | → ServiceProviderOrganization, StaffRole, EmploymentType | User/staff data |
| `staff_roles` | `id`, `code`, `name`, `category`, `is_direct_care`, `team_lane_group`, `team_lane_display_order` | ← Users | Role definitions |
| `employment_types` | `id`, `code`, `name`, `is_fulltime`, `fte_factor` | ← Users | Employment type definitions |
| `staff_availabilities` | `id`, `user_id`, `day_of_week`, `start_time`, `end_time`, `effective_from`, `effective_until` | → User | Weekly availability |
| `staff_unavailabilities` | `id`, `user_id`, `start_datetime`, `end_datetime`, `type`, `status` | → User | Time-off requests |
| `skills` | `id`, `code`, `name`, `category` | ↔ Users (staff_skills) | Skill catalog |

### 5.4 Organization/SSPO Tables

| Table | Key Columns | Relationships | Purpose |
|-------|-------------|---------------|---------|
| `service_provider_organizations` | `id`, `name`, `slug`, `type`, `region_code`, `capacity_level`, `contact_email` | ← Users, SspoServiceCapabilities | SPO/SSPO organizations |
| `sspo_service_capabilities` | `id`, `organization_id`, `service_type_id`, `delivery_mode`, `capacity_status` | → ServiceProviderOrganization, ServiceType | SSPO service offerings |
| `organization_memberships` | `id`, `user_id`, `organization_id`, `organization_role` | → User, ServiceProviderOrganization | User-org relationships |

### 5.5 Service Type Tables

| Table | Key Columns | Relationships | Purpose |
|-------|-------------|---------------|---------|
| `service_types` | `id`, `code`, `name`, `category`, `default_duration_minutes`, `cost_per_visit_cents`, `preferred_provider`, `scheduling_mode`, `min_gap_between_visits_minutes` | ↔ CareBundles, Skills | Service catalog |
| `service_categories` | `id`, `code`, `name`, `color` | ← ServiceTypes | Category definitions |
| `service_role_mappings` | `id`, `service_type_id`, `staff_role_id`, `is_primary` | → ServiceType, StaffRole | Role requirements |

### 5.6 AI/Logging Tables

| Table | Key Columns | Relationships | Purpose |
|-------|-------------|---------------|---------|
| `ai_suggestion_logs` | `id`, `patient_id`, `service_type_id`, `suggested_user_id`, `match_status`, `confidence_score`, `outcome`, `time_to_decision_seconds` | → Patient, ServiceType, User | AI suggestion tracking |
| `llm_explanation_logs` | `id`, `context_type`, `context_hash`, `provider`, `response_time_ms`, `was_fallback` | - | LLM usage audit (no PHI) |
| `bundle_engine_events` | `id`, `event_type`, `patient_id`, `scenario_id`, `event_data` | → Patient | Bundle engine analytics |

### 5.7 Metrics/Compliance Tables

| Table | Key Columns | Relationships | Purpose |
|-------|-------------|---------------|---------|
| `qin_records` | `id`, `indicator`, `band_breach`, `status`, `issued_at`, `qip_submitted_at` | - | Quality Improvement Notices |
| `oh_metrics` | `id`, `metric_type`, `period_start/end`, `value`, `target`, `band` | - | OHaH metrics tracking |

### 5.8 Key Constraints

**Database-Level Constraints:**
- Primary keys on all tables
- Foreign keys on core relationships (some missing - see Risk Analysis)
- Soft deletes (`deleted_at`) on most models
- Unique constraints on codes (`service_types.code`, `staff_roles.code`)

**Application-Level Constraints:**
- Patient non-concurrency (no DB constraint - enforced in `SchedulingEngine`)
- PSW spacing rules (enforced in `SchedulingEngine`)
- Staff capacity limits (computed dynamically)
- SSPO acceptance workflow (state machine in application)

---

## 6. Laravel Coupling & Risk Analysis

### 6.1 High-Risk Laravel-Specific Patterns

#### 6.1.1 User Model as God Class ❌

**Location:** `app/Models/User.php`

**Issues:**
- Mixed concerns: Admin, Hospital User, Staff, Coordinator, Patient
- 40+ fillable fields
- 25+ relationships
- 20+ computed attributes
- 15+ scopes

**Risk:** High - Critical path for all user operations

**Migration Impact:**
- Needs decomposition into separate entities (Staff, Patient, Admin)
- Consider CQRS pattern with separate read/write models

#### 6.1.2 Facade Usage in Services ⚠️

| Facade | Used In | Migration Approach |
|--------|---------|-------------------|
| `DB` | `SpoDashboardController`, `WorkforceCapacityService` | Replace with explicit repository |
| `Auth` | Controllers, some services | Inject user context |
| `Cache` | `BundleEngineController` | Inject cache service |
| `Log` | Most services | Inject logger |
| `Config` | LLM services, Bundle Engine | Pass config explicitly |

#### 6.1.3 Eloquent Magic in Business Logic ⚠️

**Implicit Relationship Loading:**
```php
// SchedulingController.php - lazy loads relationships
$assignments = $staff->assignedServiceAssignments()
    ->thisWeek()
    ->with(['patient', 'serviceType'])
    ->get();
```

**Model Events:**
- Few model events used (good)
- No `boot()` overrides with business logic (good)

**Global Scopes:**
- None found (good)

#### 6.1.4 Query Builder Chains ⚠️

**Location:** `WorkforceCapacityService`, `SpoDashboardController`

```php
// Complex query chains that are hard to port
$available = StaffAvailability::query()
    ->where('user_id', $staffId)
    ->currentlyEffective()
    ->forDayOfWeek($dayOfWeek)
    ->sum(\DB::raw('TIMESTAMPDIFF(MINUTE, start_time, end_time)'));
```

**Risk:** Medium - Need to rewrite as SQL or SQLAlchemy

### 6.2 Medium-Risk Patterns

#### 6.2.1 Controllers with Business Logic

| Controller | Issue | Location |
|------------|-------|----------|
| `SpoDashboardController` | Metrics calculation inline | Lines 50-200 |
| `InterraiController` | Some validation logic | `store()` method |
| `SchedulingController` | Validation before service call | `createAssignment()` |

**Recommendation:** Extract to services before migration

#### 6.2.2 Trait Dependencies

| Trait | Used By | Purpose |
|-------|---------|---------|
| `SoftDeletes` | Most models | Soft delete support |
| `HasApiTokens` | User | Sanctum auth |
| `HasFactory` | Most models | Testing |
| `Notifiable` | User | Notifications |

**Migration:** Replace with SQLAlchemy mixins or manual implementation

### 6.3 Low-Risk Patterns (Clean Code)

#### 6.3.1 Bundle Engine Services ✅

**Location:** `app/Services/BundleEngine/`

**Why Clean:**
- Clear interfaces (`ScenarioGeneratorInterface`, `AssessmentMapperInterface`)
- Pure DTOs (`PatientNeedsProfile`, `ScenarioBundleDTO`)
- Declarative configs (JSON/YAML)
- Minimal Eloquent usage
- Dependency injection via service provider

#### 6.3.2 LLM Integration ✅

**Location:** `app/Services/Llm/`

**Why Clean:**
- Interface contracts (`ExplanationProviderInterface`)
- DTOs for responses
- No Eloquent dependencies
- Config injected

#### 6.3.3 Algorithm DSL ✅

**Location:** `config/bundle_engine/`

**Why Clean:**
- Pure JSON/YAML files
- No Laravel dependencies
- Self-documenting with schemas

### 6.4 Migration Difficulty Ranking

| Module | Difficulty | Blockers | First Actions |
|--------|------------|----------|---------------|
| **Bundle Engine** | 🟢 Low | None | Port DTOs, engines |
| **LLM Integration** | 🟢 Low | None | Port to Python SDK |
| **Algorithm DSL** | 🟢 Low | None | Load same JSON/YAML |
| **Assessment Mappers** | 🟢 Low | Minor Eloquent | Extract data access |
| **RUG Classification** | 🟡 Medium | Some Eloquent | Extract algorithm |
| **Scheduling Engine** | 🟡 Medium | Eloquent scopes | Rewrite queries |
| **Workforce Capacity** | 🟡 Medium | Complex queries | Rewrite queries |
| **Controllers** | 🟡 Medium | Mixed concerns | Extract to services |
| **User/Auth** | 🔴 High | God class | Decompose first |

---

## 7. Object Model & Field Gap Analysis

### 7.1 Staff (User Model - Staff Role)

#### 7.1.1 Current Fields

| Field | Type | Nullable | Purpose |
|-------|------|----------|---------|
| `id` | bigint | No | PK |
| `name` | string | No | Full name |
| `email` | string | No | Login email |
| `role` | enum | No | System role (FIELD_STAFF, SPO_ADMIN, etc.) |
| `organization_id` | FK | Yes | Parent organization |
| `staff_role_id` | FK | Yes | Clinical role (RN, PSW, etc.) |
| `employment_type_id` | FK | Yes | FT/PT/Casual |
| `staff_status` | enum | Yes | active/inactive/on_leave/terminated |
| `hire_date` | date | Yes | Employment start |
| `max_weekly_hours` | decimal | Yes | Capacity limit |
| `fte_value` | decimal | Yes | FTE fraction |
| `lat` / `lng` | decimal | Yes | Home location for travel |
| `is_scheduling_locked` | bool | Yes | Scheduling lock flag |

#### 7.1.2 Current Usage

- Scheduling: Staff assignments, availability, utilization
- Workforce: FTE compliance, capacity calculations
- SSPO: Staff belongs to SPO or SSPO organization

#### 7.1.3 Likely Future Needs (Scheduler 2.0+)

| Field | Purpose | Exists? |
|-------|---------|---------|
| `team_lane_group` | For team lane grouping | ✅ On StaffRole |
| `home_coordinates` | For travel time | ✅ lat/lng |
| `preferred_regions` | Regional preference | ❌ Gap |
| `max_travel_radius_km` | Travel constraint | ❌ Gap |
| `patient_continuity_scores` | Cache of continuity | ❌ Gap (computed) |

#### 7.1.4 Gap Assessment

- **Major Gap:** User model is overloaded; needs decomposition into `Staff`, `Patient`, `Admin` entities
- **Minor Gap:** Missing travel preference fields
- **Recommendation:** Create dedicated `Staff` model or at minimum extract staff-specific logic to a service

---

### 7.2 Patient

#### 7.2.1 Current Fields

| Field | Type | Nullable | Purpose |
|-------|------|----------|---------|
| `id` | bigint | No | PK |
| `user_id` | FK | Yes | Associated user account |
| `ohip` | string | Yes | Health card number |
| `date_of_birth` | date | Yes | DOB |
| `status` | string | Yes | Active/Inactive/etc. |
| `address` / `city` / `postal_code` | strings | Yes | Address |
| `lat` / `lng` | decimal | Yes | Geocoded coordinates |
| `region_id` | FK | Yes | Assigned region |
| `maple_score` | string | Yes | MAPLe (cached) |
| `interrai_status` | enum | Yes | current/stale/missing |
| `risk_flags` | JSON | Yes | Clinical risk flags |
| `triage_summary` | JSON | Yes | Legacy TNP summary |

#### 7.2.2 Current Usage

- Scheduling: Patient assignments, non-concurrency
- Bundle Engine: Assessment source, scenario generation
- Dashboard: Patient list, queue management

#### 7.2.3 Likely Future Needs

| Field | Purpose | Exists? |
|-------|---------|---------|
| `current_rug_group` | Cached RUG | ❌ Gap (on assessment) |
| `current_bundle_id` | Active bundle | ✅ Via CarePlan |
| `acuity_score` | Aggregated acuity | ❌ Gap (computed) |
| `travel_complexity` | For scheduling | ❌ Gap |
| `preferred_staff_ids` | Continuity preference | ❌ Gap |

#### 7.2.4 Gap Assessment

- **Existing:** Good base structure with geocoding, region, assessment tracking
- **Gap:** Missing cached RUG/acuity for quick filtering
- **Gap:** Missing staff preferences for scheduling optimization

---

### 7.3 ServiceType

#### 7.3.1 Current Fields

| Field | Type | Nullable | Purpose |
|-------|------|----------|---------|
| `id` | bigint | No | PK |
| `code` | string | No | Unique code (NUR, PSW, PT) |
| `name` | string | No | Display name |
| `category` | string | Yes | Category (nursing, therapy, etc.) |
| `default_duration_minutes` | int | Yes | Default duration |
| `cost_per_visit_cents` | int | Yes | Default cost |
| `preferred_provider` | enum | Yes | spo/sspo/either |
| `scheduling_mode` | enum | Yes | weekly/fixed_visits |
| `min_gap_between_visits_minutes` | int | Yes | Spacing rule |

#### 7.3.2 Current Usage

- Scheduling: Service-staff matching, spacing rules
- Bundle Engine: Service selection for scenarios
- SSPO: Provider routing

#### 7.3.3 Likely Future Needs

| Field | Purpose | Exists? |
|-------|---------|---------|
| `billing_code` | Shadow billing | ❌ Gap |
| `billing_unit_type` | hourly/visit | ⚠️ Partial |
| `ohah_service_code` | OHaH mapping | ❌ Gap |
| `max_daily_frequency` | Intensity limits | ❌ Gap |

---

### 7.4 ServiceAssignment

#### 7.4.1 Current Fields

| Field | Type | Nullable | Purpose |
|-------|------|----------|---------|
| `id` | bigint | No | PK |
| `patient_id` | FK | No | Patient |
| `care_plan_id` | FK | Yes | Care plan |
| `service_type_id` | FK | No | Service type |
| `assigned_user_id` | FK | Yes | Assigned staff |
| `service_provider_organization_id` | FK | Yes | SPO/SSPO |
| `scheduled_start` / `scheduled_end` | datetime | Yes | Scheduled time |
| `actual_start` / `actual_end` | datetime | Yes | Actual time |
| `status` | enum | Yes | planned/completed/cancelled/missed |
| `verification_status` | enum | Yes | PENDING/VERIFIED/MISSED |
| `sspo_acceptance_status` | enum | Yes | pending/accepted/declined |
| `billing_rate_cents` | int | Yes | Rate snapshot |
| `frequency_per_week` | int | Yes | From care plan |
| `duration_minutes` | int | Yes | Planned duration |

#### 7.4.2 Current Usage

- Core scheduling entity
- Visit verification workflow
- SSPO acceptance workflow
- Billing calculation

#### 7.4.3 Likely Future Needs

| Field | Purpose | Exists? |
|-------|---------|---------|
| `travel_time_minutes` | Travel tracking | ❌ Gap |
| `ai_suggestion_id` | Link to suggestion | ❌ Gap |
| `scenario_id` | Link to scenario | ❌ Gap |
| `clock_in_location` | Geofence verification | ❌ Gap |
| `modification_reason` | If modified from AI | ❌ Gap |

---

### 7.5 InterraiAssessment

#### 7.5.1 Current Fields

| Field | Type | Nullable | Purpose |
|-------|------|----------|---------|
| `id` | bigint | No | PK |
| `patient_id` | FK | No | Patient |
| `assessment_type` | enum | No | hc/cha/contact |
| `assessment_date` | datetime | No | When completed |
| `assessor_id` | FK | Yes | Who completed |
| `source` | enum | Yes | hpg/spo/ohah |
| `maple_score` | string | Yes | MAPLe 1-5 |
| `adl_hierarchy` | int | Yes | ADL 0-6 |
| `cognitive_performance_scale` | int | Yes | CPS 0-6 |
| `chess_score` | int | Yes | CHESS 0-5 |
| `pain_scale` | int | Yes | Pain 0-3 |
| `falls_in_last_90_days` | bool | Yes | Fall flag |
| `wandering_flag` | bool | Yes | Wandering risk |
| `caps_triggered` | JSON | Yes | Triggered CAPs |
| `raw_items` | JSON | Yes | Full assessment items |
| `iar_upload_status` | enum | Yes | IAR status |
| `is_current` | bool | Yes | Current flag |

#### 7.5.2 Current Usage

- Bundle Engine: Source for PatientNeedsProfile
- RUG Classification: Input for algorithm
- Clinical Flags: Dashboard alerts

#### 7.5.3 Likely Future Needs

| Field | Purpose | Exists? |
|-------|---------|---------|
| `algorithm_scores` | CA algorithm outputs | ❌ Gap (computed) |
| `episode_type` | post_acute/chronic | ⚠️ Derived |
| `rehab_potential_score` | 0-100 | ⚠️ Derived |
| `needs_cluster` | For CA-only path | ⚠️ Derived |

---

### 7.6 AiSuggestionLog (Learning Loop)

#### 7.6.1 Current Fields

| Field | Type | Nullable | Purpose |
|-------|------|----------|---------|
| `id` | bigint | No | PK |
| `patient_id` | FK | No | Patient |
| `service_type_id` | FK | No | Service |
| `suggested_user_id` | FK | Yes | Suggested staff |
| `match_status` | enum | No | strong/moderate/weak/none |
| `confidence_score` | decimal | Yes | 0-100 |
| `scoring_breakdown` | JSON | Yes | Score components |
| `outcome` | enum | Yes | accepted/modified/rejected/expired |
| `assignment_id` | FK | Yes | If accepted |
| `modifications` | JSON | Yes | If modified |
| `rejection_reason` | string | Yes | If rejected |
| `time_to_decision_seconds` | int | Yes | Response time |

#### 7.6.2 Current Usage

- Learning loop for AI suggestions
- Analytics for acceptance rates
- Feedback for model improvement

#### 7.6.3 Gap Assessment

- **Existing:** Comprehensive logging structure
- **Good for:** BigQuery export for ML training
- **Missing:** Explicit link back to scenario if bundle-driven

---

### 7.7 Summary: Field Gaps by Domain

| Domain | Critical Gaps | Recommended Actions |
|--------|---------------|---------------------|
| **Staff** | Decompose from User; add travel preferences | Create Staff model |
| **Patient** | Cache RUG/acuity; add staff preferences | Add computed fields |
| **ServiceType** | Add billing codes | Migration add |
| **Assignment** | Add travel time, AI link | Migration add |
| **Assessment** | Algorithm scores are computed | Cache in profile |
| **Learning** | Good structure | Ready for ML |

---

## Appendices

### Appendix A: File Location Reference

| Component | Location |
|-----------|----------|
| Controllers | `app/Http/Controllers/Api/V2/` |
| Services | `app/Services/` |
| Bundle Engine | `app/Services/BundleEngine/` |
| LLM Services | `app/Services/Llm/` |
| Scheduling | `app/Services/Scheduling/` |
| Models | `app/Models/` |
| Migrations | `database/migrations/` |
| Routes | `routes/api.php` |
| Configs | `config/` |
| Algorithm DSL | `config/bundle_engine/` |

### Appendix B: Key Documentation References

| Document | Location | Purpose |
|----------|----------|---------|
| Scheduler 2.0 Spec | `docs/SPO_Scheduling_2.0_Functional_Spec.md` | Current scheduler design |
| Bundle Engine Design | `docs/CC21_AI_Bundle_Engine_Design.md` | Bundle architecture |
| Algorithm DSL | `docs/ALGORITHM_DSL.md` | DSL specification |
| Architecture Analysis | `docs/CC2_ARCHITECTURE_ANALYSIS.md` | Gap analysis |
| Feature Status | `harness/feature_list.json` | Feature completion |

### Appendix C: Environment Variables

```bash
# Vertex AI Configuration
VERTEX_AI_ENABLED=false
VERTEX_AI_PROJECT_ID=your-project-id
VERTEX_AI_LOCATION=us-central1
VERTEX_AI_MODEL=gemini-1.5-pro
VERTEX_AI_TEMPERATURE=0.3

# Database (currently SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite

# Authentication
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost
```

---

*Document generated for Laravel → FastAPI Transition Planning*  
*Last updated: December 4, 2025*




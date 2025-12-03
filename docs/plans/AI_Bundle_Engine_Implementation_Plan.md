# AI-Assisted Bundle Engine – Implementation Plan

**Source:** `docs/CC21_AI_Bundle_Engine_Design.md` (v1.1)  
**Created:** 2025-12-02  
**Status:** Implementation Backlog

---

## Overview

This document translates the AI-Assisted Bundle Engine design into actionable implementation tickets. The plan is organized into 5 phases, each building incrementally on the previous.

### Guiding Principles

1. **Start Small** – Begin with minimal viable DTOs, then expand
2. **No Big-Bang** – Each ticket is independently testable and deployable
3. **Metadata-Driven** – Follow existing patterns in `CareBundleBuilderService`
4. **Reuse Infrastructure** – Leverage existing `Llm\*` services for Vertex AI
5. **Test-First** – Each ticket includes unit test requirements

### Phase Summary

| Phase | Focus | Tickets | Est. Total |
|-------|-------|---------|------------|
| Phase 1 | Core Profile & Ingestion | 8 | ~L |
| Phase 2 | Single Scenario Generation | 7 | ~L |
| Phase 3 | Multi-Scenario & UI | 8 | ~XL |
| Phase 4 | Vertex AI Explanation | 5 | ~M |
| Phase 5 | AI Scenario Generation (Future) | 4 | ~L |

---

## Phase 1: Core Profile & Ingestion Infrastructure

**Goal:** Build the foundational `PatientNeedsProfile` DTO and `AssessmentIngestionService` that converts HC/CA/BMHS/referral data into a unified profile.

---

### [Ticket 1.1] Create PatientNeedsProfile DTO

**Summary:**  
Create the core `PatientNeedsProfile` DTO as a pure data container with all fields specified in the design document Section 1.2.

**Dependencies:** None

**Scope:**
- `app/Services/BundleEngine/DTOs/PatientNeedsProfile.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] DTO class created with all 50+ readonly properties from design doc
- [ ] Constructor uses PHP 8.1+ promoted properties
- [ ] `getConfidenceLabel()` method returns human-readable label
- [ ] `isSufficientForBundling()` method checks minimum data requirements
- [ ] `toDeidentifiedArray()` method returns LLM-safe array (no PHI/PII)
- [ ] Does NOT include `getApplicableScenarioAxes()` (per design refinement)
- [ ] Unit tests verify all methods
- [ ] PHPDoc comments on all properties

---

### [Ticket 1.2] Create NeedsCluster Enum

**Summary:**  
Create the `NeedsCluster` enum for CA-only path classification when RUG is unavailable.

**Dependencies:** None

**Scope:**
- `app/Services/BundleEngine/Enums/NeedsCluster.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] PHP 8.1 backed enum with string values
- [ ] All 9 cluster types from design: `HIGH_ADL`, `MODERATE_ADL`, `LOW_ADL`, `COGNITIVE_COMPLEX`, `MH_COMPLEX`, `MEDICAL_COMPLEX`, `POST_ACUTE`, `HIGH_ADL_COGNITIVE`, `GENERAL`
- [ ] Static method `fromProfile()` that maps profile data to cluster (placeholder)
- [ ] Unit tests for enum values

---

### [Ticket 1.3] Create AssessmentIngestionService Interface

**Summary:**  
Define the interface contract for `AssessmentIngestionService` as specified in design Section 2.1.

**Dependencies:** Ticket 1.1

**Scope:**
- `app/Services/BundleEngine/Contracts/AssessmentIngestionServiceInterface.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] Interface with all 6 methods from design:
  - `buildPatientNeedsProfile(Patient $patient)`
  - `buildFromHcAssessment(InterraiAssessment $hc, ?InterraiAssessment $bmhs)`
  - `buildFromCaAssessment(InterraiAssessment $ca, ?InterraiAssessment $bmhs, array $referral)`
  - `buildFromReferralOnly(Patient $patient, array $referralData)`
  - `deriveNeedsClusterFromCa(InterraiAssessment $ca)`
  - `augmentWithBmhs(PatientNeedsProfile $profile, InterraiAssessment $bmhs)`
- [ ] PHPDoc comments with parameter/return types
- [ ] Registered in `AppServiceProvider`

---

### [Ticket 1.4] Verify InterraiAssessment Field Names

**Summary:**  
Before implementing mapping logic, verify exact field names in `InterraiAssessment` model and `raw_items` JSON structure.

**Dependencies:** None

**Scope:**
- `app/Models/InterraiAssessment.php` (read-only)
- Documentation update to design doc

**Effort:** S

**Acceptance Criteria:**
- [ ] Document actual field names for all HC mappings in design Section 2.2
- [ ] Document actual `raw_items` keys for therapy, caregiver, CA, BMHS items
- [ ] Create test fixture JSON files with sample `raw_items` data
- [ ] Update design doc "⚠️ Verify" notes to "✓ Verified" where confirmed
- [ ] Log any discrepancies for resolution

---

### [Ticket 1.5] Implement HC Assessment Mapper

**Summary:**  
Implement the mapping logic from InterRAI HC assessment to `PatientNeedsProfile` fields.

**Dependencies:** Tickets 1.1, 1.3, 1.4

**Scope:**
- `app/Services/BundleEngine/Mappers/HcAssessmentMapper.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] Maps all HC fields per design Section 2.2 table
- [ ] Handles `raw_items` therapy minutes calculation
- [ ] Handles caregiver score derivation
- [ ] Sets `confidenceLevel = 'high'` for HC path
- [ ] Sets `hasFullHcAssessment = true`
- [ ] Returns partial profile that can be augmented
- [ ] Unit tests with HC assessment fixtures
- [ ] Edge case: handles null/missing values gracefully

---

### [Ticket 1.6] Implement CA Assessment Mapper

**Summary:**  
Implement the mapping logic from InterRAI CA assessment to `PatientNeedsProfile` fields.

**Dependencies:** Tickets 1.1, 1.2, 1.3, 1.4

**Scope:**
- `app/Services/BundleEngine/Mappers/CaAssessmentMapper.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] Maps CA fields per design Section 2.2 CA table
- [ ] Uses capacity questions (not self-performance)
- [ ] Sets `confidenceLevel = 'medium'` for CA-only path
- [ ] Sets `hasCaAssessment = true`
- [ ] Calls `deriveNeedsClusterFromCa()` to set `needsCluster`
- [ ] Unit tests with CA assessment fixtures
- [ ] Edge case: reduced precision vs HC noted in profile

---

### [Ticket 1.7] Implement Episode Type & Rehab Potential Derivation

**Summary:**  
Implement the `deriveEpisodeType()` and `calculateRehabPotentialScore()` methods per design Section 2.3.

**Dependencies:** Tickets 1.1, 1.3

**Scope:**
- `app/Services/BundleEngine/Derivers/EpisodeTypeDeriver.php`
- `app/Services/BundleEngine/Derivers/RehabPotentialDeriver.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] `EpisodeTypeDeriver`:
  - Priority-based rule evaluation as specified
  - Returns: `post_acute`, `complex_continuing`, `chronic`, `acute_exacerbation`, `palliative`
  - Default: `complex_continuing`
  - Helper methods: `hasRecentHospitalization()`, `isChronicStable()`
- [ ] `RehabPotentialDeriver`:
  - Point-based scoring (0-100) per design table
  - Returns `['score' => int, 'hasRehabPotential' => bool]`
  - Threshold: `hasRehabPotential = (score >= 40)`
- [ ] Unit tests for each derivation rule
- [ ] Integration tests with assessment fixtures

---

### [Ticket 1.8] Implement AssessmentIngestionService

**Summary:**  
Implement the main `AssessmentIngestionService` that orchestrates mappers and derivers.

**Dependencies:** Tickets 1.1-1.7

**Scope:**
- `app/Services/BundleEngine/AssessmentIngestionService.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] Implements `AssessmentIngestionServiceInterface`
- [ ] `buildPatientNeedsProfile(Patient $patient)`:
  - Detects available assessment types (HC, CA, BMHS)
  - Calls appropriate mapper
  - Applies BMHS augmentation if present
  - Sets RUG group from existing `RUGClassification` if available
- [ ] `buildFromHcAssessment()`: Full HC path with high confidence
- [ ] `buildFromCaAssessment()`: CA path with medium confidence
- [ ] `buildFromReferralOnly()`: Emergency path with low confidence
- [ ] `augmentWithBmhs()`: Merges BMHS data (max of existing + new)
- [ ] Registered in `AppServiceProvider` as singleton
- [ ] Integration tests with real Patient models
- [ ] Never blocks bundling (per design Rule 4)

---

## Phase 2: Single Scenario Generation

**Goal:** Build the scenario generation engine that creates a single scenario for a given axis.

---

### [Ticket 2.1] Create ScenarioAxis Enum

**Summary:**  
Create the `ScenarioAxis` enum with all patient-experience orientations from design Section 4.1.

**Dependencies:** None

**Scope:**
- `app/Services/BundleEngine/Enums/ScenarioAxis.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] PHP 8.1 backed enum with string values
- [ ] All 8 axis types: `RECOVERY_REHAB`, `SAFETY_STABILITY`, `TECH_ENABLED`, `CAREGIVER_RELIEF`, `MEDICAL_INTENSIVE`, `COGNITIVE_SUPPORT`, `COMMUNITY_INTEGRATED`, `BALANCED`
- [ ] `getLabel()` method returns human-readable name
- [ ] `getDescription()` method returns 1-sentence description
- [ ] `getEmoji()` method returns UI emoji icon
- [ ] Unit tests

---

### [Ticket 2.2] Create ScenarioServiceLine DTO

**Summary:**  
Create the DTO for a single service line within a scenario bundle.

**Dependencies:** None

**Scope:**
- `app/Services/BundleEngine/DTOs/ScenarioServiceLine.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] All properties from design Section 4.3
- [ ] `toArray()` method for API response
- [ ] Calculated property: `weeklyMinutes = frequencyPerWeek * durationMinutes`
- [ ] Calculated property: `weeklyTotalCents = frequencyPerWeek * costPerVisitCents`
- [ ] Unit tests

---

### [Ticket 2.3] Create ScenarioBundleDTO

**Summary:**  
Create the main scenario bundle DTO from design Section 4.2.

**Dependencies:** Ticket 2.2

**Scope:**
- `app/Services/BundleEngine/DTOs/ScenarioBundleDTO.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] All properties from design Section 4.2
- [ ] `getShortSummary()` method
- [ ] `toArray()` method for API response
- [ ] Accepts array of `ScenarioServiceLine` objects
- [ ] Computed properties: `totalServicesCount`, `inPersonVisitsPerWeek`, `remoteContactsPerWeek`
- [ ] Unit tests

---

### [Ticket 2.4] Create CostAnnotationService

**Summary:**  
Create the service that annotates scenarios with cost information per design Section 5.

**Dependencies:** Tickets 2.2, 2.3

**Scope:**
- `app/Services/BundleEngine/CostAnnotationService.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] `REFERENCE_CAP_CENTS = 500000` constant ($5,000)
- [ ] `annotateScenario(ScenarioBundleDTO $scenario)` method
- [ ] Cost status determination: `within_cap` (≤90%), `near_cap` (90-110%), `over_cap` (>110%)
- [ ] `generateCostNote()` with patient-centered framing (NOT budget warnings)
- [ ] Axis-specific cost notes per design
- [ ] Unit tests for all thresholds

---

### [Ticket 2.5] Create ScenarioAxisSelector

**Summary:**  
Create the policy service that determines applicable axes for a profile per design Section 6.3.

**Dependencies:** Tickets 1.1, 2.1

**Scope:**
- `app/Services/BundleEngine/ScenarioAxisSelector.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] `selectAxes(PatientNeedsProfile $profile): array` returns 3-5 axes
- [ ] Scoring system per design:
  - Recovery/Rehab: rehab potential, therapy minutes, post-acute
  - Safety/Stability: fall risk, health instability, cognitive
  - Tech-Enabled: tech readiness, RPM suitable, internet
  - Caregiver Relief: stress level, requires relief
  - Medical Intensive: extensive services, conditions
  - Cognitive Support: CPS 3+, behavioural
- [ ] Threshold: axes with score ≥40 selected
- [ ] Always includes `BALANCED` if < 3 axes
- [ ] Returns max 5 axes
- [ ] Unit tests for each scoring rule
- [ ] Integration tests with various profile combinations

---

### [Ticket 2.6] Create ScenarioGeneratorInterface

**Summary:**  
Define the interface for scenario generation per design Section 4.4.

**Dependencies:** Tickets 1.1, 2.1, 2.3

**Scope:**
- `app/Services/BundleEngine/Contracts/ScenarioGeneratorInterface.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] Interface with 4 methods:
  - `generateScenarios(PatientNeedsProfile $profile, array $options)`
  - `getApplicableAxes(PatientNeedsProfile $profile)`
  - `generateScenarioForAxis(PatientNeedsProfile $profile, ScenarioAxis $primary, ?ScenarioAxis $secondary)`
  - `validateScenarioSafety(ScenarioBundleDTO $scenario, PatientNeedsProfile $profile)`
- [ ] Return type for `generateScenarios`: `array{scenarios: ScenarioBundleDTO[], generation_metadata: array}`
- [ ] PHPDoc comments

---

### [Ticket 2.7] Implement ScenarioGenerator (Single Axis)

**Summary:**  
Implement the core scenario generator that creates a scenario for a single axis.

**Dependencies:** Tickets 1.8, 2.1-2.6, existing `CareBundleTemplateRepository`

**Scope:**
- `app/Services/BundleEngine/ScenarioGenerator.php`

**Effort:** L

**Acceptance Criteria:**
- [ ] Implements `ScenarioGeneratorInterface`
- [ ] `generateScenarioForAxis()`:
  - Gets base template from `CareBundleTemplateRepository`
  - Applies axis-specific modifiers from design Section 4.5
  - Creates `ScenarioServiceLine` objects for each service
  - Validates safety minimums
  - Annotates costs via `CostAnnotationService`
- [ ] `getMinimumSafetyRequirements(PatientNeedsProfile $profile)`:
  - Case management: always 1x/week
  - PSW: if ADL ≥3, min 7x/week
  - Nursing: if CHESS ≥2 or extensive services, min 3x/week
  - Safety monitoring: if fall risk ≥2
- [ ] `getAxisServiceModifiers(ScenarioAxis $axis)` per design
- [ ] `validateScenarioSafety()` returns `{passes, violations, warnings}`
- [ ] Registered in `AppServiceProvider`
- [ ] Unit tests for each axis modifier
- [ ] Integration tests with real templates

---

## Phase 3: Multi-Scenario Generation & UI

**Goal:** Generate multiple scenarios and build the React UI for scenario selection.

---

### [Ticket 3.1] Implement Multi-Scenario Generation

**Summary:**  
Extend `ScenarioGenerator` to produce 3-5 scenarios per patient.

**Dependencies:** Ticket 2.7

**Scope:**
- `app/Services/BundleEngine/ScenarioGenerator.php` (extend)

**Effort:** M

**Acceptance Criteria:**
- [ ] `generateScenarios()` method:
  - Calls `ScenarioAxisSelector.selectAxes()` to get applicable axes
  - Generates one scenario per primary axis
  - Creates 1-2 hybrid scenarios (complementary axis combinations)
  - Returns 3-5 scenarios sorted by relevance score
- [ ] Generation metadata includes: axes_evaluated, time_ms, profile_confidence
- [ ] All scenarios pass safety validation
- [ ] Unit tests for multi-scenario generation

---

### [Ticket 3.2] Create BundleEngine API Controller

**Summary:**  
Create the API controller for bundle engine endpoints per design Section 8.2.

**Dependencies:** Tickets 1.8, 3.1

**Scope:**
- `app/Http/Controllers/Api/V2/BundleEngineController.php`
- `routes/api.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] Endpoints:
  - `GET /api/v2/bundle-engine/{patientId}/profile` – Get patient's needs profile
  - `GET /api/v2/bundle-engine/{patientId}/scenarios` – Generate scenario bundles
  - `GET /api/v2/bundle-engine/{patientId}/scenarios/{scenarioId}` – Get scenario details
- [ ] Request validation with form requests
- [ ] Response transformation with resources
- [ ] Authorization: SPO Admin/Coordinator roles
- [ ] Rate limiting
- [ ] Feature tests for each endpoint

---

### [Ticket 3.3] Create BundleEngine API Resources

**Summary:**  
Create Laravel API resources for transforming DTOs to JSON responses.

**Dependencies:** Tickets 1.1, 2.3

**Scope:**
- `app/Http/Resources/BundleEngine/PatientNeedsProfileResource.php`
- `app/Http/Resources/BundleEngine/ScenarioBundleResource.php`
- `app/Http/Resources/BundleEngine/ScenarioServiceLineResource.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] Resources transform DTOs to API-friendly JSON
- [ ] Hide internal IDs where appropriate
- [ ] Include computed fields (summaries, labels)
- [ ] Unit tests

---

### [Ticket 3.4] Create Scenario Selection Page Component

**Summary:**  
Create the main React page component for scenario selection per design Section 6.1.

**Dependencies:** Ticket 3.2

**Scope:**
- `resources/js/pages/CarePlanning/ScenarioSelectionPage.jsx`
- `resources/js/components/App.jsx` (route)

**Effort:** M

**Acceptance Criteria:**
- [ ] Route: `/care-planning/{patientId}/scenarios`
- [ ] Header shows: patient ID, assessment type, RUG/cluster, confidence level
- [ ] Fetches scenarios from API on mount
- [ ] Loading state with spinner
- [ ] Error state with retry
- [ ] Renders `ScenarioCard` for each scenario
- [ ] "Show More Scenarios" button if > 3 scenarios
- [ ] Responsive layout

---

### [Ticket 3.5] Create ScenarioCard Component

**Summary:**  
Create the React component for displaying a single scenario card per design wireframe.

**Dependencies:** None (can build with mock data first)

**Scope:**
- `resources/js/components/bundles/ScenarioCard.jsx`

**Effort:** M

**Acceptance Criteria:**
- [ ] Card layout per design Section 6.1 wireframe
- [ ] Shows: emoji icon, scenario label, description
- [ ] Shows: key services (top 4) with frequency/duration
- [ ] Shows: cost annotation with progress bar
- [ ] Shows: in-person vs remote visit summary
- [ ] Cost note displayed if present (patient-centered framing)
- [ ] "View Details" button
- [ ] "Select This Scenario" button
- [ ] Responsive design
- [ ] Storybook story for component

---

### [Ticket 3.6] Create ScenarioDetailModal Component

**Summary:**  
Create the React modal for viewing full scenario details per design Section 6.2.

**Dependencies:** Ticket 3.5

**Scope:**
- `resources/js/components/bundles/ScenarioDetailModal.jsx`

**Effort:** M

**Acceptance Criteria:**
- [ ] Modal layout per design Section 6.2 wireframe
- [ ] Sections:
  - Patient Experience description
  - Emphasized Goals (checkmarks)
  - Services grouped by category (Therapy, Clinical, Daily Support, Community)
  - Service table: name, freq, duration, cost, mode
  - Cost Summary with progress bar
  - Operational Summary (visits, hours, delivery balance)
  - Safety Validation checklist
- [ ] "Customize Services" button (stub for future)
- [ ] "Select This Scenario" button
- [ ] Close button (X)
- [ ] ESC key closes modal
- [ ] Storybook story

---

### [Ticket 3.7] Implement Scenario Selection Flow

**Summary:**  
Implement the flow from scenario selection to care plan creation.

**Dependencies:** Tickets 3.4, 3.6, existing `CareBundleBuilderService`

**Scope:**
- `app/Http/Controllers/Api/V2/BundleEngineController.php` (extend)
- `resources/js/pages/CarePlanning/ScenarioSelectionPage.jsx` (extend)

**Effort:** M

**Acceptance Criteria:**
- [ ] `POST /api/v2/bundle-engine/{patientId}/scenarios/{scenarioId}/select` endpoint
- [ ] Endpoint converts `ScenarioBundleDTO` to `CareBundle` via `CareBundleBuilderService`
- [ ] Frontend confirmation dialog before selection
- [ ] Success redirect to existing care plan page
- [ ] Error handling with user feedback
- [ ] Feature tests

---

### [Ticket 3.8] Integrate with Existing Bundle Builder Flow

**Summary:**  
Add entry point to scenario selection from existing patient intake/bundle builder flows.

**Dependencies:** Tickets 3.4, 3.7

**Scope:**
- `resources/js/pages/CarePlanning/CareBundleWizard.jsx` (modify)
- `resources/js/pages/Patients/PatientDetailPage.jsx` (modify)

**Effort:** S

**Acceptance Criteria:**
- [ ] "AI Bundle Options" button on patient detail page (for eligible patients)
- [ ] Entry point in bundle builder wizard (step option)
- [ ] Conditionally shown based on patient having assessment data
- [ ] Feature flag to enable/disable AI bundle flow

---

## Phase 4: Vertex AI Explanation Integration

**Goal:** Add AI-powered explanations for scenarios using existing Vertex AI infrastructure.

---

### [Ticket 4.1] Create BundleExplanationDTO

**Summary:**  
Create the DTO for AI-generated scenario explanations per design Section 7.2.

**Dependencies:** None

**Scope:**
- `app/Services/BundleEngine/DTOs/BundleExplanationDTO.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] Properties: `shortExplanation`, `keyPoints`, `costContext`, `patientGoalsFit`, `source`, `responseTimeMs`, `confidenceLabel`
- [ ] `toArray()` method
- [ ] Unit tests

---

### [Ticket 4.2] Create BundleExplanationPromptBuilder

**Summary:**  
Create the prompt builder for scenario explanations per design Section 7.2.

**Dependencies:** Tickets 1.1, 2.3

**Scope:**
- `app/Services/BundleEngine/Llm/BundleExplanationPromptBuilder.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] `buildPromptPayload(PatientNeedsProfile $profile, ScenarioBundleDTO $scenario)` method
- [ ] System instruction per design (patient-experience focused, no budget language)
- [ ] Uses `$profile->toDeidentifiedArray()` for patient context
- [ ] Uses `$scenario->toArray()` for scenario context
- [ ] Output format specification for structured response
- [ ] PII validation (reuse pattern from existing `PromptBuilder`)
- [ ] Unit tests for prompt structure

---

### [Ticket 4.3] Create RulesBasedBundleExplanationProvider

**Summary:**  
Create the fallback provider for deterministic scenario explanations.

**Dependencies:** Tickets 1.1, 2.1, 2.3, 4.1

**Scope:**
- `app/Services/BundleEngine/Llm/RulesBasedBundleExplanationProvider.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] `explainScenario(PatientNeedsProfile $profile, ScenarioBundleDTO $scenario)` method
- [ ] Generates deterministic explanations based on:
  - Axis type
  - Profile characteristics
  - Service mix
- [ ] Short explanation: 1-3 sentences per axis
- [ ] Key points: 2-4 bullet points from profile/scenario data
- [ ] Sets `source = 'rules_based'`
- [ ] Unit tests for each axis type

---

### [Ticket 4.4] Create BundleExplanationService

**Summary:**  
Create the main orchestrator for scenario explanations (mirrors `LlmExplanationService` pattern).

**Dependencies:** Tickets 4.1-4.3, existing `VertexAiClient`

**Scope:**
- `app/Services/BundleEngine/Llm/BundleExplanationService.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] Tries Vertex AI first (if enabled)
- [ ] Falls back to rules-based provider on error
- [ ] Logs explanation requests to `llm_explanation_logs` table
- [ ] Measures and records response time
- [ ] Handles rate limiting and timeouts gracefully
- [ ] Registered in `AppServiceProvider`
- [ ] Integration tests with mock Vertex AI client

---

### [Ticket 4.5] Add Explanation Endpoint and UI

**Summary:**  
Add API endpoint and UI for scenario explanations.

**Dependencies:** Tickets 3.6, 4.4

**Scope:**
- `app/Http/Controllers/Api/V2/BundleEngineController.php` (extend)
- `resources/js/components/bundles/ScenarioDetailModal.jsx` (extend)

**Effort:** M

**Acceptance Criteria:**
- [ ] `GET /api/v2/bundle-engine/{patientId}/scenarios/{scenarioId}/explain` endpoint
- [ ] Returns `BundleExplanationDTO` as JSON
- [ ] "Why This Scenario?" button in modal
- [ ] Explanation panel shows:
  - Short explanation
  - Key points (bullet list)
  - Source badge (AI Generated / Rules-Based)
  - Cost context if present
- [ ] Loading state while fetching
- [ ] Feature tests

---

## Phase 5: AI Scenario Generation (Future)

**Goal:** Design only – AI-assisted scenario proposal using Vertex AI.

> **Note:** This phase is marked as future in the design document. These tickets define the work but should NOT be implemented until Phase 4 is complete and evaluated.

---

### [Ticket 5.1] Create ServiceCatalogDTO (Future)

**Summary:**  
Create the DTO for passing service catalog to LLM for scenario generation.

**Dependencies:** None

**Scope:**
- `app/Services/BundleEngine/DTOs/ServiceCatalogDTO.php`

**Effort:** S

**Acceptance Criteria:**
- [ ] Contains service types with metadata (category, cost, delivery mode)
- [ ] `toArray()` method for LLM consumption
- [ ] Filters by region availability
- [ ] Unit tests

---

### [Ticket 5.2] Create LlmScenarioGenerationPromptBuilder (Future)

**Summary:**  
Create the prompt builder for AI scenario generation per design Section 7.3.

**Dependencies:** Tickets 1.1, 5.1

**Scope:**
- `app/Services/BundleEngine/Llm/LlmScenarioGenerationPromptBuilder.php`

**Effort:** M

**Acceptance Criteria:**
- [ ] System instruction per design
- [ ] Patient profile (de-identified)
- [ ] Service catalog
- [ ] Constraints (region, reference cap, safety minimums)
- [ ] Output format specification
- [ ] Unit tests

---

### [Ticket 5.3] Create LlmScenarioGeneratorService (Future)

**Summary:**  
Create the service for AI-assisted scenario generation.

**Dependencies:** Tickets 5.1, 5.2, existing `VertexAiClient`

**Scope:**
- `app/Services/BundleEngine/Llm/LlmScenarioGeneratorService.php`

**Effort:** L

**Acceptance Criteria:**
- [ ] Implements `LlmScenarioGeneratorInterface` from design
- [ ] Calls Vertex AI with generation prompt
- [ ] Parses response into `ScenarioBundleDTO` objects
- [ ] Validates each proposal against safety requirements
- [ ] Sets `requiresHumanReview = true`
- [ ] Logs generation requests
- [ ] Integration tests

---

### [Ticket 5.4] Create Human Review Workflow (Future)

**Summary:**  
Create the UI workflow for reviewing AI-generated scenario proposals.

**Dependencies:** Ticket 5.3

**Scope:**
- `resources/js/pages/CarePlanning/AiScenarioReviewPage.jsx`
- `resources/js/components/bundles/AiProposalReviewCard.jsx`

**Effort:** M

**Acceptance Criteria:**
- [ ] Shows AI-generated proposals with "AI Proposed" badge
- [ ] Approve/Reject/Edit actions for each proposal
- [ ] Edit opens customization flow
- [ ] Approved proposals become selectable scenarios
- [ ] Audit log of review decisions
- [ ] Admin-only access

---

## Appendix: File Structure

After all phases, the new files will be organized as:

```
app/
├── Services/
│   └── BundleEngine/
│       ├── Contracts/
│       │   ├── AssessmentIngestionServiceInterface.php
│       │   └── ScenarioGeneratorInterface.php
│       ├── DTOs/
│       │   ├── PatientNeedsProfile.php
│       │   ├── ScenarioBundleDTO.php
│       │   ├── ScenarioServiceLine.php
│       │   ├── BundleExplanationDTO.php
│       │   └── ServiceCatalogDTO.php (Phase 5)
│       ├── Enums/
│       │   ├── NeedsCluster.php
│       │   └── ScenarioAxis.php
│       ├── Mappers/
│       │   ├── HcAssessmentMapper.php
│       │   └── CaAssessmentMapper.php
│       ├── Derivers/
│       │   ├── EpisodeTypeDeriver.php
│       │   └── RehabPotentialDeriver.php
│       ├── Llm/
│       │   ├── BundleExplanationPromptBuilder.php
│       │   ├── BundleExplanationService.php
│       │   ├── RulesBasedBundleExplanationProvider.php
│       │   └── LlmScenarioGenerationPromptBuilder.php (Phase 5)
│       ├── AssessmentIngestionService.php
│       ├── ScenarioGenerator.php
│       ├── ScenarioAxisSelector.php
│       └── CostAnnotationService.php
├── Http/
│   ├── Controllers/Api/V2/
│   │   └── BundleEngineController.php
│   └── Resources/BundleEngine/
│       ├── PatientNeedsProfileResource.php
│       ├── ScenarioBundleResource.php
│       └── ScenarioServiceLineResource.php
resources/
└── js/
    ├── pages/CarePlanning/
    │   ├── ScenarioSelectionPage.jsx
    │   └── AiScenarioReviewPage.jsx (Phase 5)
    └── components/bundles/
        ├── ScenarioCard.jsx
        └── ScenarioDetailModal.jsx
```

---

## Appendix: Dependencies Graph

```
Phase 1 (Foundation)
├── 1.1 PatientNeedsProfile DTO
├── 1.2 NeedsCluster Enum
├── 1.3 Interface
├── 1.4 Field Verification ─────────────────────┐
├── 1.5 HC Mapper ←─────────────────────────────┤
├── 1.6 CA Mapper ←─────────────────────────────┤
├── 1.7 Episode/Rehab Derivers ←────────────────┤
└── 1.8 AssessmentIngestionService ←────────────┘
           │
           ▼
Phase 2 (Single Scenario)
├── 2.1 ScenarioAxis Enum
├── 2.2 ScenarioServiceLine DTO
├── 2.3 ScenarioBundleDTO
├── 2.4 CostAnnotationService
├── 2.5 ScenarioAxisSelector ←── 1.1
├── 2.6 Interface
└── 2.7 ScenarioGenerator ←── all above
           │
           ▼
Phase 3 (Multi-Scenario & UI)
├── 3.1 Multi-Scenario Generation ←── 2.7
├── 3.2 API Controller ←── 1.8, 3.1
├── 3.3 API Resources
├── 3.4 Selection Page ←── 3.2
├── 3.5 ScenarioCard
├── 3.6 DetailModal
├── 3.7 Selection Flow ←── 3.4, 3.6
└── 3.8 Integration ←── 3.7
           │
           ▼
Phase 4 (AI Explanation)
├── 4.1 BundleExplanationDTO
├── 4.2 PromptBuilder ←── 1.1, 2.3
├── 4.3 RulesBasedProvider
├── 4.4 ExplanationService ←── 4.1-4.3
└── 4.5 Endpoint & UI ←── 3.6, 4.4
           │
           ▼
Phase 5 (Future - AI Generation)
├── 5.1 ServiceCatalogDTO
├── 5.2 GenerationPromptBuilder
├── 5.3 LlmScenarioGenerator
└── 5.4 Human Review Workflow
```

---

**End of Implementation Plan**


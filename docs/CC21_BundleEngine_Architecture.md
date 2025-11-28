# CC2.1 Bundle Engine – Architecture Specification

**Version:** 0.2 (Draft)  
**Scope:** Bundled High Intensity Home Care – LTC stream (extends to other streams later)  
**Codebase:** Connected Capacity 2.1 (Laravel + React)

---

## 0. Problem Statement & Context

Ontario Health atHome’s **Bundled High Intensity Home Care – LTC** program pays a **fixed weekly rate** per patient and expects Service Provider Organizations (SPOs) to dynamically choose and adjust services to keep patients safely at home while meeting performance targets.  

CC2.1 needs a **Bundle Engine** that:

1. **Reads standardized interRAI HC assessments**.
2. **Classifies patients into RUG-III/HC case-mix groups** using CIHI’s IRRS version of the algorithm.
3. **Determines eligibility** for bundled streams (e.g. LTC).
4. **Selects and instantiates bundle templates** (service mixes) appropriate to the patient’s RUG group and clinical flags.
5. **Applies configurable rules** to adjust services and cost within Ontario Health atHome’s weekly cap.
6. **Produces a patient-specific care bundle**, and then a **care plan** that can drive scheduling and shadow billing.
7. **Supports clinician-friendly UI flows** for previewing, editing, and publishing bundles.

The Bundle Engine is **not** responsible for:

- Running interRAI assessments themselves.
- Low-level visit scheduling (that’s downstream).
- Payment processing (Ontario Health atHome handles the actual payor logic).

---

## 1. High-Level Architecture

The Bundle Engine is a backend module within CC2.1 composed of:

1. **RUG Classification Engine**
2. **Eligibility & Policy Engine**
3. **Template Engine**
4. **Bundle Builder Service**
5. **Cost & Budget Engine**
6. **Care Plan Engine**
7. **API Layer (controllers & transformers)**

These sit on top of the existing Laravel domain models and are consumed by the React/Vite front-end via `/api/v2/care-builder` routes.

### 1.1 Component Diagram (Conceptual)

- **Input:**  
  - `Assessment` (interRAI HC payload)  
  - Patient metadata (region, LTC crisis status, etc.)

- **Flow:**
  1. **RUGClassificationService** → `rug_classifications` row.
  2. **BundleEligibilityService** → eligible funding streams + constraints.
  3. **CareBundleTemplateRepository** → candidate templates for that RUG & stream.
  4. **CareBundleBuilderService**  
     - Instantiates `CareBundle` from template.  
     - Applies `BundleConfigurationRule`s.  
     - Uses `CostEngine` to compute weekly cost and cap status.  
  5. **CarePlanService** → finalize & publish plan.

- **Output:**  
  - Recommended bundles  
  - A drafted or published **Care Plan** (with services and costs)  
  - Events for the scheduling/placement subsystems.

---

## 2. Domain Model

### 2.1 Core Domain Objects

- **Patient**
  - Existing CC entity.
  - Identified by `patient_id`.

- **Assessment**
  - Represents an interRAI HC assessment:
    - `id`
    - `patient_id`
    - `assessment_type` (e.g. `interRAI_HC`)
    - `assessment_date`
    - `raw_payload` (JSON of iCODE variables, if needed for audit)
  - Source: imported from CHRIS/HPG or entered manually.

- **RUGClassification**
  - Output of CIHI RUG-III-HC logic.
  - Fields (minimum):
    - `patient_id`
    - `assessment_id`
    - `rug_group` (`aR3H`, e.g. `CB0`, `IB0`)
    - `rug_category` (e.g. `Clinically Complex`, `Impaired Cognition`)
    - `adl_sum` (`x_adlsum`)
    - `iadl_sum` (`x_iadls`)
    - `cps_score` (`sCPS`)
    - `flags` (JSON: `{ "rehab": true, "extensive": false, "special_care": true, "clinically_complex": true, "impaired_cognition": false, "behaviour_problems": false }`)
  - Derived from CIHI SAS algorithm (ported to PHP/Python).

- **ServiceType**
  - Canonical service in the bundle (e.g. “Nursing Visit”, “PSW Visit”, “Remote Patient Monitoring”, “Meal Delivery”).
  - Mirrors the set defined in `careBundleConstants.js` on the front-end (service codes, categories, and default durations/costs).

- **CareBundleTemplate**
  - Configurable definition of a **bundle pattern**:
    - One template per RUG group, possibly more for sub-populations.
  - Fields:
    - `code` (e.g. `LTC_CC0_STANDARD`)
    - `funding_stream` (`LTC`, `Reactivation`, etc.)
    - `rug_group` / `rug_category`
    - `adl_range`, `iadl_range`
    - `required_flags`, `excluded_flags`
    - `weekly_cap_cents` (e.g. `500000` for $5,000/wk)
  - Has many `CareBundleTemplateService` records.

- **CareBundleTemplateService**
  - A line item included in a template:
    - `service_type_id`
    - `default_frequency_per_week`
    - `default_duration_minutes`
    - `priority` (`core`, `optional`, `stretch`)
    - `min_frequency`, `max_frequency`
    - Optional notes / rationale.

- **CareBundle** (Instance)
  - Patient-specific instantiation of a template:
    - `patient_id`
    - `rug_classification_id`
    - `care_bundle_template_id` (nullable for ad-hoc)
    - `status` (`draft`, `recommended`, `selected`, `rejected`)
    - `weekly_cost_cents`
    - `is_within_cap` (bool)
    - `metadata` (JSON – e.g. reasons, risk scores).

- **CareBundleService** (Instance)
  - Concrete services for the instance:
    - `care_bundle_id`
    - `service_type_id`
    - `frequency_per_week`
    - `duration_minutes`
    - `provider_id` (optional)
    - `cost_per_visit_cents`
    - `calculated_weekly_cost_cents`
    - `notes`.

- **CarePlan**
  - Finalized plan derived from a `CareBundle`:
    - `patient_id`
    - `care_bundle_id`
    - `status` (`draft`, `published`, `superseded`)
    - `created_by`, `approved_by`
    - `notes`.

- **CarePlanEvent**
  - Audit trail:
    - `care_plan_id`
    - `event_type` (`created`, `edited`, `published`, `revised`)
    - `diff_payload`
    - `user_id`.

- **BundleConfigurationRule**
  - Declarative rule to customize bundles:
    - Triggers:
      - `rug_group`, `rug_category`
      - Flag presence (`behaviour`, `wound`, `ventilator`, etc.)
      - Range conditions (ADL / IADL / CPS)
    - Actions:
      - Add service
      - Remove service
      - Adjust frequency/duration
      - Label as required/optional
      - Adjust bundle cap (e.g. for rural travel).

---

## 3. Core Services & Responsibilities

### 3.1 RUGClassificationService

**Responsibility:** Map a single interRAI HC assessment into a `RUGClassification`.

**Inputs:**

- `Assessment` with all required iCODE variables (58+ items defined by CIHI).

**Logic (high-level, aligned with CIHI SAS):**

1. Validate input ranges for all required iCODE variables.
2. Compute `sCPS` (Cognitive Performance Scale) via `create_sCPS_scale`.
3. Compute temporary variables:
   - `x_iadls`, `x_meal`, `x_mmed`, `x_phon`
   - `x_adlsum`, `x_bedmb`, `x_trans`, `x_toilt`, `x_eatng`
   - `x_th_min` (rehab minutes), `x_reh`
   - `x_cpal`, `x_sept`
   - `x_spec`, `x_clin`, `x_ext`, `x_ext_ct`, `x_behav`
4. Apply category triggers in canonical RUG hierarchy:
   - Special Rehabilitation → Extensive Services → Special Care → Clinically Complex → Impaired Cognition → Behaviour Problems → Reduced Physical Function.
5. Assign final `rug_group` (`aR3H`) and numeric `aNR3H` in line with CIHI’s rank list.
6. Persist `RUGClassification`.

**Notes:**

- IADL calculation must respect **2025 revision**:
  - Use performance items in private homes, capacity items otherwise.
- RUG logic must be unit-tested against CIHI’s sample test data to avoid drift.

---

### 3.2 BundleEligibilityService

**Responsibility:** Decide whether the patient should be considered for a bundled stream and under what constraints.

**Inputs:**

- `RUGClassification`
- Patient metadata (LTC waitlist status, crisis designation, exclusion for palliative, etc.).
- Program config.

**Outputs:**

- `EligibleStreamsResult`:
  - `eligible_streams` (array of codes: `['LTC']`)
  - `ineligible_reasons` (array of strings)
  - `constraints` (e.g. `{ "must_support_24_7": true, "must_accept_all_referrals": true }`).

---

### 3.3 CareBundleTemplateRepository

**Responsibility:** Provide templates matching the patient & program.

**Key methods:**

- `findForRug($stream, $rugGroup, $adlSum, $iadlSum, array $flags): Collection<CareBundleTemplate>`

**Selection criteria:**

- `funding_stream` matches.
- `rug_group` or `rug_category` matches.
- ADL/IADL ranges contain patient values.
- Required flags are present, excluded flags are false.

---

### 3.4 BundleConfigurationRuleEngine

**Responsibility:** Apply declarative rules to a base bundle to create a more precise, patient-specific configuration.

**Example rules:**

- If `rug_category = 'Behaviour Problems'` and `x_behav = true`, add behavioural nursing visits 2x/week.
- If patient lives alone and has `CPS >= 4`, add extra check-in calls.
- If weekly cost > cap by <= 10%, tag some services as `optional` and flag to clinician.

Rules are applied in stable order (e.g. `sort_order` column).

---

### 3.5 CostEngine

**Responsibility:** Translate service lists into weekly and monthly cost and evaluate against the funding cap.

**Key details:**

- Mirrors the front-end calculations (e.g. `calculateTotalCost`, `calculateMonthlyCost`) so the numbers match.
- For each service:
  - `weekly_cost = frequency_per_week * cost_per_visit`.
- For the bundle:
  - `weekly_total = sum(weekly_cost)`.
- Evaluate:
  - `weekly_total <= weekly_cap` → `is_within_cap = true`.
  - Provide `budget_status` label: `OK`, `WARNING`, `OVER_CAP`.

---

### 3.6 CareBundleBuilderService

**Responsibility:** Orchestrate all of the above to produce recommended bundles and care plans.

**Core methods:**

1. `getBundlesForPatient($patientId): array`
   - Load latest Assessment + RUGClassification.
   - Call BundleEligibilityService.
   - Fetch templates, instantiate `CareBundle`s + `CareBundleService`s.
   - Run BundleConfigurationRuleEngine.
   - Run CostEngine.
   - Return transformed payload for API.

2. `getBundleForPatient($patientId, $bundleId): CareBundle`

3. `previewBundle($patientId, $bundleId, array $overrides): CareBundle`
   - Overrides frequency/duration/services but without persisting.

4. `createCarePlan($patientId, $bundleId, array $services, string $notes): CarePlan`

5. `publishCarePlan($patientId, $carePlanId): CarePlan`
   - Validate plan is within allowed constraints or flagged overrides.
   - Update plan status to `published`.
   - Emit events to scheduling/placement modules.

---

### 3.7 CarePlanService

**Responsibility:** Encapsulate creation, versioning and publishing of care plans.

- Maintains invariants:
  - One “current” published plan per stream per patient.
  - Older plans are marked `superseded`.
- Logs `CarePlanEvent`s for audit.

---

## 4. API Layer

The front-end `careBundleBuilderApi.js` expects a set of REST endpoints that align with the service methods.

### 4.1 Endpoints (v2)

- `GET /api/v2/care-builder/{patientId}/bundles`
  - Returns list of recommended bundles for the patient.

- `GET /api/v2/care-builder/{patientId}/bundles/{bundleId}`
  - Returns a specific bundle, including service details and cost.

- `POST /api/v2/care-builder/{patientId}/bundles/preview`
  - Body: `{ bundle_id, services: [ { service_type_id, currentFrequency, currentDuration, provider_id?, notes? }, ... ] }`
  - Returns: preview bundle with recalculated cost and budget flags.

- `POST /api/v2/care-builder/{patientId}/plans`
  - Creates a draft care plan from a bundle and service list.

- `GET /api/v2/care-builder/{patientId}/plans`
  - Lists current and historic plans.

- `POST /api/v2/care-builder/{patientId}/plans/{carePlanId}/publish`
  - Publishes the plan and transitions patient from queue to active.

---

## 5. Non-Functional Requirements

### 5.1 PHIPA & Privacy

- Only store interRAI fields required for RUG calculation and audit.
- Access to bundle & plan endpoints must be gated by appropriate roles.
- All bundle/plan changes must be auditable.

### 5.2 Reliability & Determinism

- RUG classification must be deterministic and match CIHI outputs for the same inputs.
- Bundle templates and rules should be versioned so outputs are reproducible over time.

### 5.3 Extensibility

- Additional streams (Reactivation, MH&A) should be addable by:
  - Adding new templates and rules for those streams.
  - Extending eligibility logic.
- No changes to core RUG algorithm (which should be treated as a stable dependency).

---

## 6. Implementation Plan (Phased)

1. **Phase 1 – RUG Classification**
   - Implement `RUGClassificationService`.
   - Create `assessments` and `rug_classifications` tables.
   - Validate with CIHI sample data.

2. **Phase 2 – Template & Service Catalog**
   - Create `service_types`, `care_bundle_templates`, `care_bundle_template_services`.
   - Seed all 23 RUG-specific templates for the LTC stream.

3. **Phase 3 – Bundle Builder & API**
   - Implement `CareBundleBuilderService`, `CarePlanService`.
   - Wire controllers and routes to match front-end API expectations.

4. **Phase 4 – Rule Engine & Budget Logic**
   - Implement `BundleConfigurationRule` model and rule engine.
   - Add cost/budget evaluation & warnings.

5. **Phase 5 – UX & Clinical Tuning**
   - Iterate on templates & rules based on clinical feedback.
   - Add admin UI for template/rule editing.
   - Instrument analytics to monitor bundle usage and outcomes.

---
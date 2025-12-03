# CC2.1 AI-Assisted Bundle Engine – Design Specification

**Version:** 1.0 (Design Only)  
**Status:** Design Document – NOT FOR IMPLEMENTATION  
**Scope:** Intelligent, patient-experience-oriented bundle scenario generation  
**Integration:** Extends existing CC21_BundleEngine_Architecture

---

## 0. Executive Summary

This document specifies the design for extending the CC2.1 Bundle Engine with AI-assisted capabilities. Unlike the AI-Assisted Scheduling Engine (which answers "who should deliver care"), the AI-Assisted Bundle Engine answers:

> **"Given this patient's needs and our service portfolio, what *shape* should their bundle take, and how do we present options that are meaningful, intelligible, and grounded in patient experience?"**

### Key Design Principles

1. **100% Acceptance Model** – We accept all referrals; we do NOT use AI to decide who gets care
2. **Patient-Experience Framing** – Scenarios are framed around recovery, safety, convenience, and caregiver support – NOT "budget vs clinical"
3. **Assessment Flexibility** – Bundling works with HC, CA, or CA+BMHS; never blocked by missing data
4. **Cost as Reference** – $5,000/week is a reference point, not a hard cap
5. **Human-in-the-Loop** – AI generates options; coordinators/admins make final decisions

---

## 1. PatientNeedsProfile Abstraction

### 1.1 Conceptual Overview

The `PatientNeedsProfile` is a **normalized, assessment-agnostic** representation of a patient's care needs. It abstracts away the source of assessment data (HC, CA, BMHS, referral info, family input) into a unified profile that the Bundle Engine can consume.

```
┌─────────────────────────────────────────────────────────────────┐
│                    PatientNeedsProfile                          │
├─────────────────────────────────────────────────────────────────┤
│  Assessment Source (HC/CA/BMHS combinations)                    │
│  └── Determines confidence level and available data             │
├─────────────────────────────────────────────────────────────────┤
│  Functional Needs                                                │
│  ├── ADL Support Level (normalized 0-6 scale)                   │
│  ├── IADL Support Level (normalized 0-6 scale)                  │
│  └── Mobility/Transfer Complexity                               │
├─────────────────────────────────────────────────────────────────┤
│  Cognitive/Behavioural Needs                                     │
│  ├── Cognitive Support Level (CPS-derived)                      │
│  ├── Behavioural Complexity Score                               │
│  └── Mental Health Indicators (from BMHS if available)          │
├─────────────────────────────────────────────────────────────────┤
│  Clinical Risk Profile                                           │
│  ├── Falls Risk Level                                            │
│  ├── Skin Integrity Risk                                         │
│  ├── Pain Management Need                                        │
│  ├── Continence Support Level                                    │
│  └── Health Instability (CHESS-derived)                         │
├─────────────────────────────────────────────────────────────────┤
│  Support Context                                                 │
│  ├── Caregiver Availability & Stress Level                      │
│  ├── Social Support Network Strength                            │
│  ├── Living Situation (alone, with caregiver, etc.)             │
│  └── Technology Readiness                                        │
├─────────────────────────────────────────────────────────────────┤
│  Environmental Context                                           │
│  ├── Region/Geography                                            │
│  ├── Travel Complexity Score                                     │
│  ├── Service Availability Zone                                   │
│  └── Rural/Urban Classification                                  │
├─────────────────────────────────────────────────────────────────┤
│  Case Classification                                             │
│  ├── RUG Case-Mix Group (if HC available)                       │
│  ├── Needs Cluster (if CA-only)                                  │
│  ├── Rehab Potential Score                                       │
│  └── Episode Type (post-acute, chronic, complex, etc.)          │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 Model Definition

```php
<?php

namespace App\Services\BundleEngine\DTOs;

use Carbon\Carbon;

/**
 * PatientNeedsProfile
 *
 * Normalized representation of patient care needs for bundle generation.
 * This is an ASSESSMENT-AGNOSTIC abstraction that can be built from:
 * - Full InterRAI HC assessment (preferred)
 * - InterRAI Contact Assessment (CA) alone
 * - CA + BMHS combination
 * - Referral/discharge data from hospitals
 *
 * IMPORTANT: This DTO is de-identified for AI processing.
 * No names, addresses, OHIP numbers, or other PHI/PII.
 */
class PatientNeedsProfile
{
    public function __construct(
        // === Profile Metadata ===
        public readonly int $patientId,
        public readonly Carbon $profileGeneratedAt,
        public readonly string $profileVersion = '1.0',

        // === Data Source Tracking ===
        public readonly ?string $primaryAssessmentType = null,  // 'hc', 'ca', 'referral_only'
        public readonly ?Carbon $primaryAssessmentDate = null,
        public readonly bool $hasFullHcAssessment = false,
        public readonly bool $hasCaAssessment = false,
        public readonly bool $hasBmhsAssessment = false,
        public readonly bool $hasReferralData = false,
        public readonly float $dataCompletenessScore = 0.0, // 0.0 - 1.0

        // === Case Classification ===
        public readonly ?string $rugGroup = null,              // e.g., 'CB0', 'IB0' (if HC available)
        public readonly ?string $rugCategory = null,           // e.g., 'Clinically Complex'
        public readonly ?string $needsCluster = null,          // Derived cluster if no HC
        public readonly ?string $episodeType = null,           // 'post_acute', 'chronic', 'complex_continuing'
        public readonly ?int $rugNumericRank = null,           // For ordering/comparison

        // === Functional Needs (Normalized 0-6 scale) ===
        public readonly int $adlSupportLevel = 0,              // 0=independent, 6=total dependence
        public readonly int $iadlSupportLevel = 0,
        public readonly int $mobilityComplexity = 0,
        public readonly ?array $specificAdlNeeds = null,       // ['bathing', 'dressing', 'transfers']

        // === Cognitive & Behavioural ===
        public readonly int $cognitiveComplexity = 0,          // Derived from CPS (0-6)
        public readonly int $behaviouralComplexity = 0,        // From behavioural items
        public readonly int $mentalHealthComplexity = 0,       // From BMHS if available
        public readonly bool $hasWanderingRisk = false,
        public readonly bool $hasAggressionRisk = false,
        public readonly ?array $behaviouralFlags = null,       // ['verbal_aggression', 'resists_care']

        // === Clinical Risk Profile ===
        public readonly int $fallsRiskLevel = 0,               // 0=low, 1=moderate, 2=high
        public readonly int $skinIntegrityRisk = 0,
        public readonly int $painManagementNeed = 0,
        public readonly int $continenceSupport = 0,
        public readonly int $healthInstability = 0,            // CHESS-derived
        public readonly ?array $clinicalRiskFlags = null,      // ['pressure_ulcer', 'recent_fall']
        public readonly ?array $activeConditions = null,       // ['diabetes', 'chf', 'copd']

        // === Treatment/Therapy Context ===
        public readonly bool $hasRehabPotential = false,
        public readonly int $rehabPotentialScore = 0,          // 0-100
        public readonly bool $requiresExtensiveServices = false,
        public readonly ?array $extensiveServices = null,      // ['iv_therapy', 'wound_care']
        public readonly int $weeklyTherapyMinutes = 0,         // PT/OT/SLP combined

        // === Support Context ===
        public readonly int $caregiverAvailabilityScore = 0,   // 0=none, 5=24/7 available
        public readonly int $caregiverStressLevel = 0,         // 0=low, 4=very high/burnout
        public readonly bool $livesAlone = false,
        public readonly bool $caregiverRequiresRelief = false,
        public readonly int $socialSupportScore = 0,           // 0=isolated, 5=strong network

        // === Technology Readiness ===
        public readonly int $technologyReadiness = 0,          // 0=none, 3=tech-savvy
        public readonly bool $hasInternet = false,
        public readonly bool $hasPers = false,                 // Personal Emergency Response
        public readonly bool $suitableForRpm = false,          // Remote Patient Monitoring

        // === Environmental Context ===
        public readonly ?string $regionCode = null,            // Geographic region
        public readonly ?string $regionName = null,
        public readonly int $travelComplexityScore = 0,        // 0=easy, 3=very difficult
        public readonly bool $isRural = false,
        public readonly ?array $serviceAvailabilityFlags = null, // Services available in region

        // === Confidence & Completeness ===
        public readonly string $confidenceLevel = 'low',       // 'low', 'medium', 'high'
        public readonly ?array $missingDataFields = null,      // Fields we couldn't populate
        public readonly ?string $dataQualityNotes = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Helper Methods (Pure Data Accessors Only)
    |--------------------------------------------------------------------------
    |
    | NOTE ON SEPARATION OF CONCERNS:
    | This DTO is a PURE DATA CONTAINER. It should NOT contain business logic
    | or policy decisions. Specifically:
    |
    | - Scenario axis selection logic belongs in ScenarioAxisSelector (Section 6.3)
    | - Episode type derivation logic belongs in AssessmentIngestionService (Section 2.3)
    | - Rehab potential calculation belongs in AssessmentIngestionService (Section 2.3)
    |
    | The methods below are purely for data transformation/display, not policy.
    */

    /**
     * Get the confidence label for UI display.
     * (Pure data transformation, not policy)
     */
    public function getConfidenceLabel(): string
    {
        return match ($this->confidenceLevel) {
            'high' => 'High Confidence (Full HC Assessment)',
            'medium' => 'Medium Confidence (CA + supplementary data)',
            'low' => 'Low Confidence (Limited assessment data)',
            default => 'Unknown Confidence',
        };
    }

    /**
     * Check if profile has enough data for bundle generation.
     * (Data completeness check, not policy)
     */
    public function isSufficientForBundling(): bool
    {
        // Minimum: Either HC or CA assessment or referral data
        return $this->hasFullHcAssessment || $this->hasCaAssessment || $this->hasReferralData;
    }

    /**
     * Convert to array for API/LLM consumption (de-identified).
     * (Pure data transformation)
     *
     * NOTE: Does NOT include 'applicable_axes' - that is determined by
     * ScenarioAxisSelector at generation time, not stored in the profile.
     */
    public function toDeidentifiedArray(): array
    {
        return [
            'profile_version' => $this->profileVersion,
            'data_sources' => [
                'has_hc' => $this->hasFullHcAssessment,
                'has_ca' => $this->hasCaAssessment,
                'has_bmhs' => $this->hasBmhsAssessment,
                'completeness' => $this->dataCompletenessScore,
            ],
            'case_classification' => [
                'rug_group' => $this->rugGroup,
                'rug_category' => $this->rugCategory,
                'needs_cluster' => $this->needsCluster,
                'episode_type' => $this->episodeType,
            ],
            'functional_needs' => [
                'adl_level' => $this->adlSupportLevel,
                'iadl_level' => $this->iadlSupportLevel,
                'mobility_complexity' => $this->mobilityComplexity,
                'specific_adl_needs' => $this->specificAdlNeeds,
            ],
            'cognitive_behavioural' => [
                'cognitive_complexity' => $this->cognitiveComplexity,
                'behavioural_complexity' => $this->behaviouralComplexity,
                'mental_health_complexity' => $this->mentalHealthComplexity,
                'wandering_risk' => $this->hasWanderingRisk,
                'aggression_risk' => $this->hasAggressionRisk,
            ],
            'clinical_risks' => [
                'falls_risk' => $this->fallsRiskLevel,
                'skin_risk' => $this->skinIntegrityRisk,
                'pain_level' => $this->painManagementNeed,
                'continence' => $this->continenceSupport,
                'health_instability' => $this->healthInstability,
                'active_conditions' => $this->activeConditions,
            ],
            'treatment_context' => [
                'rehab_potential' => $this->hasRehabPotential,
                'rehab_score' => $this->rehabPotentialScore,
                'requires_extensive' => $this->requiresExtensiveServices,
                'weekly_therapy_minutes' => $this->weeklyTherapyMinutes,
            ],
            'support_context' => [
                'caregiver_availability' => $this->caregiverAvailabilityScore,
                'caregiver_stress' => $this->caregiverStressLevel,
                'lives_alone' => $this->livesAlone,
                'needs_respite' => $this->caregiverRequiresRelief,
                'social_support' => $this->socialSupportScore,
            ],
            'technology' => [
                'readiness' => $this->technologyReadiness,
                'has_internet' => $this->hasInternet,
                'rpm_suitable' => $this->suitableForRpm,
            ],
            'environment' => [
                'region_code' => $this->regionCode,
                'travel_complexity' => $this->travelComplexityScore,
                'is_rural' => $this->isRural,
            ],
            'confidence' => [
                'level' => $this->confidenceLevel,
                'label' => $this->getConfidenceLabel(),
            ],
            // NOTE: 'applicable_axes' is NOT included here.
            // Axis selection is policy logic that belongs in ScenarioAxisSelector.
        ];
    }
}
```

---

## 2. AssessmentIngestionService Interface

### 2.1 Service Interface

```php
<?php

namespace App\Services\BundleEngine;

use App\Models\Patient;
use App\Models\InterraiAssessment;
use App\Services\BundleEngine\DTOs\PatientNeedsProfile;

/**
 * AssessmentIngestionService
 *
 * Ingests assessment data from multiple sources and builds a unified
 * PatientNeedsProfile for bundle generation.
 *
 * Data Sources (in priority order):
 * 1. InterRAI HC (Home Care) - Full assessment, preferred
 * 2. InterRAI CA (Contact Assessment) - Rapid intake
 * 3. InterRAI BMHS - Behavioural/mental health supplement
 * 4. Hospital/OHAH referral data
 * 5. SPO/Family input (supplementary)
 *
 * Design Principles:
 * - HC is preferred when available (enables full RUG classification)
 * - CA alone is SUFFICIENT for first-phase bundling
 * - BMHS augments behavioural/MH complexity when present
 * - NEVER blocks bundling due to missing HC/RUG
 */
interface AssessmentIngestionServiceInterface
{
    /**
     * Build a PatientNeedsProfile from all available assessment data.
     *
     * @param Patient $patient The patient to build profile for
     * @return PatientNeedsProfile Unified needs profile
     */
    public function buildPatientNeedsProfile(Patient $patient): PatientNeedsProfile;

    /**
     * Build profile from a specific HC assessment.
     *
     * @param InterraiAssessment $hcAssessment Full HC assessment
     * @param InterraiAssessment|null $bmhsAssessment Optional BMHS
     * @return PatientNeedsProfile
     */
    public function buildFromHcAssessment(
        InterraiAssessment $hcAssessment,
        ?InterraiAssessment $bmhsAssessment = null
    ): PatientNeedsProfile;

    /**
     * Build profile from Contact Assessment only.
     * This is the "minimum viable" path for first-phase bundling.
     *
     * @param InterraiAssessment $caAssessment Contact Assessment
     * @param InterraiAssessment|null $bmhsAssessment Optional BMHS
     * @param array $referralData Optional hospital/OHAH referral data
     * @return PatientNeedsProfile
     */
    public function buildFromCaAssessment(
        InterraiAssessment $caAssessment,
        ?InterraiAssessment $bmhsAssessment = null,
        array $referralData = []
    ): PatientNeedsProfile;

    /**
     * Build profile from referral data only (emergency intake path).
     *
     * @param Patient $patient
     * @param array $referralData Hospital/OHAH referral data
     * @return PatientNeedsProfile Lower confidence profile
     */
    public function buildFromReferralOnly(
        Patient $patient,
        array $referralData
    ): PatientNeedsProfile;

    /**
     * Derive a "needs cluster" from CA data when HC/RUG unavailable.
     *
     * @param InterraiAssessment $caAssessment
     * @return string Cluster code (e.g., 'HIGH_ADL_LOW_COG', 'MH_COMPLEX')
     */
    public function deriveNeedsClusterFromCa(InterraiAssessment $caAssessment): string;

    /**
     * Augment an existing profile with BMHS data.
     *
     * @param PatientNeedsProfile $profile
     * @param InterraiAssessment $bmhsAssessment
     * @return PatientNeedsProfile Augmented profile
     */
    public function augmentWithBmhs(
        PatientNeedsProfile $profile,
        InterraiAssessment $bmhsAssessment
    ): PatientNeedsProfile;
}
```

### 2.2 Assessment Mapping Rules

> ⚠️ **IMPLEMENTATION NOTE:** The field names below are based on the current `InterraiAssessment` model.
> Before implementation, **verify exact field names and semantics** from:
> - `app/Models/InterraiAssessment.php` (summary fields)
> - `raw_items` JSON structure (detailed iCODE items)
> - CA-specific item naming (may differ from HC)

#### HC Assessment → PatientNeedsProfile Mapping

| InterraiAssessment Field | Profile Field | Transformation | Verify |
|--------------------------|---------------|----------------|--------|
| `adl_hierarchy` (0-6) | `adlSupportLevel` | Direct mapping | ✓ Confirmed |
| `iadl_difficulty` (0-6) | `iadlSupportLevel` | Direct mapping | ✓ Confirmed |
| `cognitive_performance_scale` (0-6) | `cognitiveComplexity` | Direct mapping | ✓ Confirmed |
| `depression_rating_scale` (0-14) | `mentalHealthComplexity` | Score 0-3 → 0, 4-7 → 1, 8-11 → 2, 12+ → 3 | ✓ Confirmed |
| `chess_score` (0-5) | `healthInstability` | Direct mapping | ✓ Confirmed |
| `pain_scale` (0-3) | `painManagementNeed` | Direct mapping | ✓ Confirmed |
| `falls_in_last_90_days` (bool) | `fallsRiskLevel` | false=0, true=1; +injury flag in raw_items=2 | ✓ Confirmed |
| `wandering_flag` (bool) | `hasWanderingRisk` | Direct mapping | ✓ Confirmed |
| `maple_score` (1-5 string) | Used for acuity derivation | See Section 2.4 | ✓ Confirmed |
| `raw_items['therapy_pt']` | `weeklyTherapyMinutes` | Sum PT+OT+SLP minutes | ⚠️ Verify keys |
| `raw_items['caregiver_lives_with']` | `caregiverAvailabilityScore` | Mapping rules below | ⚠️ Verify keys |
| `raw_items['caregiver_stress']` | `caregiverStressLevel` | Mapping rules below | ⚠️ Verify keys |

**Therapy Minutes Calculation (⚠️ Verify raw_items keys):**
```php
$weeklyTherapyMinutes = 
    ($raw['therapy_pt'] ?? 0) +
    ($raw['therapy_ot'] ?? 0) +
    ($raw['therapy_slp'] ?? 0);
```

**Caregiver Score Derivation:**
```php
$caregiverAvailabilityScore = match (true) {
    !isset($raw['caregiver_lives_with']) => 0,           // Unknown
    $raw['caregiver_lives_with'] && $raw['caregiver_daily'] => 5,  // 24/7
    $raw['caregiver_lives_with'] => 4,                    // Lives with
    $raw['caregiver_daily'] => 3,                         // Daily visits
    $raw['caregiver_weekly'] => 2,                        // Weekly
    default => 1,                                          // Minimal
};
```

#### CA Assessment → PatientNeedsProfile Mapping (Subset)

> ⚠️ **CA uses different item naming than HC.** Verify exact keys before implementation.

| CA Field (Verify Name) | Profile Field | Notes |
|------------------------|---------------|-------|
| `raw_items['ca_adl_*']` | `adlSupportLevel` | Capacity questions, not self-performance |
| `raw_items['ca_iadl_*']` | `iadlSupportLevel` | Reduced precision vs HC |
| `raw_items['ca_cognitive_*']` | `cognitiveComplexity` | Screening items, not full CPS |
| `raw_items['ca_falls']` | `fallsRiskLevel` | Binary → level (0 or 1 only) |
| `raw_items['ca_mood_*']` | `mentalHealthComplexity` | Basic screening only |
| `raw_items['ca_living_situation']` | `livesAlone` | Direct mapping |
| — | `needsCluster` | Derived cluster (see 2.4) |
| — | `confidenceLevel` | Always 'medium' for CA-only |

#### BMHS Augmentation

When BMHS is present, it **augments** (does not replace) existing profile fields:

| BMHS Field (Verify Name) | Profile Field | Augmentation Rule |
|--------------------------|---------------|-------------------|
| `raw_items['bmhs_behaviour_*']` | `behaviouralComplexity` | Max of existing + BMHS-derived |
| `raw_items['bmhs_aggression']` | `hasAggressionRisk` | Set true if indicated |
| `raw_items['bmhs_mh_diagnosis']` | `mentalHealthComplexity` | Increase if MH diagnoses present |
| `raw_items['bmhs_substance']` | `behaviouralFlags` | Add 'substance_use' flag |
| `raw_items['bmhs_self_harm']` | `clinicalRiskFlags` | Add 'self_harm_risk' flag |

### 2.3 Episode Type & Rehab Potential Derivation

> ⚠️ **CRITICAL:** These fields drive scenario selection. They must be derived via **explicit rules**, not guessed or randomly set.

#### Episode Type Rules

`episodeType` indicates the patient's care trajectory. It is derived from referral data and assessment history.

| Condition | Episode Type | Priority | Source |
|-----------|--------------|----------|--------|
| Hospital discharge ≤30 days ago | `post_acute` | 1 | Referral `discharge_date` |
| Post-surgical referral type | `post_acute` | 1 | Referral `referral_type` |
| Stroke/cardiac event referral | `post_acute` | 1 | Referral `referral_type` |
| LTC bundled program enrollment | `complex_continuing` | 2 | Patient `program_enrollment` |
| Active service >6 months + stable MAPLe | `chronic` | 3 | Service history + assessment |
| CHESS ≥3 + recent hospitalization | `acute_exacerbation` | 2 | Assessment + referral |
| Palliative/end-of-life flag | `palliative` | 1 | Patient flags |
| **Default (no matches)** | `complex_continuing` | 4 | Safe assumption for LTC bundled |

```php
/**
 * Derive episode type from available data.
 * Rules are evaluated in priority order; first match wins.
 */
public function deriveEpisodeType(
    Patient $patient,
    ?InterraiAssessment $assessment,
    ?array $referralData
): string {
    // Priority 1: Explicit referral indicators
    if ($referralData) {
        $dischargeDate = $referralData['discharge_date'] ?? null;
        if ($dischargeDate && Carbon::parse($dischargeDate)->diffInDays(now()) <= 30) {
            return 'post_acute';
        }
        
        $referralType = $referralData['referral_type'] ?? null;
        if (in_array($referralType, ['post_surgical', 'stroke', 'cardiac', 'hip_fracture'])) {
            return 'post_acute';
        }
    }
    
    // Priority 1: Palliative flag
    if ($patient->is_palliative ?? false) {
        return 'palliative';
    }
    
    // Priority 2: Acute exacerbation
    if ($assessment?->chess_score >= 3 && $this->hasRecentHospitalization($patient)) {
        return 'acute_exacerbation';
    }
    
    // Priority 2: LTC bundled program
    if ($patient->isEnrolledInProgram('ltc_bundled')) {
        return 'complex_continuing';
    }
    
    // Priority 3: Chronic stable
    if ($this->isChronicStable($patient, $assessment)) {
        return 'chronic';
    }
    
    // Priority 4: Default
    return 'complex_continuing';
}
```

#### Rehab Potential Score Rules

`rehabPotentialScore` (0-100) indicates likelihood of functional improvement. `hasRehabPotential` is derived as `rehabPotentialScore >= 40`.

| Factor | Points | Source | Notes |
|--------|--------|--------|-------|
| Post-surgical/stroke/hip fracture referral | +30 | Referral type | Strong rehab indicator |
| Therapy minutes ≥120/week in HC | +25 | `raw_items['therapy_*']` | Already receiving rehab |
| Therapy minutes 60-119/week | +15 | `raw_items['therapy_*']` | Some rehab |
| ADL improved from previous assessment | +20 | Assessment history | Demonstrates improvement capacity |
| Age <75 with CPS ≤2 | +15 | Demographics + CPS | Cognitive capacity for rehab |
| Age 75-84 with CPS ≤2 | +10 | Demographics + CPS | Reduced but present |
| Stated rehab goals in referral | +10 | Referral narrative | Patient/family motivation |
| MAPLe 1-3 (lower acuity) | +10 | Assessment | More capacity for improvement |
| Recent decline (CHESS ≥3) | -10 | Assessment | May limit rehab tolerance |
| Cognitive impairment (CPS ≥4) | -15 | Assessment | Limits rehab participation |

```php
/**
 * Calculate rehab potential score.
 * Score 0-100; hasRehabPotential = (score >= 40)
 */
public function calculateRehabPotentialScore(
    Patient $patient,
    ?InterraiAssessment $assessment,
    ?array $referralData
): int {
    $score = 0;
    
    // Referral type indicators
    $referralType = $referralData['referral_type'] ?? null;
    if (in_array($referralType, ['post_surgical', 'stroke', 'hip_fracture', 'cardiac_rehab'])) {
        $score += 30;
    }
    
    // Therapy minutes (from HC assessment)
    $therapyMinutes = $this->getWeeklyTherapyMinutes($assessment);
    if ($therapyMinutes >= 120) {
        $score += 25;
    } elseif ($therapyMinutes >= 60) {
        $score += 15;
    }
    
    // ADL improvement
    if ($this->hasAdlImprovement($patient)) {
        $score += 20;
    }
    
    // Age and cognition
    $age = $patient->age ?? 100;
    $cps = $assessment?->cognitive_performance_scale ?? 0;
    if ($age < 75 && $cps <= 2) {
        $score += 15;
    } elseif ($age < 85 && $cps <= 2) {
        $score += 10;
    }
    
    // Rehab goals stated
    if ($referralData['has_rehab_goals'] ?? false) {
        $score += 10;
    }
    
    // MAPLe (lower = more capacity)
    $maple = (int) ($assessment?->maple_score ?? 5);
    if ($maple <= 3) {
        $score += 10;
    }
    
    // Penalties
    if (($assessment?->chess_score ?? 0) >= 3) {
        $score -= 10;
    }
    if ($cps >= 4) {
        $score -= 15;
    }
    
    return max(0, min(100, $score));
}
```

#### Default Values for Missing Data

When data is unavailable, use these safe defaults:

| Field | Default | Rationale |
|-------|---------|-----------|
| `episodeType` | `'complex_continuing'` | Safe assumption for LTC bundled clients |
| `rehabPotentialScore` | `0` | Conservative; don't assume rehab without evidence |
| `hasRehabPotential` | `false` | Derived from score ≥40 |

---

### 2.4 Needs Cluster Derivation (CA-Only Path)

When HC is not available, derive a "Needs Cluster" from CA items:

```php
/**
 * Needs Clusters (when RUG unavailable)
 *
 * These are NOT RUG groups - they are simplified groupings
 * sufficient for first-phase bundling from CA data only.
 */
enum NeedsCluster: string
{
    // Physical function primary
    case HIGH_ADL = 'HIGH_ADL';           // ADL capacity 4+ = high physical dependency
    case MODERATE_ADL = 'MODERATE_ADL';   // ADL capacity 2-3
    case LOW_ADL = 'LOW_ADL';             // ADL capacity 0-1

    // Cognitive primary
    case COGNITIVE_COMPLEX = 'COGNITIVE_COMPLEX';   // Cognitive screen indicates impairment
    case MH_COMPLEX = 'MH_COMPLEX';                 // Mental health/behavioural primary

    // Medical complexity primary
    case MEDICAL_COMPLEX = 'MEDICAL_COMPLEX';       // Multiple conditions, high CHESS
    case POST_ACUTE = 'POST_ACUTE';                 // Hospital discharge, rehab potential

    // Combined
    case HIGH_ADL_COGNITIVE = 'HIGH_ADL_COGNITIVE'; // Both physical and cognitive
    case GENERAL = 'GENERAL';                       // Low complexity, general support
}

/**
 * Cluster derivation logic:
 */
public function deriveNeedsClusterFromCa(InterraiAssessment $ca): string
{
    $adlLevel = $this->estimateAdlFromCa($ca);
    $hasCoginitive = $this->hasCognitiveIndicators($ca);
    $hasMentalHealth = $this->hasMentalHealthIndicators($ca);
    $isPostAcute = $this->isPostAcuteReferral($ca);

    // Priority-based assignment
    if ($isPostAcute) {
        return NeedsCluster::POST_ACUTE->value;
    }
    if ($adlLevel >= 4 && $hasCoginitive) {
        return NeedsCluster::HIGH_ADL_COGNITIVE->value;
    }
    if ($adlLevel >= 4) {
        return NeedsCluster::HIGH_ADL->value;
    }
    if ($hasCoginitive) {
        return NeedsCluster::COGNITIVE_COMPLEX->value;
    }
    if ($hasMentalHealth) {
        return NeedsCluster::MH_COMPLEX->value;
    }
    if ($adlLevel >= 2) {
        return NeedsCluster::MODERATE_ADL->value;
    }

    return NeedsCluster::GENERAL->value;
}
```

---

## 3. RUG Algorithm Evolution

### 3.1 Integration Notes for CC21_RUG_Algorithm_Pseudocode

The existing RUG algorithm in `CC21_RUG_Algorithm_Pseudocode.md` should be updated with these rules:

#### Rule 1: HC Present → Full RUG Classification

```php
// Existing flow (unchanged)
if ($patient->hasCurrentHcAssessment()) {
    $rug = $this->rugClassificationService->classify($assessment);
    $profile->rugGroup = $rug->rug_group;
    $profile->rugCategory = $rug->rug_category;
    $profile->confidenceLevel = 'high';
}
```

#### Rule 2: No HC, CA Present → Needs Cluster

```php
// NEW: Derive needs cluster from CA
if (!$patient->hasCurrentHcAssessment() && $patient->hasCurrentCaAssessment()) {
    $profile->needsCluster = $this->deriveNeedsClusterFromCa($caAssessment);
    $profile->rugGroup = null;  // Cannot derive RUG from CA
    $profile->confidenceLevel = 'medium';

    // Map needs cluster to approximate RUG category for template selection
    $profile->approximateRugCategory = $this->mapClusterToRugCategory($profile->needsCluster);
}
```

#### Rule 3: BMHS Augments Behavioural Profile

```php
// NEW: BMHS augmentation
if ($bmhsAssessment !== null) {
    $profile = $this->augmentWithBmhs($profile, $bmhsAssessment);
    // May upgrade confidence if it fills gaps
    if ($profile->confidenceLevel === 'low') {
        $profile->confidenceLevel = 'medium';
    }
}
```

#### Rule 4: NEVER Block Bundling

```php
// CRITICAL: Always allow bundling
public function canGenerateBundles(PatientNeedsProfile $profile): bool
{
    // ANY of these conditions is sufficient
    return $profile->hasFullHcAssessment
        || $profile->hasCaAssessment
        || $profile->hasReferralData;
}
```

### 3.2 Template Selection Changes

Update `CC21_RUG_Bundle_Templates.md` to support needs cluster selection:

```php
class CareBundleTemplateRepository
{
    /**
     * Find templates for a patient profile.
     * Works with RUG groups OR needs clusters.
     */
    public function findTemplatesForProfile(PatientNeedsProfile $profile): Collection
    {
        if ($profile->rugGroup !== null) {
            // Full RUG path (existing logic)
            return $this->findByRugGroup($profile->rugGroup, $profile);
        }

        if ($profile->needsCluster !== null) {
            // Needs cluster path (new)
            return $this->findByNeedsCluster($profile->needsCluster, $profile);
        }

        // Fallback: general templates
        return $this->findGeneralTemplates($profile);
    }

    /**
     * Map needs clusters to template categories.
     */
    private function findByNeedsCluster(string $cluster, PatientNeedsProfile $profile): Collection
    {
        $rugCategories = match ($cluster) {
            'HIGH_ADL', 'HIGH_ADL_COGNITIVE' => ['Reduced Physical Function', 'Special Care'],
            'COGNITIVE_COMPLEX' => ['Impaired Cognition'],
            'MH_COMPLEX' => ['Behaviour Problems', 'Impaired Cognition'],
            'POST_ACUTE' => ['Special Rehabilitation', 'Clinically Complex'],
            'MEDICAL_COMPLEX' => ['Clinically Complex', 'Special Care'],
            default => ['Reduced Physical Function'],
        };

        return CareBundleTemplate::whereIn('rug_category', $rugCategories)
            ->where('min_adl_sum', '<=', $profile->adlSupportLevel * 2 + 4) // Approximate mapping
            ->get();
    }
}
```

---

## 4. Dynamic Scenario Bundle Generator

### 4.1 Scenario Axis Framework

Scenarios are organized around **patient-experience axes**, not clinical/budget dichotomies:

```php
/**
 * Scenario Axes - Patient Experience Orientations
 *
 * Each axis represents a different emphasis for the care bundle.
 * Patients typically have 2-4 applicable axes based on their profile.
 */
enum ScenarioAxis: string
{
    // === Primary Axes ===

    case RECOVERY_REHAB = 'recovery_rehab';
    // Emphasis: Therapy intensity, mobility goals, function restoration
    // Suitable for: Post-acute, rehab potential, therapy minutes > 0
    // Services: Heavy PT/OT/SLP, goal-focused nursing, activation

    case SAFETY_STABILITY = 'safety_stability';
    // Emphasis: Fall prevention, crisis avoidance, daily functioning
    // Suitable for: Fall risk, health instability, cognitive impairment
    // Services: Daily PSW, nursing monitoring, PERS, safety assessments

    case TECH_ENABLED = 'tech_enabled';
    // Emphasis: Remote monitoring, telehealth, reduced in-person visits
    // Suitable for: Tech-ready, stable condition, caregiver available
    // Services: RPM, virtual check-ins, telehealth nursing, PERS

    case CAREGIVER_RELIEF = 'caregiver_relief';
    // Emphasis: Respite, homemaking, family coaching, adult day programs
    // Suitable for: High caregiver stress, caregiver-dependent patient
    // Services: Respite hours, homemaking, day programs, caregiver education

    // === Secondary/Hybrid Axes ===

    case MEDICAL_INTENSIVE = 'medical_intensive';
    // Emphasis: High nursing frequency, clinical treatments
    // Suitable for: Extensive services, wound care, IV therapy
    // Services: Shift nursing, wound care, respiratory therapy

    case COGNITIVE_SUPPORT = 'cognitive_support';
    // Emphasis: Cognitive stimulation, behavioural support, structure
    // Suitable for: CPS 3+, behavioural issues, dementia
    // Services: Behavioural PSW, activation, structured routines

    case COMMUNITY_INTEGRATED = 'community_integrated';
    // Emphasis: Social engagement, transportation, community programs
    // Suitable for: Socially isolated, IADL-focused needs
    // Services: Adult day, transportation, meal programs, social visits

    case BALANCED = 'balanced';
    // Emphasis: Balanced mix across all areas
    // Default/baseline for all patients
    // Services: Mix of nursing, PSW, CSS as appropriate
}
```

### 4.2 ScenarioBundleDTO

```php
<?php

namespace App\Services\BundleEngine\DTOs;

/**
 * ScenarioBundleDTO
 *
 * Represents a single bundle scenario with patient-experience framing.
 */
class ScenarioBundleDTO
{
    public function __construct(
        // === Scenario Identity ===
        public readonly string $scenarioId,
        public readonly string $scenarioLabel,          // e.g., "Recovery-Focused Care"
        public readonly string $scenarioDescription,    // 1-2 sentences, patient-oriented
        public readonly ScenarioAxis $primaryAxis,
        public readonly ?ScenarioAxis $secondaryAxis = null,

        // === Service Configuration ===
        /** @var array<ScenarioServiceLine> */
        public readonly array $services,                // Array of service lines
        public readonly int $totalServicesCount,
        public readonly int $inPersonVisitsPerWeek,
        public readonly int $remoteContactsPerWeek,

        // === Cost Annotation ===
        public readonly int $estimatedWeeklyCostCents,
        public readonly int $referenceCap = 500000,     // $5,000 default
        public readonly string $costStatus,             // 'within_cap', 'near_cap', 'over_cap'
        public readonly ?float $costCapRatio = null,    // e.g., 0.85, 1.05
        public readonly ?string $costNote = null,       // Trade-off note for UI/LLM

        // === Operational Characteristics ===
        public readonly string $deliveryBalance,        // 'in_person_heavy', 'remote_heavy', 'balanced'
        public readonly float $inPersonPercentage,      // 0.0-1.0
        public readonly int $estimatedStaffHoursPerWeek,
        public readonly ?string $operationalNote = null,

        // === Patient Experience Notes ===
        public readonly ?string $patientExperienceNote = null,
        public readonly ?array $emphasizedGoals = null, // ['mobility', 'independence', 'safety']
        public readonly ?array $tradeOffs = null,       // Explicit trade-offs for this scenario

        // === Metadata ===
        public readonly ?string $templateCode = null,   // If derived from a specific template
        public readonly ?string $rugGroup = null,
        public readonly bool $meetsMinimumSafety = true,
        public readonly ?array $safetyFlags = null,     // Any safety concerns for review
    ) {}

    /**
     * Get a short summary for UI display.
     */
    public function getShortSummary(): string
    {
        return sprintf(
            "%s – %d services, ~$%d/week (%s)",
            $this->scenarioLabel,
            $this->totalServicesCount,
            $this->estimatedWeeklyCostCents / 100,
            $this->costStatus
        );
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'label' => $this->scenarioLabel,
            'description' => $this->scenarioDescription,
            'primary_axis' => $this->primaryAxis->value,
            'secondary_axis' => $this->secondaryAxis?->value,

            'services' => array_map(fn($s) => $s->toArray(), $this->services),
            'service_summary' => [
                'total_count' => $this->totalServicesCount,
                'in_person_visits_per_week' => $this->inPersonVisitsPerWeek,
                'remote_contacts_per_week' => $this->remoteContactsPerWeek,
            ],

            'cost' => [
                'estimated_weekly_cents' => $this->estimatedWeeklyCostCents,
                'estimated_weekly_dollars' => $this->estimatedWeeklyCostCents / 100,
                'reference_cap_dollars' => $this->referenceCap / 100,
                'status' => $this->costStatus,
                'cap_ratio' => $this->costCapRatio,
                'note' => $this->costNote,
            ],

            'operations' => [
                'delivery_balance' => $this->deliveryBalance,
                'in_person_percentage' => round($this->inPersonPercentage * 100),
                'staff_hours_per_week' => $this->estimatedStaffHoursPerWeek,
                'note' => $this->operationalNote,
            ],

            'patient_experience' => [
                'note' => $this->patientExperienceNote,
                'emphasized_goals' => $this->emphasizedGoals,
                'trade_offs' => $this->tradeOffs,
            ],

            'safety' => [
                'meets_minimum' => $this->meetsMinimumSafety,
                'flags' => $this->safetyFlags,
            ],
        ];
    }
}
```

### 4.3 ScenarioServiceLine DTO

```php
<?php

namespace App\Services\BundleEngine\DTOs;

/**
 * ScenarioServiceLine
 *
 * A single service within a scenario bundle.
 */
class ScenarioServiceLine
{
    public function __construct(
        public readonly int $serviceTypeId,
        public readonly string $serviceCode,
        public readonly string $serviceName,
        public readonly string $serviceCategory,        // 'nursing', 'psw', 'therapy', 'css', 'remote'

        public readonly int $frequencyPerWeek,
        public readonly int $durationMinutes,
        public readonly int $weeklyMinutes,             // frequency * duration
        public readonly int $costPerVisitCents,
        public readonly int $weeklyTotalCents,

        public readonly string $deliveryMode,           // 'in_person', 'remote', 'hybrid'
        public readonly string $priorityLevel,          // 'core', 'recommended', 'optional'

        public readonly ?string $rationale = null,      // Why included in this scenario
        public readonly ?array $adjustmentNotes = null, // How it differs from baseline
    ) {}

    public function toArray(): array
    {
        return [
            'service_type_id' => $this->serviceTypeId,
            'service_code' => $this->serviceCode,
            'service_name' => $this->serviceName,
            'category' => $this->serviceCategory,
            'frequency_per_week' => $this->frequencyPerWeek,
            'duration_minutes' => $this->durationMinutes,
            'weekly_minutes' => $this->weeklyMinutes,
            'cost_per_visit' => $this->costPerVisitCents / 100,
            'weekly_total' => $this->weeklyTotalCents / 100,
            'delivery_mode' => $this->deliveryMode,
            'priority' => $this->priorityLevel,
            'rationale' => $this->rationale,
        ];
    }
}
```

### 4.4 ScenarioGenerator Interface

```php
<?php

namespace App\Services\BundleEngine;

use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;

/**
 * ScenarioGeneratorInterface
 *
 * Generates 3-5 bundle scenarios for a patient based on their needs profile.
 *
 * Design Principles:
 * - Patient-experience oriented (not budget-vs-clinical)
 * - Each scenario meets minimum safety requirements
 * - Scenarios vary in emphasis, not quality
 * - Cost is annotated, not constraining
 */
interface ScenarioGeneratorInterface
{
    /**
     * Generate scenario bundles for a patient.
     *
     * @param PatientNeedsProfile $profile Patient's needs profile
     * @param array $options Optional configuration
     * @return array{scenarios: ScenarioBundleDTO[], generation_metadata: array}
     */
    public function generateScenarios(
        PatientNeedsProfile $profile,
        array $options = []
    ): array;

    /**
     * Get available scenario axes for a profile.
     *
     * @param PatientNeedsProfile $profile
     * @return array<ScenarioAxis>
     */
    public function getApplicableAxes(PatientNeedsProfile $profile): array;

    /**
     * Generate a single scenario for a specific axis.
     *
     * @param PatientNeedsProfile $profile
     * @param ScenarioAxis $primaryAxis
     * @param ScenarioAxis|null $secondaryAxis
     * @return ScenarioBundleDTO
     */
    public function generateScenarioForAxis(
        PatientNeedsProfile $profile,
        ScenarioAxis $primaryAxis,
        ?ScenarioAxis $secondaryAxis = null
    ): ScenarioBundleDTO;

    /**
     * Validate a scenario meets minimum safety requirements.
     *
     * @param ScenarioBundleDTO $scenario
     * @param PatientNeedsProfile $profile
     * @return array{passes: bool, violations: array, warnings: array}
     */
    public function validateScenarioSafety(
        ScenarioBundleDTO $scenario,
        PatientNeedsProfile $profile
    ): array;
}
```

### 4.5 Scenario Generation Logic

```php
/**
 * ScenarioGenerator Implementation Notes
 *
 * The generator produces 3-5 scenarios per patient by:
 * 1. Identifying applicable axes from the patient profile
 * 2. Generating a scenario for each primary axis
 * 3. Creating 1-2 hybrid scenarios combining complementary axes
 * 4. Ensuring each scenario meets safety minimums
 * 5. Annotating cost/operational characteristics
 */
class ScenarioGenerator implements ScenarioGeneratorInterface
{
    /**
     * Minimum service requirements for safety (varies by profile).
     */
    private function getMinimumSafetyRequirements(PatientNeedsProfile $profile): array
    {
        $minimums = [];

        // All patients: minimum case management
        $minimums['case_management'] = ['min_frequency' => 1, 'category' => 'case_mgmt'];

        // ADL-dependent: minimum PSW
        if ($profile->adlSupportLevel >= 3) {
            $minimums['psw'] = [
                'min_frequency' => max(7, $profile->adlSupportLevel * 2),
                'category' => 'psw',
            ];
        }

        // Clinical complexity: minimum nursing
        if ($profile->healthInstability >= 2 || $profile->requiresExtensiveServices) {
            $minimums['nursing'] = ['min_frequency' => 3, 'category' => 'nursing'];
        }

        // Fall risk: safety monitoring
        if ($profile->fallsRiskLevel >= 2) {
            $minimums['safety_monitoring'] = ['type' => 'rpm_or_pers', 'category' => 'remote'];
        }

        return $minimums;
    }

    /**
     * Service modulation by axis.
     */
    private function getAxisServiceModifiers(ScenarioAxis $axis): array
    {
        return match ($axis) {
            ScenarioAxis::RECOVERY_REHAB => [
                'therapy' => ['multiplier' => 1.5, 'priority' => 'core'],
                'activation' => ['multiplier' => 1.3, 'priority' => 'recommended'],
                'nursing' => ['multiplier' => 1.0, 'priority' => 'core'],
                'psw' => ['multiplier' => 0.9, 'priority' => 'core'],
            ],

            ScenarioAxis::SAFETY_STABILITY => [
                'nursing' => ['multiplier' => 1.3, 'priority' => 'core'],
                'psw' => ['multiplier' => 1.2, 'priority' => 'core'],
                'remote_monitoring' => ['multiplier' => 1.5, 'priority' => 'recommended'],
                'therapy' => ['multiplier' => 0.8, 'priority' => 'recommended'],
            ],

            ScenarioAxis::TECH_ENABLED => [
                'remote_monitoring' => ['multiplier' => 2.0, 'priority' => 'core'],
                'telehealth' => ['multiplier' => 1.5, 'priority' => 'core'],
                'nursing' => ['multiplier' => 0.7, 'priority' => 'recommended'],
                'psw' => ['multiplier' => 0.8, 'priority' => 'recommended'],
            ],

            ScenarioAxis::CAREGIVER_RELIEF => [
                'respite' => ['multiplier' => 2.0, 'priority' => 'core'],
                'homemaking' => ['multiplier' => 1.5, 'priority' => 'core'],
                'day_program' => ['multiplier' => 1.5, 'priority' => 'recommended'],
                'caregiver_education' => ['multiplier' => 1.0, 'priority' => 'core'],
            ],

            default => [], // Balanced uses template defaults
        };
    }
}
```

---

## 5. Cost Annotation Strategy

### 5.1 Cost as Reference (NOT Constraint)

```php
/**
 * CostAnnotationService
 *
 * Annotates scenarios with cost information for transparency,
 * WITHOUT using cost as a constraint or filter.
 *
 * Key Principle: $5,000/week is a REFERENCE POINT, not a hard cap.
 */
class CostAnnotationService
{
    private const REFERENCE_CAP_CENTS = 500000; // $5,000

    /**
     * Annotate a scenario with cost information.
     */
    public function annotateScenario(ScenarioBundleDTO $scenario): ScenarioBundleDTO
    {
        $weeklyTotal = $this->calculateWeeklyTotal($scenario);
        $capRatio = $weeklyTotal / self::REFERENCE_CAP_CENTS;

        $costStatus = match (true) {
            $capRatio <= 0.90 => 'within_cap',
            $capRatio <= 1.10 => 'near_cap',
            default => 'over_cap',
        };

        $costNote = $this->generateCostNote($scenario, $capRatio);

        return $scenario->withCostAnnotation(
            estimatedWeeklyCostCents: $weeklyTotal,
            costStatus: $costStatus,
            costCapRatio: round($capRatio, 2),
            costNote: $costNote
        );
    }

    /**
     * Generate cost note (NOT "budget warning" language).
     */
    private function generateCostNote(ScenarioBundleDTO $scenario, float $ratio): ?string
    {
        // Frame positively based on what the investment provides
        if ($ratio > 1.0) {
            return match ($scenario->primaryAxis) {
                ScenarioAxis::RECOVERY_REHAB =>
                    "Front-loaded therapy investment may shorten episode duration",
                ScenarioAxis::SAFETY_STABILITY =>
                    "Enhanced safety monitoring to prevent ED visits and falls",
                ScenarioAxis::MEDICAL_INTENSIVE =>
                    "Intensive clinical care appropriate for medical complexity",
                default =>
                    "Higher-touch approach for this patient's needs",
            };
        }

        if ($ratio <= 0.85) {
            return match ($scenario->primaryAxis) {
                ScenarioAxis::TECH_ENABLED =>
                    "Tech-enabled approach enables efficient remote monitoring",
                default =>
                    "Efficient service mix within typical funding envelope",
            };
        }

        return null; // Near cap, no special note needed
    }
}
```

### 5.2 Trade-Off Annotations (Patient-Centered)

```php
/**
 * Trade-off annotations for scenarios.
 * These explain what the patient GAINS, not what they're "losing."
 */
private function generateTradeOffs(ScenarioBundleDTO $scenario): array
{
    return match ($scenario->primaryAxis) {
        ScenarioAxis::RECOVERY_REHAB => [
            'emphasis' => 'Prioritizes recovery and function restoration',
            'approach' => 'More therapy sessions to accelerate progress',
            'consideration' => 'Best for patients with clear rehab goals and potential',
        ],

        ScenarioAxis::SAFETY_STABILITY => [
            'emphasis' => 'Prioritizes daily safety and crisis prevention',
            'approach' => 'Consistent daily support and monitoring',
            'consideration' => 'Best for patients at risk of falls or health instability',
        ],

        ScenarioAxis::TECH_ENABLED => [
            'emphasis' => 'Leverages technology for continuous oversight',
            'approach' => 'Remote monitoring with targeted in-person visits',
            'consideration' => 'Best for tech-comfortable patients with reliable connectivity',
        ],

        ScenarioAxis::CAREGIVER_RELIEF => [
            'emphasis' => 'Supports both patient and family caregiver',
            'approach' => 'Includes respite and family support services',
            'consideration' => 'Best when family caregiver is integral to care plan',
        ],

        default => [],
    };
}
```

---

## 6. UI/UX Design for Bundle Scenarios

### 6.1 Scenario Selection Interface

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Bundle Options for Patient #12345                                           │
│ Assessment: InterRAI HC • RUG: CB0 (Clinically Complex)                     │
│ Profile Confidence: High                                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─ Scenario 1 ─────────────────────────────────────────────────────────┐  │
│  │ 🔄 RECOVERY-FOCUSED CARE                                              │  │
│  │ ──────────────────────────────────────────────────────────────────── │  │
│  │ Prioritizes therapy and function restoration with intensive          │  │
│  │ PT/OT services to support recovery goals.                            │  │
│  │                                                                       │  │
│  │ KEY SERVICES                           COST                          │  │
│  │ • PT 3x/week (60 min)                  Est: $4,850/week             │  │
│  │ • OT 2x/week (60 min)                  Status: Within reference     │  │
│  │ • Nursing 3x/week (45 min)             ━━━━━━━━━━━░░ 97%            │  │
│  │ • PSW 10x/week (60 min)                                              │  │
│  │                                                                       │  │
│  │ 📍 15 in-person visits/week • 4 remote contacts                      │  │
│  │                                                                       │  │
│  │ [View Details]  [Select This Scenario]                               │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  ┌─ Scenario 2 ─────────────────────────────────────────────────────────┐  │
│  │ 🛡️ SAFETY & STABILITY                                                 │  │
│  │ ──────────────────────────────────────────────────────────────────── │  │
│  │ Maximizes daily functioning and fall prevention with consistent      │  │
│  │ PSW support and nursing monitoring.                                  │  │
│  │                                                                       │  │
│  │ KEY SERVICES                           COST                          │  │
│  │ • Nursing 5x/week (45 min)             Est: $5,120/week             │  │
│  │ • PSW 14x/week (60 min)                Status: Near reference        │  │
│  │ • RPM daily monitoring                 ━━━━━━━━━━━━░ 102%           │  │
│  │ • Case Mgmt 2x/week                                                  │  │
│  │                                                                       │  │
│  │ 📍 19 in-person visits/week • 7 remote contacts                      │  │
│  │                                                                       │  │
│  │ [View Details]  [Select This Scenario]                               │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  ┌─ Scenario 3 ─────────────────────────────────────────────────────────┐  │
│  │ 📱 TECH-ENABLED CARE                                                  │  │
│  │ ──────────────────────────────────────────────────────────────────── │  │
│  │ Leverages remote monitoring and telehealth for continuous            │  │
│  │ oversight with targeted in-person visits.                            │  │
│  │                                                                       │  │
│  │ KEY SERVICES                           COST                          │  │
│  │ • RPM 7x/week                          Est: $3,980/week             │  │
│  │ • Telehealth nursing 3x/week           Status: Within reference     │  │
│  │ • PSW 10x/week (60 min)                ━━━━━━━━░░░░░ 80%            │  │
│  │ • In-person nursing 2x/week                                          │  │
│  │                                                                       │  │
│  │ 📍 12 in-person visits/week • 10 remote contacts                     │  │
│  │                                                                       │  │
│  │ Note: Tech-enabled approach enables efficient remote monitoring      │  │
│  │                                                                       │  │
│  │ [View Details]  [Select This Scenario]                               │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  [+ Show More Scenarios]                                                    │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 6.2 Scenario Detail View

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 🔄 RECOVERY-FOCUSED CARE – Full Details                              [✕]   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│ PATIENT EXPERIENCE                                                          │
│ ─────────────────────────────────────────────────────────────────────────── │
│ This scenario prioritizes recovery and function restoration. With           │
│ intensive therapy support, the goal is to maximize the patient's            │
│ potential for returning to their baseline function level.                   │
│                                                                             │
│ EMPHASIZED GOALS                                                            │
│ ✓ Mobility improvement       ✓ ADL independence       ✓ Strength building  │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│ SERVICES                                                                    │
│ ─────────────────────────────────────────────────────────────────────────── │
│                                                                             │
│ THERAPY (Core)                                                              │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ Service          │ Freq/wk │ Duration │ Weekly Cost │ Mode            │ │
│ ├─────────────────────────────────────────────────────────────────────────┤ │
│ │ Physiotherapy    │ 3       │ 60 min   │ $450        │ In-person       │ │
│ │ Occupational Th. │ 2       │ 60 min   │ $300        │ In-person       │ │
│ │ Activation       │ 2       │ 45 min   │ $140        │ In-person       │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│ CLINICAL SUPPORT (Core)                                                     │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ Nursing          │ 3       │ 45 min   │ $315        │ In-person       │ │
│ │ Case Management  │ 2       │ 15 min   │ $70         │ Phone/Virtual   │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│ DAILY SUPPORT (Core)                                                        │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ PSW Visit        │ 10      │ 60 min   │ $750        │ In-person       │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│ COMMUNITY SUPPORT (Recommended)                                             │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ Meals Service    │ 5       │ —        │ $75         │ Delivery        │ │
│ │ Transportation   │ 1       │ —        │ $50         │ Service         │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│ COST SUMMARY                                                                │
│ ─────────────────────────────────────────────────────────────────────────── │
│                                                                             │
│ Estimated Weekly: $4,850                                                    │
│ Reference Cap:    $5,000                                                    │
│ Status:           ✓ Within reference (97%)                                  │
│                                                                             │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━░░ 97%                     │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│ OPERATIONAL SUMMARY                                                         │
│ ─────────────────────────────────────────────────────────────────────────── │
│                                                                             │
│ • 15 in-person visits per week                                              │
│ • 4 remote contacts per week                                                │
│ • ~18 staff hours per week                                                  │
│ • Delivery: 79% in-person, 21% remote                                       │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│ SAFETY VALIDATION                                                           │
│ ─────────────────────────────────────────────────────────────────────────── │
│ ✓ Meets minimum nursing frequency for clinical complexity                   │
│ ✓ Meets minimum PSW frequency for ADL support level                         │
│ ✓ Includes fall prevention monitoring                                       │
│                                                                             │
│ [Customize Services]                      [Select This Scenario →]          │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 6.3 Scenario Axis Selection Logic

> **DESIGN DECISION: Separation of Concerns**
>
> The `ScenarioAxisSelector` is the **single source of truth** for axis selection policy.
> This logic was intentionally NOT placed in `PatientNeedsProfile` because:
>
> 1. **DTOs should be pure data containers** – no business logic or policy decisions
> 2. **Axis selection is policy** – it determines what scenarios to show, which is a business rule
> 3. **Single point of change** – when axis criteria change, update one class
> 4. **Testability** – policy logic can be tested independently of data structure
>
> The `PatientNeedsProfile` provides the **inputs**; `ScenarioAxisSelector` applies **policy**.

```php
/**
 * ScenarioAxisSelector
 *
 * Determines which scenario axes to generate for a patient.
 * This is the SINGLE SOURCE OF TRUTH for axis selection policy.
 *
 * The selector reads data from PatientNeedsProfile (pure DTO) and
 * applies policy rules to determine applicable axes.
 */
class ScenarioAxisSelector
{
    public function selectAxes(PatientNeedsProfile $profile): array
    {
        $axes = [];
        $scores = [];

        // === Recovery/Rehab Axis ===
        // High score if: rehab potential, post-acute, therapy indicated
        $rehabScore = 0;
        if ($profile->hasRehabPotential) $rehabScore += 40;
        if ($profile->rehabPotentialScore >= 50) $rehabScore += 30;
        if ($profile->weeklyTherapyMinutes >= 60) $rehabScore += 20;
        if ($profile->episodeType === 'post_acute') $rehabScore += 30;
        $scores['recovery_rehab'] = $rehabScore;

        // === Safety/Stability Axis ===
        // High score if: fall risk, health instability, cognitive issues
        $safetyScore = 20; // Baseline relevance for all
        if ($profile->fallsRiskLevel >= 1) $safetyScore += 30;
        if ($profile->fallsRiskLevel >= 2) $safetyScore += 20;
        if ($profile->healthInstability >= 2) $safetyScore += 25;
        if ($profile->cognitiveComplexity >= 3) $safetyScore += 20;
        $scores['safety_stability'] = $safetyScore;

        // === Tech-Enabled Axis ===
        // High score if: tech-ready, stable, has internet
        $techScore = 0;
        if ($profile->technologyReadiness >= 2) $techScore += 40;
        if ($profile->suitableForRpm) $techScore += 30;
        if ($profile->hasInternet) $techScore += 15;
        if ($profile->healthInstability <= 2) $techScore += 15;
        $scores['tech_enabled'] = $techScore;

        // === Caregiver Relief Axis ===
        // High score if: high caregiver stress, caregiver-dependent
        $caregiverScore = 0;
        if ($profile->caregiverStressLevel >= 2) $caregiverScore += 40;
        if ($profile->caregiverStressLevel >= 3) $caregiverScore += 20;
        if ($profile->caregiverRequiresRelief) $caregiverScore += 30;
        if (!$profile->livesAlone && $profile->caregiverAvailabilityScore >= 3) {
            $caregiverScore += 15;
        }
        $scores['caregiver_relief'] = $caregiverScore;

        // === Medical Intensive Axis ===
        // High score if: extensive services, high clinical needs
        $medicalScore = 0;
        if ($profile->requiresExtensiveServices) $medicalScore += 50;
        if ($profile->healthInstability >= 3) $medicalScore += 30;
        if (count($profile->activeConditions ?? []) >= 3) $medicalScore += 20;
        $scores['medical_intensive'] = $medicalScore;

        // === Cognitive Support Axis ===
        // High score if: cognitive impairment, behavioural issues
        $cognitiveScore = 0;
        if ($profile->cognitiveComplexity >= 3) $cognitiveScore += 40;
        if ($profile->behaviouralComplexity >= 2) $cognitiveScore += 30;
        if ($profile->hasWanderingRisk) $cognitiveScore += 20;
        $scores['cognitive_support'] = $cognitiveScore;

        // Select top 3-5 axes with score >= 40
        arsort($scores);
        foreach ($scores as $axis => $score) {
            if ($score >= 40 && count($axes) < 5) {
                $axes[] = ScenarioAxis::from($axis);
            }
        }

        // Always include balanced if < 3 axes
        if (count($axes) < 3) {
            $axes[] = ScenarioAxis::BALANCED;
        }

        return $axes;
    }
}
```

---

## 7. Vertex AI Integration Design (Phase 2)

### 7.1 Overview

Vertex AI Gemini will serve two roles:
1. **Scenario Explanation** – Generate patient-friendly explanations for selected scenarios
2. **Scenario Generation** – AI-assisted scenario proposal (future)

**Architecture Principle:** All LLM interactions are:
- De-identified (no PHI/PII in prompts)
- Metadata-driven (LLM explains facts, doesn't invent them)
- Human-in-the-loop (coordinator reviews before applying)

### 7.2 Scenario Explanation Service Design

```php
<?php

namespace App\Services\BundleEngine\Llm;

use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;

/**
 * BundleExplanationServiceInterface
 *
 * Generates AI-powered explanations for bundle scenarios.
 * Uses Vertex AI Gemini as the LLM backend.
 *
 * IMPORTANT:
 * - All inputs are de-identified before sending to Vertex AI
 * - Outputs are based on provided metadata, not invented
 * - Explanations are framed around patient experience, not budget
 */
interface BundleExplanationServiceInterface
{
    /**
     * Generate an explanation for a selected scenario.
     *
     * @param PatientNeedsProfile $profile De-identified patient profile
     * @param ScenarioBundleDTO $scenario The scenario to explain
     * @return BundleExplanationDTO
     */
    public function explainScenario(
        PatientNeedsProfile $profile,
        ScenarioBundleDTO $scenario
    ): BundleExplanationDTO;

    /**
     * Generate a comparison between two scenarios.
     *
     * @param PatientNeedsProfile $profile
     * @param ScenarioBundleDTO $scenarioA
     * @param ScenarioBundleDTO $scenarioB
     * @return BundleComparisonDTO
     */
    public function compareScenarios(
        PatientNeedsProfile $profile,
        ScenarioBundleDTO $scenarioA,
        ScenarioBundleDTO $scenarioB
    ): BundleComparisonDTO;
}
```

#### Explanation DTO

```php
<?php

namespace App\Services\BundleEngine\DTOs;

/**
 * BundleExplanationDTO
 *
 * AI-generated explanation for a bundle scenario.
 */
class BundleExplanationDTO
{
    public function __construct(
        public readonly string $shortExplanation,       // 1-3 sentences
        public readonly array $keyPoints,               // Bullet points
        public readonly ?string $costContext = null,    // Optional cost note
        public readonly ?string $patientGoalsFit = null,// How it fits goals
        public readonly string $source,                 // 'vertex_ai' | 'rules_based'
        public readonly ?int $responseTimeMs = null,
        public readonly string $confidenceLabel,        // 'high', 'medium', 'low'
    ) {}
}
```

#### Prompt Structure for Explanation

```php
/**
 * Prompt payload for scenario explanation.
 */
class BundleExplanationPromptBuilder
{
    public function buildPromptPayload(
        PatientNeedsProfile $profile,
        ScenarioBundleDTO $scenario
    ): array {
        return [
            'system_instruction' => <<<'INSTRUCTION'
You are an AI assistant helping Ontario Health at Home care coordinators
explain bundle scenarios to patients and families.

Your explanations should be:
- Patient-experience focused (goals, comfort, safety)
- Professional and compassionate
- Based ONLY on the metadata provided
- Never mention "budget" or "cost constraints"
- 1-3 sentences for the short explanation

You are explaining WHY this bundle fits the patient's needs,
not defending a budget decision.
INSTRUCTION,

            'patient_context' => $profile->toDeidentifiedArray(),
            'scenario' => $scenario->toArray(),

            'output_format' => [
                'short_explanation' => 'string (1-3 sentences)',
                'key_points' => 'array of 2-4 bullet points',
                'patient_goals_fit' => 'string (how this supports their goals)',
            ],
        ];
    }
}
```

### 7.3 Scenario Generation Service Design (Future)

```php
<?php

namespace App\Services\BundleEngine\Llm;

use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\DTOs\ServiceCatalogDTO;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;

/**
 * LlmScenarioGeneratorInterface
 *
 * FUTURE: AI-assisted scenario generation using Vertex AI Gemini.
 *
 * This service will allow the LLM to propose scenario bundles
 * given a patient profile and service catalog. All proposals
 * go through human review before application.
 *
 * NOT FOR IMPLEMENTATION YET.
 */
interface LlmScenarioGeneratorInterface
{
    /**
     * Generate scenario proposals via Vertex AI.
     *
     * @param PatientNeedsProfile $profile De-identified patient profile
     * @param ServiceCatalogDTO $catalog Available services with metadata
     * @param array $constraints Program constraints (region, availability)
     * @return LlmScenarioProposalsDTO
     */
    public function generateScenarioProposals(
        PatientNeedsProfile $profile,
        ServiceCatalogDTO $catalog,
        array $constraints = []
    ): LlmScenarioProposalsDTO;
}

/**
 * LlmScenarioProposalsDTO
 *
 * Container for AI-generated scenario proposals.
 */
class LlmScenarioProposalsDTO
{
    public function __construct(
        /** @var ScenarioBundleDTO[] */
        public readonly array $proposals,
        public readonly string $generationId,
        public readonly array $metadata,
        public readonly bool $requiresHumanReview = true,
    ) {}
}
```

#### Prompt Structure for Generation (Design Only)

```php
/**
 * Prompt payload for AI scenario generation.
 * DESIGN ONLY - Not for implementation yet.
 */
class LlmScenarioGenerationPromptBuilder
{
    public function buildPromptPayload(
        PatientNeedsProfile $profile,
        ServiceCatalogDTO $catalog,
        array $constraints
    ): array {
        return [
            'system_instruction' => <<<'INSTRUCTION'
You are an AI assistant helping design care bundles for Ontario Health at Home.

Your task: Given a patient's needs profile and available services,
propose 3-5 bundle scenarios with different patient-experience orientations.

Requirements:
- Each scenario must meet minimum safety requirements
- Scenarios should vary in emphasis (recovery, safety, tech, caregiver support)
- Include services that match the patient's actual needs
- Annotate costs but do not optimize for budget
- Provide brief rationale for each scenario

Output JSON format with these scenarios:
- scenario_label: Patient-friendly name
- scenario_description: 1-2 sentence description
- primary_axis: Main emphasis
- services: Array of service selections
- cost_annotation: Estimated weekly cost and context
- pros_cons: Brief trade-off notes
INSTRUCTION,

            'patient_profile' => $profile->toDeidentifiedArray(),

            'service_catalog' => $catalog->toArray(),

            'constraints' => [
                'region_code' => $constraints['region'] ?? null,
                'reference_cap_dollars' => 5000,
                'min_safety_requirements' => $this->getMinimumSafetyRequirements($profile),
                'available_service_types' => $constraints['available_services'] ?? null,
            ],

            'output_format' => [
                'proposals' => [
                    [
                        'scenario_label' => 'string',
                        'scenario_description' => 'string',
                        'primary_axis' => 'string',
                        'secondary_axis' => 'string|null',
                        'services' => [
                            [
                                'service_code' => 'string',
                                'frequency_per_week' => 'int',
                                'duration_minutes' => 'int',
                                'rationale' => 'string',
                            ],
                        ],
                        'estimated_weekly_cost' => 'int',
                        'cost_note' => 'string|null',
                        'patient_experience_note' => 'string',
                        'trade_offs' => ['string'],
                    ],
                ],
            ],
        ];
    }
}
```

### 7.4 Reusing Existing LLM Infrastructure

The Bundle Engine LLM integration will reuse existing infrastructure:

| Component | Existing Code | Bundle Engine Usage |
|-----------|---------------|---------------------|
| `VertexAiClient` | `App\Services\Llm\VertexAi\VertexAiClient` | Reuse for API calls |
| `VertexAiConfig` | `App\Services\Llm\VertexAi\VertexAiConfig` | Reuse config management |
| Exception Classes | `App\Services\Llm\Exceptions\*` | Reuse error handling |
| Rate Limiting | Built into `VertexAiClient` | Reuse existing limits |
| ADC Auth | Built into `VertexAiClient` | Reuse for GCP auth |

**New Components Needed:**
- `BundleExplanationPromptBuilder` – Builds de-identified prompts for explanations
- `BundleExplanationService` – Orchestrates explanation generation
- `RulesBasedBundleExplanationProvider` – Fallback for deterministic explanations
- `LlmScenarioGenerationPromptBuilder` – (Future) Prompts for AI scenario generation

---

## 8. Integration with Existing Architecture

### 8.1 Updated Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     AI-ASSISTED BUNDLE ENGINE FLOW                      │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────┐     ┌──────────────────────┐     ┌──────────────────────┐
│  Patient    │────>│ AssessmentIngestion  │────>│ PatientNeedsProfile  │
│  Referral   │     │      Service         │     │      (DTO)           │
└─────────────┘     └──────────────────────┘     └──────────────────────┘
                              │                            │
        ┌─────────────────────┼─────────────────────┐      │
        │                     │                     │      │
        ▼                     ▼                     ▼      │
   ┌─────────┐          ┌─────────┐          ┌─────────┐   │
   │   HC    │          │   CA    │          │  BMHS   │   │
   │ (Full)  │          │ (Rapid) │          │(Mental) │   │
   └─────────┘          └─────────┘          └─────────┘   │
        │                     │                     │      │
        └─────────────────────┼─────────────────────┘      │
                              │                            │
                              ▼                            │
                    ┌──────────────────┐                   │
                    │ RUG or Needs     │                   │
                    │ Cluster          │                   │
                    │ Classification   │                   │
                    └──────────────────┘                   │
                              │                            │
                              ▼                            ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      SCENARIO GENERATOR                                  │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │ 1. Determine applicable axes from PatientNeedsProfile              │ │
│  │ 2. Generate 3-5 scenarios (different emphases)                     │ │
│  │ 3. Validate safety minimums for each scenario                      │ │
│  │ 4. Annotate costs (reference, not constraint)                      │ │
│  │ 5. Return ScenarioBundleDTOs                                       │ │
│  └────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          UI LAYER                                        │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │ • Scenario cards with patient-experience framing                   │ │
│  │ • Service lists with rationale                                     │ │
│  │ • Cost annotation (not "budget warnings")                          │ │
│  │ • "Why this scenario?" explanation (AI or rules-based)             │ │
│  └────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │  Coordinator     │
                    │  Selection       │
                    └──────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────────┐
│               EXISTING BUNDLE ENGINE (CC21_BundleEngine)                │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │ • CareBundleBuilderService.createCarePlan()                        │ │
│  │ • BundleConfigurationRuleEngine.applyRules()                       │ │
│  │ • CostEngine.evaluateBundle()                                      │ │
│  │ • CarePlanService.publishPlan()                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │  Published       │
                    │  Care Plan       │
                    └──────────────────┘
```

### 8.2 API Endpoints (Proposed)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v2/bundle-engine/{patientId}/profile` | GET | Get patient's needs profile |
| `/api/v2/bundle-engine/{patientId}/scenarios` | GET | Generate scenario bundles |
| `/api/v2/bundle-engine/{patientId}/scenarios/{scenarioId}` | GET | Get scenario details |
| `/api/v2/bundle-engine/{patientId}/scenarios/{scenarioId}/explain` | GET | Get AI explanation |
| `/api/v2/bundle-engine/{patientId}/scenarios/compare` | POST | Compare two scenarios |
| `/api/v2/bundle-engine/{patientId}/scenarios/{scenarioId}/select` | POST | Select scenario for plan |

---

## 9. Implementation Phases (Future)

### Phase 1: Core Infrastructure
- [ ] `PatientNeedsProfile` DTO
- [ ] `AssessmentIngestionService` implementation
- [ ] Needs cluster derivation logic
- [ ] Unit tests with assessment fixtures

### Phase 2: Scenario Generator
- [ ] `ScenarioBundleDTO` and `ScenarioServiceLine` DTOs
- [ ] `ScenarioGenerator` implementation
- [ ] Axis selection logic
- [ ] Safety validation rules
- [ ] Cost annotation service

### Phase 3: API & UI
- [ ] API controllers and routes
- [ ] React components for scenario selection
- [ ] Scenario detail views
- [ ] Cost annotation display

### Phase 4: AI Explanation
- [ ] `BundleExplanationPromptBuilder`
- [ ] `BundleExplanationService`
- [ ] `RulesBasedBundleExplanationProvider` (fallback)
- [ ] Integration with existing Vertex AI client

### Phase 5: AI Generation (Future)
- [ ] `LlmScenarioGenerationPromptBuilder`
- [ ] `LlmScenarioGeneratorService`
- [ ] Human review workflow
- [ ] Admin override controls

---

## 10. Design Refinements Log

This section documents refinements made to the initial design based on technical review.

### Refinement 1: Field Naming Verification (v1.1)

**Issue:** Initial mapping tables used assumed field names that may not match actual `InterraiAssessment` model.

**Resolution:** 
- Added "⚠️ Verify" column to mapping tables
- Confirmed field names against `app/Models/InterraiAssessment.php`
- Added explicit notes about `raw_items` key verification for CA and BMHS
- Implementation must verify exact keys before use

### Refinement 2: Episode Type & Rehab Potential Derivation (v1.1)

**Issue:** `episodeType` and `rehabPotentialScore` were used in the design but their derivation rules were not specified. These fields are policy-laden and could be misused if randomly set.

**Resolution:**
- Added Section 2.3 with explicit derivation rules for both fields
- Specified priority order for episode type determination
- Created point-based scoring system for rehab potential
- Defined safe defaults for missing data:
  - `episodeType` defaults to `'complex_continuing'`
  - `rehabPotentialScore` defaults to `0`
  - `hasRehabPotential` derived as `score >= 40`

### Refinement 3: Separation of Concerns - DTO vs Policy (v1.1)

**Issue:** Original `PatientNeedsProfile` included `getApplicableScenarioAxes()` method, which contains policy logic (which axes to show) rather than pure data.

**Resolution:**
- Removed `getApplicableScenarioAxes()` from `PatientNeedsProfile` DTO
- Moved axis selection logic to `ScenarioAxisSelector` (Section 6.3)
- Added design decision documentation explaining the separation
- `PatientNeedsProfile` is now a pure data container
- `toDeidentifiedArray()` no longer includes `applicable_axes` (computed at generation time)

**Architectural Principle:**
```
PatientNeedsProfile (DTO)     →  Pure data, no policy
ScenarioAxisSelector (Service) →  Policy decisions
```

---

## 11. Appendix: Glossary

| Term | Definition |
|------|------------|
| **HC (Home Care)** | Full InterRAI Home Care assessment – the preferred data source |
| **CA (Contact Assessment)** | Rapid InterRAI intake assessment – sufficient for first-phase bundling |
| **BMHS** | InterRAI Behavioural/Mental Health Screener – optional supplement |
| **RUG** | Resource Utilization Group – case-mix classification from HC assessment |
| **Needs Cluster** | Simplified grouping derived from CA when RUG unavailable |
| **Scenario Axis** | Patient-experience orientation (recovery, safety, tech, caregiver) |
| **Reference Cap** | $5,000/week guideline – NOT a hard constraint |
| **PHI/PII** | Protected Health Information / Personally Identifiable Information |
| **ADC** | Application Default Credentials (Google Cloud authentication) |
| **DTO** | Data Transfer Object – pure data container with no business logic |

---

**Document Status:** Design Complete – Refined (v1.1)  
**Revision History:**
- v1.0 – Initial design
- v1.1 – Field verification notes, episode type/rehab derivation rules, DTO/policy separation

**Next Steps:** Technical review, then implementation planning


# CC2.1 Bundle Engine - Developer Guide

This guide explains the new RUG-III/HC-driven care bundle architecture implemented in Connected Capacity 2.1.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Key Components](#key-components)
3. [Data Flow](#data-flow)
4. [Running the Demo](#running-the-demo)
5. [Extending the System](#extending-the-system)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

CC2.1 replaces the legacy "Transition Needs Profile" (TNP) approach with a standardized, metadata-driven care bundle system based on CIHI's RUG-III/HC classification algorithm.

### Core Principles

1. **Assessment-Driven**: All care decisions stem from InterRAI HC assessments
2. **RUG Classification**: Each patient receives a RUG group (e.g., CB0, IB0) that determines service eligibility
3. **Template-Based**: 23 pre-configured bundle templates match the 23 RUG groups
4. **Metadata-Driven**: Business rules are encoded in configuration, not code

### RUG Categories (in hierarchy order)

| Category | RUG Groups | Description |
|----------|------------|-------------|
| Special Rehabilitation | RB0, RA2, RA1 | Intensive therapy focus |
| Extensive Services | SE3, SE2, SE1 | Complex medical (IV, ventilator) |
| Special Care | SSB, SSA | High clinical complexity |
| Clinically Complex | CC0, CB0, CA2, CA1 | Multiple clinical conditions |
| Impaired Cognition | IB0, IA2, IA1 | Cognitive impairment |
| Behaviour Problems | BB0, BA2, BA1 | Behavioural symptoms |
| Reduced Physical Function | PD0, PC0, PB0, PA2, PA1 | Physical assistance needs |

---

## Key Components

### Models

| Model | Location | Purpose |
|-------|----------|---------|
| `InterraiAssessment` | `app/Models/InterraiAssessment.php` | Stores InterRAI HC assessment data |
| `RUGClassification` | `app/Models/RUGClassification.php` | Computed RUG group from assessment |
| `CareBundleTemplate` | `app/Models/CareBundleTemplate.php` | 23 RUG-specific bundle definitions |
| `CareBundleTemplateService` | `app/Models/CareBundleTemplateService.php` | Services within templates |
| `CareBundle` | `app/Models/CareBundle.php` | Instantiated bundle for a patient |
| `CarePlan` | `app/Models/CarePlan.php` | Published plan with service assignments |

### Services

| Service | Location | Responsibility |
|---------|----------|----------------|
| `RUGClassificationService` | `app/Services/RUGClassificationService.php` | Computes RUG group from assessment |
| `InterraiSummaryService` | `app/Services/InterraiSummaryService.php` | Generates narrative summaries and clinical flags |
| `CareBundleBuilderService` | `app/Services/CareBundleBuilderService.php` | Builds bundles and care plans |
| `CareBundleTemplateRepository` | `app/Services/CareBundleTemplateRepository.php` | Finds matching templates |

### Relationships Diagram

```
Patient
  ├── InterraiAssessment (hasMany)
  │     └── is_current: boolean
  ├── RUGClassification (hasMany, via assessment)
  │     └── is_current: boolean
  └── CarePlan (hasMany)
        ├── CareBundle
        ├── CareBundleTemplate
        └── ServiceAssignment (hasMany)

CareBundleTemplate
  └── CareBundleTemplateService (hasMany)
        └── ServiceType
```

---

## Data Flow

### 1. Assessment → Classification

```php
// When an InterRAI HC assessment is completed:
$assessment = InterraiAssessment::create([
    'patient_id' => $patient->id,
    'assessment_type' => 'hc',
    'adl_hierarchy' => 3,
    'cognitive_performance_scale' => 2,
    'chess_score' => 3,
    // ... other fields
]);

// Classify the patient
$rugService = new RUGClassificationService();
$classification = $rugService->classify($assessment);

// Result: RUGClassification with rug_group='CB0', rug_category='Clinically Complex'
```

### 2. Classification → Bundle Recommendations

```php
// Get recommended bundles for a patient
$builderService = app(CareBundleBuilderService::class);
$result = $builderService->getRugBasedBundles($patient->id);

// Returns:
// [
//   'patient_id' => 1,
//   'rug_classification' => [...],
//   'bundles' => [
//     ['id' => 1, 'code' => 'LTC_CB0_STANDARD', 'isRecommended' => true, ...],
//     ['id' => 2, 'code' => 'LTC_CA2_STANDARD', 'isRecommended' => false, ...],
//   ]
// ]
```

### 3. Template Selection → Care Plan

```php
// Build a care plan from a template
$carePlan = $builderService->buildCarePlanFromTemplate(
    patientId: $patient->id,
    templateId: $template->id,
    serviceConfigurations: [
        ['service_type_id' => 1, 'currentFrequency' => 5],
        ['service_type_id' => 2, 'currentFrequency' => 3],
    ],
    userId: auth()->id()
);
```

### 4. Care Plan Publishing

```php
// Publish the plan (activates services, transitions patient)
$publishedPlan = $builderService->publishCarePlan($carePlan, auth()->id());
```

### 5. Narrative Summary Generation

```php
// Generate patient overview summary
$summaryService = new InterraiSummaryService();
$summary = $summaryService->generateSummary($patient);

// Returns:
// [
//   'narrative_summary' => 'Patient has moderate-high care needs...',
//   'clinical_flags' => [
//     'high_fall_risk' => true,
//     'high_cognitive_impairment' => false,
//     ...
//   ],
//   'rug_summary' => [...],
//   'assessment_status' => 'current'
// ]
```

---

## Running the Demo

### Fresh Setup

```bash
# Run migrations and seed demo data
php artisan migrate:fresh --seed

# This creates:
# - 5 patients in Intake Queue (pending bundle selection)
# - 10 Active patients (with published care plans)
# - 23 RUG bundle templates
# - Sample service types
```

### Demo Data Structure

**Intake Queue Patients (5):**
- Each has an InterRAI HC assessment
- Each has a RUG classification
- No published care plan yet
- Ready for bundle selection

**Active Patients (10):**
- Each has InterRAI HC assessment + RUG classification
- Each has a published care plan
- Services are active
- Covers all major RUG categories

### Key Seeders

| Seeder | Purpose |
|--------|---------|
| `RUGBundleTemplatesSeeder` | Creates 23 bundle templates |
| `ServiceTypeSeeder` | Creates service types (NUR, PSW, PT, etc.) |
| `DemoPatientsSeeder` | Creates 15 demo patients |
| `DemoBundlesSeeder` | Creates demo bundles and plans |

---

## Extending the System

### Adding a New Service Type

1. Add to `ServiceTypeSeeder`:
```php
ServiceType::create([
    'code' => 'NEW_SVC',
    'name' => 'New Service',
    'description' => 'Description of the new service',
    'default_cost_per_visit_cents' => 10000, // $100
]);
```

2. Add to relevant templates in `RUGBundleTemplatesSeeder`:
```php
$this->addServices($template, [
    ['code' => 'NEW_SVC', 'freq' => 2, 'duration' => 60, 'required' => false],
]);
```

### Modifying Bundle Templates

Templates are seeded via `RUGBundleTemplatesSeeder`. To modify:

1. Find the relevant `seed*()` method (e.g., `seedCB0()`)
2. Update template properties or services
3. Re-run the seeder: `php artisan db:seed --class=RUGBundleTemplatesSeeder`

### Adding Clinical Flags

1. Update `InterraiSummaryService::generateSummary()` to compute new flags
2. Add flag definitions to `FLAG_DEFINITIONS` constant
3. Update `RUGClassificationService` if the flag affects RUG classification

### Customizing Narrative Generation

Modify `InterraiSummaryService::generateNarrative()`:
```php
protected function generateNarrative(Patient $patient, InterraiAssessment $assessment, ?RUGClassification $rug): string
{
    // Add custom narrative logic here
}
```

---

## Testing

### Running Tests

```bash
# All tests
php artisan test

# Specific service tests
php artisan test tests/Unit/Services/RUGClassificationServiceTest.php
php artisan test tests/Unit/Services/InterraiSummaryServiceTest.php
php artisan test tests/Unit/Services/CareBundleBuilderServiceTest.php
```

### Test Structure

| Test File | Coverage |
|-----------|----------|
| `RUGClassificationServiceTest` | RUG algorithm, category mapping, numeric ranks |
| `InterraiSummaryServiceTest` | Narrative generation, clinical flags, stale detection |
| `CareBundleBuilderServiceTest` | Template matching, plan building, publishing |

### Writing New Tests

```php
use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Services\RUGClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_feature(): void
    {
        // Create patient
        $patient = Patient::factory()->create();

        // Create assessment
        $assessment = InterraiAssessment::factory()
            ->highNeeds() // or lowNeeds(), withCognitiveImpairment(), etc.
            ->create(['patient_id' => $patient->id]);

        // Classify
        $rugService = new RUGClassificationService();
        $rug = $rugService->classify($assessment);

        // Assert
        $this->assertEquals('PD0', $rug->rug_group);
    }
}
```

### Factory States

`InterraiAssessmentFactory` provides these states:

| State | Description |
|-------|-------------|
| `lowNeeds()` | MAPLe 1, low ADL/CPS |
| `moderateNeeds()` | MAPLe 3, moderate scores |
| `highNeeds()` | MAPLe 5, high ADL/CPS |
| `withCognitiveImpairment()` | CPS 3-6, wandering |
| `clinicallyComplex()` | High CHESS, pain |
| `stale()` | Assessment >3 months old |

---

## Troubleshooting

### "No InterRAI assessment available"

Patient doesn't have an assessment. Either:
- Complete an InterRAI HC assessment
- Use demo mode (run seeders)

### "Patient requires InterRAI HC assessment before bundle selection"

Same as above - patient needs assessment data.

### Template Not Matching

Check that:
1. RUG classification exists: `$patient->latestRugClassification`
2. Template is active: `is_active = true` and `is_current_version = true`
3. ADL/IADL ranges match
4. Required flags are present in classification

### Missing Services in Bundle

Services are filtered by:
1. `is_required` flag (always included)
2. `is_conditional` flag with matching `condition_flags`

Check the patient's RUG classification flags.

---

## API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v2/care-builder/{patientId}/bundles` | GET | List recommended bundles |
| `/api/v2/care-builder/{patientId}/bundles/{bundleId}` | GET | Get specific bundle |
| `/api/v2/care-builder/{patientId}/bundles/preview` | POST | Preview bundle configuration |
| `/api/v2/care-builder/{patientId}/plans` | GET | List patient's care plans |
| `/api/v2/care-builder/{patientId}/plans` | POST | Create new care plan |
| `/api/v2/care-builder/{patientId}/plans/{carePlanId}/publish` | POST | Publish care plan |
| `/api/v2/patients/{patientId}/overview` | GET | Get patient overview with summary |

---

## References

- [CC21_BundleEngine_Architecture.md](./CC21_BundleEngine_Architecture.md) - Detailed architecture
- [CC21_RUG_Bundle_Templates.md](./CC21_RUG_Bundle_Templates.md) - Template definitions
- [CC21_RUG_Algorithm_Pseudocode.md](./CC21_RUG_Algorithm_Pseudocode.md) - Classification algorithm

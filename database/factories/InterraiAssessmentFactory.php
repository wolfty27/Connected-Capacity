<?php

namespace Database\Factories;

use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for InterraiAssessment model.
 *
 * Creates realistic InterRAI HC assessment data for testing.
 */
class InterraiAssessmentFactory extends Factory
{
    protected $model = InterraiAssessment::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'assessment_type' => 'hc',
            'assessment_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'assessor_id' => null,
            'assessor_role' => 'RN',
            'source' => InterraiAssessment::SOURCE_HPG,

            // Clinical scores
            'maple_score' => (string) $this->faker->numberBetween(1, 5),
            'rai_cha_score' => (string) $this->faker->numberBetween(1, 6),
            'adl_hierarchy' => $this->faker->numberBetween(0, 6),
            'iadl_difficulty' => $this->faker->numberBetween(0, 6),
            'cognitive_performance_scale' => $this->faker->numberBetween(0, 6),
            'depression_rating_scale' => $this->faker->numberBetween(0, 14),
            'pain_scale' => $this->faker->numberBetween(0, 4),
            'chess_score' => $this->faker->numberBetween(0, 5),

            // Flags
            'falls_in_last_90_days' => $this->faker->boolean(30),
            'wandering_flag' => $this->faker->boolean(15),

            // CAPs
            'caps_triggered' => $this->faker->randomElements(
                ['falls', 'pain', 'adl', 'cardio', 'pressure_ulcer', 'nutrition'],
                $this->faker->numberBetween(0, 3)
            ),

            // Diagnosis
            'primary_diagnosis_icd10' => $this->faker->randomElement(['G30', 'I50', 'J44', 'N18', 'F03']),
            'secondary_diagnoses' => [],

            // Sync status
            'iar_upload_status' => InterraiAssessment::IAR_NOT_REQUIRED,
            'chris_sync_status' => InterraiAssessment::CHRIS_NOT_REQUIRED,

            // Versioning
            'version' => 1,
            'is_current' => true,
            'workflow_status' => 'completed',

            // Raw items (iCODE) - basic structure
            'raw_items' => [],
        ];
    }

    /**
     * Create an assessment with low care needs.
     */
    public function lowNeeds(): static
    {
        return $this->state(fn(array $attributes) => [
            'maple_score' => '1',
            'adl_hierarchy' => 0,
            'iadl_difficulty' => 1,
            'cognitive_performance_scale' => 0,
            'chess_score' => 0,
            'pain_scale' => 0,
            'falls_in_last_90_days' => false,
        ]);
    }

    /**
     * Create an assessment with moderate care needs.
     */
    public function moderateNeeds(): static
    {
        return $this->state(fn(array $attributes) => [
            'maple_score' => '3',
            'adl_hierarchy' => 3,
            'iadl_difficulty' => 3,
            'cognitive_performance_scale' => 2,
            'chess_score' => 2,
            'pain_scale' => 1,
            'falls_in_last_90_days' => false,
        ]);
    }

    /**
     * Create an assessment with high care needs.
     */
    public function highNeeds(): static
    {
        return $this->state(fn(array $attributes) => [
            'maple_score' => '5',
            'adl_hierarchy' => 5,
            'iadl_difficulty' => 5,
            'cognitive_performance_scale' => 4,
            'chess_score' => 4,
            'pain_scale' => 3,
            'falls_in_last_90_days' => true,
        ]);
    }

    /**
     * Create an assessment with cognitive impairment.
     */
    public function withCognitiveImpairment(): static
    {
        return $this->state(fn(array $attributes) => [
            'cognitive_performance_scale' => $this->faker->numberBetween(3, 6),
            'wandering_flag' => $this->faker->boolean(40),
        ]);
    }

    /**
     * Create an assessment with clinical complexity.
     */
    public function clinicallyComplex(): static
    {
        return $this->state(fn(array $attributes) => [
            'chess_score' => $this->faker->numberBetween(3, 5),
            'pain_scale' => $this->faker->numberBetween(2, 4),
            'primary_diagnosis_icd10' => 'N18', // Chronic kidney disease
        ]);
    }

    /**
     * Create a stale assessment (>3 months old).
     */
    public function stale(): static
    {
        return $this->state(fn(array $attributes) => [
            'assessment_date' => $this->faker->dateTimeBetween('-6 months', '-4 months'),
        ]);
    }

    /**
     * Create assessment with raw iCODE items.
     */
    public function withRawItems(): static
    {
        return $this->state(function (array $attributes) {
            $adl = $attributes['adl_hierarchy'] ?? 3;

            return [
                'raw_items' => [
                    // ADL items
                    'iG1ha' => $adl, // Bed mobility
                    'iG1ia' => $adl, // Transfer
                    'iG1ea' => $adl, // Toilet use
                    'iG1ja' => max(0, $adl - 1), // Eating
                    'iG1aa' => $adl, // IADL - meal prep
                    'iG1da' => $adl, // IADL - housework
                    'iG1eb' => $adl, // IADL - finances

                    // Cognitive
                    'iB1' => $attributes['cognitive_performance_scale'] ?? 0,
                    'iB3a' => min(3, $attributes['cognitive_performance_scale'] ?? 0),
                    'iC1' => 0,

                    // Clinical
                    'iJ1a' => $attributes['pain_scale'] ?? 0,
                    'iJ1b' => $attributes['pain_scale'] ?? 0,
                    'chess' => $attributes['chess_score'] ?? 0,
                    'drs' => $attributes['depression_rating_scale'] ?? 0,

                    // Behaviour
                    'iE3a' => 0, 'iE3b' => 0, 'iE3c' => 0,
                    'iE3d' => 0, 'iE3e' => 0, 'iE3f' => 0,

                    // Treatment
                    'iN3eb' => 0, 'iN3fb' => 0, 'iN3gb' => 0,
                    'iP1aa' => 0, 'iP1ab' => 0, 'iP1ae' => 0,
                    'iP1af' => 0, 'iP1ag' => 0, 'iP1ah' => 0,

                    // Falls
                    'iJ2a' => $attributes['falls_in_last_90_days'] ? 1 : 0,

                    // Location
                    'location' => 'community',
                ],
            ];
        });
    }
}

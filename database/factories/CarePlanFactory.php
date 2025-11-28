<?php

namespace Database\Factories;

use App\Models\CareBundle;
use App\Models\CareBundleTemplate;
use App\Models\CarePlan;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CarePlan>
 */
class CarePlanFactory extends Factory
{
    protected $model = CarePlan::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'care_bundle_id' => null,
            'care_bundle_template_id' => null,
            'version' => 1,
            'status' => 'active',
            'goals' => ['Maintain independence', 'Improve mobility'],
            'risks' => ['Fall risk'],
            'interventions' => [],
            'approved_at' => now()->subDays(rand(1, 30)),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the care plan is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
            'approved_at' => now()->subDays(rand(1, 30)),
        ]);
    }

    /**
     * Indicate that the care plan is draft.
     */
    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'draft',
            'approved_at' => null,
        ]);
    }
}

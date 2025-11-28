<?php

namespace Database\Factories;

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    protected $model = ServiceType::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->lexify('???'));

        return [
            'code' => $code,
            'name' => $this->faker->words(3, true) . ' Service',
            'category' => $this->faker->randomElement(['Nursing', 'PSW', 'Allied Health', 'Other']),
            'description' => $this->faker->sentence(),
            'default_duration_minutes' => $this->faker->randomElement([30, 45, 60, 90, 120]),
            'billing_unit_type' => $this->faker->randomElement(['per_hour', 'per_visit', 'per_15min']),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the service type is nursing.
     */
    public function nursing(): static
    {
        return $this->state(fn(array $attributes) => [
            'code' => 'NUR',
            'name' => 'Nursing Service',
            'category' => 'Nursing',
        ]);
    }

    /**
     * Indicate that the service type is PSW.
     */
    public function psw(): static
    {
        return $this->state(fn(array $attributes) => [
            'code' => 'PSW',
            'name' => 'Personal Support Worker',
            'category' => 'PSW',
        ]);
    }

    /**
     * Indicate that the service type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}

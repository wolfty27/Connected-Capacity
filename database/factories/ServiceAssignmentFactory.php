<?php

namespace Database\Factories;

use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceAssignment>
 */
class ServiceAssignmentFactory extends Factory
{
    protected $model = ServiceAssignment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $scheduledStart = $this->faker->dateTimeBetween('-7 days', '+7 days');
        $durationMinutes = $this->faker->randomElement([30, 45, 60, 90, 120]);
        $scheduledEnd = (clone $scheduledStart)->modify("+{$durationMinutes} minutes");

        return [
            'care_plan_id' => CarePlan::factory(),
            'patient_id' => Patient::factory(),
            'service_provider_organization_id' => ServiceProviderOrganization::factory(),
            'service_type_id' => ServiceType::factory(),
            'assigned_user_id' => User::factory(),
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'verified_at' => null,
            'verification_source' => null,
            'verified_by_user_id' => null,
            'frequency_rule' => '1x per week',
            'source' => ServiceAssignment::SOURCE_INTERNAL,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the assignment is verified.
     */
    public function verified(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ServiceAssignment::STATUS_COMPLETED,
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'verified_at' => now(),
            'verification_source' => ServiceAssignment::VERIFICATION_SOURCE_STAFF_MANUAL,
        ]);
    }

    /**
     * Indicate that the assignment is missed.
     */
    public function missed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ServiceAssignment::STATUS_MISSED,
            'verification_status' => ServiceAssignment::VERIFICATION_MISSED,
            'verified_at' => now(),
            'verification_source' => ServiceAssignment::VERIFICATION_SOURCE_COORDINATOR,
        ]);
    }

    /**
     * Indicate that the assignment is overdue (past grace period).
     */
    public function overdue(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ServiceAssignment::STATUS_PLANNED,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'scheduled_start' => now()->subDays(2), // Past 24h grace period
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment is scheduled for soon (within warning threshold).
     */
    public function upcoming(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ServiceAssignment::STATUS_PLANNED,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'scheduled_start' => now()->addHour(),
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $start = $attributes['scheduled_start'] ?? now()->subHours(2);
            $end = (clone $start)->modify('+60 minutes');

            return [
                'status' => ServiceAssignment::STATUS_COMPLETED,
                'actual_start' => $start,
                'actual_end' => $end,
            ];
        });
    }

    /**
     * Set the organization for the assignment.
     */
    public function forOrganization(int $organizationId): static
    {
        return $this->state(fn(array $attributes) => [
            'service_provider_organization_id' => $organizationId,
        ]);
    }
}

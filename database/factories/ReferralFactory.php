<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition()
    {
        return [
            'patient_id' => Patient::factory(),
            'service_type_id' => null,
            'service_provider_organization_id' => null,
            'submitted_by' => User::factory(),
            'status' => Referral::STATUS_SUBMITTED,
            'source' => 'manual',
            'intake_notes' => $this->faker->paragraph(),
            'metadata' => [
                'referral_reason' => $this->faker->sentence(),
                'preferred_language' => $this->faker->languageCode(),
            ],
        ];
    }
}

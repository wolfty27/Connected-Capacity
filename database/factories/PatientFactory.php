<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition()
    {
        return [
            'user_id'            => User::factory(),
            'hospital_id'        => Hospital::factory(),
            'retirement_home_id' => null,
            'date_of_birth'      => $this->faker->date(),
            'status'             => 'Inactive',
            'gender'             => $this->faker->randomElement(['Male', 'Female']),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HospitalFactory extends Factory
{
    protected $model = Hospital::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'documents' => null,
            'website' => $this->faker->url(),
            'calendly' => null,
        ];
    }
}

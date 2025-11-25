<?php

namespace Database\Factories;

use App\Models\ServiceProviderOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceProviderOrganization>
 */
class ServiceProviderOrganizationFactory extends Factory
{
    protected $model = ServiceProviderOrganization::class;

    protected array $domains = [
        'dementia',
        'mental_health',
        'clinical',
        'community',
        'technology',
    ];

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'type' => $this->faker->randomElement(['se_health', 'partner', 'external']),
            'contact_name' => $this->faker->name(),
            'contact_email' => $this->faker->unique()->safeEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'province' => $this->faker->stateAbbr(),
            'postal_code' => strtoupper($this->faker->bothify('?#?#?#')),
            'regions' => $this->faker->randomElements(
                ['Central', 'Toronto', 'West', 'East', 'North'],
                rand(1, 3)
            ),
            'capabilities' => $this->faker->randomElements($this->domains, rand(1, count($this->domains))),
            'active' => true,
        ];
    }
}

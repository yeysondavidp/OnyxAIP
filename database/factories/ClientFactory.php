<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'client_name'     => fake()->company(),
            'client_code'     => strtoupper(fake()->unique()->lexify('???')),
            'primary_contact' => fake()->name(),
            'primary_email'   => fake()->companyEmail(),
            'notes'           => null,
            'sla_profile_id'  => null,
            'is_active'       => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicianProfile>
 */
class TechnicianProfileFactory extends Factory
{
    protected $model = TechnicianProfile::class;

    public function definition(): array
    {
        return [
            'name'                 => fake()->name(),
            'email'                => fake()->unique()->safeEmail(),
            'phone'                => fake()->phoneNumber(),
            'specialty_categories' => null,
            'certifications'       => null,
            'preferred_client_ids' => null,
            'asset_competency'     => null,
            'is_active'            => true,
            'user_id'              => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withAccount(): static
    {
        return $this->state(function () {
            $user = User::factory()->technician()->create();

            return ['user_id' => $user->id, 'email' => $user->email, 'name' => $user->name];
        });
    }
}

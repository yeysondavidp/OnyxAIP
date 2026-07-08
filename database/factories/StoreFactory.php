<?php

namespace Database\Factories;

use App\Enums\AustralianState;
use App\Enums\Country;
use App\Enums\NewZealandRegion;
use App\Enums\StoreType;
use App\Models\Client;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'client_id'           => Client::factory(),
            'store_name'          => fake()->company().' '.fake()->city(),
            'store_code'          => strtoupper(fake()->unique()->bothify('???-???-###')),
            'store_type'          => fake()->randomElement(StoreType::cases())->value,
            'address_line1'       => fake()->streetAddress(),
            'suburb'              => fake()->city(),
            'state'               => fake()->randomElement(AustralianState::cases())->value,
            'postcode'            => (string) fake()->numberBetween(1000, 9999),
            'country'             => 'Australia',
            'store_timezone'      => 'Australia/Sydney',
            'store_manager_name'  => null,
            'store_manager_phone' => null,
            'store_manager_email' => null,
            'notes'               => null,
            'is_active'           => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** A store in Auckland, NZ — the only region the platform models today (US-03.5). */
    public function newZealand(): static
    {
        return $this->state(fn () => [
            'suburb'         => 'Auckland',
            'state'          => NewZealandRegion::Auckland->value,
            'postcode'       => (string) fake()->numberBetween(1000, 2999),
            'country'        => Country::NewZealand->value,
            'store_timezone' => 'Pacific/Auckland',
        ]);
    }
}

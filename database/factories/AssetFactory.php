<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $client = Client::factory()->create();
        $store  = Store::factory()->create(['client_id' => $client->id]);

        return [
            'asset_code'    => strtoupper(fake()->unique()->bothify('???-###-???')),
            'asset_type'    => AssetType::DigitalScreen->value,
            'client_id'     => $client->id,
            'store_id'      => $store->id,
            'asset_name'    => 'Screen '.fake()->numberBetween(1, 99),
            'manufacturer'  => fake()->randomElement(['Samsung', 'LG', 'NEC']),
            'model'         => fake()->bothify('??###'),
            'serial_number' => null,
            'asset_status'  => AssetStatus::Active->value,
            'notes'         => null,
        ];
    }

    public function forStore(Store $store): static
    {
        return $this->state([
            'client_id' => $store->client_id,
            'store_id'  => $store->id,
        ]);
    }

    public function forClientAndStore(Client $client, Store $store): static
    {
        return $this->state([
            'client_id' => $client->id,
            'store_id'  => $store->id,
        ]);
    }

    public function ofType(AssetType $type): static
    {
        return $this->state(['asset_type' => $type->value]);
    }

    public function faulty(): static
    {
        return $this->state(['asset_status' => AssetStatus::Faulty->value]);
    }

    public function decommissioned(): static
    {
        return $this->state(['asset_status' => AssetStatus::Decommissioned->value]);
    }
}

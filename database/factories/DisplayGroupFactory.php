<?php

namespace Database\Factories;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\DisplayGroup;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisplayGroup>
 */
class DisplayGroupFactory extends Factory
{
    protected $model = DisplayGroup::class;

    public function definition(): array
    {
        $store  = Store::factory()->create();
        $player = Asset::factory()->forStore($store)->create([
            'asset_type' => AssetType::MediaPlayer->value,
        ]);

        return [
            'store_id'           => $store->id,
            'group_name'         => 'Bay '.fake()->numberBetween(1, 9),
            'player_asset_id'    => $player->id,
            'layout_description' => null,
            'notes'              => null,
        ];
    }

    public function forStore(Store $store, Asset $player): static
    {
        return $this->state([
            'store_id'        => $store->id,
            'player_asset_id' => $player->id,
        ]);
    }
}

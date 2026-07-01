<?php

namespace Database\Factories;

use App\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSetting>
 */
class PlatformSettingFactory extends Factory
{
    protected $model = PlatformSetting::class;

    public function definition(): array
    {
        return [
            'setting_key' => fake()->unique()->word(),
            'value'       => fake()->numberBetween(1, 100),
        ];
    }
}

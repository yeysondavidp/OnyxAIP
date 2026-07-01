<?php

namespace Database\Factories;

use App\Enums\MonitoringCoverage;
use App\Models\SlaProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaProfile>
 */
class SlaProfileFactory extends Factory
{
    protected $model = SlaProfile::class;

    public function definition(): array
    {
        return [
            'name'                           => fake()->company().' Standard SLA',
            'acknowledgement_hours'          => 2,
            'onsite_response_metro_hours'    => 10,
            'onsite_response_regional_hours' => 20,
            'resolution_hours'               => 40,
            'monitoring_coverage'            => MonitoringCoverage::BusinessHoursOnly,
            'is_active'                      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

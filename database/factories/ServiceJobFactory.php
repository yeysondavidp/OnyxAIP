<?php

namespace Database\Factories;

use App\Enums\EarlyStartWindow;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceJob>
 */
class ServiceJobFactory extends Factory
{
    protected $model = ServiceJob::class;

    public function definition(): array
    {
        $store = Store::factory()->create();

        return [
            'job_reference'         => strtoupper(fake()->unique()->bothify('JOB-####')),
            'job_name'              => fake()->sentence(5),
            'job_description'       => fake()->paragraph(),
            'job_type'              => fake()->randomElement(JobType::cases())->value,
            'client_id'             => $store->client_id,
            'store_id'              => $store->id,
            'job_timezone'          => 'Australia/Sydney',
            'scheduled_date'        => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'scheduled_time'        => '09:00',
            'early_start_window'    => EarlyStartWindow::Anytime->value,
            'job_status'            => JobStatus::Draft->value,
            'parent_job_id'         => null,
            'job_level'             => 0,
            'client_email'          => null,
            'client_name'           => null,
            'sla_breached'          => false,
            'force_complete_reason' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['job_status' => JobStatus::Draft->value]);
    }

    public function invited(): static
    {
        return $this->state(['job_status' => JobStatus::Invited->value]);
    }

    public function inProgress(): static
    {
        return $this->state(['job_status' => JobStatus::InProgress->value]);
    }

    public function completed(): static
    {
        return $this->state(['job_status' => JobStatus::Completed->value]);
    }

    public function validated(): static
    {
        return $this->state(['job_status' => JobStatus::Validated->value]);
    }

    public function forClient(Client $client, Store $store): static
    {
        return $this->state([
            'client_id'    => $client->id,
            'store_id'     => $store->id,
            'job_timezone' => $store->store_timezone,
        ]);
    }
}

<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\EarlyStartWindow;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use App\Services\JobTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Access control ────────────────────────────────────────────────────────────

it('pm can view the job board', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->get(route('jobs.index'))
        ->assertOk();
});

it('technician cannot view the job board', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->get(route('jobs.index'))
        ->assertForbidden();
});

it('unauthenticated user is redirected to login', function () {
    $this->get(route('jobs.index'))
        ->assertRedirect(route('login'));
});

// ── Create / Store (US-08.1) ──────────────────────────────────────────────────

it('pm can create a service job', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'job_reference'      => 'JOB-001',
            'job_name'           => 'Pandora Q3 Maintenance',
            'job_description'    => 'Inspect all screens.',
            'job_type'           => JobType::RoutineMaintenance->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
            'scheduled_date'     => '2026-07-15',
            'scheduled_time'     => '09:00',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('service_jobs', [
        'job_reference' => 'JOB-001',
        'job_status'    => 'draft',
        'client_id'     => $store->client_id,        // derived server-side
        'store_id'      => $store->id,
        'job_timezone'  => $store->store_timezone,   // derived from store
    ]);
});

it('job_reference must be unique', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->create(['job_reference' => 'JOB-DUP']);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'job_reference'      => 'JOB-DUP',
            'job_name'           => 'Another job',
            'job_description'    => 'Desc.',
            'job_type'           => JobType::Survey->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
        ])
        ->assertSessionHasErrors('job_reference');
});

it('client_id is derived from store and not accepted from the request', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();

    // Attempt to pass a different client_id
    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'client_id'          => 9999, // should be ignored
            'job_reference'      => 'JOB-002',
            'job_name'           => 'Test',
            'job_description'    => 'Desc.',
            'job_type'           => JobType::Survey->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
        ])
        ->assertRedirect();

    $job = ServiceJob::where('job_reference', 'JOB-002')->first();
    expect($job)->not->toBeNull();
    expect($job->client_id)->toBe($store->client_id);
    expect($job->client_id)->not->toBe(9999);
});

// ── Affected assets (US-08.2) ─────────────────────────────────────────────────

it('attaching an asset auto-transitions it to under_maintenance', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $asset = Asset::factory()->create([
        'store_id'     => $store->id,
        'client_id'    => $store->client_id,
        'asset_type'   => AssetType::DigitalScreen->value,
        'asset_status' => AssetStatus::Active->value,
    ]);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'job_reference'      => 'JOB-003',
            'job_name'           => 'Screen check',
            'job_description'    => 'Inspect screens.',
            'job_type'           => JobType::RoutineMaintenance->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
            'asset_ids'          => [$asset->id],
        ])
        ->assertRedirect();

    expect($asset->fresh()->asset_status)->toBe(AssetStatus::UnderMaintenance);
    $this->assertDatabaseHas('job_assets', ['asset_id' => $asset->id]);
});

it('cannot attach an asset from a different store', function () {
    $pm         = User::factory()->pm()->create();
    $store      = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $asset      = Asset::factory()->create([
        'store_id'   => $otherStore->id,
        'client_id'  => $otherStore->client_id,
        'asset_type' => AssetType::DigitalScreen->value,
    ]);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'job_reference'      => 'JOB-004',
            'job_name'           => 'Test',
            'job_description'    => 'Desc.',
            'job_type'           => JobType::Survey->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
            'asset_ids'          => [$asset->id],
        ])
        ->assertSessionHasErrors('asset_ids');
});

// ── State machine (US-08.3) ────────────────────────────────────────────────────

it('pm can transition a job from draft to invited', function () {
    $pm  = User::factory()->pm()->create();
    $job = ServiceJob::factory()->draft()->create(['client_id' => $pm->client_id ?? ServiceJob::factory()->create()->client_id]);

    // Re-create with a store the PM can see
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();

    $this->actingAs($pm)
        ->post(route('jobs.invite', $job))
        ->assertRedirect();

    expect($job->fresh()->job_status)->toBe(JobStatus::Invited);
});

it('illegal status transition is rejected', function () {
    $service = new JobTransitionService;
    $pm      = User::factory()->pm()->create();
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();

    expect(fn () => $service->transitionTo($job, JobStatus::Validated, $pm))
        ->toThrow(InvalidArgumentException::class);

    expect($job->fresh()->job_status)->toBe(JobStatus::Draft);
});

it('cancelled job is soft-deleted and hidden from active queries', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();

    $this->actingAs($pm)
        ->delete(route('jobs.destroy', $job))
        ->assertRedirect();

    // Soft-deleted — not found in normal queries
    expect(ServiceJob::find($job->id))->toBeNull();

    // But exists with trashed scope
    expect(ServiceJob::withTrashed()->find($job->id))->not->toBeNull();
});

// ── Multi-technician assignment (US-08.4) ─────────────────────────────────────

it('assigns technicians via pivot with invited status', function () {
    $pm    = User::factory()->pm()->create();
    $tech  = User::factory()->technician()->create();
    $store = Store::factory()->create();

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'job_reference'      => 'JOB-005',
            'job_name'           => 'Multi-tech job',
            'job_description'    => 'Desc.',
            'job_type'           => JobType::Survey->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
            'technician_ids'     => [$tech->id],
        ])
        ->assertRedirect();

    $job = ServiceJob::where('job_reference', 'JOB-005')->first();
    expect($job)->not->toBeNull();
    $this->assertDatabaseHas('job_technicians', [
        'job_id'            => $job->id,
        'user_id'           => $tech->id,
        'technician_status' => 'invited',
    ]);
});

it('cannot assign a non-technician user', function () {
    $pm      = User::factory()->pm()->create();
    $notTech = User::factory()->pm()->create(); // another PM
    $store   = Store::factory()->create();

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'job_reference'      => 'JOB-006',
            'job_name'           => 'Test',
            'job_description'    => 'Desc.',
            'job_type'           => JobType::Survey->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
            'technician_ids'     => [$notTech->id],
        ])
        ->assertSessionHasErrors('technician_ids');
});

// ── Hierarchy (US-08.5) ────────────────────────────────────────────────────────

it('creates a sub-job inheriting parent client_id', function () {
    $pm     = User::factory()->pm()->create();
    $store  = Store::factory()->create();
    $client = Client::find($store->client_id);
    $parent = ServiceJob::factory()->forClient($client, $store)->create(['job_level' => 0]);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'parent_job_id'      => $parent->id,
            'job_reference'      => 'JOB-SUB-001',
            'job_name'           => 'Sub-job',
            'job_description'    => 'Desc.',
            'job_type'           => JobType::Survey->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
        ])
        ->assertRedirect();

    $sub = ServiceJob::where('job_reference', 'JOB-SUB-001')->first();
    expect($sub)->not->toBeNull();
    expect($sub->job_level)->toBe(1);
    expect($sub->client_id)->toBe($parent->client_id);
});

it('rejects a sub-job whose store belongs to a different client than the parent', function () {
    $pm         = User::factory()->pm()->create();
    $store      = Store::factory()->create();
    $client     = Client::find($store->client_id);
    $otherStore = Store::factory()->create(); // different client
    $parent     = ServiceJob::factory()->forClient($client, $store)->create(['job_level' => 0]);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $otherStore->id,
            'parent_job_id'      => $parent->id,
            'job_reference'      => 'JOB-CROSS',
            'job_name'           => 'Cross-client sub',
            'job_description'    => 'Desc.',
            'job_type'           => JobType::Survey->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
        ])
        ->assertSessionHasErrors('parent_job_id');
});

it('max-1-remediation rule: second remediation child is rejected', function () {
    $pm     = User::factory()->pm()->create();
    $store  = Store::factory()->create();
    $client = Client::find($store->client_id);
    $sub    = ServiceJob::factory()->forClient($client, $store)->create(['job_level' => 1]);

    // First remediation — OK
    ServiceJob::factory()->forClient($client, $store)->create([
        'job_level'     => 2,
        'parent_job_id' => $sub->id,
    ]);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'parent_job_id'      => $sub->id,
            'job_reference'      => 'JOB-REM-2',
            'job_name'           => 'Second remediation',
            'job_description'    => 'Desc.',
            'job_type'           => JobType::Survey->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
        ])
        ->assertSessionHasErrors('parent_job_id');
});

it('no-level-3: child of a remediation is rejected', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $rem   = ServiceJob::factory()->create([
        'store_id'  => $store->id,
        'client_id' => $store->client_id,
        'job_level' => 2,  // Level 2 — can't have children
    ]);

    $this->actingAs($pm)
        ->post(route('jobs.store'), [
            'store_id'           => $store->id,
            'parent_job_id'      => $rem->id,
            'job_reference'      => 'JOB-L3',
            'job_name'           => 'Level 3 attempt',
            'job_description'    => 'Desc.',
            'job_type'           => JobType::Survey->value,
            'early_start_window' => EarlyStartWindow::Anytime->value,
        ])
        ->assertSessionHasErrors('parent_job_id');
});

// ── Show (US-08.7 scope guard) ────────────────────────────────────────────────

it('pm can view a job detail page', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->create();

    $this->actingAs($pm)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee($job->job_name);
});

// ── Force complete (US-08.4) ──────────────────────────────────────────────────

it('force complete requires a reason note', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->inProgress()->create();

    $this->actingAs($pm)
        ->post(route('jobs.force-complete', $job), ['force_complete_reason' => ''])
        ->assertSessionHasErrors('force_complete_reason');
});

it('pm can force-complete an in-progress job with a reason', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->inProgress()->create();

    $this->actingAs($pm)
        ->post(route('jobs.force-complete', $job), [
            'force_complete_reason' => 'Technician on-site confirmed completion verbally.',
        ])
        ->assertRedirect();

    expect($job->fresh()->job_status)->toBe(JobStatus::Completed);
});

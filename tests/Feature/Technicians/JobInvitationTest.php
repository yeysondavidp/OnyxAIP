<?php

use App\Mail\JobInvitationMail;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\JobInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ── Send invitation (US-09.2) ──────────────────────────────────────────────────

it('pm can send an invitation email to a technician', function () {
    Queue::fake();
    Mail::fake();

    $pm      = User::factory()->pm()->create();
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();
    $profile = TechnicianProfile::factory()->create();

    // Assign the profile to the job first
    $job->technicians()->attach($profile->id, ['technician_status' => 'invited']);

    app(JobInvitationService::class)->invite($job, $profile, $pm);

    Mail::assertQueued(JobInvitationMail::class, function ($mail) use ($profile) {
        return $mail->hasTo($profile->email);
    });

    // Invitation token written to DB
    $this->assertDatabaseHas('job_technicians', [
        'job_id'                => $job->id,
        'technician_profile_id' => $profile->id,
    ]);

    $row = DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->first();

    expect($row->invitation_token)->not->toBeNull();
    expect($row->token_expires_at)->not->toBeNull();
});

it('resending an invitation invalidates the old token', function () {
    Mail::fake();

    $pm      = User::factory()->pm()->create();
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();
    $profile = TechnicianProfile::factory()->create();

    $job->technicians()->attach($profile->id, ['technician_status' => 'invited']);

    $service = app(JobInvitationService::class);
    $service->invite($job, $profile, $pm);

    $firstToken = DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->value('invitation_token');

    // Resend
    $service->invite($job, $profile, $pm);

    $secondToken = DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->value('invitation_token');

    expect($secondToken)->not->toBe($firstToken);
});

// ── Token resolution (US-09.4) ────────────────────────────────────────────────

it('resolveToken returns profile id for a valid unexpired token', function () {
    Mail::fake();

    $pm      = User::factory()->pm()->create();
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();
    $profile = TechnicianProfile::factory()->create();

    $job->technicians()->attach($profile->id, ['technician_status' => 'invited']);

    app(JobInvitationService::class)->invite($job, $profile, $pm);

    $token = DB::table('job_technicians')
        ->where('job_id', $job->id)
        ->where('technician_profile_id', $profile->id)
        ->value('invitation_token');

    $service = app(JobInvitationService::class);
    expect($service->resolveToken($job->id, (string) $token))->toBe($profile->id);
});

it('resolveToken returns null for an expired token', function () {
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();
    $profile = TechnicianProfile::factory()->create();

    DB::table('job_technicians')->insert([
        'job_id'                => $job->id,
        'technician_profile_id' => $profile->id,
        'technician_status'     => 'invited',
        'invitation_token'      => 'expiredtoken123',
        'token_expires_at'      => now()->subHour(), // expired
    ]);

    $service = app(JobInvitationService::class);
    expect($service->resolveToken($job->id, 'expiredtoken123'))->toBeNull();
});

it('resolveToken returns null for an unknown token', function () {
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();

    $service = app(JobInvitationService::class);
    expect($service->resolveToken($job->id, 'totally-made-up-token'))->toBeNull();
});

// ── Send via HTTP endpoint (US-09.2) ──────────────────────────────────────────

it('pm can send invitations via the invite endpoint', function () {
    Mail::fake();

    $pm      = User::factory()->pm()->create();
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();
    $profile = TechnicianProfile::factory()->create();

    // Assign profile to job first
    $job->technicians()->attach($profile->id, ['technician_status' => 'invited']);

    $this->actingAs($pm)
        ->post(route('jobs.invite.send', $job), [
            'profile_ids' => [$profile->id],
        ])
        ->assertRedirect(route('jobs.show', $job));

    Mail::assertQueued(JobInvitationMail::class);
});

it('non-pm cannot send invitations', function () {
    $tech    = User::factory()->technician()->create();
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();
    $profile = TechnicianProfile::factory()->create();

    $this->actingAs($tech)
        ->post(route('jobs.invite.send', $job), [
            'profile_ids' => [$profile->id],
        ])
        ->assertForbidden();
});

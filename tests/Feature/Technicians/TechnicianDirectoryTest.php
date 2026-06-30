<?php

use App\Enums\TechnicianSpecialty;
use App\Models\Client;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Access control (US-09.1) ──────────────────────────────────────────────────

it('pm can view the technician directory', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)->get(route('technicians.index'))->assertOk();
});

it('technician cannot view the directory', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)->get(route('technicians.index'))->assertForbidden();
});

it('unauthenticated user is redirected to login', function () {
    $this->get(route('technicians.index'))->assertRedirect(route('login'));
});

// ── Create (US-09.1) ──────────────────────────────────────────────────────────

it('pm can create a technician profile', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('technicians.store'), [
            'name'  => 'Michael Installer',
            'email' => 'michael@example.com',
            'phone' => '0412 345 678',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('technician_profiles', [
        'name'      => 'Michael Installer',
        'email'     => 'michael@example.com',
        'is_active' => 1,
        'user_id'   => null,  // guest — no account
    ]);
});

it('specialty categories are validated against the fixed enum', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('technicians.store'), [
            'name'                 => 'Bad Tech',
            'email'                => 'bad@example.com',
            'specialty_categories' => ['not_a_real_specialty'],
        ])
        ->assertSessionHasErrors('specialty_categories.0');
});

it('preferred client ids must reference real clients', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('technicians.store'), [
            'name'                 => 'Bad Tech',
            'email'                => 'bad@example.com',
            'preferred_client_ids' => [99999],
        ])
        ->assertSessionHasErrors('preferred_client_ids.0');
});

it('can link a profile to a technician user account', function () {
    $pm   = User::factory()->pm()->create();
    $user = User::factory()->technician()->create();

    $this->actingAs($pm)
        ->post(route('technicians.store'), [
            'name'    => 'Sneider Account',
            'email'   => 'sneider@example.com',
            'user_id' => $user->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('technician_profiles', [
        'name'    => 'Sneider Account',
        'user_id' => $user->id,
    ]);
});

it('cannot link a profile to a non-technician user', function () {
    $pm     = User::factory()->pm()->create();
    $pmUser = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('technicians.store'), [
            'name'    => 'Bad Link',
            'email'   => 'bad@example.com',
            'user_id' => $pmUser->id,
        ])
        ->assertSessionHasErrors('user_id');
});

// ── Soft-delete / deactivate (US-09.1) ────────────────────────────────────────

it('deactivating a profile soft-deletes it and hides from active list', function () {
    $pm      = User::factory()->pm()->create();
    $profile = TechnicianProfile::factory()->create();

    $this->actingAs($pm)
        ->delete(route('technicians.destroy', $profile))
        ->assertRedirect();

    // Marked inactive
    expect($profile->fresh()?->is_active)->toBeFalse();
    // Soft-deleted
    expect(TechnicianProfile::find($profile->id))->toBeNull();
    expect(TechnicianProfile::withTrashed()->find($profile->id))->not->toBeNull();
});

// ── Update (US-09.1) ──────────────────────────────────────────────────────────

it('pm can update a technician profile', function () {
    $pm      = User::factory()->pm()->create();
    $profile = TechnicianProfile::factory()->create(['name' => 'Old Name']);

    $this->actingAs($pm)
        ->put(route('technicians.update', $profile), [
            'name'  => 'New Name',
            'email' => $profile->email,
        ])
        ->assertRedirect();

    expect($profile->fresh()?->name)->toBe('New Name');
});

// ── Specialty and cert storage (US-09.1) ─────────────────────────────────────

it('stores specialty categories as a json array', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('technicians.store'), [
            'name'                 => 'Specialist Tech',
            'email'                => 'spec@example.com',
            'specialty_categories' => [
                TechnicianSpecialty::DigitalSignage->value,
                TechnicianSpecialty::Electrical->value,
            ],
        ])
        ->assertRedirect();

    $profile = TechnicianProfile::where('email', 'spec@example.com')->first();
    expect($profile)->not->toBeNull();
    expect($profile->specialty_categories)->toContain(TechnicianSpecialty::DigitalSignage->value);
    expect($profile->specialty_categories)->toContain(TechnicianSpecialty::Electrical->value);
});

it('preferred client ids are validated against real clients', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    $this->actingAs($pm)
        ->post(route('technicians.store'), [
            'name'                 => 'Client-aware Tech',
            'email'                => 'clienttech@example.com',
            'preferred_client_ids' => [$client->id],
        ])
        ->assertRedirect();

    $profile = TechnicianProfile::where('email', 'clienttech@example.com')->first();
    expect($profile)->not->toBeNull();
    expect($profile->preferred_client_ids)->toContain($client->id);
});

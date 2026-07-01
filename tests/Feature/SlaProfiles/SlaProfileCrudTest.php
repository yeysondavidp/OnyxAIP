<?php

use App\Enums\MonitoringCoverage;
use App\Models\Client;
use App\Models\SlaProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('pm can view the sla profile list', function () {
    $pm = User::factory()->pm()->create();
    SlaProfile::factory()->create(['name' => 'Pandora Standard']);

    $this->actingAs($pm)
        ->get(route('sla-profiles.index'))
        ->assertOk()
        ->assertSee('Pandora Standard');
});

it('technician cannot view the sla profile list', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->get(route('sla-profiles.index'))
        ->assertForbidden();
});

it('pm can create an sla profile', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('sla-profiles.store'), [
            'name'                           => 'Sephora Standard',
            'acknowledgement_hours'          => 2,
            'onsite_response_metro_hours'    => 10,
            'onsite_response_regional_hours' => 20,
            'resolution_hours'               => 40,
            'monitoring_coverage'            => MonitoringCoverage::BusinessHoursOnly->value,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('sla_profiles', [
        'name'             => 'Sephora Standard',
        'resolution_hours' => 40,
        'is_active'        => 1,
    ]);
});

it('technician cannot create an sla profile', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->post(route('sla-profiles.store'), [
            'name'                           => 'Sneaky',
            'acknowledgement_hours'          => 2,
            'onsite_response_metro_hours'    => 10,
            'onsite_response_regional_hours' => 20,
            'resolution_hours'               => 40,
            'monitoring_coverage'            => MonitoringCoverage::BusinessHoursOnly->value,
        ])
        ->assertForbidden();
});

it('resolution_hours must be a positive integer', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('sla-profiles.store'), [
            'name'                           => 'Bad Profile',
            'acknowledgement_hours'          => 2,
            'onsite_response_metro_hours'    => 10,
            'onsite_response_regional_hours' => 20,
            'resolution_hours'               => 0,
            'monitoring_coverage'            => MonitoringCoverage::BusinessHoursOnly->value,
        ])
        ->assertSessionHasErrors('resolution_hours');
});

it('pm can assign an sla profile to a client', function () {
    $pm      = User::factory()->pm()->create();
    $client  = Client::factory()->create();
    $profile = SlaProfile::factory()->create();

    $this->actingAs($pm)
        ->patch(route('clients.update', $client), [
            'client_name'    => $client->client_name,
            'client_code'    => $client->client_code,
            'sla_profile_id' => $profile->id,
        ])
        ->assertRedirect();

    expect($client->fresh()->sla_profile_id)->toBe($profile->id);
});

it('cannot assign an inactive sla profile to a client', function () {
    $pm      = User::factory()->pm()->create();
    $client  = Client::factory()->create();
    $profile = SlaProfile::factory()->inactive()->create();

    $this->actingAs($pm)
        ->patch(route('clients.update', $client), [
            'client_name'    => $client->client_name,
            'client_code'    => $client->client_code,
            'sla_profile_id' => $profile->id,
        ])
        ->assertSessionHasErrors('sla_profile_id');
});

it('cannot hard-delete an sla profile still assigned to a client', function () {
    $profile = SlaProfile::factory()->create();
    Client::factory()->create(['sla_profile_id' => $profile->id]);

    expect(fn () => $profile->delete())->toThrow(QueryException::class);
});

it('pm can view an sla profile detail page with assigned clients', function () {
    $pm      = User::factory()->pm()->create();
    $profile = SlaProfile::factory()->create(['name' => 'Dior Standard']);
    Client::factory()->create(['client_name' => 'Dior ANZ', 'sla_profile_id' => $profile->id]);

    $this->actingAs($pm)
        ->get(route('sla-profiles.show', $profile))
        ->assertOk()
        ->assertSee('Dior Standard')
        ->assertSee('Dior ANZ');
});

it('client show page displays the assigned sla profile', function () {
    $pm      = User::factory()->pm()->create();
    $profile = SlaProfile::factory()->create(['name' => 'Pandora Standard']);
    $client  = Client::factory()->create(['sla_profile_id' => $profile->id]);

    $this->actingAs($pm)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Pandora Standard');
});

it('pm can deactivate an sla profile (soft, not hard-deleted)', function () {
    $pm      = User::factory()->pm()->create();
    $profile = SlaProfile::factory()->create();

    $this->actingAs($pm)
        ->delete(route('sla-profiles.destroy', $profile))
        ->assertRedirect(route('sla-profiles.index'));

    $this->assertDatabaseHas('sla_profiles', ['id' => $profile->id, 'is_active' => 0]);
});

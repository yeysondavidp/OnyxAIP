<?php

use App\Enums\AustralianState;
use App\Enums\Country;
use App\Enums\NewZealandRegion;
use App\Enums\StoreType;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\SlaProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function validStorePayload(Client $client, array $overrides = []): array
{
    return array_merge([
        'client_id'      => $client->id,
        'store_name'     => 'Pandora Pitt St Mall',
        'store_code'     => 'PAN-SYD-001',
        'store_type'     => StoreType::ConceptStore->value,
        'address_line1'  => '188 Pitt St',
        'suburb'         => 'Sydney',
        'state'          => AustralianState::Nsw->value,
        'postcode'       => '2000',
        'store_timezone' => 'Australia/Sydney',
    ], $overrides);
}

// ── Index (US-03.2) ───────────────────────────────────────────────────────────

it('pm can view the store list', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create(['store_name' => 'Pandora Pitt St']);

    $this->actingAs($pm)
        ->get(route('stores.index'))
        ->assertOk()
        ->assertSee('Pandora Pitt St');
});

it('technician cannot view the store list', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->get(route('stores.index'))
        ->assertForbidden();
});

// ── Create / Store (US-03.1) ──────────────────────────────────────────────────

it('pm can view the add store form with the country cascade', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->get(route('stores.create'))
        ->assertOk()
        ->assertSee('Country')
        ->assertSee('New Zealand');
});

it('pm can create a store', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    $this->actingAs($pm)
        ->post(route('stores.store'), validStorePayload($client))
        ->assertRedirect();

    $this->assertDatabaseHas('stores', [
        'store_name' => 'Pandora Pitt St Mall',
        'store_code' => 'PAN-SYD-001',
        'client_id'  => $client->id,
        'is_active'  => 1,
    ]);
});

it('store_code must be unique', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    Store::factory()->create(['store_code' => 'PAN-SYD-001']);

    $this->actingAs($pm)
        ->post(route('stores.store'), validStorePayload($client, ['store_code' => 'PAN-SYD-001']))
        ->assertSessionHasErrors('store_code');
});

it('state must be a valid Australian state', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    $this->actingAs($pm)
        ->post(route('stores.store'), validStorePayload($client, ['state' => 'INVALID']))
        ->assertSessionHasErrors('state');
});

// ── Country/region support (US-03.5) ─────────────────────────────────────────

it('pm can create a store in new zealand', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    $payload = validStorePayload($client, [
        'store_code'     => 'DIO-AUK-001',
        'suburb'         => 'Auckland',
        'state'          => NewZealandRegion::Auckland->value,
        'postcode'       => '1010',
        'country'        => Country::NewZealand->value,
        'store_timezone' => 'Pacific/Auckland',
    ]);

    $this->actingAs($pm)
        ->post(route('stores.store'), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('stores', [
        'store_code'     => 'DIO-AUK-001',
        'country'        => 'New Zealand',
        'state'          => 'AUK',
        'store_timezone' => 'Pacific/Auckland',
    ]);
});

it('rejects a new zealand country paired with an australian state', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    $payload = validStorePayload($client, ['country' => Country::NewZealand->value]);

    $this->actingAs($pm)
        ->post(route('stores.store'), $payload)
        ->assertSessionHasErrors('state');
});

it('rejects an australian country paired with a new zealand region', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    $payload = validStorePayload($client, [
        'country' => Country::Australia->value,
        'state'   => NewZealandRegion::Auckland->value,
    ]);

    $this->actingAs($pm)
        ->post(route('stores.store'), $payload)
        ->assertSessionHasErrors('state');
});

it('updating an australian store without a country field leaves its country unchanged', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create(['country' => Country::Australia->value]);

    $payload = [
        'store_name'     => $store->store_name,
        'store_code'     => $store->store_code,
        'store_type'     => $store->store_type->value,
        'address_line1'  => $store->address_line1,
        'suburb'         => $store->suburb,
        'state'          => $store->state->value,
        'postcode'       => $store->postcode,
        'store_timezone' => $store->store_timezone,
    ];

    $this->actingAs($pm)
        ->patch(route('stores.update', $store), $payload)
        ->assertRedirect(route('stores.show', $store));

    $this->assertDatabaseHas('stores', ['id' => $store->id, 'country' => 'Australia']);
});

it('store_timezone must be a valid timezone', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    $this->actingAs($pm)
        ->post(route('stores.store'), validStorePayload($client, ['store_timezone' => 'Not/ATimezone']))
        ->assertSessionHasErrors('store_timezone');
});

it('technician cannot create a store', function () {
    $tech   = User::factory()->technician()->create();
    $client = Client::factory()->create();

    $this->actingAs($tech)
        ->post(route('stores.store'), validStorePayload($client))
        ->assertForbidden();
});

// ── Show (US-03.1 / US-03.3 skeleton) ────────────────────────────────────────

it('pm can view a store dashboard', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create(['store_name' => 'Pandora Bondi']);

    $this->actingAs($pm)
        ->get(route('stores.show', $store))
        ->assertOk()
        ->assertSee('Pandora Bondi');
});

it('store dashboard shows sla status for an open sla-tracked job', function () {
    $pm      = User::factory()->pm()->create();
    $profile = SlaProfile::factory()->create();
    $client  = Client::factory()->create(['sla_profile_id' => $profile->id]);
    $store   = Store::factory()->create(['client_id' => $client->id]);
    ServiceJob::factory()->forClient($client, $store)->create([
        'job_reference'            => 'JOB-SLA-DASH',
        'sla_profile_id'           => $profile->id,
        'sla_clock_started_at'     => now()->subHour(),
        'sla_resolution_target_at' => now()->addHour(),
        'sla_at_risk_at'           => now()->addMinutes(30),
    ]);

    $this->actingAs($pm)
        ->get(route('stores.show', $store))
        ->assertOk()
        ->assertSee('JOB-SLA-DASH')
        ->assertSee('On track');
});

it('store dashboard lists a recent service job even when it has no affected assets', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();
    $store  = Store::factory()->create(['client_id' => $client->id]);
    ServiceJob::factory()->forClient($client, $store)->validated()->create([
        'job_reference' => 'JOB-NO-ASSETS',
        'job_name'      => 'Assetless visit',
    ]);

    $this->actingAs($pm)
        ->get(route('stores.show', $store))
        ->assertOk()
        ->assertSee('Recent Service Jobs')
        ->assertSee('Assetless visit')
        ->assertSee('JOB-NO-ASSETS');
});

it('cross-tenant store dashboard is inaccessible', function () {
    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();
    $storeB  = Store::factory()->create(['client_id' => $clientB->id]);

    $userA = User::factory()->clientUser()->create(['client_id' => $clientA->id]);

    // The global ClientScope makes clientB's stores invisible → 404 (information hiding).
    // Either 404 (scope) or 403 (policy) is secure; the scope fires first here.
    $response = $this->actingAs($userA)->get(route('stores.show', $storeB));
    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

// ── Edit / Update (US-03.1) ───────────────────────────────────────────────────

it('pm can view the edit store form with the country cascade', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();

    $this->actingAs($pm)
        ->get(route('stores.edit', $store))
        ->assertOk()
        ->assertSee('Country')
        ->assertSee('New Zealand');
});

it('pm can update a store', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create(['store_name' => 'Old Name']);

    $payload = [
        'store_name'     => 'New Name',
        'store_code'     => $store->store_code,
        'store_type'     => $store->store_type->value,
        'address_line1'  => $store->address_line1,
        'suburb'         => $store->suburb,
        'state'          => $store->state->value,
        'postcode'       => $store->postcode,
        'store_timezone' => $store->store_timezone,
    ];

    $this->actingAs($pm)
        ->patch(route('stores.update', $store), $payload)
        ->assertRedirect(route('stores.show', $store));

    $this->assertDatabaseHas('stores', ['id' => $store->id, 'store_name' => 'New Name']);
});

// ── Destroy / Deactivate (US-03.1) ────────────────────────────────────────────

it('pm can deactivate a store (soft, not hard-deleted)', function () {
    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();

    $this->actingAs($pm)
        ->delete(route('stores.destroy', $store))
        ->assertRedirect(route('stores.index'));

    $this->assertDatabaseHas('stores', ['id' => $store->id, 'is_active' => 0]);
    $this->assertDatabaseCount('stores', 1);
});

it('technician cannot deactivate a store', function () {
    $tech  = User::factory()->technician()->create();
    $store = Store::factory()->create();

    $this->actingAs($tech)
        ->delete(route('stores.destroy', $store))
        ->assertForbidden();
});

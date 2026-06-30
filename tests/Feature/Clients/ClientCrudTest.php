<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Index (US-02.3) ───────────────────────────────────────────────────────────

it('pm can view the client list', function () {
    $pm = User::factory()->pm()->create();
    Client::factory()->create(['client_name' => 'Pandora', 'client_code' => 'PAN']);
    Client::factory()->create(['client_name' => 'Sephora', 'client_code' => 'SEP']);

    $this->actingAs($pm)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Pandora')
        ->assertSee('Sephora');
});

it('technician cannot view the client list', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->get(route('clients.index'))
        ->assertForbidden();
});

it('unauthenticated visitor is redirected to login', function () {
    $this->get(route('clients.index'))
        ->assertRedirect(route('login'));
});

// ── Create / Store (US-02.1) ──────────────────────────────────────────────────

it('pm can create a client', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('clients.store'), [
            'client_name'     => 'Dior ANZ',
            'client_code'     => 'DIO',
            'primary_contact' => 'Sophie Martin',
            'primary_email'   => 'sophie@dior.com',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('clients', [
        'client_name' => 'Dior ANZ',
        'client_code' => 'DIO',
        'is_active'   => 1,
    ]);
});

it('client_code must be unique', function () {
    $pm = User::factory()->pm()->create();
    Client::factory()->create(['client_code' => 'DIO']);

    $this->actingAs($pm)
        ->post(route('clients.store'), [
            'client_name' => 'Another Dior',
            'client_code' => 'DIO',
        ])
        ->assertSessionHasErrors('client_code');
});

it('client_name is required', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->post(route('clients.store'), ['client_code' => 'TST'])
        ->assertSessionHasErrors('client_name');
});

it('technician cannot create a client', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->post(route('clients.store'), [
            'client_name' => 'Sneaky Client',
            'client_code' => 'SNK',
        ])
        ->assertForbidden();
});

// ── Show (US-02.1) ────────────────────────────────────────────────────────────

it('pm can view a client detail page', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create(['client_name' => 'Pandora ANZ']);

    $this->actingAs($pm)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Pandora ANZ');
});

// ── Edit / Update (US-02.1) ───────────────────────────────────────────────────

it('pm can edit a client', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create(['client_name' => 'Old Name', 'client_code' => 'OLD']);

    $this->actingAs($pm)
        ->patch(route('clients.update', $client), [
            'client_name' => 'New Name',
            'client_code' => 'OLD',
        ])
        ->assertRedirect(route('clients.show', $client));

    $this->assertDatabaseHas('clients', [
        'id'          => $client->id,
        'client_name' => 'New Name',
    ]);
});

it('client_code unique rule excludes the current client on update', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create(['client_code' => 'PAN']);

    $this->actingAs($pm)
        ->patch(route('clients.update', $client), [
            'client_name' => 'Pandora Updated',
            'client_code' => 'PAN', // same code — should pass
        ])
        ->assertSessionHasNoErrors();
});

// ── Destroy / Deactivate (US-02.1) ────────────────────────────────────────────

it('pm can deactivate a client (soft, not hard-deleted)', function () {
    $pm     = User::factory()->pm()->create();
    $client = Client::factory()->create();

    $this->actingAs($pm)
        ->delete(route('clients.destroy', $client))
        ->assertRedirect(route('clients.index'));

    $this->assertDatabaseHas('clients', ['id' => $client->id, 'is_active' => 0]);
    $this->assertDatabaseCount('clients', 1); // record is preserved
});

it('technician cannot deactivate a client', function () {
    $tech   = User::factory()->technician()->create();
    $client = Client::factory()->create();

    $this->actingAs($tech)
        ->delete(route('clients.destroy', $client))
        ->assertForbidden();

    $this->assertDatabaseHas('clients', ['id' => $client->id, 'is_active' => 1]);
});

<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// PolicyTest uses factories to avoid coupling to specific required field values.
// Policy assertions are about role/client_id logic, not field validation.

uses(RefreshDatabase::class);

// ── ClientPolicy ──────────────────────────────────────────────────────────────

it('pm can view any client', function () {
    $pm     = User::factory()->create(['role' => UserRole::Pm]);
    $client = Client::factory()->create(['client_name' => 'Pandora', 'client_code' => 'PAN']);

    expect($pm->can('view', $client))->toBeTrue()
        ->and($pm->can('viewAny', Client::class))->toBeTrue();
});

it('pm can create update and delete clients', function () {
    $pm     = User::factory()->create(['role' => UserRole::Pm]);
    $client = Client::factory()->create(['client_name' => 'Sephora', 'client_code' => 'SEP']);

    expect($pm->can('create', Client::class))->toBeTrue()
        ->and($pm->can('update', $client))->toBeTrue()
        ->and($pm->can('delete', $client))->toBeTrue();
});

it('client_user can only view their own client', function () {
    $clientA = Client::factory()->create(['client_name' => 'Pandora', 'client_code' => 'PAN']);
    $clientB = Client::factory()->create(['client_name' => 'Sephora', 'client_code' => 'SEP']);

    $user = User::factory()->create(['role' => UserRole::ClientUser, 'client_id' => $clientA->id]);

    expect($user->can('view', $clientA))->toBeTrue()
        ->and($user->can('view', $clientB))->toBeFalse();
});

it('client_user cannot create or mutate clients', function () {
    $clientA = Client::create(['client_name' => 'Pandora', 'client_code' => 'PAN']);
    $user    = User::factory()->create(['role' => UserRole::ClientUser, 'client_id' => $clientA->id]);

    expect($user->can('create', Client::class))->toBeFalse()
        ->and($user->can('update', $clientA))->toBeFalse()
        ->and($user->can('delete', $clientA))->toBeFalse();
});

it('technician cannot access client resources', function () {
    $client = Client::factory()->create(['client_name' => 'Pandora', 'client_code' => 'PAN']);
    $tech   = User::factory()->technician()->create();

    expect($tech->can('viewAny', Client::class))->toBeFalse()
        ->and($tech->can('view', $client))->toBeFalse()
        ->and($tech->can('create', Client::class))->toBeFalse();
});

// ── StorePolicy ───────────────────────────────────────────────────────────────

it('pm can view any store regardless of client', function () {
    $clientA = Client::factory()->create(['client_name' => 'Pandora', 'client_code' => 'PAN']);
    $clientB = Client::factory()->create(['client_name' => 'Sephora', 'client_code' => 'SEP']);
    $pm      = User::factory()->create(['role' => UserRole::Pm]);
    $storeA  = Store::factory()->create(['client_id' => $clientA->id, 'store_code' => 'PAN-SYD-001']);
    $storeB  = Store::factory()->create(['client_id' => $clientB->id, 'store_code' => 'SEP-SYD-001']);

    expect($pm->can('view', $storeA))->toBeTrue()
        ->and($pm->can('view', $storeB))->toBeTrue()
        ->and($pm->can('update', $storeA))->toBeTrue()
        ->and($pm->can('delete', $storeB))->toBeTrue();
});

it('client_user cannot view stores from another client', function () {
    $clientA = Client::factory()->create(['client_name' => 'Pandora', 'client_code' => 'PAN']);
    $clientB = Client::factory()->create(['client_name' => 'Sephora', 'client_code' => 'SEP']);
    $storeA  = Store::factory()->create(['client_id' => $clientA->id, 'store_code' => 'PAN-SYD-001']);
    $storeB  = Store::factory()->create(['client_id' => $clientB->id, 'store_code' => 'SEP-SYD-001']);

    $user = User::factory()->create(['role' => UserRole::ClientUser, 'client_id' => $clientA->id]);

    expect($user->can('view', $storeA))->toBeTrue()
        ->and($user->can('view', $storeB))->toBeFalse(); // cross-tenant deny
});

it('client_user cannot mutate stores even within their own client', function () {
    $clientA = Client::factory()->create(['client_name' => 'Pandora', 'client_code' => 'PAN']);
    $storeA  = Store::factory()->create(['client_id' => $clientA->id, 'store_code' => 'PAN-SYD-001']);
    $user    = User::factory()->create(['role' => UserRole::ClientUser, 'client_id' => $clientA->id]);

    expect($user->can('update', $storeA))->toBeFalse()
        ->and($user->can('delete', $storeA))->toBeFalse()
        ->and($user->can('create', Store::class))->toBeFalse();
});

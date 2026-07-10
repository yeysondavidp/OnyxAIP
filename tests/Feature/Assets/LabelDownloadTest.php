<?php

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('pm can download a single asset label pdf', function () {
    $store = Store::factory()->create();
    $pm    = User::factory()->create(['role' => UserRole::Pm->value, 'client_id' => $store->client_id]);
    $asset = Asset::factory()->forStore($store)->create(['asset_code' => 'PAN-SCR-LBL']);

    $response = $this->actingAs($pm)->get(route('assets.label', $asset));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('pm can download a batch label sheet for multiple assets', function () {
    $store  = Store::factory()->create();
    $pm     = User::factory()->create(['role' => UserRole::Pm->value, 'client_id' => $store->client_id]);
    $assets = Asset::factory()->forStore($store)->count(2)->create();

    $response = $this->actingAs($pm)->post(route('assets.labels.batch'), [
        'asset_ids' => $assets->pluck('id')->all(),
    ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

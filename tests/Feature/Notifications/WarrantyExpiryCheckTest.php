<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Store;
use App\Models\User;
use App\Notifications\PmNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('fires once per configured threshold the asset already falls within', function () {
    Notification::fake();

    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $asset = Asset::factory()->create([
        'store_id'        => $store->id,
        'client_id'       => $store->client_id,
        'warranty_expiry' => now()->addDays(25), // within all of 30/60/90
    ]);

    $this->artisan('warranty:check-expiry')->assertSuccessful();

    Notification::assertSentToTimes($pm, PmNotification::class, 3);
    $this->assertDatabaseCount('warranty_notification_log', 3);
});

it('is idempotent on a second run', function () {
    Notification::fake();

    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    Asset::factory()->create([
        'store_id'        => $store->id,
        'client_id'       => $store->client_id,
        'warranty_expiry' => now()->addDays(25),
    ]);

    $this->artisan('warranty:check-expiry')->assertSuccessful();
    $this->artisan('warranty:check-expiry')->assertSuccessful();

    Notification::assertSentToTimes($pm, PmNotification::class, 3);
});

it('skips assets with no warranty_expiry', function () {
    Notification::fake();

    $store = Store::factory()->create();
    Asset::factory()->create(['store_id' => $store->id, 'client_id' => $store->client_id, 'warranty_expiry' => null]);

    $this->artisan('warranty:check-expiry')->assertSuccessful();

    $this->assertDatabaseCount('warranty_notification_log', 0);
});

it('skips decommissioned assets', function () {
    Notification::fake();

    $store = Store::factory()->create();
    Asset::factory()->create([
        'store_id'        => $store->id,
        'client_id'       => $store->client_id,
        'warranty_expiry' => now()->addDays(10),
        'asset_status'    => AssetStatus::Decommissioned->value,
    ]);

    $this->artisan('warranty:check-expiry')->assertSuccessful();

    $this->assertDatabaseCount('warranty_notification_log', 0);
});

it('does not fire for an asset outside every threshold window', function () {
    Notification::fake();

    $store = Store::factory()->create();
    Asset::factory()->create([
        'store_id'        => $store->id,
        'client_id'       => $store->client_id,
        'warranty_expiry' => now()->addDays(200),
    ]);

    $this->artisan('warranty:check-expiry')->assertSuccessful();

    $this->assertDatabaseCount('warranty_notification_log', 0);
});

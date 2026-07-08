<?php

use App\Enums\AustralianState;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function placeholderNzStore(array $overrides = []): Store
{
    return Store::factory()->create(array_merge([
        'store_name'     => 'DIOR Boutique Commercial Bay',
        'country'        => 'Australia',
        'state'          => AustralianState::Nsw->value,
        'store_timezone' => 'Australia/Sydney',
        'notes'          => 'Imported from vendor CSV export — address, suburb and postcode need PM confirmation.'
            .' Actual location: Auckland, New Zealand (Commercial Bay) — state stored as an Australian placeholder only;'
            .' the system does not yet support non-AU stores.',
    ], $overrides));
}

it('corrects a placeholder nz store to its real country, region, and timezone', function () {
    $store = placeholderNzStore();

    $this->artisan('stores:backfill-nz')->assertSuccessful();

    $store->refresh();

    expect($store->country->value)->toBe('New Zealand');
    expect($store->state->value)->toBe('AUK');
    expect($store->store_timezone)->toBe('Pacific/Auckland');
    expect($store->notes)->not->toContain('Australian placeholder only');
    expect($store->notes)->toContain('Auckland, New Zealand (Commercial Bay)');
});

it('is idempotent — running it twice does not error or double-edit', function () {
    placeholderNzStore();

    $this->artisan('stores:backfill-nz')->assertSuccessful();
    $this->artisan('stores:backfill-nz')->assertSuccessful();
});

it('does not touch a normal australian store', function () {
    $store = Store::factory()->create(['country' => 'Australia', 'state' => AustralianState::Vic->value]);

    $this->artisan('stores:backfill-nz')->assertSuccessful();

    $store->refresh();
    expect($store->country->value)->toBe('Australia');
    expect($store->state->value)->toBe('VIC');
});

it('does not write anything on a dry run', function () {
    placeholderNzStore();

    $this->artisan('stores:backfill-nz --dry-run')->assertSuccessful();

    $store = Store::first();
    expect($store->country->value)->toBe('Australia');
});

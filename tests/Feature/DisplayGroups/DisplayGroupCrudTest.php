<?php

use App\Enums\AssetType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\DisplayGroup;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function pmFor(Store $store): User
{
    // PMs are unrestricted in v1 — any PM can manage any store.
    return User::factory()->create(['role' => UserRole::Pm->value]);
}

function playerAt(Store $store): Asset
{
    return Asset::factory()->forStore($store)->create([
        'asset_type' => AssetType::MediaPlayer->value,
    ]);
}

function screenAt(Store $store): Asset
{
    return Asset::factory()->forStore($store)->create([
        'asset_type' => AssetType::DigitalScreen->value,
    ]);
}

// ── Index ─────────────────────────────────────────────────────────────────────

it('pm can view display group index for their store', function () {
    $store = Store::factory()->create();
    $pm    = pmFor($store);

    $this->actingAs($pm)->get(route('stores.display-groups.index', $store))
        ->assertOk();
});

it('unauthenticated user is redirected from display group index', function () {
    $store = Store::factory()->create();

    $this->get(route('stores.display-groups.index', $store))
        ->assertRedirect(route('login'));
});

it('technician is denied display group index', function () {
    $store      = Store::factory()->create();
    $technician = User::factory()->create(['role' => UserRole::Technician->value]);

    $this->actingAs($technician)->get(route('stores.display-groups.index', $store))
        ->assertForbidden();
});

// ── Create (happy path) ───────────────────────────────────────────────────────

it('pm can create a display group', function () {
    $store  = Store::factory()->create();
    $pm     = pmFor($store);
    $player = playerAt($store);
    $screen = screenAt($store);

    $this->actingAs($pm)->post(route('stores.display-groups.store', $store), [
        'group_name'       => 'Window Bay North',
        'player_asset_id'  => $player->id,
        'screen_asset_ids' => [$screen->id],
    ])->assertRedirect(route('stores.display-groups.index', $store));

    expect(DisplayGroup::where('store_id', $store->id)->count())->toBe(1);
    expect(DisplayGroup::first()->screens()->count())->toBe(1);
});

// ── DB unique constraints enforced ───────────────────────────────────────────

it('db unique constraint prevents assigning same player to two groups', function () {
    $store  = Store::factory()->create();
    $player = playerAt($store);
    $screen = screenAt($store);

    DisplayGroup::factory()->forStore($store, $player)->create();

    expect(fn () => DB::table('display_groups')->insert([
        'store_id'        => $store->id,
        'group_name'      => 'Duplicate',
        'player_asset_id' => $player->id,
        'created_at'      => now(),
        'updated_at'      => now(),
    ])
    )->toThrow(QueryException::class);
});

it('db unique constraint prevents assigning same screen to two groups', function () {
    $store   = Store::factory()->create();
    $player1 = playerAt($store);
    $player2 = playerAt($store);
    $screen  = screenAt($store);

    $group1 = DisplayGroup::factory()->forStore($store, $player1)->create();
    $group1->screens()->attach($screen->id);

    $group2 = DisplayGroup::factory()->forStore($store, $player2)->create();

    expect(fn () => DB::table('display_group_screens')->insert([
        'display_group_id' => $group2->id,
        'asset_id'         => $screen->id,
    ])
    )->toThrow(QueryException::class);
});

// ── Cross-client deny ─────────────────────────────────────────────────────────

it('unauthenticated request cannot create display group', function () {
    $storeB = Store::factory()->create();
    $player = playerAt($storeB);
    $screen = screenAt($storeB);

    $this->post(route('stores.display-groups.store', $storeB), [
        'group_name'       => 'Test',
        'player_asset_id'  => $player->id,
        'screen_asset_ids' => [$screen->id],
    ])->assertRedirect(route('login'));
});

// ── Delete (soft delete, releases assets) ────────────────────────────────────

it('pm can delete a display group', function () {
    $store  = Store::factory()->create();
    $pm     = pmFor($store);
    $player = playerAt($store);
    $screen = screenAt($store);

    $group = DisplayGroup::factory()->forStore($store, $player)->create();
    $group->screens()->attach($screen->id);

    $this->actingAs($pm)->delete(route('stores.display-groups.destroy', [$store, $group]))
        ->assertRedirect(route('stores.display-groups.index', $store));

    expect(DisplayGroup::withTrashed()->find($group->id))->not->toBeNull();
    expect(DisplayGroup::find($group->id))->toBeNull(); // soft-deleted
});

// ── Audit entries written ─────────────────────────────────────────────────────

it('audit entry is written when display group is created', function () {
    $store  = Store::factory()->create();
    $pm     = pmFor($store);
    $player = playerAt($store);
    $screen = screenAt($store);

    $this->actingAs($pm)->post(route('stores.display-groups.store', $store), [
        'group_name'       => 'Audit Test Bay',
        'player_asset_id'  => $player->id,
        'screen_asset_ids' => [$screen->id],
    ]);

    expect(AuditLog::where('action', 'created')
        ->where('auditable_type', 'App\Models\DisplayGroup')
        ->count()
    )->toBeGreaterThan(0);
});

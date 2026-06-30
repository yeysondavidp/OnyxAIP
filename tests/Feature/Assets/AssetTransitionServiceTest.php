<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Services\AssetTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AssetTransitionService::class);
});

// ── Permitted transitions ────────────────────────────────────────────────────

it('transitions active to faulty', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Active->value]);

    $this->service->transitionTo($asset, AssetStatus::Faulty);

    expect($asset->asset_status)->toBe(AssetStatus::Faulty);
    expect(Asset::find($asset->id)->asset_status)->toBe(AssetStatus::Faulty);
});

it('transitions faulty to under maintenance', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Faulty->value]);

    $this->service->transitionTo($asset, AssetStatus::UnderMaintenance);

    expect($asset->asset_status)->toBe(AssetStatus::UnderMaintenance);
});

it('transitions under maintenance back to active', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::UnderMaintenance->value]);

    $this->service->transitionTo($asset, AssetStatus::Active);

    expect($asset->asset_status)->toBe(AssetStatus::Active);
});

it('transitions active to decommissioned', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Active->value]);

    $this->service->transitionTo($asset, AssetStatus::Decommissioned);

    expect($asset->asset_status)->toBe(AssetStatus::Decommissioned);
});

// ── Illegal transitions rejected at model level ──────────────────────────────

it('rejects decommissioned to active (illegal transition)', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Decommissioned->value]);

    expect(fn () => $this->service->transitionTo($asset, AssetStatus::Active))
        ->toThrow(InvalidArgumentException::class);

    // DB unchanged
    expect(Asset::find($asset->id)->asset_status)->toBe(AssetStatus::Decommissioned);
});

it('rejects offline to faulty (illegal transition)', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Offline->value]);

    expect(fn () => $this->service->transitionTo($asset, AssetStatus::Faulty))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects faulty to active (skips under_maintenance step)', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Faulty->value]);

    expect(fn () => $this->service->transitionTo($asset, AssetStatus::Active))
        ->toThrow(InvalidArgumentException::class);
});

// ── Asset history written ────────────────────────────────────────────────────

it('writes an asset history record on transition', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Active->value]);

    $this->service->transitionTo($asset, AssetStatus::Faulty, reason: 'Screen blank');

    $history = AssetHistory::where('asset_id', $asset->id)->first();

    expect($history)->not->toBeNull()
        ->and($history->status_before)->toBe(AssetStatus::Active)
        ->and($history->status_after)->toBe(AssetStatus::Faulty)
        ->and($history->reason)->toBe('Screen blank');
});

it('history record cannot be updated (append-only)', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Active->value]);
    $this->service->transitionTo($asset, AssetStatus::Faulty);

    $history = AssetHistory::where('asset_id', $asset->id)->firstOrFail();
    $result  = $history->save(); // exists = true, should return false

    expect($result)->toBeFalse();
});

it('history record cannot be deleted (append-only)', function () {
    $asset = Asset::factory()->create(['asset_status' => AssetStatus::Active->value]);
    $this->service->transitionTo($asset, AssetStatus::Faulty);

    $history = AssetHistory::where('asset_id', $asset->id)->firstOrFail();
    $result  = $history->delete();

    expect($result)->toBeFalse();
});

// ── DB constraint enforced ───────────────────────────────────────────────────

it('isPermitted returns false for unknown transition', function () {
    expect(
        $this->service->isPermitted(AssetStatus::Decommissioned, AssetStatus::Active)
    )->toBeFalse();
});

it('permittedTransitionsFrom active returns correct set', function () {
    $permitted = $this->service->permittedTransitionsFrom(AssetStatus::Active);

    expect($permitted)->toContain(AssetStatus::Faulty)
        ->toContain(AssetStatus::Offline)
        ->toContain(AssetStatus::Decommissioned)
        ->not->toContain(AssetStatus::UnderMaintenance);
});

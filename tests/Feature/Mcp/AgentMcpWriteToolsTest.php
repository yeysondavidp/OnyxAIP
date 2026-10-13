<?php

use App\Enums\AgentAbility;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\DisplayGroup;
use App\Models\JobAttachment;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function writerKey(?int $clientId = null): string
{
    return agentKey([AgentAbility::RegisterRead, AgentAbility::AssetsWrite, AgentAbility::JobsWrite, AgentAbility::AttachmentsWrite], $clientId);
}

function playerPayload(array $overrides = []): array
{
    return array_merge([
        'asset_code'   => 'PAN-PLY-0101',
        'asset_type'   => 'media_player',
        'store_code'   => 'PAN-MIR-001',
        'asset_name'   => 'Beat MIB 19 — Front window',
        'manufacturer' => 'Beat',
        'model'        => 'MIB 19',
        'asset_status' => 'active',
        'player_type'  => 'standalone_hardware',
        'mac_address'  => '00:CE:39:D8:2A:51',
    ], $overrides);
}

beforeEach(fn () => Store::factory()->create(['store_code' => 'PAN-MIR-001']));

// ── create_asset ─────────────────────────────────────────────────────────────

it('create_asset with dry_run validates and writes nothing', function () {
    $result = callTool(writerKey(), 'create_asset', playerPayload(['dry_run' => true]));

    expect([$result['data']['dry_run'], Asset::count()])->toBe([true, 0]);
});

it('create_asset registers a player with its detail row', function () {
    callTool(writerKey(), 'create_asset', playerPayload());

    expect(Asset::where('asset_code', 'PAN-PLY-0101')->with('playerDetail')->sole()->playerDetail->mac_address)->toBe('00:CE:39:D8:2A:51');
});

it('create_asset applies the asset form rules', function () {
    expect(callTool(writerKey(), 'create_asset', playerPayload(['player_type' => null]))['text'])->toContain('player type');
});

it('create_asset refuses router passwords', function () {
    expect(callTool(writerKey(), 'create_asset', playerPayload(['asset_type' => 'infrastructure', 'admin_password' => 'x']))['text'])
        ->toContain('Router passwords can only be set by a PM');
});

it('a client-scoped key cannot create assets at another client\'s store', function () {
    $other = Store::factory()->create(['store_code' => 'SEP-XXX-001']);
    $mine  = Store::factory()->create();

    $result = callTool(writerKey($mine->client_id), 'create_asset', playerPayload(['store_code' => $other->store_code]));

    expect([$result['isError'], Asset::count()])->toBe([true, 0]);
});

// ── update_asset ─────────────────────────────────────────────────────────────

it('update_asset changes only the fields sent', function () {
    $key = writerKey();
    callTool($key, 'create_asset', playerPayload());

    $result = callTool($key, 'update_asset', ['asset_code' => 'PAN-PLY-0101', 'model' => 'MIB X']);

    expect([$result['data']['changed'], Asset::where('asset_code', 'PAN-PLY-0101')->value('model')])->toBe([['model'], 'MIB X']);
});

it('update_asset will not change the status', function () {
    $key = writerKey();
    callTool($key, 'create_asset', playerPayload());

    expect(callTool($key, 'update_asset', ['asset_code' => 'PAN-PLY-0101', 'asset_status' => 'faulty'])['text'])
        ->toContain('Use change_asset_status');
});

it('update_asset keeps router passwords it never sees', function () {
    $key   = writerKey();
    $store = Store::where('store_code', 'PAN-MIR-001')->sole();
    $asset = Asset::factory()->create(['store_id' => $store->id, 'client_id' => $store->client_id, 'asset_type' => 'infrastructure', 'asset_code' => 'PAN-INF-0101']);
    $asset->createDetail(['admin_password' => 'keep-me']);

    callTool($key, 'update_asset', ['asset_code' => 'PAN-INF-0101', 'location_notes' => 'Rack']);

    expect($asset->infrastructureDetail()->sole()->admin_password)->toBe('keep-me');
});

// ── change_asset_status ──────────────────────────────────────────────────────

it('change_asset_status records the agent and the reason in history', function () {
    $key = writerKey();
    callTool($key, 'create_asset', playerPayload());

    callTool($key, 'change_asset_status', ['asset_code' => 'PAN-PLY-0101', 'status' => 'faulty', 'reason' => 'Blank screen on visit']);

    expect(AssetHistory::latest('id')->first()->only(['status_after', 'actor_role', 'reason']))
        ->toBe(['status_after' => AssetStatus::Faulty, 'actor_role' => 'agent', 'reason' => 'Blank screen on visit']);
});

it('change_asset_status refuses a transition the lifecycle does not allow', function () {
    $key = writerKey();
    callTool($key, 'create_asset', playerPayload(['asset_status' => 'decommissioned']));

    expect(callTool($key, 'change_asset_status', ['asset_code' => 'PAN-PLY-0101', 'status' => 'active', 'reason' => 'x'])['text'])
        ->toContain('final status');
});

// ── attach_assets_to_job ─────────────────────────────────────────────────────

it('attach_assets_to_job records status before and moves active assets to under maintenance', function () {
    $key   = writerKey();
    $store = Store::where('store_code', 'PAN-MIR-001')->sole();
    $job   = ServiceJob::factory()->create(['store_id' => $store->id, 'client_id' => $store->client_id, 'job_reference' => 'JOB-2026-901']);
    callTool($key, 'create_asset', playerPayload());

    callTool($key, 'attach_assets_to_job', ['job_reference' => 'JOB-2026-901', 'asset_codes' => ['PAN-PLY-0101']]);

    $asset = Asset::where('asset_code', 'PAN-PLY-0101')->sole();
    expect([$job->assets()->sole()->pivot->status_before, $asset->asset_status])->toBe(['active', AssetStatus::UnderMaintenance]);
});

it('attach_assets_to_job refuses assets from another store', function () {
    $key   = writerKey();
    $store = Store::where('store_code', 'PAN-MIR-001')->sole();
    ServiceJob::factory()->create(['store_id' => $store->id, 'client_id' => $store->client_id, 'job_reference' => 'JOB-2026-902']);
    Asset::factory()->create(['asset_code' => 'ELSEWHERE-1']);

    expect(callTool($key, 'attach_assets_to_job', ['job_reference' => 'JOB-2026-902', 'asset_codes' => ['ELSEWHERE-1']])['text'])
        ->toContain('Not found at this job\'s store: ELSEWHERE-1');
});

it('attach_assets_to_job leaves a validated job alone', function () {
    $store = Store::where('store_code', 'PAN-MIR-001')->sole();
    ServiceJob::factory()->create(['store_id' => $store->id, 'client_id' => $store->client_id, 'job_reference' => 'JOB-2026-903', 'job_status' => 'validated']);

    expect(callTool(writerKey(), 'attach_assets_to_job', ['job_reference' => 'JOB-2026-903', 'asset_codes' => ['X']])['text'])
        ->toContain('can no longer change');
});

// ── create_display_group ─────────────────────────────────────────────────────

it('create_display_group links a player to its screens', function () {
    $key   = writerKey();
    $store = Store::where('store_code', 'PAN-MIR-001')->sole();
    callTool($key, 'create_asset', playerPayload());
    Asset::factory()->create(['store_id' => $store->id, 'client_id' => $store->client_id, 'asset_type' => 'digital_screen', 'asset_code' => 'PAN-SCR-0101']);

    callTool($key, 'create_display_group', ['store_code' => 'PAN-MIR-001', 'group_name' => 'Front window', 'player_code' => 'PAN-PLY-0101', 'screen_codes' => ['PAN-SCR-0101']]);

    expect(DisplayGroup::with('screens')->sole()->screens->pluck('asset_code')->all())->toBe(['PAN-SCR-0101']);
});

it('create_display_group refuses a screen that is not at the store', function () {
    $key = writerKey();
    callTool($key, 'create_asset', playerPayload());

    expect(callTool($key, 'create_display_group', ['store_code' => 'PAN-MIR-001', 'group_name' => 'G', 'player_code' => 'PAN-PLY-0101', 'screen_codes' => ['NOPE']])['isError'])
        ->toBeTrue();
});

// ── add_job_attachment ───────────────────────────────────────────────────────

it('add_job_attachment stores a pdf with its detected type', function () {
    Storage::fake('local');
    $store = Store::where('store_code', 'PAN-MIR-001')->sole();
    ServiceJob::factory()->create(['store_id' => $store->id, 'client_id' => $store->client_id, 'job_reference' => 'JOB-2026-904']);

    callTool(writerKey(), 'add_job_attachment', ['job_reference' => 'JOB-2026-904', 'filename' => 'report.pdf', 'content_base64' => base64_encode("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")]);

    expect(JobAttachment::sole()->only(['original_filename', 'mime_type']))->toBe(['original_filename' => 'report.pdf', 'mime_type' => 'application/pdf']);
});

it('add_job_attachment refuses content that is not on the allow-list', function () {
    Storage::fake('local');
    $store = Store::where('store_code', 'PAN-MIR-001')->sole();
    ServiceJob::factory()->create(['store_id' => $store->id, 'client_id' => $store->client_id, 'job_reference' => 'JOB-2026-905']);

    $result = callTool(writerKey(), 'add_job_attachment', ['job_reference' => 'JOB-2026-905', 'filename' => 'report.pdf', 'content_base64' => base64_encode('#!/bin/sh echo hi')]);

    expect([$result['isError'], JobAttachment::count()])->toBe([true, 0]);
});

// ── abilities ────────────────────────────────────────────────────────────────

it('a read-only key cannot call write tools', function () {
    mcp(agentKey(), 'tools/call', ['name' => 'create_asset', 'arguments' => playerPayload()])
        ->assertJsonPath('error.message', 'Tool [create_asset] not found.');
});

it('no tool can delete anything', function () {
    $names = collect(mcp(writerKey(), 'tools/list')->json('result.tools'))->pluck('name');

    expect($names->filter(fn (string $n) => str_contains($n, 'delete') || str_contains($n, 'remove'))->all())->toBe([]);
});

it('an attachments key still cannot delete attachments', function () {
    $agent = User::factory()->agent()->create();
    $job   = ServiceJob::factory()->create();
    Sanctum::actingAs($agent, [AgentAbility::AttachmentsWrite->value]);

    expect([$agent->can('addAttachment', $job), $agent->can('manageAttachments', $job)])->toBe([true, false]);
});

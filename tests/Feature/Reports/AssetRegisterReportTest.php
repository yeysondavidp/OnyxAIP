<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\ReportExport;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('pm can generate an asset register csv scoped to one client', function () {
    Storage::fake('local');

    $pm = User::factory()->pm()->create();

    $clientA = Client::factory()->create();
    $storeA  = Store::factory()->create(['client_id' => $clientA->id]);
    Asset::factory()->forClientAndStore($clientA, $storeA)->count(2)->create();

    $clientB = Client::factory()->create();
    $storeB  = Store::factory()->create(['client_id' => $clientB->id]);
    Asset::factory()->forClientAndStore($clientB, $storeB)->create();

    $this->actingAs($pm)->post(route('reports.asset-register.store'), [
        'client_id'   => $clientA->id,
        'report_kind' => 'register_csv',
    ])->assertRedirect(route('reports.index'));

    $export = ReportExport::where('client_id', $clientA->id)->firstOrFail();

    expect($export->row_count)->toBe(2);

    $csv = Storage::disk('local')->get($export->path);
    expect($csv)->toContain($storeA->store_name);
    expect($csv)->not->toContain($storeB->store_name);
});

it('technician cannot access the report screens', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)->get(route('reports.asset-register.create'))->assertForbidden();
});

it('unauthenticated visitor is redirected to login', function () {
    $this->get(route('reports.asset-register.create'))->assertRedirect(route('login'));
});

it('report policy rejects a client_id the acting user is not permitted to see', function () {
    $clientA        = Client::factory()->create();
    $clientB        = Client::factory()->create();
    $restrictedUser = User::factory()->clientUser()->create(['client_id' => $clientA->id]);

    expect($restrictedUser->can('generateForClient', [ReportExport::class, $clientA->id]))->toBeTrue();
    expect($restrictedUser->can('generateForClient', [ReportExport::class, $clientB->id]))->toBeFalse();
});

<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\ContentChangeFrequency;
use App\Enums\LightType;
use App\Enums\Orientation;
use App\Enums\PlayerType;
use App\Enums\TotemSuppliedBy;
use App\Http\Requests\CreateAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Asset::class);

        return view('assets.index');
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);

        return view('assets.create', $this->formData());
    }

    public function store(CreateAssetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        [$baseData, $detailData] = $this->splitValidated($validated);

        $asset = Asset::createWithDetail($baseData, $detailData);

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', "Asset '{$asset->asset_name}' has been created.");
    }

    public function show(Asset $asset): View
    {
        $this->authorize('view', $asset);

        $asset->load(['client', 'store', 'parent']);
        Asset::loadTypeDetails(collect([$asset]));

        return view('assets.show', compact('asset'));
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);

        $asset->load(['screenDetail', 'playerDetail', 'lightboxDetail', 'infrastructureDetail', 'windowFixtureDetail']);

        return view('assets.edit', array_merge($this->formData(), compact('asset')));
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validated();

        [$baseData, $detailData] = $this->splitValidated($validated);

        $asset->updateWithDetail($baseData, $detailData);

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', "Asset '{$asset->asset_name}' has been updated.");
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        $asset->update(['asset_status' => AssetStatus::Decommissioned]);

        return redirect()
            ->route('assets.index')
            ->with('success', "Asset '{$asset->asset_name}' has been decommissioned.");
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function splitValidated(mixed $validated): array
    {
        $baseKeys = [
            'asset_code', 'asset_type', 'client_id', 'store_id', 'asset_name',
            'manufacturer', 'model', 'serial_number', 'purchase_date', 'warranty_expiry',
            'install_date', 'asset_status', 'location_notes', 'parent_asset_id', 'notes',
        ];

        $baseData   = array_intersect_key($validated, array_flip($baseKeys));
        $detailData = array_diff_key($validated, array_flip($baseKeys));

        return [$baseData, $detailData];
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $clients = Client::with(['stores' => fn ($q) => $q->where('is_active', true)->orderBy('store_name')->select(['id', 'client_id', 'store_name'])])
            ->where('is_active', true)
            ->orderBy('client_name')
            ->get(['id', 'client_name']);

        return [
            'clients'                  => $clients,
            'assetTypes'               => AssetType::cases(),
            'assetStatuses'            => AssetStatus::cases(),
            'orientations'             => Orientation::cases(),
            'totemSuppliedByOptions'   => TotemSuppliedBy::cases(),
            'playerTypes'              => PlayerType::cases(),
            'lightTypes'               => LightType::cases(),
            'contentChangeFrequencies' => ContentChangeFrequency::cases(),
        ];
    }
}

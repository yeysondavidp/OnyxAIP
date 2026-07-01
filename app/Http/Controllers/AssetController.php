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
use App\Models\ServiceHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $serviceHistory = $asset->serviceHistory()->with('serviceJob')->paginate(10);

        return view('assets.show', compact('asset', 'serviceHistory'));
    }

    /** Serve a service-history-referenced photo, scoped to this asset's own history rows (US-11.3). */
    public function downloadHistoryPhoto(Request $request, Asset $asset): StreamedResponse
    {
        $this->authorize('view', $asset);

        $path = (string) $request->query('path', '');

        // Only allow paths that appear in this asset's own service_history rows —
        // prevents path traversal / cross-asset access via a manipulated query string.
        $allowed = ServiceHistory::where('asset_id', $asset->id)
            ->get()
            ->flatMap(fn (ServiceHistory $h) => array_merge($h->before_photo_paths ?? [], $h->after_photo_paths ?? []));

        abort_unless($allowed->contains($path), 403);

        return Storage::disk('local')->download($path);
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

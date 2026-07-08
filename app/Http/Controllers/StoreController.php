<?php

namespace App\Http\Controllers;

use App\Enums\AustralianState;
use App\Enums\Country;
use App\Enums\JobStatus;
use App\Enums\NewZealandRegion;
use App\Enums\StoreType;
use App\Http\Requests\CreateStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Models\Asset;
use App\Models\Client;
use App\Models\DisplayGroup;
use App\Models\ServiceJob;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Store::class);

        return view('stores.index');
    }

    public function create(): View
    {
        $this->authorize('create', Store::class);

        return view('stores.create', self::countryFormData() + [
            'clients'    => Client::where('is_active', true)->orderBy('client_name')->get(['id', 'client_name']),
            'storeTypes' => StoreType::cases(),
        ]);
    }

    public function store(CreateStoreRequest $request): RedirectResponse
    {
        $data            = $request->validated();
        $data['country'] = $data['country'] ?? Country::Australia->value;

        if (empty($data['store_code'])) {
            $data['store_code'] = Store::generateCode(Client::findOrFail($data['client_id']), $data['suburb']);
        }

        // client_id comes from the validated form — PM is authorised to assign any client
        $store = Store::create($data);

        return redirect()
            ->route('stores.show', $store)
            ->with('success', "Store '{$store->store_name}' has been created.");
    }

    public function show(Store $store): View
    {
        $this->authorize('view', $store);

        $assetCount = Asset::where('store_id', $store->id)->count();
        $assets     = Asset::where('store_id', $store->id)
            ->orderBy('asset_name')
            ->limit(10)
            ->get();
        $displayGroups = DisplayGroup::with(['player', 'screens'])
            ->where('store_id', $store->id)
            ->orderBy('group_name')
            ->get();

        // Open, SLA-tracked jobs for this store (§8, US-12.3)
        $slaTrackedJobs = ServiceJob::where('store_id', $store->id)
            ->whereNotNull('sla_clock_started_at')
            ->whereNotIn('job_status', [JobStatus::Validated->value, JobStatus::Cancelled->value])
            ->orderByDesc('sla_breached')
            ->orderByDesc('sla_at_risk')
            ->orderBy('sla_resolution_target_at')
            ->get();

        // Recent service jobs at this store (§8) — independent of the
        // per-asset Service History log below, so a visit still shows up
        // here even when it has no affected assets attached.
        $recentJobs = ServiceJob::where('store_id', $store->id)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('stores.show', [
            'store'          => $store->load('client'),
            'assets'         => $assets,
            'assetCount'     => $assetCount,
            'displayGroups'  => $displayGroups,
            'slaTrackedJobs' => $slaTrackedJobs,
            'recentJobs'     => $recentJobs,
        ]);
    }

    public function edit(Store $store): View
    {
        $this->authorize('update', $store);

        return view('stores.edit', self::countryFormData() + [
            'store'      => $store,
            'clients'    => Client::where('is_active', true)->orderBy('client_name')->get(['id', 'client_name']),
            'storeTypes' => StoreType::cases(),
        ]);
    }

    public function update(UpdateStoreRequest $request, Store $store): RedirectResponse
    {
        $data = $request->validated();

        $store->update($data);

        return redirect()
            ->route('stores.show', $store)
            ->with('success', "Store '{$store->store_name}' has been updated.");
    }

    public function destroy(Store $store): RedirectResponse
    {
        $this->authorize('delete', $store);

        $store->update(['is_active' => false]);

        return redirect()
            ->route('stores.index')
            ->with('success', "Store '{$store->store_name}' has been deactivated.");
    }

    /**
     * Shared country/state/timezone options for the create and edit forms'
     * Country → State/Region → Timezone cascade (US-03.5). One source so the
     * two views never drift apart.
     *
     * @return array<string, mixed>
     */
    private static function countryFormData(): array
    {
        return [
            'countries'           => Country::cases(),
            'australianStates'    => AustralianState::cases(),
            'newZealandRegions'   => NewZealandRegion::cases(),
            'australianTimezones' => Country::Australia->timezones(),
            'newZealandTimezones' => Country::NewZealand->timezones(),
        ];
    }
}

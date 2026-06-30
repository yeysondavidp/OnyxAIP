<?php

namespace App\Http\Controllers;

use App\Enums\AustralianState;
use App\Enums\StoreType;
use App\Http\Requests\CreateStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Models\Asset;
use App\Models\Client;
use App\Models\DisplayGroup;
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

        return view('stores.create', [
            'clients'    => Client::where('is_active', true)->orderBy('client_name')->get(['id', 'client_name']),
            'storeTypes' => StoreType::cases(),
            'states'     => AustralianState::cases(),
            'timezones'  => self::australianTimezones(),
        ]);
    }

    public function store(CreateStoreRequest $request): RedirectResponse
    {
        $data            = $request->validated();
        $data['country'] = $data['country'] ?? 'Australia';

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

        return view('stores.show', [
            'store'         => $store->load('client'),
            'assets'        => $assets,
            'assetCount'    => $assetCount,
            'displayGroups' => $displayGroups,
        ]);
    }

    public function edit(Store $store): View
    {
        $this->authorize('update', $store);

        return view('stores.edit', [
            'store'      => $store,
            'clients'    => Client::where('is_active', true)->orderBy('client_name')->get(['id', 'client_name']),
            'storeTypes' => StoreType::cases(),
            'states'     => AustralianState::cases(),
            'timezones'  => self::australianTimezones(),
        ]);
    }

    public function update(UpdateStoreRequest $request, Store $store): RedirectResponse
    {
        $data            = $request->validated();
        $data['country'] = $data['country'] ?? 'Australia';

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

    /** @return array<string, string> */
    private static function australianTimezones(): array
    {
        return [
            'Australia/Sydney'    => 'Sydney / Canberra (AEST/AEDT)',
            'Australia/Melbourne' => 'Melbourne (AEST/AEDT)',
            'Australia/Brisbane'  => 'Brisbane (AEST — no daylight saving)',
            'Australia/Perth'     => 'Perth (AWST)',
            'Australia/Adelaide'  => 'Adelaide (ACST/ACDT)',
            'Australia/Darwin'    => 'Darwin (ACST — no daylight saving)',
            'Australia/Hobart'    => 'Hobart (AEST/AEDT)',
            'Australia/Lord_Howe' => 'Lord Howe Island (LHST/LHDT)',
        ];
    }
}

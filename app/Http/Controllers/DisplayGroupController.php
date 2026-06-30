<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Http\Requests\CreateDisplayGroupRequest;
use App\Http\Requests\UpdateDisplayGroupRequest;
use App\Models\Asset;
use App\Models\DisplayGroup;
use App\Models\Store;
use App\Services\DisplayGroupService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DisplayGroupController extends Controller
{
    public function __construct(private readonly DisplayGroupService $service) {}

    public function index(Store $store): View
    {
        $this->authorize('viewAny', [DisplayGroup::class, $store]);

        $groups = DisplayGroup::with(['player', 'screens'])
            ->where('store_id', $store->id)
            ->orderBy('group_name')
            ->get();

        return view('display-groups.index', compact('store', 'groups'));
    }

    public function create(Store $store): View
    {
        $this->authorize('create', [DisplayGroup::class, $store]);

        [$players, $screens] = $this->eligibleAssets($store);

        return view('display-groups.create', compact('store', 'players', 'screens'));
    }

    public function store(CreateDisplayGroupRequest $request, Store $store): RedirectResponse
    {
        $this->authorize('create', [DisplayGroup::class, $store]);

        $group = $this->service->create($store, $request->validated());

        return redirect()
            ->route('stores.display-groups.index', $store)
            ->with('success', "Display group '{$group->group_name}' has been created.");
    }

    public function edit(Store $store, DisplayGroup $displayGroup): View
    {
        $this->authorize('update', $displayGroup);

        [$players, $screens] = $this->eligibleAssets($store, $displayGroup->id);
        $displayGroup->load(['player', 'screens']);

        return view('display-groups.edit', compact('store', 'displayGroup', 'players', 'screens'));
    }

    public function update(UpdateDisplayGroupRequest $request, Store $store, DisplayGroup $displayGroup): RedirectResponse
    {
        $this->authorize('update', $displayGroup);

        $this->service->update($displayGroup, $request->validated());

        return redirect()
            ->route('stores.display-groups.index', $store)
            ->with('success', "Display group '{$displayGroup->group_name}' has been updated.");
    }

    public function destroy(Store $store, DisplayGroup $displayGroup): RedirectResponse
    {
        $this->authorize('delete', $displayGroup);

        $name = $displayGroup->group_name;
        $this->service->delete($displayGroup);

        return redirect()
            ->route('stores.display-groups.index', $store)
            ->with('success', "Display group '{$name}' has been deleted.");
    }

    /**
     * Returns [players, screens] that are eligible for selection at this store.
     * When editing, the current group's own player/screens are re-included.
     *
     * @return array{0: Collection<int, Asset>, 1: Collection<int, Asset>}
     */
    private function eligibleAssets(Store $store, ?int $excludeGroupId = null): array
    {
        $players = Asset::where('store_id', $store->id)
            ->where('asset_type', AssetType::MediaPlayer->value)
            ->where(fn ($q) => $q->whereNotIn('id', function ($q2) use ($excludeGroupId) {
                $q2->select('player_asset_id')->from('display_groups')->whereNull('deleted_at')
                    ->when($excludeGroupId, fn ($q3) => $q3->where('id', '!=', $excludeGroupId));
            }))
            ->orderBy('asset_name')
            ->get();

        $screens = Asset::where('store_id', $store->id)
            ->where('asset_type', AssetType::DigitalScreen->value)
            ->where(fn ($q) => $q->whereNotIn('id', function ($q2) use ($excludeGroupId) {
                $q2->select('asset_id')->from('display_group_screens')
                    ->join('display_groups', 'display_groups.id', '=', 'display_group_screens.display_group_id')
                    ->whereNull('display_groups.deleted_at')
                    ->when($excludeGroupId, fn ($q3) => $q3->where('display_groups.id', '!=', $excludeGroupId));
            }))
            ->orderBy('asset_name')
            ->get();

        return [$players, $screens];
    }
}

<?php

namespace App\Livewire\Assets;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AssetList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'client')]
    public string $clientFilter = '';

    #[Url(as: 'store')]
    public string $storeFilter = '';

    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingClientFilter(): void
    {
        $this->storeFilter = '';
        $this->resetPage();
    }

    public function updatingStoreFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<Asset> */
    private function assets(): LengthAwarePaginator
    {
        return Asset::with(['client', 'store'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('asset_name', 'like', $term)
                        ->orWhere('asset_code', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('serial_number', 'like', $term);
                });
            })
            ->when($this->clientFilter !== '', fn ($q) => $q->where('client_id', (int) $this->clientFilter))
            ->when($this->storeFilter !== '', fn ($q) => $q->where('store_id', (int) $this->storeFilter))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('asset_type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('asset_status', $this->statusFilter))
            ->orderBy('asset_name')
            ->paginate(25);
    }

    /** @return Collection<int, Store> */
    private function filteredStores(): Collection
    {
        return Store::where('is_active', true)
            ->when($this->clientFilter !== '', fn ($q) => $q->where('client_id', (int) $this->clientFilter))
            ->orderBy('store_name')
            ->get(['id', 'client_id', 'store_name']);
    }

    public function render(): View
    {
        return view('livewire.assets.asset-list', [
            'assets'   => $this->assets(),
            'clients'  => Client::orderBy('client_name')->get(['id', 'client_name']),
            'stores'   => $this->filteredStores(),
            'types'    => AssetType::cases(),
            'statuses' => AssetStatus::cases(),
        ]);
    }
}

<?php

namespace App\Livewire\Stores;

use App\Enums\AustralianState;
use App\Enums\StoreType;
use App\Models\Client;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StoreList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'client')]
    public string $clientFilter = '';

    #[Url(as: 'state')]
    public string $stateFilter = '';

    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'inactive')]
    public bool $showInactive = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingClientFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStateFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingShowInactive(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<Store> */
    private function stores(): LengthAwarePaginator
    {
        return Store::with('client')
            ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('store_name', 'like', $term)
                        ->orWhere('store_code', 'like', $term);
                });
            })
            ->when($this->clientFilter !== '', fn ($q) => $q->where('client_id', (int) $this->clientFilter))
            ->when($this->stateFilter !== '', fn ($q) => $q->where('state', $this->stateFilter))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('store_type', $this->typeFilter))
            ->orderBy('store_name')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.stores.store-list', [
            'stores'  => $this->stores(),
            'clients' => Client::orderBy('client_name')->get(['id', 'client_name']),
            'states'  => AustralianState::cases(),
            'types'   => StoreType::cases(),
        ]);
    }
}

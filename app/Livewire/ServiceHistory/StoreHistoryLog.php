<?php

namespace App\Livewire\ServiceHistory;

use App\Enums\AssetType;
use App\Models\ServiceHistory;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Per-store aggregated chronological service history (US-11.4).
 *
 * Reads only — history is append-only. Shares the ServiceHistory::forStore()
 * aggregation path so this view and future EPIC-14 reporting never diverge.
 */
class StoreHistoryLog extends Component
{
    use WithPagination;

    public Store $store;

    #[Url(as: 'type')]
    public string $assetType = '';

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function updatedAssetType(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<ServiceHistory> */
    private function entries(): LengthAwarePaginator
    {
        return ServiceHistory::forStore($this->store->id, [
            'asset_type' => $this->assetType !== '' ? $this->assetType : null,
            'from'       => $this->dateFrom  !== '' ? $this->dateFrom : null,
            'to'         => $this->dateTo    !== '' ? $this->dateTo : null,
        ])->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.service-history.store-history-log', [
            'entries'    => $this->entries(),
            'assetTypes' => AssetType::cases(),
        ]);
    }
}

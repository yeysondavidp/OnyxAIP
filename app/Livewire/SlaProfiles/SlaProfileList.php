<?php

namespace App\Livewire\SlaProfiles;

use App\Models\SlaProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SlaProfileList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'inactive')]
    public bool $showInactive = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShowInactive(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<SlaProfile> */
    private function profiles(): LengthAwarePaginator
    {
        return SlaProfile::query()
            ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->withCount('clients')
            ->orderBy('name')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.sla-profiles.sla-profile-list', [
            'profiles' => $this->profiles(),
        ]);
    }
}

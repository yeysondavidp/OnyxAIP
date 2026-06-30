<?php

namespace App\Livewire\Technicians;

use App\Models\TechnicianProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TechnicianList extends Component
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

    /** @return LengthAwarePaginator<TechnicianProfile> */
    private function profiles(): LengthAwarePaginator
    {
        return TechnicianProfile::withTrashed()
            ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true)->whereNull('deleted_at'))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.technicians.technician-list', [
            'profiles' => $this->profiles(),
        ]);
    }
}

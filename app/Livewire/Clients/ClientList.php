<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ClientList extends Component
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

    /** @return LengthAwarePaginator<Client> */
    private function clients(): LengthAwarePaginator
    {
        return Client::query()
            ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('client_name', 'like', $term)
                        ->orWhere('client_code', 'like', $term);
                });
            })
            ->orderBy('client_name')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.clients.client-list', [
            'clients' => $this->clients(),
        ]);
    }
}

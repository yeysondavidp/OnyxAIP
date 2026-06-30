<?php

namespace App\Livewire\ServiceJobs;

use App\Enums\JobStatus;
use App\Models\Client;
use App\Models\ServiceJob;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class JobList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    #[Url(as: 'client')]
    public string $filterClient = '';

    #[Url(as: 'state')]
    public string $filterState = '';

    #[Url(as: 'sla')]
    public bool $filterSlaBreached = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterClient(): void
    {
        $this->resetPage();
    }

    public function updatedFilterState(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSlaBreached(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<ServiceJob> */
    private function jobs(): LengthAwarePaginator
    {
        return ServiceJob::with(['client', 'store', 'technicians'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('job_name', 'like', $term)
                        ->orWhere('job_reference', 'like', $term);
                });
            })
            ->when($this->filterStatus !== '', function ($q) {
                $status = JobStatus::tryFrom($this->filterStatus);
                if ($status !== null) {
                    $q->where('job_status', $status->value);
                }
            })
            ->when($this->filterClient !== '', function ($q) {
                $q->where('client_id', (int) $this->filterClient);
            })
            ->when($this->filterState !== '', function ($q) {
                $q->whereHas('store', fn ($sq) => $sq->where('state', $this->filterState));
            })
            ->when($this->filterSlaBreached, fn ($q) => $q->where('sla_breached', true))
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function render(): View
    {
        $clients  = Client::where('is_active', true)->orderBy('client_name')->get(['id', 'client_name']);
        $statuses = JobStatus::cases();
        $auStates = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'];

        return view('livewire.service-jobs.job-list', [
            'jobs'     => $this->jobs(),
            'clients'  => $clients,
            'statuses' => $statuses,
            'auStates' => $auStates,
        ]);
    }
}

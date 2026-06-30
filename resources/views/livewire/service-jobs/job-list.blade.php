<div>
    {{-- Filters ──────────────────────────────────────────────────────── --}}
    <div style="display: flex; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-4); align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <x-onyx.input
                name="search"
                placeholder="Search by name or reference…"
                wire:model.live.debounce.300ms="search"
                aria-label="Search jobs"
            />
        </div>

        <select wire:model.live="filterStatus"
            style="height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-14); color: var(--text-primary); background: var(--surface-primary); cursor: pointer; min-width: 160px;">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterClient"
            style="height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-14); color: var(--text-primary); background: var(--surface-primary); cursor: pointer; min-width: 160px;">
            <option value="">All clients</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}">{{ $client->client_name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterState"
            style="height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-14); color: var(--text-primary); background: var(--surface-primary); cursor: pointer; min-width: 120px;">
            <option value="">All states</option>
            @foreach ($auStates as $state)
                <option value="{{ $state }}">{{ $state }}</option>
            @endforeach
        </select>

        <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-primary); cursor: pointer; height: 40px; white-space: nowrap;">
            <input type="checkbox" wire:model.live="filterSlaBreached" style="accent-color: var(--bronze-600);">
            SLA breached only
        </label>
    </div>

    {{-- Table ─────────────────────────────────────────────────────────── --}}
    @if ($jobs->isEmpty())
        <x-onyx.empty
            title="{{ $search || $filterStatus || $filterClient || $filterState || $filterSlaBreached ? 'No jobs match these filters' : 'No service jobs yet' }}"
            description="{{ $search || $filterStatus || $filterClient || $filterState || $filterSlaBreached ? 'Try adjusting your filters.' : 'Create your first service job to get started.' }}"
        >
            @if (! $search && ! $filterStatus && ! $filterClient && ! $filterState && ! $filterSlaBreached)
                <x-onyx.button href="{{ route('jobs.create') }}" variant="primary" size="sm">Create job</x-onyx.button>
            @endif
        </x-onyx.empty>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-default);">
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary); white-space: nowrap;">Reference</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Job name</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Client</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Store</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary); white-space: nowrap;">Scheduled</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Status</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Technicians</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jobs as $job)
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: var(--space-3); white-space: nowrap;">
                                <span style="font-family: monospace; font-size: var(--fs-13); color: var(--text-secondary);">{{ $job->job_reference }}</span>
                                @if ($job->sla_breached)
                                    <x-onyx.badge tone="critical" variant="soft" style="margin-left: var(--space-1);">SLA</x-onyx.badge>
                                @endif
                            </td>
                            <td style="padding: var(--space-3);">
                                <a href="{{ route('jobs.show', $job) }}"
                                    style="color: var(--text-primary); font-weight: var(--weight-medium); text-decoration: none;">
                                    {{ $job->job_name }}
                                </a>
                                @if ($job->job_level > 0)
                                    <span style="font-size: var(--fs-12); color: var(--text-tertiary); margin-left: var(--space-1);">
                                        {{ $job->job_level === 1 ? 'Sub-job' : 'Remediation' }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: var(--space-3); color: var(--text-secondary);">{{ $job->client?->client_name }}</td>
                            <td style="padding: var(--space-3); color: var(--text-secondary);">
                                @if ($job->store)
                                    <a href="{{ route('stores.show', $job->store) }}"
                                        style="color: var(--bronze-600); text-decoration: none;">{{ $job->store->store_name }}</a>
                                @endif
                            </td>
                            <td style="padding: var(--space-3); white-space: nowrap; color: var(--text-secondary);">
                                @if ($job->scheduled_date)
                                    {{ $job->scheduled_date->format('d M Y') }}
                                @else
                                    <span style="color: var(--text-tertiary);">—</span>
                                @endif
                            </td>
                            <td style="padding: var(--space-3);">
                                <x-onyx.badge :tone="$job->job_status->tone()" variant="soft">
                                    {{ $job->job_status->label() }}
                                </x-onyx.badge>
                            </td>
                            <td style="padding: var(--space-3); color: var(--text-secondary);">
                                {{ $job->technicians->count() ?: '—' }}
                            </td>
                            <td style="padding: var(--space-3); text-align: right;">
                                <a href="{{ route('jobs.show', $job) }}"
                                    style="font-size: var(--fs-13); color: var(--bronze-600); text-decoration: none;">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($jobs->hasPages())
            <div style="margin-top: var(--space-4);">
                {{ $jobs->links() }}
            </div>
        @endif
    @endif
</div>

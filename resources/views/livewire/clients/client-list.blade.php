<div>
    {{-- Search + filter bar --}}
    <div style="display: flex; align-items: center; gap: var(--space-4); margin-bottom: var(--space-5); flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <x-onyx.input
                type="search"
                name="search"
                label="Search clients"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name or code…"
                :error="null"
            />
        </div>

        <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-secondary); cursor: pointer; white-space: nowrap; padding-top: 22px;">
            <input type="checkbox" wire:model.live="showInactive" style="width: 16px; height: 16px; cursor: pointer;">
            Show inactive
        </label>
    </div>

    {{-- Loading state --}}
    <div wire:loading.delay style="margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-secondary);">
        <x-onyx.spinner size="sm" />
        Loading…
    </div>

    {{-- Empty state --}}
    @if ($clients->isEmpty() && $search === '' && !$showInactive)
        <x-onyx.empty
            icon="briefcase"
            heading="No clients added yet"
            body="Add your first client to start registering stores and assets."
        >
            <x-onyx.button href="{{ route('clients.create') }}" variant="accent">
                Add client
            </x-onyx.button>
        </x-onyx.empty>

    @elseif ($clients->isEmpty())
        <x-onyx.empty
            icon="search"
            heading="No clients match your search"
            body="Try a different name or client code, or clear the search."
        >
            <x-onyx.button wire:click="$set('search', '')" variant="outline" size="sm">
                Clear search
            </x-onyx.button>
        </x-onyx.empty>

    @else
        {{-- Table --}}
        <div style="overflow-x: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                <thead>
                    <tr style="background: var(--surface-sunken); border-bottom: 1px solid var(--border-subtle);">
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary); white-space: nowrap;">Client</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Code</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Primary Contact</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Status</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: right; font-weight: var(--weight-semibold); color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                        <tr
                            style="border-bottom: 1px solid var(--border-subtle); {{ !$client->is_active ? 'opacity: 0.55;' : '' }}"
                            wire:key="client-{{ $client->id }}"
                        >
                            <td style="padding: var(--space-3) var(--space-4);">
                                <a href="{{ route('clients.show', $client) }}"
                                   style="font-weight: var(--weight-medium); color: var(--text-primary); text-decoration: none;"
                                   onmouseover="this.style.color='var(--bronze-600)'"
                                   onmouseout="this.style.color='var(--text-primary)'">
                                    {{ $client->client_name }}
                                </a>
                                @if (!$client->is_active)
                                    <x-onyx.badge tone="neutral" variant="soft" class="ms-2">Deactivated</x-onyx.badge>
                                @endif
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); font-family: var(--font-mono, monospace); font-size: var(--fs-13); color: var(--text-secondary);">
                                {{ $client->client_code }}
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">
                                {{ $client->primary_contact ?? '—' }}
                                @if ($client->primary_email)
                                    <span style="display: block; font-size: var(--fs-13); color: var(--text-muted);">{{ $client->primary_email }}</span>
                                @endif
                            </td>
                            <td style="padding: var(--space-3) var(--space-4);">
                                <x-onyx.badge
                                    :tone="$client->is_active ? 'positive' : 'neutral'"
                                    variant="soft"
                                >
                                    {{ $client->is_active ? 'Active' : 'Inactive' }}
                                </x-onyx.badge>
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); text-align: right; white-space: nowrap;">
                                <x-onyx.button href="{{ route('clients.show', $client) }}" variant="ghost" size="sm">View</x-onyx.button>
                                <x-onyx.button href="{{ route('clients.edit', $client) }}" variant="ghost" size="sm">Edit</x-onyx.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($clients->hasPages())
            <div style="margin-top: var(--space-5);">
                {{ $clients->links() }}
            </div>
        @endif
    @endif
</div>

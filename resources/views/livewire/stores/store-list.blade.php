<div>
    {{-- Filter bar --}}
    <div style="display: flex; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-5);">

        <div style="flex: 1; min-width: 200px;">
            <x-onyx.input
                type="search"
                name="search"
                label="Search stores"
                wire:model.live.debounce.300ms="search"
                placeholder="Name or store code…"
                :error="null"
            />
        </div>

        <div style="min-width: 160px;">
            <x-onyx.select name="client_filter" label="Client" wire:model.live="clientFilter">
                <option value="">All clients</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->client_name }}</option>
                @endforeach
            </x-onyx.select>
        </div>

        <div style="min-width: 140px;">
            <x-onyx.select name="state_filter" label="State" wire:model.live="stateFilter">
                <option value="">All states</option>
                @foreach ($states as $state)
                    <option value="{{ $state->value }}">{{ $state->value }}</option>
                @endforeach
            </x-onyx.select>
        </div>

        <div style="min-width: 180px;">
            <x-onyx.select name="type_filter" label="Store type" wire:model.live="typeFilter">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </x-onyx.select>
        </div>

        <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-secondary); cursor: pointer; white-space: nowrap; padding-top: 22px;">
            <input type="checkbox" wire:model.live="showInactive" style="width: 16px; height: 16px; cursor: pointer;">
            Show inactive
        </label>
    </div>

    {{-- Active filter indicators --}}
    @if ($clientFilter !== '' || $stateFilter !== '' || $typeFilter !== '')
        <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap; align-items: center;">
            <span style="font-size: var(--fs-13); color: var(--text-secondary);">Filtering by:</span>
            @if ($clientFilter !== '')
                <x-onyx.tag wire:click="$set('clientFilter', '')" style="cursor: pointer;">
                    Client ×
                </x-onyx.tag>
            @endif
            @if ($stateFilter !== '')
                <x-onyx.tag wire:click="$set('stateFilter', '')" style="cursor: pointer;">
                    {{ $stateFilter }} ×
                </x-onyx.tag>
            @endif
            @if ($typeFilter !== '')
                <x-onyx.tag wire:click="$set('typeFilter', '')" style="cursor: pointer;">
                    Type ×
                </x-onyx.tag>
            @endif
        </div>
    @endif

    {{-- Loading --}}
    <div wire:loading.delay style="margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-secondary);">
        <x-onyx.spinner size="sm" />
        Loading…
    </div>

    {{-- Empty state --}}
    @if ($stores->isEmpty() && $search === '' && $clientFilter === '' && $stateFilter === '' && $typeFilter === '' && !$showInactive)
        <x-onyx.empty
            icon="map-pin"
            heading="No stores added yet"
            body="Add your first store to start registering assets and service jobs."
        >
            <x-onyx.button href="{{ route('stores.create') }}" variant="accent">
                Add store
            </x-onyx.button>
        </x-onyx.empty>

    @elseif ($stores->isEmpty())
        <x-onyx.empty
            icon="search"
            heading="No stores match your filters"
            body="Try adjusting or clearing your search and filters."
        >
            <x-onyx.button wire:click="$set('search', ''); $set('clientFilter', ''); $set('stateFilter', ''); $set('typeFilter', '')" variant="outline" size="sm">
                Clear filters
            </x-onyx.button>
        </x-onyx.empty>

    @else
        <div style="overflow-x: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                <thead>
                    <tr style="background: var(--surface-sunken); border-bottom: 1px solid var(--border-subtle);">
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Store</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Client</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Type</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">State</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Status</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: right; font-weight: var(--weight-semibold); color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stores as $store)
                        <tr
                            style="border-bottom: 1px solid var(--border-subtle); {{ !$store->is_active ? 'opacity: 0.55;' : '' }}"
                            wire:key="store-{{ $store->id }}"
                        >
                            <td style="padding: var(--space-3) var(--space-4);">
                                <a href="{{ route('stores.show', $store) }}"
                                   style="font-weight: var(--weight-medium); color: var(--text-primary); text-decoration: none;"
                                   onmouseover="this.style.color='var(--bronze-600)'"
                                   onmouseout="this.style.color='var(--text-primary)'">
                                    {{ $store->store_name }}
                                </a>
                                <span style="display: block; font-size: var(--fs-12); font-family: monospace; color: var(--text-muted);">{{ $store->store_code }}</span>
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">
                                {{ $store->client?->client_name ?? '—' }}
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">
                                {{ $store->store_type?->label() ?? '—' }}
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">
                                {{ $store->state?->value ?? '—' }}
                            </td>
                            <td style="padding: var(--space-3) var(--space-4);">
                                <x-onyx.badge
                                    :tone="$store->is_active ? 'positive' : 'neutral'"
                                    variant="soft"
                                >
                                    {{ $store->is_active ? 'Active' : 'Inactive' }}
                                </x-onyx.badge>
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); text-align: right; white-space: nowrap;">
                                <x-onyx.button href="{{ route('stores.show', $store) }}" variant="ghost" size="sm">View</x-onyx.button>
                                <x-onyx.button href="{{ route('stores.edit', $store) }}" variant="ghost" size="sm">Edit</x-onyx.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($stores->hasPages())
            <div style="margin-top: var(--space-5);">
                {{ $stores->links() }}
            </div>
        @endif
    @endif
</div>

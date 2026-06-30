<div>
    {{-- Filter bar --}}
    <div style="display: flex; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-5);">

        <div style="flex: 1; min-width: 200px;">
            <x-onyx.input
                type="search"
                name="search"
                label="Search assets"
                wire:model.live.debounce.300ms="search"
                placeholder="Name, code, model, serial…"
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

        <div style="min-width: 160px;">
            <x-onyx.select name="store_filter" label="Store" wire:model.live="storeFilter">
                <option value="">All stores</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                @endforeach
            </x-onyx.select>
        </div>

        <div style="min-width: 160px;">
            <x-onyx.select name="type_filter" label="Asset type" wire:model.live="typeFilter">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </x-onyx.select>
        </div>

        <div style="min-width: 160px;">
            <x-onyx.select name="status_filter" label="Status" wire:model.live="statusFilter">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </x-onyx.select>
        </div>
    </div>

    {{-- Active filter pills --}}
    @if ($clientFilter !== '' || $storeFilter !== '' || $typeFilter !== '' || $statusFilter !== '')
        <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap; align-items: center;">
            <span style="font-size: var(--fs-13); color: var(--text-secondary);">Filtering by:</span>
            @if ($clientFilter !== '')
                <x-onyx.tag wire:click="$set('clientFilter', '')" style="cursor: pointer;">Client ×</x-onyx.tag>
            @endif
            @if ($storeFilter !== '')
                <x-onyx.tag wire:click="$set('storeFilter', '')" style="cursor: pointer;">Store ×</x-onyx.tag>
            @endif
            @if ($typeFilter !== '')
                <x-onyx.tag wire:click="$set('typeFilter', '')" style="cursor: pointer;">{{ $typeFilter }} ×</x-onyx.tag>
            @endif
            @if ($statusFilter !== '')
                <x-onyx.tag wire:click="$set('statusFilter', '')" style="cursor: pointer;">{{ $statusFilter }} ×</x-onyx.tag>
            @endif
        </div>
    @endif

    {{-- Loading --}}
    <div wire:loading.delay style="margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-secondary);">
        <x-onyx.spinner size="sm" />
        Loading…
    </div>

    {{-- Empty state — no assets at all --}}
    @if ($assets->isEmpty() && $search === '' && $clientFilter === '' && $storeFilter === '' && $typeFilter === '' && $statusFilter === '')
        <x-onyx.empty
            icon="monitor"
            heading="No assets registered yet"
            body="Add your first asset to start building the register."
        >
            <x-onyx.button href="{{ route('assets.create') }}" variant="primary" size="sm">Add asset</x-onyx.button>
        </x-onyx.empty>

    {{-- Empty state — filters returned nothing --}}
    @elseif ($assets->isEmpty())
        <x-onyx.empty
            icon="search"
            heading="No assets match your filters"
            body="Try adjusting your search or clearing a filter."
            size="sm"
        />

    {{-- Table --}}
    @else
        <x-onyx.card variant="default" padding="none">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <th style="padding: var(--space-3) var(--space-4); text-align: left; font-size: var(--fs-12); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide); white-space: nowrap;">Code</th>
                            <th style="padding: var(--space-3) var(--space-4); text-align: left; font-size: var(--fs-12); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Name</th>
                            <th style="padding: var(--space-3) var(--space-4); text-align: left; font-size: var(--fs-12); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide); white-space: nowrap;">Type</th>
                            <th style="padding: var(--space-3) var(--space-4); text-align: left; font-size: var(--fs-12); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Store</th>
                            <th style="padding: var(--space-3) var(--space-4); text-align: left; font-size: var(--fs-12); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Status</th>
                            <th style="padding: var(--space-3) var(--space-4); width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assets as $asset)
                            <tr style="border-bottom: 1px solid var(--border-subtle); transition: background var(--duration-fast) var(--ease-standard);"
                                onmouseover="this.style.background='var(--surface-sunken)'"
                                onmouseout="this.style.background=''">
                                <td style="padding: var(--space-3) var(--space-4);">
                                    <span style="font-family: monospace; font-size: var(--fs-13); color: var(--text-secondary);">{{ $asset->asset_code }}</span>
                                </td>
                                <td style="padding: var(--space-3) var(--space-4);">
                                    <a href="{{ route('assets.show', $asset) }}" style="color: var(--text-primary); text-decoration: none; font-weight: var(--weight-medium);">
                                        {{ $asset->asset_name }}
                                    </a>
                                    <div style="font-size: var(--fs-12); color: var(--text-muted); margin-top: 2px;">{{ $asset->manufacturer }} {{ $asset->model }}</div>
                                </td>
                                <td style="padding: var(--space-3) var(--space-4); white-space: nowrap;">
                                    <span style="font-size: var(--fs-13); color: var(--text-secondary);">{{ $asset->asset_type->label() }}</span>
                                </td>
                                <td style="padding: var(--space-3) var(--space-4);">
                                    @if ($asset->store)
                                        <a href="{{ route('stores.show', $asset->store) }}" style="color: var(--bronze-600); text-decoration: none; font-size: var(--fs-13);">
                                            {{ $asset->store->store_name }}
                                        </a>
                                    @endif
                                </td>
                                <td style="padding: var(--space-3) var(--space-4);">
                                    <x-onyx.badge :tone="$asset->asset_status->tone()" variant="soft">
                                        {{ $asset->asset_status->label() }}
                                    </x-onyx.badge>
                                </td>
                                <td style="padding: var(--space-3) var(--space-4); text-align: right;">
                                    <a href="{{ route('assets.show', $asset) }}" style="font-size: var(--fs-13); color: var(--bronze-600); text-decoration: none; white-space: nowrap;">View →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-onyx.card>

        {{-- Pagination --}}
        @if ($assets->hasPages())
            <div style="margin-top: var(--space-4);">
                {{ $assets->links() }}
            </div>
        @endif
    @endif
</div>

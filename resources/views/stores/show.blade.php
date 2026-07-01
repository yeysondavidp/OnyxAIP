<x-layouts.app title="{{ $store->store_name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('stores.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Stores</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $store->store_name }}</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('stores.edit', $store) }}" variant="outline" size="sm">Edit</x-onyx.button>
    </x-slot:headerActions>

    {{-- Header --}}
    <div style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-1);">
            <h1 style="font-size: var(--fs-28); font-weight: var(--weight-bold); color: var(--text-primary);">{{ $store->store_name }}</h1>
            <x-onyx.badge :tone="$store->is_active ? 'positive' : 'neutral'" variant="soft">
                {{ $store->is_active ? 'Active' : 'Deactivated' }}
            </x-onyx.badge>
        </div>
        <div style="display: flex; align-items: center; gap: var(--space-4); font-size: var(--fs-14); color: var(--text-secondary);">
            <span style="font-family: monospace; font-size: var(--fs-13);">{{ $store->store_code }}</span>
            <span>·</span>
            <a href="{{ route('clients.show', $store->client) }}" style="color: var(--bronze-600); text-decoration: none;">{{ $store->client?->client_name }}</a>
            <span>·</span>
            <span>{{ $store->store_type?->label() }}</span>
        </div>
    </div>

    {{-- Two-column layout --}}
    <div style="display: grid; grid-template-columns: 1fr 320px; gap: var(--space-6); align-items: start;">

        {{-- Left --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            {{-- Location details --}}
            <x-onyx.card variant="default" padding="lg">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Location</h2>

                <div style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                    <div style="display: flex; gap: var(--space-3);">
                        <span style="width: 130px; flex-shrink: 0; color: var(--text-secondary);">Address</span>
                        <span style="color: var(--text-primary);">
                            {{ $store->address_line1 }}<br>
                            {{ $store->suburb }}, {{ $store->state?->value }} {{ $store->postcode }}<br>
                            {{ $store->country }}
                        </span>
                    </div>
                    <div style="display: flex; gap: var(--space-3);">
                        <span style="width: 130px; flex-shrink: 0; color: var(--text-secondary);">Timezone</span>
                        <span style="color: var(--text-primary);">{{ $store->store_timezone }}</span>
                    </div>
                </div>
            </x-onyx.card>

            {{-- Asset inventory --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">Asset Inventory</h2>
                    <x-onyx.button href="{{ route('assets.create', ['client_id' => $store->client_id, 'store_id' => $store->id]) }}" variant="outline" size="sm">Add asset</x-onyx.button>
                </div>
                @if ($assets->isEmpty())
                    <div style="padding: var(--space-10);">
                        <x-onyx.empty icon="monitor" heading="No assets registered" body="Add the first asset for this store." size="sm">
                            <x-onyx.button href="{{ route('assets.create', ['client_id' => $store->client_id, 'store_id' => $store->id]) }}" variant="primary" size="sm">Add asset</x-onyx.button>
                        </x-onyx.empty>
                    </div>
                @else
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-subtle);">
                                    <th style="padding: var(--space-3) var(--space-5); text-align: left; font-size: var(--fs-12); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Code</th>
                                    <th style="padding: var(--space-3) var(--space-5); text-align: left; font-size: var(--fs-12); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Name</th>
                                    <th style="padding: var(--space-3) var(--space-5); text-align: left; font-size: var(--fs-12); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Type</th>
                                    <th style="padding: var(--space-3) var(--space-5); text-align: left; font-size: var(--fs-12); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assets as $asset)
                                    <tr style="border-bottom: 1px solid var(--border-subtle);">
                                        <td style="padding: var(--space-3) var(--space-5);"><span style="font-family: monospace; font-size: var(--fs-13); color: var(--text-secondary);">{{ $asset->asset_code }}</span></td>
                                        <td style="padding: var(--space-3) var(--space-5);">
                                            <a href="{{ route('assets.show', $asset) }}" style="color: var(--text-primary); text-decoration: none; font-weight: var(--weight-medium);">{{ $asset->asset_name }}</a>
                                            <div style="font-size: var(--fs-12); color: var(--text-muted);">{{ $asset->manufacturer }} {{ $asset->model }}</div>
                                        </td>
                                        <td style="padding: var(--space-3) var(--space-5); color: var(--text-secondary); font-size: var(--fs-13);">{{ $asset->asset_type->label() }}</td>
                                        <td style="padding: var(--space-3) var(--space-5);">
                                            <x-onyx.badge :tone="$asset->asset_status->tone()" variant="soft">{{ $asset->asset_status->label() }}</x-onyx.badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($assetCount > 10)
                        <div style="padding: var(--space-4) var(--space-5); border-top: 1px solid var(--border-subtle);">
                            <a href="{{ route('assets.index', ['store' => $store->id]) }}" style="font-size: var(--fs-13); color: var(--bronze-600); text-decoration: none;">
                                View all {{ $assetCount }} assets →
                            </a>
                        </div>
                    @endif
                @endif
            </x-onyx.card>

            {{-- Open faults placeholder — populated by EPIC-06/08 --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle);">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">Open Faults</h2>
                </div>
                <div style="padding: var(--space-8); text-align: center;">
                    <x-onyx.badge tone="positive" variant="soft">No open faults</x-onyx.badge>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-2);">Fault tracking available once service jobs are configured.</p>
                </div>
            </x-onyx.card>

            {{-- Service History (US-11.4) --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle);">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">Service History</h2>
                </div>
                <div style="padding: var(--space-5);">
                    <livewire:service-history.store-history-log :store="$store" />
                </div>
            </x-onyx.card>

            {{-- Display Groups (US-05.2) --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">Display Groups</h2>
                    @can('create', [App\Models\DisplayGroup::class, $store])
                        <x-onyx.button href="{{ route('stores.display-groups.create', $store) }}" variant="outline" size="sm">Add group</x-onyx.button>
                    @endcan
                </div>
                <div style="padding: var(--space-5);">
                    @if ($displayGroups->isEmpty())
                        <x-onyx.empty icon="layout" heading="No display groups configured" body="Add a display group to map players to screens." size="sm">
                            @can('create', [App\Models\DisplayGroup::class, $store])
                                <x-onyx.button href="{{ route('stores.display-groups.create', $store) }}" variant="outline" size="sm">Add display group</x-onyx.button>
                            @endcan
                        </x-onyx.empty>
                    @else
                        <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                            @foreach ($displayGroups as $group)
                                @include('display-groups._topology-card', ['group' => $group, 'showActions' => false])
                            @endforeach
                        </div>
                        <div style="margin-top: var(--space-4);">
                            <a href="{{ route('stores.display-groups.index', $store) }}" style="font-size: var(--fs-13); color: var(--bronze-600); text-decoration: none;">
                                View all display groups →
                            </a>
                        </div>
                    @endif
                </div>
            </x-onyx.card>

        </div>

        {{-- Right sidebar --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            {{-- Store manager --}}
            <x-onyx.card variant="default" padding="md">
                <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-3);">Store Manager</h2>

                @if ($store->store_manager_name || $store->store_manager_phone || $store->store_manager_email)
                    <div style="display: flex; flex-direction: column; gap: var(--space-2); font-size: var(--fs-14);">
                        @if ($store->store_manager_name)
                            <p style="color: var(--text-primary); font-weight: var(--weight-medium);">{{ $store->store_manager_name }}</p>
                        @endif
                        @if ($store->store_manager_phone)
                            <a href="tel:{{ $store->store_manager_phone }}" style="color: var(--text-secondary); text-decoration: none;">{{ $store->store_manager_phone }}</a>
                        @endif
                        @if ($store->store_manager_email)
                            <a href="mailto:{{ $store->store_manager_email }}" style="color: var(--bronze-600); text-decoration: none; font-size: var(--fs-13);">{{ $store->store_manager_email }}</a>
                        @endif
                    </div>
                @else
                    <p style="font-size: var(--fs-14); color: var(--text-muted);">No manager contact recorded.</p>
                @endif
            </x-onyx.card>

            {{-- SLA Status (§8, US-12.3) --}}
            <x-onyx.card variant="default" padding="md">
                <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-3);">SLA Status</h2>

                @if ($slaTrackedJobs->isEmpty())
                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                        <x-onyx.badge tone="positive" variant="soft">No open SLA-tracked jobs</x-onyx.badge>
                    </div>
                @else
                    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                        @foreach ($slaTrackedJobs as $job)
                            <a href="{{ route('jobs.show', $job) }}" style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); text-decoration: none;">
                                <span style="font-size: var(--fs-13); color: var(--text-primary); font-weight: var(--weight-medium); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $job->job_reference }}
                                </span>
                                <x-onyx.sla-badge :status="$job->slaStatus()" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-onyx.card>

            {{-- Notes --}}
            @if ($store->notes)
                <x-onyx.card variant="default" padding="md">
                    <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-3);">Internal Notes</h2>
                    <p style="font-size: var(--fs-14); color: var(--text-secondary); white-space: pre-wrap;">{{ $store->notes }}</p>
                </x-onyx.card>
            @endif

        </div>
    </div>

</x-layouts.app>

<x-layouts.app title="{{ $client->client_name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('clients.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Clients</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $client->client_name }}</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('clients.edit', $client) }}" variant="outline" size="sm">Edit</x-onyx.button>
    </x-slot:headerActions>

    {{-- Header --}}
    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-1);">
                <h1 style="font-size: var(--fs-28); font-weight: var(--weight-bold); color: var(--text-primary);">{{ $client->client_name }}</h1>
                <x-onyx.badge
                    :tone="$client->is_active ? 'positive' : 'neutral'"
                    variant="soft"
                >
                    {{ $client->is_active ? 'Active' : 'Deactivated' }}
                </x-onyx.badge>
            </div>
            <p style="font-size: var(--fs-14); color: var(--text-secondary); font-family: monospace;">{{ $client->client_code }}</p>
        </div>
    </div>

    {{-- Detail grid --}}
    <div style="display: grid; grid-template-columns: 1fr 360px; gap: var(--space-6); align-items: start;">

        {{-- Left: metadata + stores --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            {{-- Contact details --}}
            <x-onyx.card variant="default" padding="lg">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Contact Details</h2>

                <div style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                    <div style="display: flex; gap: var(--space-3);">
                        <span style="width: 130px; flex-shrink: 0; color: var(--text-secondary);">Primary contact</span>
                        <span style="color: var(--text-primary);">{{ $client->primary_contact ?? '—' }}</span>
                    </div>
                    <div style="display: flex; gap: var(--space-3);">
                        <span style="width: 130px; flex-shrink: 0; color: var(--text-secondary);">Email</span>
                        @if ($client->primary_email)
                            <a href="mailto:{{ $client->primary_email }}" style="color: var(--bronze-600);">{{ $client->primary_email }}</a>
                        @else
                            <span style="color: var(--text-muted);">—</span>
                        @endif
                    </div>
                </div>
            </x-onyx.card>

            {{-- Stores list --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">
                        Stores
                        <x-onyx.badge tone="neutral" variant="soft" style="margin-left: var(--space-2);">
                            {{ $client->stores()->where('is_active', true)->count() }}
                        </x-onyx.badge>
                    </h2>
                    <x-onyx.button href="{{ route('stores.create') }}?client_id={{ $client->id }}" variant="ghost" size="sm">Add store</x-onyx.button>
                </div>

                @php $stores = $client->stores()->where('is_active', true)->orderBy('store_name')->limit(10)->get(); @endphp

                @if ($stores->isEmpty())
                    <div style="padding: var(--space-8);">
                        <x-onyx.empty icon="map-pin" heading="No stores yet" size="sm">
                            <x-onyx.button href="{{ route('stores.create') }}" variant="accent" size="sm">Add first store</x-onyx.button>
                        </x-onyx.empty>
                    </div>
                @else
                    <div>
                        @foreach ($stores as $store)
                            <a href="{{ route('stores.show', $store) }}"
                               style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-4) var(--space-6); border-bottom: 1px solid var(--border-subtle); text-decoration: none; color: inherit;"
                               onmouseover="this.style.background='var(--surface-sunken)'"
                               onmouseout="this.style.background='transparent'">
                                <div>
                                    <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $store->store_name }}</span>
                                    <span style="display: block; font-size: var(--fs-12); color: var(--text-secondary);">{{ $store->store_code }} · {{ $store->state?->value }} · {{ $store->store_type?->label() }}</span>
                                </div>
                                <x-icon name="chevron-right" size="16" style="color: var(--text-muted);" />
                            </a>
                        @endforeach
                    </div>
                    @if ($client->stores()->where('is_active', true)->count() > 10)
                        <div style="padding: var(--space-4) var(--space-6);">
                            <x-onyx.button href="{{ route('stores.index') }}?client={{ $client->id }}" variant="ghost" size="sm">
                                View all stores →
                            </x-onyx.button>
                        </div>
                    @endif
                @endif
            </x-onyx.card>

        </div>

        {{-- Right sidebar --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            {{-- SLA Profile --}}
            <x-onyx.card variant="default" padding="md">
                <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-3);">SLA Profile</h2>
                @if ($client->sla_profile_id)
                    <p style="font-size: var(--fs-14); color: var(--text-secondary);">Profile #{{ $client->sla_profile_id }}</p>
                @else
                    <x-onyx.alert tone="caution">
                        This client has no SLA profile — SLA tracking is disabled.
                    </x-onyx.alert>
                @endif
            </x-onyx.card>

            {{-- Internal notes --}}
            @if ($client->notes)
                <x-onyx.card variant="default" padding="md">
                    <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-3);">Internal Notes</h2>
                    <p style="font-size: var(--fs-14); color: var(--text-secondary); white-space: pre-wrap;">{{ $client->notes }}</p>
                </x-onyx.card>
            @endif

        </div>
    </div>

</x-layouts.app>

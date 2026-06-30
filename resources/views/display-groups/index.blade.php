<x-layouts.app title="Display Groups — {{ $store->store_name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('stores.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Stores</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('stores.show', $store) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $store->store_name }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Display Groups</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('stores.display-groups.create', $store) }}" variant="primary" size="sm">Add display group</x-onyx.button>
    </x-slot:headerActions>

    @if (session('success'))
        <x-onyx.alert tone="positive" style="margin-bottom: var(--space-5);">{{ session('success') }}</x-onyx.alert>
    @endif

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Display Groups</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">
            Player-to-screen topology for <a href="{{ route('stores.show', $store) }}" style="color: var(--bronze-600); text-decoration: none;">{{ $store->store_name }}</a>.
        </p>
    </div>

    @if ($groups->isEmpty())
        <x-onyx.empty icon="layout" heading="No display groups configured" body="Add a display group to map a media player to its screens at this store.">
            <x-onyx.button href="{{ route('stores.display-groups.create', $store) }}" variant="primary" size="sm">Add display group</x-onyx.button>
        </x-onyx.empty>
    @else
        <div style="display: flex; flex-direction: column; gap: var(--space-4);">
            @foreach ($groups as $group)
                @include('display-groups._topology-card', ['group' => $group, 'showActions' => true])
            @endforeach
        </div>
    @endif

</x-layouts.app>

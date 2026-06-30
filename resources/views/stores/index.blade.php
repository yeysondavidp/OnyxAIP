<x-layouts.app title="Stores">

    <x-slot:breadcrumbs>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Stores</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('stores.create') }}" variant="accent" size="sm">
            Add store
        </x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Stores</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">All registered retail locations across all clients.</p>
    </div>

    <livewire:stores.store-list />

</x-layouts.app>

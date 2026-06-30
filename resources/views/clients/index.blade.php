<x-layouts.app title="Clients">

    <x-slot:breadcrumbs>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Clients</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('clients.create') }}" variant="accent" size="sm">
            Add client
        </x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Clients</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">All brand accounts — stores, assets, and service jobs are scoped to a client.</p>
    </div>

    <livewire:clients.client-list />

</x-layouts.app>

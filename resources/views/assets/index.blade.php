<x-layouts.app title="Asset Registry">

    <x-slot:breadcrumbs>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Asset Registry</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('assets.create') }}" variant="primary" size="sm">Add asset</x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Asset Registry</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">All deployed assets across clients and stores.</p>
    </div>

    <livewire:assets.asset-list />

</x-layouts.app>

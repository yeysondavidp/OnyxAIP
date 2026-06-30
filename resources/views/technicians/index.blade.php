<x-layouts.app title="Technician Directory">

    <x-slot:breadcrumbs>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Technicians</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('technicians.create') }}" variant="primary" size="sm">Add technician</x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Technician Directory</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">Field technicians and installers available for job dispatch.</p>
    </div>

    <livewire:technicians.technician-list />

</x-layouts.app>

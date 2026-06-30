<x-layouts.app title="Service Jobs">

    <x-slot:breadcrumbs>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Service Jobs</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('jobs.create') }}" variant="primary" size="sm">Create job</x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Service Jobs</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">All field service jobs across clients and stores.</p>
    </div>

    <livewire:service-jobs.job-list />

</x-layouts.app>

<x-layouts.app title="SLA Profiles">

    <x-slot:breadcrumbs>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">SLA Profiles</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('sla-profiles.create') }}" variant="accent" size="sm">
            Add SLA profile
        </x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">SLA Profiles</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">Response and resolution targets, assignable to clients (§10.1).</p>
    </div>

    <livewire:sla-profiles.sla-profile-list />

</x-layouts.app>

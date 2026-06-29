<x-layouts.app title="Dashboard">

    {{-- Page heading --}}
    <div style="margin-bottom: var(--space-8);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">
            Dashboard
        </h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">
            Overview of assets, service jobs, and open faults across all clients.
        </p>
    </div>

    {{-- Stat cards --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-5); margin-bottom: var(--space-8);">

        <x-onyx.card variant="default" padding="lg">
            <x-onyx.eyebrow tone="muted">Total Assets</x-onyx.eyebrow>
            <p style="font-size: var(--fs-36); font-weight: var(--weight-bold); color: var(--text-primary); margin: var(--space-2) 0 var(--space-1);">
                —
            </p>
            <p style="font-size: var(--fs-13); color: var(--text-secondary);">Across all clients</p>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="lg">
            <x-onyx.eyebrow tone="muted">Open Faults</x-onyx.eyebrow>
            <p style="font-size: var(--fs-36); font-weight: var(--weight-bold); color: var(--critical-600); margin: var(--space-2) 0 var(--space-1);">
                —
            </p>
            <p style="font-size: var(--fs-13); color: var(--text-secondary);">Assets requiring attention</p>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="lg">
            <x-onyx.eyebrow tone="muted">Active Jobs</x-onyx.eyebrow>
            <p style="font-size: var(--fs-36); font-weight: var(--weight-bold); color: var(--text-primary); margin: var(--space-2) 0 var(--space-1);">
                —
            </p>
            <p style="font-size: var(--fs-13); color: var(--text-secondary);">In progress or pending</p>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="lg">
            <x-onyx.eyebrow tone="muted">SLA Breaches</x-onyx.eyebrow>
            <p style="font-size: var(--fs-36); font-weight: var(--weight-bold); color: var(--caution-600); margin: var(--space-2) 0 var(--space-1);">
                —
            </p>
            <p style="font-size: var(--fs-13); color: var(--text-secondary);">Requiring escalation</p>
        </x-onyx.card>

    </div>

    {{-- Two-column content area --}}
    <div style="display: grid; grid-template-columns: 1fr 360px; gap: var(--space-6); align-items: start;">

        {{-- Recent service jobs --}}
        <x-onyx.card variant="default" padding="none">
            <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">Recent Service Jobs</h2>
                <x-onyx.button href="{{ route('jobs.index') }}" variant="ghost" size="sm">View all</x-onyx.button>
            </div>

            <div style="padding: var(--space-12); text-align: center;">
                <x-icon name="tool" size="32" style="color: var(--text-tertiary); margin: 0 auto var(--space-3);" />
                <p style="font-size: var(--fs-14); color: var(--text-secondary); margin-bottom: var(--space-4);">
                    No service jobs yet. Create your first job to get started.
                </p>
                <x-onyx.button href="{{ route('jobs.create') }}" variant="accent" size="sm">
                    Create service job
                </x-onyx.button>
            </div>
        </x-onyx.card>

        {{-- Right column --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            {{-- Open faults --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--border-subtle);">
                    <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary);">Open Faults</h2>
                </div>
                <div style="padding: var(--space-8); text-align: center;">
                    <x-onyx.badge tone="positive" variant="soft">All clear</x-onyx.badge>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-2);">No open faults</p>
                </div>
            </x-onyx.card>

            {{-- Quick actions --}}
            <x-onyx.card variant="default" padding="md">
                <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Quick Actions</h2>
                <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                    <x-onyx.button href="{{ route('clients.create') }}" variant="outline" size="sm" :fullWidth="true">
                        Add client
                    </x-onyx.button>
                    <x-onyx.button href="{{ route('stores.create') }}" variant="outline" size="sm" :fullWidth="true">
                        Add store
                    </x-onyx.button>
                    <x-onyx.button href="{{ route('assets.create') }}" variant="outline" size="sm" :fullWidth="true">
                        Register asset
                    </x-onyx.button>
                    <x-onyx.button href="{{ route('technicians.create') }}" variant="outline" size="sm" :fullWidth="true">
                        Add technician
                    </x-onyx.button>
                </div>
            </x-onyx.card>

        </div>

    </div>

</x-layouts.app>

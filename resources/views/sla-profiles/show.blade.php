<x-layouts.app title="{{ $profile->name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('sla-profiles.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">SLA Profiles</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $profile->name }}</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('sla-profiles.edit', $profile) }}" variant="outline" size="sm">Edit</x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-1);">
            <h1 style="font-size: var(--fs-28); font-weight: var(--weight-bold); color: var(--text-primary);">{{ $profile->name }}</h1>
            <x-onyx.badge :tone="$profile->is_active ? 'positive' : 'neutral'" variant="soft">
                {{ $profile->is_active ? 'Active' : 'Deactivated' }}
            </x-onyx.badge>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 360px; gap: var(--space-6); align-items: start;">

        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            <x-onyx.card variant="default" padding="lg">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Response &amp; Resolution Windows</h2>

                <div style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                    <div style="display: flex; gap: var(--space-3);">
                        <span style="width: 220px; flex-shrink: 0; color: var(--text-secondary);">Acknowledgement</span>
                        <span style="color: var(--text-primary);">{{ $profile->acknowledgement_hours }} business hours</span>
                    </div>
                    <div style="display: flex; gap: var(--space-3);">
                        <span style="width: 220px; flex-shrink: 0; color: var(--text-secondary);">On-site response — metro</span>
                        <span style="color: var(--text-primary);">{{ $profile->onsite_response_metro_hours }} business hours</span>
                    </div>
                    <div style="display: flex; gap: var(--space-3);">
                        <span style="width: 220px; flex-shrink: 0; color: var(--text-secondary);">On-site response — regional</span>
                        <span style="color: var(--text-primary);">{{ $profile->onsite_response_regional_hours }} business hours</span>
                    </div>
                    <div style="display: flex; gap: var(--space-3);">
                        <span style="width: 220px; flex-shrink: 0; color: var(--text-secondary);">Resolution target</span>
                        <span style="color: var(--text-primary); font-weight: var(--weight-medium);">{{ $profile->resolution_hours }} business hours</span>
                    </div>
                    <div style="display: flex; gap: var(--space-3);">
                        <span style="width: 220px; flex-shrink: 0; color: var(--text-secondary);">Monitoring coverage</span>
                        <span style="color: var(--text-primary);">{{ $profile->monitoring_coverage->label() }}</span>
                    </div>
                </div>
            </x-onyx.card>

        </div>

        <div style="display: flex; flex-direction: column; gap: var(--space-5);">
            <x-onyx.card variant="default" padding="md">
                <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-3);">
                    Assigned Clients
                    <x-onyx.badge tone="neutral" variant="soft" style="margin-left: var(--space-2);">{{ $clients->count() }}</x-onyx.badge>
                </h2>

                @if ($clients->isEmpty())
                    <p style="font-size: var(--fs-13); color: var(--text-muted);">No active clients use this profile yet. Assign it from a client's edit page.</p>
                @else
                    <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                        @foreach ($clients as $client)
                            <a href="{{ route('clients.show', $client) }}" style="font-size: var(--fs-14); color: var(--bronze-600); text-decoration: none;">
                                {{ $client->client_name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-onyx.card>
        </div>

    </div>

</x-layouts.app>

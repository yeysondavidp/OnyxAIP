<div>
    {{-- Search + filter bar --}}
    <div style="display: flex; align-items: center; gap: var(--space-4); margin-bottom: var(--space-5); flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <x-onyx.input
                type="search"
                name="search"
                label="Search profiles"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name…"
                :error="null"
            />
        </div>

        <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-secondary); cursor: pointer; white-space: nowrap; padding-top: 22px;">
            <input type="checkbox" wire:model.live="showInactive" style="width: 16px; height: 16px; cursor: pointer;">
            Show inactive
        </label>
    </div>

    {{-- Loading state --}}
    <div wire:loading.delay style="margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-secondary);">
        <x-onyx.spinner size="sm" />
        Loading…
    </div>

    {{-- Empty state --}}
    @if ($profiles->isEmpty() && $search === '' && !$showInactive)
        <x-onyx.empty
            icon="shield"
            heading="No SLA profiles yet"
            body="Create a profile to define response and resolution targets for a client."
        >
            <x-onyx.button href="{{ route('sla-profiles.create') }}" variant="accent">
                Add SLA profile
            </x-onyx.button>
        </x-onyx.empty>

    @elseif ($profiles->isEmpty())
        <x-onyx.empty
            icon="search"
            heading="No profiles match your search"
            body="Try a different name, or clear the search."
        >
            <x-onyx.button wire:click="$set('search', '')" variant="outline" size="sm">
                Clear search
            </x-onyx.button>
        </x-onyx.empty>

    @else
        <div style="overflow-x: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                <thead>
                    <tr style="background: var(--surface-sunken); border-bottom: 1px solid var(--border-subtle);">
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Profile</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Resolution target</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Monitoring</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Clients</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Status</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: right; font-weight: var(--weight-semibold); color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($profiles as $profile)
                        <tr
                            style="border-bottom: 1px solid var(--border-subtle); {{ !$profile->is_active ? 'opacity: 0.55;' : '' }}"
                            wire:key="profile-{{ $profile->id }}"
                        >
                            <td style="padding: var(--space-3) var(--space-4);">
                                <a href="{{ route('sla-profiles.show', $profile) }}"
                                   style="font-weight: var(--weight-medium); color: var(--text-primary); text-decoration: none;"
                                   onmouseover="this.style.color='var(--bronze-600)'"
                                   onmouseout="this.style.color='var(--text-primary)'">
                                    {{ $profile->name }}
                                </a>
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">
                                {{ $profile->resolution_hours }} business hours
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">
                                {{ $profile->monitoring_coverage->label() }}
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">
                                {{ $profile->clients_count }}
                            </td>
                            <td style="padding: var(--space-3) var(--space-4);">
                                <x-onyx.badge :tone="$profile->is_active ? 'positive' : 'neutral'" variant="soft">
                                    {{ $profile->is_active ? 'Active' : 'Inactive' }}
                                </x-onyx.badge>
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); text-align: right; white-space: nowrap;">
                                <x-onyx.button href="{{ route('sla-profiles.show', $profile) }}" variant="ghost" size="sm">View</x-onyx.button>
                                <x-onyx.button href="{{ route('sla-profiles.edit', $profile) }}" variant="ghost" size="sm">Edit</x-onyx.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($profiles->hasPages())
            <div style="margin-top: var(--space-5);">
                {{ $profiles->links() }}
            </div>
        @endif
    @endif
</div>

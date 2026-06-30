<div>
    <div style="display: flex; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-4); align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <x-onyx.input name="search" placeholder="Search by name or email…"
                wire:model.live.debounce.300ms="search" aria-label="Search technicians" />
        </div>
        <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); cursor: pointer; height: 40px;">
            <input type="checkbox" wire:model.live="showInactive" style="accent-color: var(--bronze-600);">
            Show deactivated
        </label>
    </div>

    @if ($profiles->isEmpty())
        <x-onyx.empty
            title="{{ $search ? 'No technicians match your search' : 'No technicians yet' }}"
            description="{{ $search ? 'Try a different name or email.' : 'Add your first technician to the directory.' }}"
        >
            @if (! $search)
                <x-onyx.button href="{{ route('technicians.create') }}" variant="primary" size="sm">Add technician</x-onyx.button>
            @endif
        </x-onyx.empty>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-default);">
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Name</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Email</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Specialties</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Account</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($profiles as $profile)
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: var(--space-3);">
                                <a href="{{ route('technicians.show', $profile) }}"
                                    style="font-weight: var(--weight-medium); color: var(--text-primary); text-decoration: none;">
                                    {{ $profile->name }}
                                </a>
                            </td>
                            <td style="padding: var(--space-3); color: var(--text-secondary);">{{ $profile->email }}</td>
                            <td style="padding: var(--space-3); color: var(--text-secondary);">
                                {{ collect($profile->specialtyLabels())->implode(', ') ?: '—' }}
                            </td>
                            <td style="padding: var(--space-3);">
                                @if ($profile->hasAccount())
                                    <x-onyx.badge tone="positive" variant="soft">Account</x-onyx.badge>
                                @else
                                    <span style="font-size: var(--fs-13); color: var(--text-tertiary);">Guest</span>
                                @endif
                            </td>
                            <td style="padding: var(--space-3);">
                                <x-onyx.badge :tone="$profile->is_active ? 'positive' : 'neutral'" variant="soft">
                                    {{ $profile->is_active ? 'Active' : 'Deactivated' }}
                                </x-onyx.badge>
                            </td>
                            <td style="padding: var(--space-3); text-align: right;">
                                <a href="{{ route('technicians.show', $profile) }}"
                                    style="font-size: var(--fs-13); color: var(--bronze-600); text-decoration: none;">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($profiles->hasPages())
            <div style="margin-top: var(--space-4);">{{ $profiles->links() }}</div>
        @endif
    @endif
</div>

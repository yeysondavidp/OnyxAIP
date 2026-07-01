<div>
    {{-- Filters ──────────────────────────────────────────────────────── --}}
    <div style="display: flex; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-4); align-items: flex-end;">
        <select wire:model.live="assetType"
            style="height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-14); color: var(--text-primary); background: var(--surface-primary); cursor: pointer; min-width: 180px;">
            <option value="">All asset types</option>
            @foreach ($assetTypes as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </select>

        <div>
            <label style="display: block; font-size: var(--fs-11); color: var(--text-tertiary); margin-bottom: var(--space-1);">From</label>
            <input type="date" wire:model.live="dateFrom"
                style="height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-13);">
        </div>
        <div>
            <label style="display: block; font-size: var(--fs-11); color: var(--text-tertiary); margin-bottom: var(--space-1);">To</label>
            <input type="date" wire:model.live="dateTo"
                style="height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-13);">
        </div>

        @if ($assetType !== '' || $dateFrom !== '' || $dateTo !== '')
            <button type="button" wire:click="$set('assetType', ''); $set('dateFrom', ''); $set('dateTo', '')"
                style="height: 40px; padding: 0 var(--space-3); background: none; border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-13); color: var(--text-secondary); cursor: pointer;">
                Clear filters
            </button>
        @endif
    </div>

    {{-- Log ───────────────────────────────────────────────────────────── --}}
    @if ($entries->isEmpty())
        <x-onyx.empty icon="clock"
            heading="{{ $assetType || $dateFrom || $dateTo ? 'No history matches these filters' : 'No service history yet' }}"
            body="{{ $assetType || $dateFrom || $dateTo ? 'Try adjusting your filters.' : 'Entries appear here once jobs at this store are validated.' }}"
            size="sm" />
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-default);">
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary); white-space: nowrap;">Date</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Asset</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Job</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Type</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Status change</th>
                        <th style="text-align: left; padding: var(--space-2) var(--space-3); font-weight: var(--weight-semibold); color: var(--text-secondary);">Technician(s)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: var(--space-3); white-space: nowrap; color: var(--text-secondary);">{{ $entry->service_date->format('d M Y') }}</td>
                            <td style="padding: var(--space-3);">
                                @if ($entry->asset)
                                    <a href="{{ route('assets.show', $entry->asset) }}" style="color: var(--text-primary); font-weight: var(--weight-medium); text-decoration: none;">{{ $entry->asset->asset_name }}</a>
                                    <div style="font-size: var(--fs-12); color: var(--text-tertiary); font-family: monospace;">{{ $entry->asset->asset_code }}</div>
                                @endif
                            </td>
                            <td style="padding: var(--space-3);">
                                @if ($entry->serviceJob)
                                    <a href="{{ route('jobs.show', $entry->serviceJob) }}" style="color: var(--bronze-600); text-decoration: none; font-family: monospace; font-size: var(--fs-13);">{{ $entry->serviceJob->job_reference }}</a>
                                @endif
                            </td>
                            <td style="padding: var(--space-3); color: var(--text-secondary);">{{ $entry->job_type->label() }}</td>
                            <td style="padding: var(--space-3);">
                                <x-onyx.badge tone="neutral" variant="soft">{{ $entry->status_before->label() }}</x-onyx.badge>
                                <span style="color: var(--text-tertiary); margin: 0 var(--space-1);">→</span>
                                <x-onyx.badge :tone="$entry->status_after->tone()" variant="soft">{{ $entry->status_after->label() }}</x-onyx.badge>
                            </td>
                            <td style="padding: var(--space-3); color: var(--text-secondary); font-size: var(--fs-13);">
                                {{ $entry->technicianProfiles()->pluck('name')->implode(', ') ?: '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: var(--space-4);">
            {{ $entries->links() }}
        </div>
    @endif
</div>

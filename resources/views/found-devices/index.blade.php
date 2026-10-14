<x-layouts.app title="Devices to verify">

    <x-slot:breadcrumbs>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Devices to verify</span>
    </x-slot:breadcrumbs>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Devices to verify</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary); max-width: 640px;">
            Devices technicians found on site. They stay out of the register, reports and dashboards until you verify them.
        </p>
    </div>

    @if ($devices->isEmpty())
        <x-onyx.card variant="default" padding="xl">
            <x-onyx.empty icon="check-circle" heading="Nothing to verify" body="When a technician adds a device on site, it appears here for you to check." />
        </x-onyx.card>
    @else
        <div style="overflow-x: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                <thead>
                    <tr style="background: var(--surface-sunken); border-bottom: 1px solid var(--border-subtle);">
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Device</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Store</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Found</th>
                        <th style="padding: var(--space-3) var(--space-4); text-align: right;"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($devices as $device)
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: var(--space-3) var(--space-4);">
                                <span style="display: block; font-weight: var(--weight-medium); color: var(--text-primary);">{{ $device->asset_name }}</span>
                                <span style="font-size: var(--fs-12); color: var(--text-secondary); font-family: monospace;">{{ $device->asset_code }}</span>
                                <span style="font-size: var(--fs-12); color: var(--text-secondary);"> · {{ $device->asset_type->label() }}</span>
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">{{ $device->store?->store_name }}</td>
                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">
                                {{ $device->created_at?->format('d/m/Y') }} · {{ $device->foundBy?->name ?? 'Technician' }}
                                @if ($device->foundOnJob) · {{ $device->foundOnJob->job_reference }} @endif
                            </td>
                            <td style="padding: var(--space-3) var(--space-4); text-align: right;">
                                <x-onyx.button href="{{ route('found-devices.show', $device) }}" variant="outline" size="sm">Check</x-onyx.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</x-layouts.app>

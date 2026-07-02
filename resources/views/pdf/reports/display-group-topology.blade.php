<x-pdf.report-layout
    title="Display Group Topology — {{ $store->store_name }}"
    :client="$client"
    :generated-at="$generatedAt"
    :timezone="$timezone"
>
    @forelse ($groups as $group)
        <div style="margin-bottom: 8mm; page-break-inside: avoid;">
            <h3 style="font-size: 11pt; margin-bottom: 3mm;">{{ $group->group_name }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Model</th>
                        <th>Serial</th>
                        <th>Status</th>
                        <th>Last Service</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($group->player)
                        <tr>
                            <td>Player</td>
                            <td>{{ $group->player->manufacturer }} {{ $group->player->model }}</td>
                            <td>{{ $group->player->serial_number ?? '—' }}</td>
                            <td>{{ $group->player->asset_status->label() }}</td>
                            <td>{{ $lastService->get($group->player->id) ?? '—' }}</td>
                        </tr>
                    @endif
                    @foreach ($group->screens as $screen)
                        <tr>
                            <td>Screen</td>
                            <td>{{ $screen->manufacturer }} {{ $screen->model }}</td>
                            <td>{{ $screen->serial_number ?? '—' }}</td>
                            <td>{{ $screen->asset_status->label() }}</td>
                            <td>{{ $lastService->get($screen->id) ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($group->layout_description)
                <p style="font-size: 8pt; color: #555; margin-top: 2mm;">{{ $group->layout_description }}</p>
            @endif
        </div>
    @empty
        <p style="color: #888; padding: 8mm 0;">No Display Groups configured for this store yet.</p>
    @endforelse

    @if ($ungrouped->isNotEmpty())
        <div style="margin-top: 8mm; page-break-inside: avoid;">
            <h3 style="font-size: 11pt; margin-bottom: 3mm;">Assets not in a Display Group</h3>
            <table>
                <thead>
                    <tr>
                        <th>Asset Code</th>
                        <th>Type</th>
                        <th>Model</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ungrouped as $asset)
                        <tr>
                            <td>{{ $asset->asset_code }}</td>
                            <td>{{ $asset->asset_type->label() }}</td>
                            <td>{{ $asset->manufacturer }} {{ $asset->model }}</td>
                            <td>{{ $asset->asset_status->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-pdf.report-layout>

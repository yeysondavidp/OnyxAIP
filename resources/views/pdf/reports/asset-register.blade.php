<x-pdf.report-layout
    title="Asset Register"
    :client="$client"
    :filter-summary="$filterSummary"
    :generated-at="$generatedAt"
    :timezone="$timezone"
>
    <table>
        <thead>
            <tr>
                <th>Asset Code</th>
                <th>Type</th>
                <th>Name</th>
                <th>Manufacturer / Model</th>
                <th>Store</th>
                <th>State</th>
                <th>Status</th>
                <th>Warranty Expiry</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assets as $asset)
                <tr>
                    <td>{{ $asset->asset_code }}</td>
                    <td>{{ $asset->asset_type->label() }}</td>
                    <td>{{ $asset->asset_name }}</td>
                    <td>{{ $asset->manufacturer }} {{ $asset->model }}</td>
                    <td>{{ $asset->store?->store_name }}</td>
                    <td>{{ $asset->store?->state->value }}</td>
                    <td>{{ $asset->asset_status->label() }}</td>
                    <td>{{ $asset->warranty_expiry?->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: #888; padding: 8mm;">No assets match the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-pdf.report-layout>

<x-pdf.report-layout
    title="Service History — {{ $asset->asset_code }}"
    :client="$client"
    :filter-summary="$asset->asset_name.' · '.$asset->store?->store_name"
    :generated-at="$generatedAt"
    :timezone="$timezone"
>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Job Type</th>
                <th>Technician(s)</th>
                <th>Status Before → After</th>
                <th>Notes</th>
                <th>Photos</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($history as $h)
                <tr>
                    <td>{{ $h->service_date->format('d/m/Y') }}</td>
                    <td>{{ $h->job_type->label() }}</td>
                    <td>{{ $h->technicianNames }}</td>
                    <td>{{ $h->status_before->label() }} → {{ $h->status_after->label() }}</td>
                    <td>{{ $h->technician_notes ?? '—' }}</td>
                    <td>
                        @foreach ($h->photoLinks as $photo)
                            <a href="{{ $photo['url'] }}">{{ $photo['label'] }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #888; padding: 8mm;">No service history recorded for this asset yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-pdf.report-layout>

<x-pdf.report-layout
    title="Service History — {{ $store->store_name }}"
    :client="$client"
    :generated-at="$generatedAt"
    :timezone="$timezone"
>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Asset Code</th>
                <th>Job Reference</th>
                <th>Job Type</th>
                <th>Technician(s)</th>
                <th>Status Before → After</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($history as $h)
                <tr>
                    <td>{{ $h->service_date->format('d/m/Y') }}</td>
                    <td>{{ $h->asset?->asset_code }}</td>
                    <td>{{ $h->serviceJob?->job_reference }}</td>
                    <td>{{ $h->job_type->label() }}</td>
                    <td>{{ $h->technicianNames }}</td>
                    <td>{{ $h->status_before->label() }} → {{ $h->status_after->label() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #888; padding: 8mm;">No service history recorded for this store yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-pdf.report-layout>

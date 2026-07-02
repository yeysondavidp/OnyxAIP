<x-layouts.app title="Reports">

    <x-slot:breadcrumbs>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Reports</span>
    </x-slot:breadcrumbs>

    <div style="margin-bottom: var(--space-8);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">
            Reports
        </h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">
            Export asset registers, service history, SLA compliance, and more.
        </p>
    </div>

    @if (session('success'))
        <div style="margin-bottom: var(--space-6);">
            <x-onyx.alert tone="positive" title="Report requested">
                {!! session('success') !!}
            </x-onyx.alert>
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-10);">

        <x-onyx.card variant="default" padding="lg" :interactive="true" as="a" href="{{ route('reports.asset-register.create') }}" style="text-decoration: none;">
            <h2 style="font-size: var(--fs-16); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">Asset Register</h2>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Full inventory export (CSV/PDF) plus a status summary (CSV).</p>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="lg" :interactive="true" as="a" href="{{ route('reports.service-history.create') }}" style="text-decoration: none;">
            <h2 style="font-size: var(--fs-16); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">Service History</h2>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Per-asset (PDF) or per-store (PDF/CSV) maintenance record.</p>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="lg" :interactive="true" as="a" href="{{ route('reports.open-faults.create') }}" style="text-decoration: none;">
            <h2 style="font-size: var(--fs-16); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">Open Faults</h2>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Every asset currently Faulty or Offline across a client's estate.</p>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="lg" :interactive="true" as="a" href="{{ route('reports.sla-compliance.create') }}" style="text-decoration: none;">
            <h2 style="font-size: var(--fs-16); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">SLA Compliance</h2>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Job-by-job SLA outcome for a client over a date range.</p>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="lg" :interactive="true" as="a" href="{{ route('reports.technician-hours.create') }}" style="text-decoration: none;">
            <h2 style="font-size: var(--fs-16); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">Technician Hours</h2>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Worked hours per technician over a date range.</p>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="lg" :interactive="true" as="a" href="{{ route('reports.warranty-forecast.create') }}" style="text-decoration: none;">
            <h2 style="font-size: var(--fs-16); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">Warranty Expiry Forecast</h2>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Assets whose warranty falls within a date range.</p>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="lg" :interactive="true" as="a" href="{{ route('reports.display-group-topology.create') }}" style="text-decoration: none;">
            <h2 style="font-size: var(--fs-16); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">Display Group Topology</h2>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Player-to-screen map for a store's reporting pack (PDF).</p>
        </x-onyx.card>

    </div>

    <div>
        <h2 style="font-size: var(--fs-18); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Recent exports</h2>

        @if ($recent->isEmpty())
            <x-onyx.card variant="outline" padding="none">
                <x-onyx.empty icon="download" heading="No reports generated yet" body="Run a report above — it'll show up here with a download link." />
            </x-onyx.card>
        @else
            <x-onyx.card variant="default" padding="none">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <th style="text-align: left; padding: var(--space-3) var(--space-4); font-size: var(--fs-12); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Report</th>
                            <th style="text-align: left; padding: var(--space-3) var(--space-4); font-size: var(--fs-12); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Client</th>
                            <th style="text-align: left; padding: var(--space-3) var(--space-4); font-size: var(--fs-12); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Status</th>
                            <th style="text-align: left; padding: var(--space-3) var(--space-4); font-size: var(--fs-12); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Requested</th>
                            <th style="text-align: right; padding: var(--space-3) var(--space-4);"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $export)
                            <tr style="border-bottom: 1px solid var(--border-subtle);">
                                <td style="padding: var(--space-3) var(--space-4); font-size: var(--fs-14); color: var(--text-primary);">{{ $export->report_type->label() }} ({{ strtoupper($export->format->value) }})</td>
                                <td style="padding: var(--space-3) var(--space-4); font-size: var(--fs-14); color: var(--text-secondary);">{{ $export->client?->client_name ?? '—' }}</td>
                                <td style="padding: var(--space-3) var(--space-4);">
                                    <x-onyx.badge :tone="$export->status->value === 'ready' ? 'positive' : ($export->status->value === 'failed' ? 'critical' : 'info')">
                                        {{ $export->status->label() }}
                                    </x-onyx.badge>
                                </td>
                                <td style="padding: var(--space-3) var(--space-4); font-size: var(--fs-14); color: var(--text-secondary);">{{ $export->created_at->diffForHumans() }}</td>
                                <td style="padding: var(--space-3) var(--space-4); text-align: right;">
                                    @if ($export->downloadUrl)
                                        <x-onyx.button href="{{ $export->downloadUrl }}" variant="outline" size="sm">Download</x-onyx.button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-onyx.card>

            <div style="margin-top: var(--space-4);">{{ $recent->links() }}</div>
        @endif
    </div>

</x-layouts.app>

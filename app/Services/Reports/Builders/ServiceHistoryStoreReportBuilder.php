<?php

namespace App\Services\Reports\Builders;

use App\Models\ReportExport;
use App\Models\ServiceHistory;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Services\Reports\CsvExportWriter;
use App\Services\Reports\PdfExportWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * US-14.2 — Service History per Store (PDF + CSV). Must call
 * ServiceHistory::forStore() — its own docblock mandates reuse by EPIC-14
 * reporting rather than a separate query path.
 */
class ServiceHistoryStoreReportBuilder implements ReportBuilder
{
    public function __construct(
        private readonly CsvExportWriter $csv,
        private readonly PdfExportWriter $pdf,
    ) {}

    public function countQuery(array $params, ?int $clientId): Builder
    {
        return ServiceHistory::forStore((int) $params['store_id'], $this->filters($params));
    }

    public function writeCsv(ReportExport $export, string $path): int
    {
        $storeId = (int) $export->params['store_id'];
        $filters = $this->filters($export->params);

        $technicians = $this->technicianNames($storeId, $filters);
        $query       = ServiceHistory::forStore($storeId, $filters);

        return $this->csv->streamToDisk('local', $path, [
            'Service Date', 'Asset Code', 'Asset Name', 'Job Reference', 'Job Type',
            'Status Before', 'Status After', 'Technician(s)', 'Notes',
        ], $query, fn (ServiceHistory $h) => [
            $h->service_date->format('Y-m-d'),
            $h->asset?->asset_code,
            $h->asset?->asset_name,
            $h->serviceJob?->job_reference,
            $h->job_type->label(),
            $h->status_before->label(),
            $h->status_after->label(),
            $this->namesFor($h, $technicians),
            $h->technician_notes,
        ]);
    }

    public function writePdf(ReportExport $export, string $path): int
    {
        $store   = Store::findOrFail($export->params['store_id']);
        $filters = $this->filters($export->params);

        $technicians = $this->technicianNames($store->id, $filters);
        $history     = ServiceHistory::forStore($store->id, $filters)->get();

        $history->each(function (ServiceHistory $h) use ($technicians) {
            $h->technicianNames = $this->namesFor($h, $technicians);
        });

        $this->pdf->writeToDisk('local', $path, 'pdf.reports.service-history-store', [
            'store'       => $store,
            'history'     => $history,
            'client'      => $export->client,
            'generatedAt' => now(),
            'timezone'    => $store->store_timezone,
        ]);

        return $history->count();
    }

    /** @return array{asset_type?: string, from?: string, to?: string} */
    private function filters(array $params): array
    {
        return array_filter([
            'asset_type' => $params['asset_type'] ?? null,
            'from'       => $params['date_from']  ?? null,
            'to'         => $params['date_to']    ?? null,
        ]);
    }

    /** @return Collection<int, string> */
    private function technicianNames(int $storeId, array $filters)
    {
        $ids = ServiceHistory::forStore($storeId, $filters)
            ->pluck('technician_profile_ids')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        return TechnicianProfile::whereIn('id', $ids)->pluck('name', 'id');
    }

    private function namesFor(ServiceHistory $h, $technicians): string
    {
        return collect($h->technician_profile_ids ?? [])
            ->map(fn ($id) => $technicians->get($id, 'Unknown'))
            ->implode(', ');
    }
}

<?php

namespace App\Services\Reports\Builders;

use App\Models\Asset;
use App\Models\ReportExport;
use App\Models\Store;
use App\Services\Reports\CsvExportWriter;
use App\Services\Reports\PdfExportWriter;
use Illuminate\Database\Eloquent\Builder;

/**
 * US-14.1 — Asset Register export (CSV + PDF), filtered by client/state/store/type.
 */
class AssetRegisterReportBuilder implements ReportBuilder
{
    public function __construct(
        private readonly CsvExportWriter $csv,
        private readonly PdfExportWriter $pdf,
    ) {}

    public function countQuery(array $params, ?int $clientId): Builder
    {
        return $this->query($params, $clientId);
    }

    public function writeCsv(ReportExport $export, string $path): int
    {
        $query = $this->query($export->params, $export->client_id)->with('store');

        return $this->csv->streamToDisk('local', $path, [
            'Asset Code', 'Asset Type', 'Asset Name', 'Manufacturer', 'Model', 'Serial Number',
            'Store', 'State', 'Asset Status', 'Purchase Date', 'Warranty Expiry', 'Install Date',
        ], $query, fn (Asset $a) => [
            $a->asset_code,
            $a->asset_type->label(),
            $a->asset_name,
            $a->manufacturer,
            $a->model,
            $a->serial_number,
            $a->store?->store_name,
            $a->store?->state->value,
            $a->asset_status->label(),
            $a->purchase_date?->format('Y-m-d'),
            $a->warranty_expiry?->format('Y-m-d'),
            $a->install_date?->format('Y-m-d'),
        ]);
    }

    public function writePdf(ReportExport $export, string $path): int
    {
        $query = $this->query($export->params, $export->client_id)->with('store');
        $rows  = $query->get();

        $storeId = $export->params['store_id'] ?? null;
        $tz      = is_numeric($storeId) ? Store::find((int) $storeId)?->store_timezone : null;

        $this->pdf->writeToDisk('local', $path, 'pdf.reports.asset-register', [
            'assets'        => $rows,
            'client'        => $export->client,
            'filterSummary' => $this->filterSummary($export->params),
            'generatedAt'   => now(),
            'timezone'      => $tz ?? 'Australia/Sydney',
        ]);

        return $rows->count();
    }

    /** @return Builder<Asset> */
    private function query(array $params, ?int $clientId): Builder
    {
        return Asset::allClients()
            ->where('client_id', $clientId)
            ->when($params['store_id'] ?? null, fn (Builder $q, $storeId) => $q->where('store_id', $storeId))
            ->when($params['asset_type'] ?? null, fn (Builder $q, $type) => $q->where('asset_type', $type))
            ->when($params['state'] ?? null, fn (Builder $q, $state) => $q->whereHas('store', fn (Builder $s) => $s->where('state', $state)))
            ->orderBy('asset_code');
    }

    private function filterSummary(array $params): string
    {
        $parts = [];
        if (! empty($params['state'])) {
            $parts[] = 'State: '.$params['state'];
        }
        if (! empty($params['store_id']) && is_numeric($params['store_id'])) {
            $storeId = (int) $params['store_id'];
            $store   = Store::find($storeId);
            $parts[] = 'Store: '.($store instanceof Store ? $store->store_name : (string) $storeId);
        }
        if (! empty($params['asset_type'])) {
            $parts[] = 'Type: '.$params['asset_type'];
        }

        return $parts === [] ? 'All assets' : implode(' · ', $parts);
    }
}

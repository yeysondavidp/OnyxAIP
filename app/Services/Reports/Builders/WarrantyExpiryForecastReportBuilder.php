<?php

namespace App\Services\Reports\Builders;

use App\Models\Asset;
use App\Models\ReportExport;
use App\Services\Reports\CsvExportWriter;
use App\Support\DurationCalculator;
use Illuminate\Database\Eloquent\Builder;

/**
 * US-14.4 — Warranty Expiry Forecast (CSV). Reuses the day-math extracted
 * from CheckWarrantyExpiry (App\Support\DurationCalculator::daysUntil)
 * rather than recomputing it.
 */
class WarrantyExpiryForecastReportBuilder implements ReportBuilder
{
    public function __construct(private readonly CsvExportWriter $csv) {}

    public function countQuery(array $params, ?int $clientId): Builder
    {
        return $this->query($params, $clientId);
    }

    public function writeCsv(ReportExport $export, string $path): int
    {
        $query = $this->query($export->params, $export->client_id)->with('store');
        $today = now();

        return $this->csv->streamToDisk('local', $path, [
            'Store', 'Asset Code', 'Model', 'Serial Number', 'Warranty Expiry', 'Days Until Expiry',
        ], $query, function (Asset $a) use ($today) {
            $expiry = $a->warranty_expiry;

            return [
                $a->store->store_name,
                $a->asset_code,
                $a->model,
                $a->serial_number,
                $expiry?->format('Y-m-d') ?? '',
                $expiry === null ? '' : (string) DurationCalculator::daysUntil($today, $expiry),
            ];
        });
    }

    public function writePdf(ReportExport $export, string $path): int
    {
        throw new \LogicException('Warranty Expiry Forecast is CSV-only.');
    }

    /** @return Builder<Asset> */
    private function query(array $params, ?int $clientId): Builder
    {
        return Asset::allClients()
            ->where('client_id', $clientId)
            ->whereBetween('warranty_expiry', [$params['date_from'], $params['date_to']])
            ->orderBy('warranty_expiry');
    }
}

<?php

namespace App\Services\Reports\Builders;

use App\Models\Asset;
use App\Models\ReportExport;
use App\Services\Reports\CsvExportWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * US-14.1 — Asset Status Summary (CSV only): counts grouped by type/status
 * for the selected client/state.
 */
class AssetStatusSummaryReportBuilder implements ReportBuilder
{
    public function __construct(private readonly CsvExportWriter $csv) {}

    public function countQuery(array $params, ?int $clientId): Builder
    {
        return $this->query($params, $clientId);
    }

    public function writeCsv(ReportExport $export, string $path): int
    {
        $rows = $this->query($export->params, $export->client_id)
            ->select('asset_type', 'asset_status', DB::raw('count(*) as total'))
            ->groupBy('asset_type', 'asset_status')
            ->orderBy('asset_type')
            ->orderBy('asset_status')
            ->get();

        $csvRows = $rows->map(fn (Asset $r) => [
            $r->asset_type->label(),
            $r->asset_status->label(),
            (string) $r->total,
        ]);

        return $this->csv->writeRowsToDisk('local', $path, ['Asset Type', 'Asset Status', 'Count'], $csvRows);
    }

    public function writePdf(ReportExport $export, string $path): int
    {
        throw new \LogicException('Asset Status Summary is CSV-only.');
    }

    /** @return Builder<Asset> */
    private function query(array $params, ?int $clientId): Builder
    {
        return Asset::allClients()
            ->where('client_id', $clientId)
            ->when($params['state'] ?? null, fn (Builder $q, $state) => $q->whereHas('store', fn (Builder $s) => $s->where('state', $state)));
    }
}

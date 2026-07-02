<?php

namespace App\Services\Reports\Builders;

use App\Models\Asset;
use App\Models\DisplayGroup;
use App\Models\ReportExport;
use App\Models\ServiceHistory;
use App\Models\Store;
use App\Services\Reports\PdfExportWriter;
use Illuminate\Database\Eloquent\Builder;

/**
 * US-14.5 — Display Group Topology (PDF only). Last-service-date for every
 * component (player + screens across the whole store) is resolved in one
 * batched query, not per group (Engineering Bar — avoid N+1 across groups).
 */
class DisplayGroupTopologyReportBuilder implements ReportBuilder
{
    public function __construct(private readonly PdfExportWriter $pdf) {}

    public function countQuery(array $params, ?int $clientId): Builder
    {
        return DisplayGroup::where('store_id', $params['store_id']);
    }

    public function writeCsv(ReportExport $export, string $path): int
    {
        throw new \LogicException('Display Group Topology is PDF-only.');
    }

    public function writePdf(ReportExport $export, string $path): int
    {
        $store  = Store::findOrFail($export->params['store_id']);
        $groups = DisplayGroup::where('store_id', $store->id)->with(['player', 'screens'])->get();

        $componentIds = $groups->flatMap(fn (DisplayGroup $g) => [
            $g->player_asset_id,
            ...$g->screens->pluck('id'),
        ])->unique()->values()->all();

        $lastService = ServiceHistory::whereIn('asset_id', $componentIds)
            ->selectRaw('asset_id, MAX(service_date) as last_service_date')
            ->groupBy('asset_id')
            ->pluck('last_service_date', 'asset_id');

        $ungrouped = Asset::where('store_id', $store->id)
            ->whereNotIn('id', $componentIds)
            ->orderBy('asset_code')
            ->get();

        $this->pdf->writeToDisk('local', $path, 'pdf.reports.display-group-topology', [
            'store'       => $store,
            'groups'      => $groups,
            'lastService' => $lastService,
            'ungrouped'   => $ungrouped,
            'client'      => $export->client,
            'generatedAt' => now(),
            'timezone'    => $store->store_timezone,
        ]);

        return $groups->count();
    }
}

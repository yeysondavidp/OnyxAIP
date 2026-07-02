<?php

namespace App\Services\Reports\Builders;

use App\Models\Asset;
use App\Models\ReportExport;
use App\Models\ServiceHistory;
use App\Models\TechnicianProfile;
use App\Services\Reports\PdfExportWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

/**
 * US-14.2 — Service History per Asset (PDF only). Reuses Asset::serviceHistory()
 * (already ordered, documented as "reused by EPIC-14 reporting") rather than
 * re-querying service_history directly.
 */
class ServiceHistoryAssetReportBuilder implements ReportBuilder
{
    public function __construct(private readonly PdfExportWriter $pdf) {}

    public function countQuery(array $params, ?int $clientId): Builder
    {
        return ServiceHistory::where('asset_id', $params['asset_id']);
    }

    public function writeCsv(ReportExport $export, string $path): int
    {
        throw new \LogicException('Service History per Asset is PDF-only.');
    }

    public function writePdf(ReportExport $export, string $path): int
    {
        $asset   = Asset::with('store')->findOrFail($export->params['asset_id']);
        $history = $asset->serviceHistory()->with('serviceJob')->get();

        $technicianIds = $history->pluck('technician_profile_ids')->flatten()->filter()->unique()->values()->all();
        $technicians   = TechnicianProfile::whereIn('id', $technicianIds)->pluck('name', 'id');

        $history->each(function (ServiceHistory $h) use ($technicians, $export) {
            $h->technicianNames = collect($h->technician_profile_ids ?? [])
                ->map(fn ($id) => $technicians->get($id, 'Unknown'))
                ->implode(', ');

            $h->photoLinks = collect([
                ...array_map(fn ($p) => ['label' => 'Before', 'path' => $p], $h->before_photo_paths ?? []),
                ...array_map(fn ($p) => ['label' => 'After', 'path' => $p], $h->after_photo_paths ?? []),
            ])->map(fn ($p) => [
                'label' => $p['label'],
                'url'   => URL::temporarySignedRoute('reports.photo.show', $export->expires_at, [
                    'serviceHistory' => $h->id,
                    'path'           => $p['path'],
                ]),
            ]);
        });

        $this->pdf->writeToDisk('local', $path, 'pdf.reports.service-history-asset', [
            'asset'       => $asset,
            'history'     => $history,
            'client'      => $export->client,
            'generatedAt' => now(),
            'timezone'    => $asset->store->store_timezone,
        ]);

        return $history->count();
    }
}

<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RedirectsAfterReportRun;
use App\Http\Requests\Reports\ServiceHistoryReportRequest;
use App\Models\Asset;
use App\Models\ReportExport;
use App\Models\Store;
use App\Services\Reports\Builders\ServiceHistoryAssetReportBuilder;
use App\Services\Reports\Builders\ServiceHistoryStoreReportBuilder;
use App\Services\Reports\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceHistoryReportController extends Controller
{
    use RedirectsAfterReportRun;

    public function create(): View
    {
        $this->authorize('viewAny', ReportExport::class);

        return view('reports.service-history.create', [
            'assets' => Asset::with('store')->orderBy('asset_code')->get(),
            'stores' => Store::where('is_active', true)->orderBy('store_name')->get(),
        ]);
    }

    public function store(
        ServiceHistoryReportRequest $request,
        ReportService $reportService,
        ServiceHistoryAssetReportBuilder $assetBuilder,
        ServiceHistoryStoreReportBuilder $storeBuilder,
    ): RedirectResponse {
        $validated = $request->validated();
        $kind      = (string) $validated['report_kind'];

        $params = [
            'asset_id'   => $validated['asset_id']   ?? null,
            'store_id'   => $validated['store_id']   ?? null,
            'asset_type' => $validated['asset_type'] ?? null,
            'date_from'  => $validated['date_from']  ?? null,
            'date_to'    => $validated['date_to']    ?? null,
        ];

        [$type, $format, $builder, $clientId] = match ($kind) {
            'asset_pdf' => [
                ReportType::ServiceHistoryAsset,
                ReportFormat::Pdf,
                $assetBuilder,
                Asset::findOrFail($params['asset_id'])->client_id,
            ],
            'store_pdf' => [
                ReportType::ServiceHistoryStore,
                ReportFormat::Pdf,
                $storeBuilder,
                Store::findOrFail($params['store_id'])->client_id,
            ],
            'store_csv' => [
                ReportType::ServiceHistoryStore,
                ReportFormat::Csv,
                $storeBuilder,
                Store::findOrFail($params['store_id'])->client_id,
            ],
            default => throw new \UnexpectedValueException("Unknown report_kind: {$kind}"),
        };

        $export = $reportService->run($type, $format, $clientId, $request->user()->id, $params, $builder);

        return $this->redirectAfterReportRun($export);
    }
}

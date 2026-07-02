<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RedirectsAfterReportRun;
use App\Http\Requests\Reports\AssetRegisterReportRequest;
use App\Models\Client;
use App\Models\ReportExport;
use App\Models\Store;
use App\Services\Reports\Builders\AssetRegisterReportBuilder;
use App\Services\Reports\Builders\AssetStatusSummaryReportBuilder;
use App\Services\Reports\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssetRegisterReportController extends Controller
{
    use RedirectsAfterReportRun;

    public function create(): View
    {
        $this->authorize('viewAny', ReportExport::class);

        return view('reports.asset-register.create', [
            'clients' => Client::where('is_active', true)->orderBy('client_name')->get(),
            'stores'  => Store::where('is_active', true)->orderBy('store_name')->get(),
        ]);
    }

    public function store(
        AssetRegisterReportRequest $request,
        ReportService $reportService,
        AssetRegisterReportBuilder $registerBuilder,
        AssetStatusSummaryReportBuilder $summaryBuilder,
    ): RedirectResponse {
        $validated = $request->validated();
        $clientId  = (int) $validated['client_id'];
        $kind      = (string) $validated['report_kind'];

        $params = [
            'store_id'   => $validated['store_id']   ?? null,
            'state'      => $validated['state']      ?? null,
            'asset_type' => $validated['asset_type'] ?? null,
        ];

        [$type, $format, $builder] = match ($kind) {
            'register_csv'       => [ReportType::AssetRegister, ReportFormat::Csv, $registerBuilder],
            'register_pdf'       => [ReportType::AssetRegister, ReportFormat::Pdf, $registerBuilder],
            'status_summary_csv' => [ReportType::AssetStatusSummary, ReportFormat::Csv, $summaryBuilder],
            default              => throw new \UnexpectedValueException("Unknown report_kind: {$kind}"),
        };

        $export = $reportService->run($type, $format, $clientId, $request->user()->id, $params, $builder);

        return $this->redirectAfterReportRun($export);
    }
}

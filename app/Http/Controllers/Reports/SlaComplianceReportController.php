<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RedirectsAfterReportRun;
use App\Http\Requests\Reports\SlaComplianceReportRequest;
use App\Models\Client;
use App\Models\ReportExport;
use App\Services\Reports\Builders\SlaComplianceReportBuilder;
use App\Services\Reports\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SlaComplianceReportController extends Controller
{
    use RedirectsAfterReportRun;

    public function create(): View
    {
        $this->authorize('viewAny', ReportExport::class);

        return view('reports.sla-compliance.create', [
            'clients' => Client::where('is_active', true)->orderBy('client_name')->get(),
        ]);
    }

    public function store(SlaComplianceReportRequest $request, ReportService $reportService, SlaComplianceReportBuilder $builder): RedirectResponse
    {
        $validated = $request->validated();
        $clientId  = (int) $validated['client_id'];

        $params = [
            'date_from' => $validated['date_from'],
            'date_to'   => $validated['date_to'],
        ];

        $export = $reportService->run(ReportType::SlaCompliance, ReportFormat::Csv, $clientId, $request->user()->id, $params, $builder);

        return $this->redirectAfterReportRun($export);
    }
}

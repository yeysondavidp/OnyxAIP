<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RedirectsAfterReportRun;
use App\Http\Requests\Reports\OpenFaultsReportRequest;
use App\Models\Client;
use App\Models\ReportExport;
use App\Services\Reports\Builders\OpenFaultsReportBuilder;
use App\Services\Reports\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OpenFaultsReportController extends Controller
{
    use RedirectsAfterReportRun;

    public function create(): View
    {
        $this->authorize('viewAny', ReportExport::class);

        return view('reports.open-faults.create', [
            'clients' => Client::where('is_active', true)->orderBy('client_name')->get(),
        ]);
    }

    public function store(OpenFaultsReportRequest $request, ReportService $reportService, OpenFaultsReportBuilder $builder): RedirectResponse
    {
        $validated = $request->validated();
        $clientId  = (int) $validated['client_id'];

        $params = ['state' => $validated['state'] ?? null];

        $export = $reportService->run(ReportType::OpenFaults, ReportFormat::Csv, $clientId, $request->user()->id, $params, $builder);

        return $this->redirectAfterReportRun($export);
    }
}

<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RedirectsAfterReportRun;
use App\Http\Requests\Reports\DisplayGroupTopologyReportRequest;
use App\Models\ReportExport;
use App\Models\Store;
use App\Services\Reports\Builders\DisplayGroupTopologyReportBuilder;
use App\Services\Reports\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DisplayGroupTopologyReportController extends Controller
{
    use RedirectsAfterReportRun;

    public function create(): View
    {
        $this->authorize('viewAny', ReportExport::class);

        return view('reports.display-group-topology.create', [
            'stores' => Store::where('is_active', true)->orderBy('store_name')->get(),
        ]);
    }

    public function store(DisplayGroupTopologyReportRequest $request, ReportService $reportService, DisplayGroupTopologyReportBuilder $builder): RedirectResponse
    {
        $validated = $request->validated();
        $store     = Store::findOrFail($validated['store_id']);

        $params = ['store_id' => $store->id];

        $export = $reportService->run(ReportType::DisplayGroupTopology, ReportFormat::Pdf, $store->client_id, $request->user()->id, $params, $builder);

        return $this->redirectAfterReportRun($export);
    }
}

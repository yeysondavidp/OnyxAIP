<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RedirectsAfterReportRun;
use App\Http\Requests\Reports\TechnicianHoursReportRequest;
use App\Models\ReportExport;
use App\Models\TechnicianProfile;
use App\Services\Reports\Builders\TechnicianHoursReportBuilder;
use App\Services\Reports\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TechnicianHoursReportController extends Controller
{
    use RedirectsAfterReportRun;

    public function create(): View
    {
        $this->authorize('generateTechnicianHours', ReportExport::class);

        return view('reports.technician-hours.create', [
            'technicians' => TechnicianProfile::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(TechnicianHoursReportRequest $request, ReportService $reportService, TechnicianHoursReportBuilder $builder): RedirectResponse
    {
        $validated = $request->validated();

        $params = [
            'technician_profile_id' => $validated['technician_profile_id'] ?? null,
            'date_from'             => $validated['date_from'],
            'date_to'               => $validated['date_to'],
        ];

        $export = $reportService->run(ReportType::TechnicianHours, ReportFormat::Csv, null, $request->user()->id, $params, $builder);

        return $this->redirectAfterReportRun($export);
    }
}

<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportExportStatus;
use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class ReportsIndexController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ReportExport::class);

        $recent = ReportExport::permittedFor(auth()->user())
            ->with('client')
            ->latest()
            ->paginate(15);

        $recent->getCollection()->transform(function (ReportExport $export) {
            $export->downloadUrl = $export->status === ReportExportStatus::Ready && now()->lessThan($export->expires_at)
                ? URL::temporarySignedRoute('reports.download', $export->expires_at, ['reportExport' => $export->id])
                : null;

            return $export;
        });

        return view('reports.index', ['recent' => $recent]);
    }
}

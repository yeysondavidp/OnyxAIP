<?php

namespace App\Http\Controllers\Reports\Concerns;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;

/**
 * Shared "your report is ready / your report is generating" flash message,
 * reused by every EPIC-14 report controller's store() action.
 */
trait RedirectsAfterReportRun
{
    private function redirectAfterReportRun(ReportExport $export): RedirectResponse
    {
        if ($export->status === ReportExportStatus::Ready) {
            $url = URL::temporarySignedRoute('reports.download', $export->expires_at, ['reportExport' => $export->id]);

            return redirect()->route('reports.index')
                ->with('success', "Your report is ready. <a href=\"{$url}\">Download it here</a>.");
        }

        return redirect()->route('reports.index')
            ->with('success', "Your report is being generated — we'll email you a download link when it's ready.");
    }
}

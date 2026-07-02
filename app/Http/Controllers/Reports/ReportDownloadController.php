<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportExportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\WriteAuditLog;
use App\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Signed-URL-only download for generated report files (EPIC-14). This route
 * carries no `auth` middleware and no policy check — the signature is the
 * sole guard, per the ACs ("served only via signed URL"). Do not add an
 * authenticated fallback path here (LabelSheetController's docblock claims
 * signed-URL serving but actually gates through an authenticated route —
 * that inconsistency must not be repeated).
 */
class ReportDownloadController extends Controller
{
    public function show(Request $request, ReportExport $reportExport): StreamedResponse
    {
        abort_unless($reportExport->status === ReportExportStatus::Ready, 404);
        abort_if(now()->greaterThan($reportExport->expires_at), 410);
        abort_unless(is_string($reportExport->path) && Storage::disk($reportExport->disk)->exists($reportExport->path), 404);

        WriteAuditLog::dispatch(
            userId: null,
            userRole: null,
            action: 'report.downloaded',
            auditableType: ReportExport::class,
            auditableId: $reportExport->id,
            before: null,
            after: ['report_type' => $reportExport->report_type->value, 'format' => $reportExport->format->value],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $extension = $reportExport->format->value;

        return Storage::disk($reportExport->disk)->download(
            $reportExport->path,
            $reportExport->report_type->value.'.'.$extension,
        );
    }
}

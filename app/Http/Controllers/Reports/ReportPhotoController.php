<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ServiceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Signed-URL photo access for report PDFs (US-14.2). Report PDFs are handed
 * to people outside the authenticated PM portal, so photo evidence must be
 * reachable without a login — unlike AssetController::downloadHistoryPhoto,
 * which requires an authenticated session and is therefore unusable here.
 */
class ReportPhotoController extends Controller
{
    public function show(Request $request, ServiceHistory $serviceHistory): StreamedResponse
    {
        $path = (string) $request->query('path', '');

        $allowed = array_merge(
            $serviceHistory->before_photo_paths ?? [],
            $serviceHistory->after_photo_paths  ?? [],
        );

        abort_unless(in_array($path, $allowed, true), 403);

        return Storage::disk('local')->download($path);
    }
}

<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Thin wrapper around DomPDF, mirroring LabelSheetService's existing usage —
 * the one call-site every EPIC-14 PDF report shares (Engineering Bar — Clean).
 */
class PdfExportWriter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function writeToDisk(
        string $disk,
        string $path,
        string $view,
        array $data,
        string $paper = 'a4',
        string $orientation = 'portrait',
    ): void {
        $pdf = Pdf::loadView($view, $data);
        $pdf->setPaper($paper, $orientation);

        Storage::disk($disk)->put($path, $pdf->output());
    }
}

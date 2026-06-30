<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\LabelSheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LabelSheetController extends Controller
{
    public function __construct(private readonly LabelSheetService $service) {}

    /**
     * Single-asset label download (US-07.2 — "Download Label" on asset detail).
     */
    public function single(Asset $asset): StreamedResponse
    {
        $this->authorize('view', $asset);

        $filename = $this->service->generate(collect([$asset]), auth()->user());

        return $this->pdfDownload($filename, 'label-'.$asset->asset_code.'.pdf');
    }

    /**
     * Batch label sheet for selected asset IDs.
     * asset_ids must all belong to the acting PM's permitted client scope.
     */
    public function batch(Request $request): StreamedResponse|RedirectResponse
    {
        $request->validate([
            'asset_ids'   => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['required', 'integer'],
        ]);

        $assets = Asset::whereIn('id', $request->input('asset_ids'))->get();

        if ($assets->isEmpty()) {
            return back()->withErrors(['asset_ids' => 'No valid assets selected.']);
        }

        foreach ($assets as $asset) {
            $this->authorize('view', $asset);
        }

        $filename = $this->service->generate($assets, auth()->user());

        return $this->pdfDownload($filename, 'labels.pdf');
    }

    private function pdfDownload(string $storagePath, string $downloadName): StreamedResponse
    {
        $content = $this->service->read($storagePath);

        return response()->streamDownload(
            fn () => print ($content),
            $downloadName,
            ['Content-Type' => 'application/pdf']
        );
    }
}

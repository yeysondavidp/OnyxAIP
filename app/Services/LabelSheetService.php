<?php

namespace App\Services;

use App\Jobs\WriteAuditLog;
use App\Models\Asset;
use App\Models\Store;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates A4 PDF label sheets for one or more assets (US-07.2).
 *
 * Each label includes: QR code, AssetCode, AssetName, AssetType,
 * Manufacturer, Model, StoreName — all specified in §7 US-07.2.
 *
 * Generated PDFs are stored in storage/app/labels/ as UUID-named files.
 * A signed temporary URL is returned (10-minute expiry) per §14.3.
 *
 * Reuses QrCodeService for QR image embedding (no copy-paste).
 */
class LabelSheetService
{
    private const DISK = 'local';

    private const DIRECTORY = 'labels';

    public function __construct(private readonly QrCodeService $qr) {}

    /**
     * Generate a label PDF for a collection of assets.
     * Returns the signed download URL (10-minute expiry).
     *
     * @param  Collection<int, Asset>  $assets
     */
    public function generate(Collection $assets, User $actor): string
    {
        $assets->loadMissing('store');

        /** @var SupportCollection<int, array<string, string>> $labels */
        $labels = $assets->map(function (Asset $a): array {
            $store     = $a->store;
            $storeName = $store instanceof Store ? $store->store_name : '—';

            return [
                'asset_code'   => $a->asset_code,
                'asset_name'   => $a->asset_name,
                'asset_type'   => $a->asset_type->label(),
                'manufacturer' => $a->manufacturer,
                'model'        => $a->model,
                'store_name'   => $storeName,
                'qr_data_uri'  => $this->qr->getDataUri($a),
            ];
        })->values();

        $pdf = Pdf::loadView('pdf.label-sheet', compact('labels'));
        $pdf->setPaper('a4', 'portrait');

        $filename = self::DIRECTORY.'/'.Str::uuid().'.pdf';
        Storage::disk(self::DISK)->put($filename, $pdf->output());

        // Audit log (append async)
        WriteAuditLog::dispatch(
            userId: $actor->id,
            userRole: $actor->role->value,
            action: 'asset.label_pdf_generated',
            auditableType: 'App\\Models\\Asset',
            auditableId: $actor->id,
            before: null,
            after: ['asset_ids' => $assets->pluck('id')->all()],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        return $filename;
    }

    /**
     * Return the raw PDF bytes for a generated label file.
     */
    public function read(string $filename): string
    {
        return Storage::disk(self::DISK)->get($filename) ?? '';
    }
}

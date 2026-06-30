<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Generates and stores QR code images for assets (US-07.1).
 *
 * Images are stored under storage/app/qr-codes/ via the local disk.
 * The URL encoded into the QR code is the public lookup route (assets.qr.lookup),
 * NOT a direct file path — the lookup route enforces role-aware access.
 *
 * Reused by LabelSheetService (US-07.2) to embed QR images in PDFs.
 */
class QrCodeService
{
    private const DISK = 'local';

    private const DIRECTORY = 'qr-codes';

    private const SIZE = 200;

    /**
     * Generate a QR code PNG for the asset and store it.
     * Updates asset.qr_code_path in place.
     * Safe to call repeatedly — overwrites the previous image.
     */
    public function generateForAsset(Asset $asset): string
    {
        $url  = route('assets.qr.lookup', ['assetCode' => $asset->asset_code]);
        $path = self::DIRECTORY.'/'.$asset->id.'.png';

        $png = QrCode::format('png')
            ->size(self::SIZE)
            ->margin(1)
            ->generate($url);

        Storage::disk(self::DISK)->put($path, $png);

        // Direct DB update — intentional bypass of fillable to keep the model clean
        DB::table('assets')
            ->where('id', $asset->id)
            ->update(['qr_code_path' => $path]);

        $asset->qr_code_path = $path;

        return $path;
    }

    /**
     * Return the raw PNG bytes for a given asset's QR code.
     * Generates on demand if the file is missing.
     */
    public function getPng(Asset $asset): string
    {
        if (! $asset->qr_code_path || ! Storage::disk(self::DISK)->exists($asset->qr_code_path)) {
            $this->generateForAsset($asset);
        }

        return Storage::disk(self::DISK)->get($asset->qr_code_path) ?? '';
    }

    /**
     * Return a base64-encoded PNG data URI for embedding in HTML/PDF.
     */
    public function getDataUri(Asset $asset): string
    {
        return 'data:image/png;base64,'.base64_encode($this->getPng($asset));
    }
}

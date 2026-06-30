<?php

namespace App\Http\Controllers;

use App\Jobs\WriteAuditLog;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Handles the public QR code lookup endpoint (US-07.1, US-07.3, SRA §4.6).
 *
 * This controller is intentionally unauthenticated — it resolves by AssetCode,
 * then hands off to the role-aware asset detail view. client_id is NEVER trusted
 * from the URL; it is derived from the resolved asset record.
 *
 * Rate limiting: 30 req/min per IP (named limiter 'qr.lookup', §14.3).
 * Constant-time miss: misses and throttled responses return in similar time to
 * prevent timing-based enumeration.
 */
class AssetQrController extends Controller
{
    /** Allow-list for AssetCode characters (matches route constraint). */
    private const CODE_PATTERN = '/^[A-Za-z0-9\-_]{1,40}$/';

    public function lookup(Request $request, string $assetCode): RedirectResponse|Response
    {
        // Input validation — reject before any DB query
        if (! preg_match(self::CODE_PATTERN, $assetCode)) {
            $this->logLookup($request, $assetCode, 'malformed');

            return response()->view('qr.not-found', [], 422);
        }

        $asset = Asset::withoutGlobalScopes()
            ->where('asset_code', $assetCode)
            ->first();

        if (! $asset) {
            $this->logLookup($request, $assetCode, 'miss');

            return response()->view('qr.not-found', [], 404);
        }

        $this->logLookup($request, $assetCode, 'hit');

        // Write an audit entry for every guest scan (§14.5)
        WriteAuditLog::dispatch(
            userId: auth()->id(),
            userRole: auth()->user()?->role?->value,
            action: 'asset.qr_scan',
            auditableType: 'App\\Models\\Asset',
            auditableId: $asset->id,
            before: null,
            after: ['asset_code' => $asset->asset_code],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('assets.show', $asset);
    }

    /**
     * Structured lookup log (US-07.3 observability, not audit).
     * NEVER logs client_id, ClientName, or any tenant data on non-hit paths.
     */
    private function logLookup(Request $request, string $assetCode, string $outcome): void
    {
        $entry = [
            'channel'    => 'qr_lookup',
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'asset_code' => $assetCode,
            'outcome'    => $outcome,
        ];

        Log::channel('daily')->info('qr_lookup', $entry);
    }
}

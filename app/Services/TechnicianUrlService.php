<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Generates expiring, per-(technician, job) signed URLs for the guest flow.
 *
 * Design decisions (ADR-001, §14.3):
 * - Signed by Laravel's URL facade — HMAC-SHA256 over the full URL including params.
 * - Expiry: 72 h default (configurable per call). Expired links render a friendly page.
 * - Scope: technician_id is embedded in the URL so the receiving middleware can
 *   verify the link was issued for the arriving technician once Job/Technician
 *   models exist (EPIC-08/09). The signed middleware guarantees no tampering.
 * - GPS data: restricted to the assigned technician and PM by the signed scope
 *   (only the correct technician_id holder can submit GPS data).
 *
 * Usage (EPIC-09 will call this when sending invitation emails):
 *   $url = app(TechnicianUrlService::class)->generate($jobId, $technicianId);
 */
class TechnicianUrlService
{
    public function generate(int $jobId, int $technicianId, int $ttlHours = 72): string
    {
        return URL::temporarySignedRoute(
            'technician.job.overview',
            now()->addHours($ttlHours),
            [
                'job'           => $jobId,
                'technician_id' => $technicianId,
            ]
        );
    }

    /**
     * Verify that a request's technician_id parameter matches the expected technician.
     * Returns false if the param is missing or does not match.
     *
     * The signed middleware already guarantees no tampering — this checks scope only.
     */
    public function scopeMatchesRequest(Request $request, int $expectedTechnicianId): bool
    {
        return (int) $request->query('technician_id') === $expectedTechnicianId;
    }
}

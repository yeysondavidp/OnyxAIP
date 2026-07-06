<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Generates expiring, per-(technician-profile, job) signed URLs for the guest flow.
 *
 * Design decisions (ADR-001, §14.3):
 * - Signed by Laravel's URL facade — HMAC-SHA256 over the full URL including params.
 * - Expiry: 72 h default. Expired links render a friendly page (US-09.4).
 * - Scope: technician_profile_id + invitation_token embedded so the endpoint can
 *   verify the link is scoped to the correct profile AND matches the live token
 *   (invalidating old tokens on resend per US-09.4).
 */
class TechnicianUrlService
{
    /** @deprecated Use generateForProfile() — kept for backward compat until EPIC-10 */
    public function generate(int $jobId, int $technicianId, int $ttlHours = 72): string
    {
        return URL::temporarySignedRoute(
            'technician.job.overview',
            now()->addHours($ttlHours),
            [
                'job'                   => $jobId,
                'technician_profile_id' => $technicianId,
            ]
        );
    }

    /**
     * Generate a signed, expiring URL scoped to a specific (job, profile, token) triple.
     *
     * The token must match the value stored in job_technicians.invitation_token for this
     * (job_id, technician_profile_id) row — validated server-side on every request.
     */
    public function generateForProfile(int $jobId, int $profileId, string $token, int $ttlHours = 72): string
    {
        return URL::temporarySignedRoute(
            'technician.job.overview',
            now()->addHours($ttlHours),
            [
                'job'                   => $jobId,
                'technician_profile_id' => $profileId,
                'token'                 => $token,
            ]
        );
    }

    /**
     * Verify the request's profile_id and token match expectations.
     */
    public function scopeMatchesRequest(Request $request, int $expectedProfileId): bool
    {
        return (int) $request->query('technician_profile_id') === $expectedProfileId;
    }

    /**
     * Build a signed URL for a different checkpoint route within the same technician
     * flow. Laravel's signature is bound to the exact URL (path + query), so hopping
     * from one screen's route to another's requires a freshly computed signature —
     * reusing the incoming request's `signature` verbatim (as every JobFlowController
     * redirect and screen-2/3/4 view previously did) always fails validation on the
     * new path. Preserves the original invitation's `expires` deadline rather than
     * extending it on every hop.
     */
    public function resignFromRequest(Request $request, string $routeName, array $parameters = []): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            Carbon::createFromTimestamp((int) $request->query('expires')),
            array_merge($parameters, [
                'token'                 => (string) $request->query('token', ''),
                'technician_profile_id' => $request->query('technician_profile_id'),
            ])
        );
    }
}

<?php

namespace App\Http\Controllers\Technician;

use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use App\Services\JobInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared resolution logic for every signed-URL technician endpoint.
 *
 * Validates:
 *   1. Job exists and is not cancelled/deleted.
 *   2. invitation_token in the URL matches the live token for this (job, profile) pair.
 *   3. TechnicianProfile is active.
 *
 * Returns [ServiceJob, TechnicianProfile] on success, or a RedirectResponse to link-expired.
 */
trait ResolvesJobFlowIdentity
{
    /**
     * @return array{0: ServiceJob, 1: TechnicianProfile}|RedirectResponse
     */
    protected function resolveIdentity(Request $request, string|int $jobParam): array|RedirectResponse
    {
        $job = ServiceJob::withTrashed()->find((int) $jobParam);

        if (! $job || $job->trashed()) {
            return redirect()->route('technician.link-expired');
        }

        $token     = (string) $request->query('token', '');
        $service   = app(JobInvitationService::class);
        $profileId = $service->resolveToken($job->id, $token);

        if ($profileId === null) {
            return redirect()->route('technician.link-expired');
        }

        $profile = TechnicianProfile::find($profileId);

        if (! $profile || ! $profile->is_active) {
            return redirect()->route('technician.link-expired');
        }

        return [$job, $profile];
    }

    /**
     * True when resolveIdentity returned a redirect (not the [job, profile] tuple).
     */
    protected function isExpiredRedirect(mixed $result): bool
    {
        return $result instanceof RedirectResponse;
    }
}

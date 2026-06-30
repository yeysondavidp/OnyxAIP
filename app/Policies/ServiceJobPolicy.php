<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ServiceJob;
use App\Models\User;

/**
 * Authorises ServiceJob actions by role AND client_id scope (Engineering Bar #1).
 *
 * The ClientScope global scope handles the read side. This policy enforces
 * the write side and model-level access checks. Both layers are required.
 */
class ServiceJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function view(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $job);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    public function update(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $job);
    }

    public function delete(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $job);
    }

    /** PM can force-complete (transition to Completed from InProgress with reason). */
    public function forceComplete(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $job);
    }

    /** PM validates a completed job. */
    public function validate(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $job);
    }

    /** PM flags a completed job as requiring remediation. */
    public function flagRemediation(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $job);
    }

    /** PM cancels a job (soft-delete). */
    public function cancel(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $job);
    }

    /** PM can manage attachments on a job they own. */
    public function manageAttachments(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Pm && $this->withinScope($user, $job);
    }

    private function withinScope(User $user, ServiceJob $job): bool
    {
        $permitted = $user->permittedClientIds();

        if ($permitted === null) {
            return true; // PM — unrestricted
        }

        return in_array($job->client_id, $permitted, strict: true);
    }
}

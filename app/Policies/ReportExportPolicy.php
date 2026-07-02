<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ReportExport;
use App\Models\User;

/**
 * Authorises EPIC-14 report generation/download. Reports aren't CRUD on a
 * single model — the authorisation target varies (a client_id, or an
 * existing Store/Asset already checked via StorePolicy/AssetPolicy, or
 * "PM only" for Technician Hours) — so this policy only owns the checks
 * that don't map onto an existing model policy. Named to match Laravel's
 * policy auto-discovery convention for the ReportExport model (no explicit
 * Gate::policy() registration needed, consistent with every other policy
 * in this app).
 */
class ReportExportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Pm || $user->role === UserRole::ClientUser;
    }

    /** May this actor run a report scoped to $clientId? */
    public function generateForClient(User $user, int $clientId): bool
    {
        $permitted = $user->permittedClientIds();

        return $permitted === null || in_array($clientId, $permitted, strict: true);
    }

    /** Technician Hours has no client dimension (AC: "scoped to ONYX's jobs only") — PM only. */
    public function generateTechnicianHours(User $user): bool
    {
        return $user->role === UserRole::Pm;
    }

    /** Authenticated re-download from the "recent exports" list (not the signed URL route). */
    public function download(User $user, ReportExport $export): bool
    {
        if ($export->client_id === null) {
            return $user->role === UserRole::Pm;
        }

        return $this->generateForClient($user, $export->client_id);
    }
}

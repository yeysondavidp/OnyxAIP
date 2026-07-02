<?php

namespace App\Services\Notifications;

use App\Enums\AssetStatus;
use App\Enums\EmailTemplateSlot;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\ReportExport;
use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Notifications\PmNotification;
use App\Notifications\TechnicianNotification;
use App\Support\JobScheduleFormatter;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The single reusable service that dispatches every notification type in the
 * platform (US-13.1-13.4, SRA §12.1/§12.2). One method per trigger — callers
 * (transition services, event listeners, scheduled commands) never build a
 * Notification or resolve recipients themselves.
 *
 * PM recipients: every `role=Pm` user. PMs are not client_id-scoped in this
 * app (App\Models\User: "Null for PM (sees all clients)") — there is no PM
 * partitioning to apply in v1; the underlying job/asset query stays scoped,
 * only the recipient set is platform-wide by design.
 */
class NotificationDispatcher
{
    // ── PM notifications (US-13.1) ──────────────────────────────────────────────

    public function jobStatusChanged(ServiceJob $job): void
    {
        $job->loadMissing('store');

        $this->notifyPms(EmailTemplateSlot::PmJobStatusChanged, [
            'job_reference' => $job->job_reference,
            'job_name'      => $job->job_name,
            'store_name'    => $job->store->store_name,
            'new_status'    => $job->job_status->label(),
        ], route('jobs.show', $job));
    }

    public function assetStatusChanged(Asset $asset, AssetStatus $before, AssetStatus $after): void
    {
        $asset = $this->fullAsset($asset);

        $this->notifyPms(EmailTemplateSlot::PmAssetStatusChanged, [
            'asset_code' => $asset->asset_code,
            'asset_name' => $asset->asset_name,
            'store_name' => $asset->store->store_name,
            'old_status' => $before->label(),
            'new_status' => $after->label(),
        ], route('assets.show', $asset));
    }

    public function newFaultReported(Asset $asset, ?ServiceJob $job = null): void
    {
        $asset = $this->fullAsset($asset);

        $this->notifyPms(EmailTemplateSlot::PmNewFaultReported, [
            'asset_code'    => $asset->asset_code,
            'asset_name'    => $asset->asset_name,
            'store_name'    => $asset->store->store_name,
            'job_reference' => $job === null ? '' : $job->job_reference,
        ], route('assets.show', $asset));
    }

    public function slaWarning(ServiceJob $job): void
    {
        $job->loadMissing(['store', 'client']);

        $this->notifyPms(EmailTemplateSlot::PmSlaWarning, [
            'job_reference'   => $job->job_reference,
            'store_name'      => $job->store->store_name,
            'client_name'     => $job->client->client_name,
            'percent_elapsed' => (string) $this->percentElapsed($job),
        ], route('jobs.show', $job));
    }

    public function slaBreached(ServiceJob $job): void
    {
        $job->loadMissing(['store', 'client']);

        $this->notifyPms(EmailTemplateSlot::PmSlaBreached, [
            'job_reference' => $job->job_reference,
            'store_name'    => $job->store->store_name,
            'client_name'   => $job->client->client_name,
        ], route('jobs.show', $job));
    }

    public function warrantyExpiryApproaching(Asset $asset, int $daysRemaining): void
    {
        $asset = $this->fullAsset($asset);

        $this->notifyPms(EmailTemplateSlot::PmWarrantyExpiryApproaching, [
            'asset_code'     => $asset->asset_code,
            'asset_name'     => trim($asset->manufacturer.' '.$asset->model),
            'store_name'     => $asset->store->store_name,
            'expiry_date'    => $asset->warranty_expiry?->format('d/m/Y') ?? '',
            'days_remaining' => (string) $daysRemaining,
        ], route('assets.show', $asset));
    }

    /**
     * Notifies only the PM who requested the report (EPIC-14) — deliberately
     * not notifyPms(): a report another PM didn't ask for shouldn't land in
     * their inbox, unlike every other PM notification in this class which is
     * platform-wide by design.
     */
    public function reportReady(ReportExport $export, User $requestedBy): void
    {
        $export->loadMissing('client');

        $downloadUrl = URL::temporarySignedRoute(
            'reports.download',
            $export->expires_at,
            ['reportExport' => $export->id],
        );

        Notification::send($requestedBy, new PmNotification(EmailTemplateSlot::ReportReady, [
            'report_type'  => $export->report_type->label(),
            'client_name'  => $export->client === null ? '' : ' for '.$export->client->client_name,
            'download_url' => $downloadUrl,
            'expires_at'   => $export->expires_at->timezone('Australia/Sydney')->format('l j F Y \a\t g:ia T'),
        ], $downloadUrl));
    }

    // ── Technician notifications (US-13.4) ──────────────────────────────────────

    public function technicianJobReminder(ServiceJob $job, TechnicianProfile $profile, string $signedUrl): void
    {
        $job->loadMissing('store');

        Notification::route('mail', $profile->email)->notify(new TechnicianNotification(
            EmailTemplateSlot::JobReminder,
            $this->technicianJobVariables($job, $profile, $signedUrl),
            $signedUrl,
        ));
    }

    public function technicianLinkExpiryWarning(ServiceJob $job, TechnicianProfile $profile, string $freshSignedUrl): void
    {
        Notification::route('mail', $profile->email)->notify(new TechnicianNotification(
            EmailTemplateSlot::LinkExpiryWarning,
            [
                'technician_name' => $profile->name,
                'job_reference'   => $job->job_reference,
                'job_name'        => $job->job_name,
                'signed_url'      => $freshSignedUrl,
            ],
            $freshSignedUrl,
        ));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Callers sometimes pass a partially-hydrated Asset (e.g.
     * ServiceJobController::syncAffectedAssets() only selects id/asset_status) —
     * re-fetch by id with the fields/relations these notifications actually need,
     * rather than trust whatever columns happened to be on the instance passed in.
     */
    private function fullAsset(Asset $asset): Asset
    {
        return Asset::with('store')->findOrFail($asset->id);
    }

    /** @param  array<string, string>  $variables */
    private function notifyPms(EmailTemplateSlot $slot, array $variables, string $actionUrl): void
    {
        $pms = User::where('role', UserRole::Pm->value)->get();

        if ($pms->isEmpty()) {
            return;
        }

        Notification::send($pms, new PmNotification($slot, $variables, $actionUrl));
    }

    /** @return array<string, string> */
    private function technicianJobVariables(ServiceJob $job, TechnicianProfile $profile, string $signedUrl): array
    {
        return [
            'technician_name' => $profile->name,
            'job_reference'   => $job->job_reference,
            'job_name'        => $job->job_name,
            'store_name'      => $job->store->store_name,
            'store_address'   => trim($job->store->address_line1.', '.$job->store->suburb.' '.$job->store->state->value),
            'scheduled_date'  => JobScheduleFormatter::format($job),
            'signed_url'      => $signedUrl,
        ];
    }

    private function percentElapsed(ServiceJob $job): int
    {
        if ($job->sla_clock_started_at === null || $job->sla_resolution_target_at === null) {
            return 0;
        }

        $total = $job->sla_clock_started_at->diffInSeconds($job->sla_resolution_target_at);

        if ($total <= 0) {
            return 100;
        }

        $elapsed = $job->sla_clock_started_at->diffInSeconds(now());

        return (int) min(100, max(0, round($elapsed / $total * 100)));
    }
}

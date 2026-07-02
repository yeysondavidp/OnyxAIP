<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Enums\PlatformSettingKey;
use App\Enums\TechnicianJobStatus;
use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use App\Services\JobInvitationService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Settings\PlatformSettings;
use App\Services\TechnicianUrlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Warns a technician when their signed job link is about to expire (US-13.4,
 * default 6h before expiry — US-16.1) and re-issues a fresh token/link in the
 * SAME way JobInvitationService::invite()'s resend branch does — never hand-
 * constructed. Idempotent via technician_notification_log's unique
 * (job_id, technician_profile_id, notification_type) index.
 */
class SendTechnicianLinkExpiryWarnings extends Command
{
    protected $signature = 'technicians:send-link-expiry-warnings';

    protected $description = 'Warn technicians and re-issue a fresh link before their signed URL expires (US-13.4)';

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly NotificationDispatcher $notifications,
        private readonly TechnicianUrlService $urlService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $warningHours = (int) $this->settings->get(PlatformSettingKey::LinkExpiryWarningHours->value, 6);

        $rows = DB::table('job_technicians')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '>', now())
            ->where('token_expires_at', '<=', now()->addHours($warningHours))
            ->where('technician_status', '!=', TechnicianJobStatus::Completed->value)
            ->get();

        $sent = 0;

        foreach ($rows as $row) {
            $alreadySent = DB::table('technician_notification_log')
                ->where('job_id', $row->job_id)
                ->where('technician_profile_id', $row->technician_profile_id)
                ->where('notification_type', 'link_expiry_warning')
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $job = ServiceJob::whereNotIn('job_status', [JobStatus::Validated->value, JobStatus::Cancelled->value])
                ->find($row->job_id);
            $profile = TechnicianProfile::find($row->technician_profile_id);

            if ($job === null || $profile === null) {
                continue;
            }

            $freshToken     = Str::random(48);
            $freshExpiresAt = now()->addHours(JobInvitationService::TTL_HOURS);

            DB::table('job_technicians')
                ->where('job_id', $job->id)
                ->where('technician_profile_id', $profile->id)
                ->update([
                    'invitation_token' => $freshToken,
                    'token_expires_at' => $freshExpiresAt,
                ]);

            $freshUrl = $this->urlService->generateForProfile(
                $job->id,
                $profile->id,
                $freshToken,
                JobInvitationService::TTL_HOURS,
            );

            $this->notifications->technicianLinkExpiryWarning($job, $profile, $freshUrl);

            DB::table('technician_notification_log')->insert([
                'job_id'                => $job->id,
                'technician_profile_id' => $profile->id,
                'notification_type'     => 'link_expiry_warning',
                'sent_at'               => now(),
            ]);

            $sent++;
        }

        $this->info("Technician link-expiry warnings complete: {$sent} sent.");

        return self::SUCCESS;
    }
}

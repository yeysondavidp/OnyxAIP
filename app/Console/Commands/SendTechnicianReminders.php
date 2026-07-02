<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Enums\PlatformSettingKey;
use App\Enums\TechnicianJobStatus;
use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Settings\PlatformSettings;
use App\Services\TechnicianUrlService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reminds each assigned technician of an upcoming visit within the configured
 * window (US-13.4, default 24h before scheduled time — US-16.1). Uses the
 * SAME signed URL already issued at invitation time (must still be valid at
 * reminder time, per the story) — link-expiry-warning is a separate command
 * that re-issues a fresh one. Idempotent via technician_notification_log's
 * unique (job_id, technician_profile_id, notification_type) index.
 *
 * Reads job_technicians via DB::table() rather than the Eloquent pivot —
 * matches the existing convention in JobInvitationService/JobFlowService.
 */
class SendTechnicianReminders extends Command
{
    protected $signature = 'technicians:send-reminders';

    protected $description = 'Send technician job reminders within the configured window (US-13.4)';

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly NotificationDispatcher $notifications,
        private readonly TechnicianUrlService $urlService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $reminderHours = (int) $this->settings->get(PlatformSettingKey::TechnicianReminderHours->value, 24);
        $windowEnd     = now()->addHours($reminderHours);

        $jobs = ServiceJob::whereNotNull('scheduled_date')
            ->whereNotNull('scheduled_time')
            ->whereNotIn('job_status', [JobStatus::Validated->value, JobStatus::Cancelled->value])
            ->with('store')
            ->get();

        $sent = 0;

        foreach ($jobs as $job) {
            $scheduledAt = Carbon::parse(
                $job->scheduled_date->format('Y-m-d').' '.$job->scheduled_time,
                'UTC',
            );

            if ($scheduledAt->isPast() || $scheduledAt->gt($windowEnd)) {
                continue;
            }

            $pivotRows = DB::table('job_technicians')
                ->where('job_id', $job->id)
                ->whereNotIn('technician_status', [TechnicianJobStatus::Completed->value, TechnicianJobStatus::Started->value])
                ->get();

            foreach ($pivotRows as $row) {
                $token          = $row->invitation_token;
                $tokenExpiresAt = $row->token_expires_at;

                if ($token === null || $tokenExpiresAt === null || Carbon::parse($tokenExpiresAt)->isPast()) {
                    continue; // no live link to remind with
                }

                $alreadySent = DB::table('technician_notification_log')
                    ->where('job_id', $job->id)
                    ->where('technician_profile_id', $row->technician_profile_id)
                    ->where('notification_type', 'reminder')
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $profile = TechnicianProfile::find($row->technician_profile_id);

                if ($profile === null) {
                    continue;
                }

                $remainingHours = max(1, (int) now()->diffInHours(Carbon::parse($tokenExpiresAt)));
                $signedUrl      = $this->urlService->generateForProfile($job->id, $profile->id, $token, $remainingHours);

                $this->notifications->technicianJobReminder($job, $profile, $signedUrl);

                DB::table('technician_notification_log')->insert([
                    'job_id'                => $job->id,
                    'technician_profile_id' => $profile->id,
                    'notification_type'     => 'reminder',
                    'sent_at'               => now(),
                ]);

                $sent++;
            }
        }

        $this->info("Technician reminders complete: {$sent} sent.");

        return self::SUCCESS;
    }
}

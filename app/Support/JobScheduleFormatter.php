<?php

namespace App\Support;

use App\Models\ServiceJob;
use Illuminate\Support\Carbon;

/**
 * Formats a job's scheduled date/time in its store's local timezone for
 * technician-facing copy (emails, reminders) — "Wednesday, 01 July 2026 at
 * 9:00 AM (Australia/Sydney)". Shared by JobInvitationService (EPIC-09) and
 * NotificationDispatcher (EPIC-13) so the format is defined exactly once.
 */
class JobScheduleFormatter
{
    public static function format(ServiceJob $job): string
    {
        if (! $job->scheduled_date) {
            return '';
        }

        $dt = Carbon::parse(
            $job->scheduled_date->format('Y-m-d').($job->scheduled_time ? ' '.$job->scheduled_time : ''),
            'UTC',
        )->setTimezone($job->job_timezone);

        $formatted = $dt->format('l, d F Y');

        if ($job->scheduled_time) {
            $formatted .= ' at '.$dt->format('g:i A').' ('.$job->job_timezone.')';
        }

        return $formatted;
    }
}

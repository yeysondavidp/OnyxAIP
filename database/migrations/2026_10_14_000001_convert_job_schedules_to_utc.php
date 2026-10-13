<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Until 2026-10-14 the job form saved the scheduled date/time exactly as the PM typed it
 * (store-local), while every reader treats the columns as UTC (SRA §5.1) — so a 16:30 Sydney
 * visit was shown to technicians as 03:30 the next day. The form now converts on save; this
 * converts the rows saved before the fix, using each job's own timezone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->shift(fn (string $local, string $tz) => Carbon::parse($local, $tz)->utc());
    }

    public function down(): void
    {
        $this->shift(fn (string $utc, string $tz) => Carbon::parse($utc, 'UTC')->setTimezone($tz));
    }

    /** @param callable(string, string): Carbon $convert */
    private function shift(callable $convert): void
    {
        DB::table('service_jobs')
            ->whereNotNull('scheduled_date')
            ->whereNotNull('scheduled_time')
            ->orderBy('id')
            ->each(function (object $job) use ($convert): void {
                $at = $convert(substr((string) $job->scheduled_date, 0, 10).' '.$job->scheduled_time, (string) $job->job_timezone);

                DB::table('service_jobs')->where('id', $job->id)->update([
                    'scheduled_date' => $at->format('Y-m-d'),
                    'scheduled_time' => $at->format('H:i:s'),
                ]);
            });
    }
};

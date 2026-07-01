<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Events\JobSlaAtRisk;
use App\Events\JobSlaBreached;
use App\Models\ServiceJob;
use Illuminate\Console\Command;

/**
 * Scheduled recompute of SLA at-risk/breached flags (US-12.3, §10.2).
 *
 * Target instants (sla_at_risk_at / sla_resolution_target_at) are computed
 * once at clock-start via BusinessHoursCalculator (US-12.2) — this command
 * only compares them to now(), so it stays cheap even run hourly against
 * every open fault job (no business-hours arithmetic on the hot path).
 *
 * Scheduled hourly in routes/console.php.
 */
class RecomputeSlaStatus extends Command
{
    protected $signature = 'sla:recompute';

    protected $description = 'Recompute SLA at-risk/breached flags for open, SLA-tracked jobs (US-12.3)';

    public function handle(): int
    {
        $now = now();

        $jobs = ServiceJob::whereNotNull('sla_clock_started_at')
            ->where('sla_breached', false)
            ->whereNotIn('job_status', [JobStatus::Validated->value, JobStatus::Cancelled->value])
            ->get();

        $newlyAtRisk   = 0;
        $newlyBreached = 0;

        foreach ($jobs as $job) {
            $wasAtRisk   = $job->sla_at_risk;
            $nowAtRisk   = $job->sla_at_risk_at           !== null && $now->gte($job->sla_at_risk_at);
            $nowBreached = $job->sla_resolution_target_at !== null && $now->gte($job->sla_resolution_target_at);

            if ($nowAtRisk === $wasAtRisk && ! $nowBreached) {
                continue;
            }

            $job->update([
                'sla_at_risk'  => $nowAtRisk,
                'sla_breached' => $nowBreached,
            ]);

            if ($nowBreached) {
                $newlyBreached++;
                event(new JobSlaBreached($job));
            } elseif ($nowAtRisk) {
                $newlyAtRisk++;
                event(new JobSlaAtRisk($job));
            }
        }

        $this->info(sprintf(
            'Checked %d open SLA-tracked job(s): %d newly at-risk, %d newly breached.',
            $jobs->count(),
            $newlyAtRisk,
            $newlyBreached,
        ));

        return self::SUCCESS;
    }
}

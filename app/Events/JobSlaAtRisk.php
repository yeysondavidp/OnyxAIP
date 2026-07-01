<?php

namespace App\Events;

use App\Models\ServiceJob;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once, on the false→true edge, when a job's SLA clock crosses the
 * at-risk threshold (US-12.3). No listeners yet — EPIC-13 (notifications)
 * will subscribe when it's built; this sprint only needs the signal to exist.
 */
class JobSlaAtRisk
{
    use Dispatchable;

    public function __construct(public readonly ServiceJob $job) {}
}

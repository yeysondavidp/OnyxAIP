<?php

namespace App\Listeners;

use App\Events\JobSlaAtRisk;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Bridges the Sprint 9 SLA event to the PM notification (US-13.2). */
class NotifyPmOfSlaAtRisk implements ShouldQueue
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    public function handle(JobSlaAtRisk $event): void
    {
        $this->notifications->slaWarning($event->job);
    }
}

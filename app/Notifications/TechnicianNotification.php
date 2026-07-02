<?php

namespace App\Notifications;

use App\Enums\EmailTemplateSlot;
use App\Mail\EmailTemplateMail;
use App\Notifications\Concerns\RendersEmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Reusable notification for the technician-facing types added in this epic —
 * JobReminder and LinkExpiryWarning (US-13.4). TECH_JOB_INVITATION keeps using
 * its own EPIC-09 mailer path (see JobInvitationService) — left untouched.
 *
 * Mail-only: technicians have no authenticated portal/bell in v1 (guest,
 * signed-URL access per US-01.4), so 'database' is never a channel here.
 */
class TechnicianNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersEmailTemplate;

    /**
     * @param  array<string, string>  $variables
     */
    public function __construct(
        public readonly EmailTemplateSlot $slot,
        public readonly array $variables,
        public readonly string $actionUrl,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): EmailTemplateMail
    {
        return $this->buildMail($notifiable, $this->slot, $this->variables, $this->actionUrl, 'Open job');
    }
}

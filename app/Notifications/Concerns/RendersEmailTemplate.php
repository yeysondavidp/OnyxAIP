<?php

namespace App\Notifications\Concerns;

use App\Enums\EmailTemplateSlot;
use App\Mail\EmailTemplateMail;
use App\Services\Emails\EmailTemplateRenderer;

/**
 * Shared toMail() body for PmNotification and TechnicianNotification (US-13.1-13.4).
 *
 * Returns the same EmailTemplateMail Mailable built in EPIC-16 rather than a
 * MailMessage — EmailTemplateRenderer's output is already HTML-escaped/sanitised
 * for raw HTML rendering (`{!! !!}` in emails/layout.blade.php); MailMessage's
 * markdown template would escape it a second time, mangling special characters.
 *
 * When toMail() returns a Mailable, Laravel does NOT auto-attach the recipient
 * the way it does for a MailMessage (Illuminate\Notifications\Channels\MailChannel
 * just calls `$message->send($mailer)` directly) — the address must be set here,
 * via routeNotificationFor('mail'), which works for both a real Notifiable model
 * (defaults to ->email) and Notification::route('mail', $address) callers.
 */
trait RendersEmailTemplate
{
    /**
     * @param  array<string, string>  $variables
     */
    protected function buildMail(
        object $notifiable,
        EmailTemplateSlot $slot,
        array $variables,
        string $actionUrl,
        string $actionLabel,
    ): EmailTemplateMail {
        $rendered = app(EmailTemplateRenderer::class)->render($slot, $variables);

        return (new EmailTemplateMail(
            subjectLine: $rendered['subject'],
            renderedBody: $rendered['body'],
            ctaUrl: $actionUrl,
            ctaLabel: $actionLabel,
        ))->to($notifiable->routeNotificationFor('mail', $this));
    }
}

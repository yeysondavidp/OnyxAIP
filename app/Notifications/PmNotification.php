<?php

namespace App\Notifications;

use App\Enums\EmailTemplateSlot;
use App\Enums\PlatformSettingKey;
use App\Mail\EmailTemplateMail;
use App\Notifications\Concerns\RendersEmailTemplate;
use App\Services\Emails\EmailTemplateRenderer;
use App\Services\Settings\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * One reusable notification class for every PM-facing notification type
 * (US-13.1/13.2/13.3, SRA §12.1). The type IS the EmailTemplateSlot — no
 * separate notification-type enum needed.
 *
 * Preference suppression happens once, here, in via() — the single point
 * Laravel's notification lifecycle already gives us for this (US-16.1's
 * disabled_notification_types setting) — rather than branching at every
 * call site in NotificationDispatcher.
 */
class PmNotification extends Notification implements ShouldQueue
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
        $disabled = app(PlatformSettings::class)->get(PlatformSettingKey::DisabledNotificationTypes->value, []);

        if (in_array($this->slot->value, $disabled, true)) {
            return [];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): EmailTemplateMail
    {
        return $this->buildMail($notifiable, $this->slot, $this->variables, $this->actionUrl, 'View details');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $rendered = app(EmailTemplateRenderer::class)->render($this->slot, $this->variables);

        return [
            'slot'    => $this->slot->value,
            'label'   => $this->slot->label(),
            'subject' => $rendered['subject'],
            'url'     => $this->actionUrl,
        ];
    }
}

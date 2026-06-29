<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ops alert for storage disk or Redis memory threshold breaches (US-00.7).
 * Delivered via the mail channel now; extend via()/toX() when §12 notification
 * infra is built to add in-app or Slack channels.
 */
class StorageAlertNotification extends Notification
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[ONYX AIP Alert] '.$this->subject)
            ->line($this->body)
            ->line('Please review the server and take action before service is impacted.');
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic sender for any PM-customised email template (US-16.2). Only used
 * once a slot has a saved EmailTemplate row — the default, un-customised
 * path for each slot keeps using its own purpose-built Mailable (e.g.
 * JobInvitationMail) unchanged.
 */
class EmailTemplateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $renderedBody,
        public readonly ?string $ctaUrl = null,
        public readonly string $ctaLabel = 'Open link',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.layout',
            with: [
                'subject'  => $this->subjectLine,
                'body'     => nl2br($this->renderedBody),
                'ctaUrl'   => $this->ctaUrl,
                'ctaLabel' => $this->ctaLabel,
            ],
        );
    }
}

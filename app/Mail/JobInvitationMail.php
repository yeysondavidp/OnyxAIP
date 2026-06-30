<?php

namespace App\Mail;

use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ServiceJob $job,
        public readonly TechnicianProfile $profile,
        public readonly string $invitationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Job invitation: {$this->job->job_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.job-invitation',
        );
    }
}

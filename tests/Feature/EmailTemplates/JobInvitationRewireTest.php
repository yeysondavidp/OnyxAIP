<?php

use App\Enums\EmailTemplateSlot;
use App\Mail\EmailTemplateMail;
use App\Mail\JobInvitationMail;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\JobInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('sends the original JobInvitationMail unchanged when no custom template exists', function () {
    Mail::fake();

    $pm      = User::factory()->pm()->create();
    $store   = Store::factory()->create();
    $job     = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)->draft()->create();
    $profile = TechnicianProfile::factory()->create();
    $job->technicians()->attach($profile->id, ['technician_status' => 'invited']);

    app(JobInvitationService::class)->invite($job, $profile, $pm);

    Mail::assertQueued(JobInvitationMail::class);
    Mail::assertNotQueued(EmailTemplateMail::class);
});

it('sends EmailTemplateMail with the pm-customised subject/body once the slot is customised', function () {
    Mail::fake();

    EmailTemplate::create([
        'slot'    => EmailTemplateSlot::JobInvitation->value,
        'subject' => 'Custom: {{job_name}}',
        'body'    => 'Hi {{technician_name}}, open {{signed_url}}.',
    ]);

    $pm    = User::factory()->pm()->create();
    $store = Store::factory()->create();
    $job   = ServiceJob::factory()->forClient(Client::find($store->client_id), $store)
        ->draft()->create(['job_name' => 'Screen Repair']);
    $profile = TechnicianProfile::factory()->create(['name' => 'Sneider Ruiz']);
    $job->technicians()->attach($profile->id, ['technician_status' => 'invited']);

    app(JobInvitationService::class)->invite($job, $profile, $pm);

    Mail::assertNotQueued(JobInvitationMail::class);
    Mail::assertQueued(EmailTemplateMail::class, function (EmailTemplateMail $mail) {
        return $mail->subjectLine === 'Custom: Screen Repair'
            && str_contains($mail->renderedBody, 'Sneider Ruiz')
            && $mail->ctaUrl !== null;
    });
});

<?php

use App\Enums\EmailTemplateSlot;
use App\Models\EmailTemplate;
use App\Services\Emails\EmailTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('falls back to the slot default when no template row exists', function () {
    $rendered = app(EmailTemplateRenderer::class)->render(
        EmailTemplateSlot::JobInvitation,
        EmailTemplateSlot::JobInvitation->dummyVariables(),
    );

    expect($rendered['subject'])->toBe('Job invitation: Quarterly Screen Maintenance');
    expect($rendered['body'])->toContain('Michael Chen');
});

it('substitutes only allow-listed variables', function () {
    EmailTemplate::create([
        'slot'    => EmailTemplateSlot::PmSlaBreached->value,
        'subject' => 'Breach on {{job_reference}}',
        'body'    => '{{store_name}} — {{client_name}} — {{job_url}}',
    ]);

    $rendered = app(EmailTemplateRenderer::class)->render(EmailTemplateSlot::PmSlaBreached, [
        'job_reference' => 'JOB-0099',
        'store_name'    => 'Sephora Bondi Junction',
        'client_name'   => 'Sephora Australia',
        'job_url'       => 'https://example.test/jobs/99',
    ]);

    expect($rendered['subject'])->toBe('Breach on JOB-0099');
    expect($rendered['body'])->toBe('Sephora Bondi Junction — Sephora Australia — https://example.test/jobs/99');
});

it('html-escapes every substituted variable value — xss attempt', function () {
    EmailTemplate::create([
        'slot'    => EmailTemplateSlot::PmSlaBreached->value,
        'subject' => 'Breach on {{job_reference}}',
        'body'    => 'Store: {{store_name}}',
    ]);

    $rendered = app(EmailTemplateRenderer::class)->render(EmailTemplateSlot::PmSlaBreached, [
        'job_reference' => 'JOB-0099',
        'store_name'    => '<script>alert(1)</script>',
        'client_name'   => 'Sephora Australia',
        'job_url'       => 'https://example.test/jobs/99',
    ]);

    expect($rendered['body'])->not->toContain('<script>');
    expect($rendered['body'])->toContain('&lt;script&gt;');
});

it('leaves an unresolved allow-listed variable as an empty string, not the raw token', function () {
    EmailTemplate::create([
        'slot'    => EmailTemplateSlot::PmSlaBreached->value,
        'subject' => 'Breach on {{job_reference}}',
        'body'    => 'Store: {{store_name}}',
    ]);

    $rendered = app(EmailTemplateRenderer::class)->render(EmailTemplateSlot::PmSlaBreached, [
        'job_reference' => 'JOB-0099',
        // store_name intentionally omitted
    ]);

    expect($rendered['body'])->toBe('Store: ');
});

<?php

use App\Enums\EmailTemplateSlot;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('pm can view the email templates index', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->get(route('email-templates.index'))
        ->assertOk()
        ->assertSee('Using default');
});

it('technician cannot view the email templates index', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->get(route('email-templates.index'))
        ->assertForbidden();
});

it('index shows customised badge once a template is saved', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)->patch(route('email-templates.update', EmailTemplateSlot::JobInvitation->value), [
        'subject' => 'Custom subject {{job_name}}',
        'body'    => 'Custom body {{signed_url}}',
    ]);

    $this->actingAs($pm)
        ->get(route('email-templates.index'))
        ->assertOk()
        ->assertSee('Customised');
});

it('pm can save a valid custom template', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)
        ->patch(route('email-templates.update', EmailTemplateSlot::JobInvitation->value), [
            'subject' => 'Your visit: {{job_name}}',
            'body'    => 'Hi {{technician_name}}, see {{signed_url}} for details.',
        ])
        ->assertRedirect(route('email-templates.index'));

    $this->assertDatabaseHas('email_templates', [
        'slot'    => EmailTemplateSlot::JobInvitation->value,
        'subject' => 'Your visit: {{job_name}}',
    ]);
});

it('strips html tags from subject and body before storing', function () {
    $pm = User::factory()->pm()->create();

    $this->actingAs($pm)->patch(route('email-templates.update', EmailTemplateSlot::JobInvitation->value), [
        'subject' => '<b>Bold</b> subject {{job_name}}',
        'body'    => '<script>alert(1)</script>Body with {{signed_url}}',
    ]);

    $template = EmailTemplate::where('slot', EmailTemplateSlot::JobInvitation->value)->first();

    expect($template->subject)->not->toContain('<b>');
    expect($template->body)->not->toContain('<script>');
});

it('rejects a variable that is not on the slot allow-list, naming it in the error', function () {
    $pm = User::factory()->pm()->create();

    $response = $this->actingAs($pm)->patch(route('email-templates.update', EmailTemplateSlot::JobInvitation->value), [
        'subject' => 'Subject {{job_name}}',
        'body'    => 'Body {{signed_url}} {{password_reset_token}}',
    ]);

    $response->assertSessionHasErrors('body');
    expect(session('errors')->first('body'))->toContain('password_reset_token');
});

it('soft-blocks a save missing a required variable until confirmed', function () {
    $pm = User::factory()->pm()->create();

    // signed_url is required for JobInvitation and is omitted here.
    $this->actingAs($pm)
        ->patch(route('email-templates.update', EmailTemplateSlot::JobInvitation->value), [
            'subject' => 'Subject {{job_name}}',
            'body'    => 'Body with no link',
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('email_templates', ['slot' => EmailTemplateSlot::JobInvitation->value]);

    // Confirming the override saves it and logs the audit note.
    $this->actingAs($pm)
        ->patch(route('email-templates.update', EmailTemplateSlot::JobInvitation->value), [
            'subject'                  => 'Subject {{job_name}}',
            'body'                     => 'Body with no link',
            'confirm_missing_required' => '1',
        ])
        ->assertRedirect(route('email-templates.index'));

    $this->assertDatabaseHas('email_templates', ['slot' => EmailTemplateSlot::JobInvitation->value]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'email_template.required_var_override']);
});

it('preview renders with dummy data only, never a real technician or job record', function () {
    $pm = User::factory()->pm()->create();

    $response = $this->actingAs($pm)->get(route('email-templates.preview', EmailTemplateSlot::JobInvitation->value));

    $response->assertOk()->assertSee('Michael Chen'); // the slot's dummy technician name
});

it('technician cannot save an email template', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->patch(route('email-templates.update', EmailTemplateSlot::JobInvitation->value), [
            'subject' => 'Subject',
            'body'    => 'Body',
        ])
        ->assertForbidden();
});

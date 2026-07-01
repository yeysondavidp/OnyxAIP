<?php

namespace App\Services\Emails;

use App\Enums\EmailTemplateSlot;
use App\Models\EmailTemplate;

/**
 * Renders a template slot's subject/body with safe, allow-listed variable
 * substitution (US-16.2). Every substituted value is HTML-escaped and only
 * tokens on the slot's own allow-list are replaced — plain str_replace() over
 * a known list, never eval() or a dynamic template engine (Engineering Bar:
 * Secure). Falls back to the slot's built-in default when no PM customisation
 * has been saved.
 */
class EmailTemplateRenderer
{
    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body: string}
     */
    public function render(EmailTemplateSlot $slot, array $variables): array
    {
        $template = EmailTemplate::where('slot', $slot->value)->first();

        $subject = $template === null ? $slot->defaultSubject() : ($template->subject ?? $slot->defaultSubject());
        $body    = $template === null ? $slot->defaultBody() : ($template->body ?? $slot->defaultBody());

        foreach (array_keys($slot->allowedVariables()) as $key) {
            $value   = e((string) ($variables[$key] ?? ''));
            $token   = '{{'.$key.'}}';
            $subject = str_replace($token, $value, $subject);
            $body    = str_replace($token, $value, $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }
}

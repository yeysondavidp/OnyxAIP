<?php

namespace App\Http\Requests;

use App\Enums\EmailTemplateSlot;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', EmailTemplate::class);
    }

    /**
     * Strip HTML before the length rules run — otherwise a wall of markup
     * could pad past the max-length check while decoding to something short.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => trim(strip_tags((string) $this->input('subject'))),
            'body'    => trim(strip_tags((string) $this->input('body'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'subject'                  => ['required', 'string', 'max:200', $this->disallowedVariableRule()],
            'body'                     => ['required', 'string', 'max:5000', $this->disallowedVariableRule()],
            'confirm_missing_required' => ['sometimes', 'boolean'],
        ];
    }

    private function disallowedVariableRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            /** @var EmailTemplateSlot $slot */
            $slot    = $this->route('slot');
            $allowed = array_keys($slot->allowedVariables());

            preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', (string) $value, $matches);

            foreach (array_unique($matches[1]) as $name) {
                if (! in_array($name, $allowed, true)) {
                    $fail('The variable "{{'.$name.'}}" is not available for this template.');

                    return;
                }
            }
        };
    }
}

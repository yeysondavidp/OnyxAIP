<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('client'));
    }

    public function rules(): array
    {
        $clientId = $this->route('client')?->id;

        return [
            'client_name'     => ['required', 'string', 'max:255'],
            'client_code'     => ['required', 'string', 'max:10', 'alpha_dash', 'uppercase', Rule::unique('clients', 'client_code')->ignore($clientId)],
            'primary_contact' => ['nullable', 'string', 'max:255'],
            'primary_email'   => ['nullable', 'email', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:5000'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_code.unique'     => 'Client code is already in use.',
            'client_code.alpha_dash' => 'Client code may only contain letters, numbers, dashes, and underscores.',
            'client_code.uppercase'  => 'Client code must be uppercase.',
        ];
    }
}

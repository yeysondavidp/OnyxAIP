<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Client::class);
    }

    public function rules(): array
    {
        return [
            'client_name'     => ['required', 'string', 'max:255'],
            'client_code'     => ['required', 'string', 'max:10', 'alpha_dash', 'uppercase', 'unique:clients,client_code'],
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

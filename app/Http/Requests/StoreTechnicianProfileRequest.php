<?php

namespace App\Http\Requests;

use App\Enums\TechnicianSpecialty;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTechnicianProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TechnicianProfile::class);
    }

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:255'],
            'email'                  => ['required', 'email', 'max:255'],
            'phone'                  => ['nullable', 'string', 'max:30'],
            'specialty_categories'   => ['nullable', 'array'],
            'specialty_categories.*' => [Rule::enum(TechnicianSpecialty::class)],
            'certifications'         => ['nullable', 'array'],
            'certifications.*'       => ['string', 'max:100'],
            'preferred_client_ids'   => ['nullable', 'array'],
            'preferred_client_ids.*' => ['integer', Rule::exists('clients', 'id')],
            'asset_competency'       => ['nullable', 'string', 'max:1000'],
            'is_active'              => ['sometimes', 'boolean'],
            // Optional link to a technician user account
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Ensure linked user has the technician role
            $userId = $this->input('user_id');
            if ($userId) {
                $user = User::find($userId);
                if ($user && ! $user->isTechnician()) {
                    $v->errors()->add('user_id', 'Only users with the Technician role can be linked to a profile.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'specialty_categories.*.enum' => 'One or more specialty values are not valid.',
        ];
    }
}

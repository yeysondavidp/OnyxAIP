<?php

namespace App\Http\Requests;

use App\Enums\AustralianState;
use App\Enums\StoreType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('store'));
    }

    public function rules(): array
    {
        $storeId = $this->route('store')?->id;

        return [
            'store_name'          => ['required', 'string', 'max:255'],
            'store_code'          => ['required', 'string', 'max:20', Rule::unique('stores', 'store_code')->ignore($storeId)],
            'store_type'          => ['required', Rule::enum(StoreType::class)],
            'address_line1'       => ['required', 'string', 'max:255'],
            'suburb'              => ['required', 'string', 'max:100'],
            'state'               => ['required', Rule::enum(AustralianState::class)],
            'postcode'            => ['required', 'string', 'max:10'],
            'country'             => ['sometimes', 'string', 'max:60'],
            'store_timezone'      => ['required', 'timezone:all'],
            'store_manager_name'  => ['nullable', 'string', 'max:255'],
            'store_manager_phone' => ['nullable', 'string', 'max:30'],
            'store_manager_email' => ['nullable', 'email', 'max:255'],
            'notes'               => ['nullable', 'string', 'max:5000'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_code.unique'       => 'Store code is already in use.',
            'store_timezone.timezone' => 'Please select a valid timezone.',
            'state.enum'              => 'Please select a valid Australian state or territory.',
            'store_type.enum'         => 'Please select a valid store type.',
        ];
    }
}

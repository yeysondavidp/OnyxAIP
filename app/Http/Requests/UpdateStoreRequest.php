<?php

namespace App\Http\Requests;

use App\Enums\Country;
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
        $store   = $this->route('store');
        $storeId = $store?->id;

        // Falls back to the store's current country (not just Australia) when
        // the form omits it, so an untouched country doesn't get re-validated
        // against the wrong region list on a partial update (US-03.5).
        $country     = Country::tryFrom((string) $this->input('country')) ?? $store->country ?? Country::Australia;
        $stateValues = array_map(fn ($region) => $region->value, $country->regions());

        return [
            'store_name'          => ['required', 'string', 'max:255'],
            'store_code'          => ['required', 'string', 'max:20', Rule::unique('stores', 'store_code')->ignore($storeId)],
            'store_type'          => ['required', Rule::enum(StoreType::class)],
            'address_line1'       => ['required', 'string', 'max:255'],
            'suburb'              => ['required', 'string', 'max:100'],
            'country'             => ['sometimes', Rule::enum(Country::class)],
            'state'               => ['required', Rule::in($stateValues)],
            'postcode'            => ['required', 'string', 'max:10'],
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
            'state.in'                => 'Please select a valid state or region for the selected country.',
            'country.enum'            => 'Please select a valid country.',
            'store_type.enum'         => 'Please select a valid store type.',
        ];
    }
}

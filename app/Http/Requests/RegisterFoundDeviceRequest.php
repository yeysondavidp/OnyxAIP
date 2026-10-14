<?php

namespace App\Http\Requests;

use App\Enums\AssetType;
use App\Enums\Orientation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A device a technician finds on site (US-21.1). Only the type and one photo are required — a
 * technician may not be able to read everything; the PM completes the rest when verifying.
 * Access is checked by the signed-URL middleware and JobFlowController::resolveIdentity().
 */
class RegisterFoundDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_type'         => ['required', Rule::enum(AssetType::class)],
            'location_notes'     => ['nullable', 'string', 'max:500'],
            'asset_name'         => ['nullable', 'string', 'max:255'],
            'manufacturer'       => ['nullable', 'string', 'max:255'],
            'model'              => ['nullable', 'string', 'max:255'],
            'serial_number'      => ['nullable', 'string', 'max:100'],
            'mac_address'        => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/'],
            'screen_size_inches' => ['nullable', 'numeric', 'min:1', 'max:999'],
            'orientation'        => ['nullable', Rule::enum(Orientation::class)],
            'photo'              => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'confirm_new'        => ['sometimes', 'boolean'],
            // Token for the label read this server stored (US-21.2) — provenance, never values.
            'ai_token' => ['nullable', 'string', 'alpha_num', 'size:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'asset_type.required' => 'Choose what kind of device it is.',
            'photo.required'      => 'Take at least one photo of the device or its label.',
            'photo.image'         => 'The photo must be a picture (JPG, PNG or WebP).',
            'mac_address.regex'   => 'The MAC address looks like 00:CE:39:D8:2A:51.',
        ];
    }
}

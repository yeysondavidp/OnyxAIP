<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\ContentChangeFrequency;
use App\Enums\LightType;
use App\Enums\Orientation;
use App\Enums\PlayerType;
use App\Enums\TotemSuppliedBy;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset && $this->user()->can('update', $asset);
    }

    public function rules(): array
    {
        /** @var Asset $asset */
        $asset = $this->route('asset');

        $type     = $this->input('asset_type');
        $clientId = $this->input('client_id');

        $isScreen   = $type === AssetType::DigitalScreen->value;
        $isPlayer   = $type === AssetType::MediaPlayer->value;
        $isLightbox = $type === AssetType::Lightbox->value;

        return [
            // ── Base fields ────────────────────────────────────────────────
            'asset_code'      => ['required', 'string', 'max:40', Rule::unique('assets', 'asset_code')->ignore($asset->id)],
            'asset_type'      => ['required', Rule::enum(AssetType::class)],
            'client_id'       => ['required', 'integer', 'exists:clients,id'],
            'store_id'        => ['required', 'integer', Rule::exists('stores', 'id')->where('client_id', $clientId)],
            'asset_name'      => ['required', 'string', 'max:255'],
            'manufacturer'    => ['required', 'string', 'max:255'],
            'model'           => ['required', 'string', 'max:255'],
            'serial_number'   => ['nullable', 'string', 'max:100'],
            'purchase_date'   => ['nullable', 'date'],
            'warranty_expiry' => ['nullable', 'date'],
            'install_date'    => ['nullable', 'date'],
            'asset_status'    => ['required', Rule::enum(AssetStatus::class)],
            'location_notes'  => ['nullable', 'string', 'max:500'],
            'parent_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('client_id', $clientId)],
            'notes'           => ['nullable', 'string', 'max:5000'],

            // ── Digital Screen ─────────────────────────────────────────────
            'screen_size_inches' => [$isScreen ? 'required' : 'nullable', 'numeric', 'min:1', 'max:999'],
            'resolution_width'   => [$isScreen ? 'required' : 'nullable', 'integer', 'min:1'],
            'resolution_height'  => [$isScreen ? 'required' : 'nullable', 'integer', 'min:1'],
            'orientation'        => [$isScreen ? 'required' : 'nullable', Rule::enum(Orientation::class)],
            'mount_type'         => [$isScreen ? 'required' : 'nullable', 'string', 'max:100'],
            'totem_supplied_by'  => [$isScreen ? 'required' : 'nullable', Rule::enum(TotemSuppliedBy::class)],

            // ── Media Player ───────────────────────────────────────────────
            'player_type'      => [$isPlayer ? 'required' : 'nullable', Rule::enum(PlayerType::class)],
            'cms_platform'     => ['nullable', 'string', 'max:100'],
            'ip_address'       => ['nullable', 'ip'],
            'mac_address'      => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/'],
            'firmware_version' => ['nullable', 'string', 'max:100'],

            // ── Lightbox ───────────────────────────────────────────────────
            'lightbox_dimensions'      => [$isLightbox ? 'required' : 'nullable', 'string', 'max:100'],
            'light_type'               => [$isLightbox ? 'required' : 'nullable', Rule::enum(LightType::class)],
            'content_change_frequency' => [$isLightbox ? 'required' : 'nullable', Rule::enum(ContentChangeFrequency::class)],

            // ── Infrastructure ─────────────────────────────────────────────
            'cable_type'              => ['nullable', 'string', 'max:100'],
            'length'                  => ['nullable', 'numeric', 'min:0'],
            'connected_from_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('client_id', $clientId)],
            'connected_to_asset_id'   => ['nullable', 'integer', Rule::exists('assets', 'id')->where('client_id', $clientId)],

            // ── Window Fixture ─────────────────────────────────────────────
            'fixture_dimensions' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'asset_code.unique'      => 'This asset code is already in use.',
            'store_id.exists'        => 'The selected store does not belong to the chosen client.',
            'parent_asset_id.exists' => 'The parent asset does not exist within the chosen client.',
            'mac_address.regex'      => 'MAC address must be in the format AA:BB:CC:DD:EE:FF.',
            'ip_address.ip'          => 'Please enter a valid IP address.',
        ];
    }
}

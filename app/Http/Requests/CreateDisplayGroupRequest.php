<?php

namespace App\Http\Requests;

use App\Enums\AssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDisplayGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy check is in the controller; request only validates inputs.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $storeId = $this->route('store')?->id;

        return [
            'group_name' => ['required', 'string', 'max:255'],

            'player_asset_id' => [
                'required',
                Rule::exists('assets', 'id')->where(fn ($q) => $q
                    ->where('asset_type', AssetType::MediaPlayer->value)
                    ->where('store_id', $storeId)
                    ->whereNotIn('id', function ($q2) {
                        $q2->select('player_asset_id')->from('display_groups')->whereNull('deleted_at');
                    })
                ),
            ],

            'screen_asset_ids'   => ['required', 'array', 'min:1'],
            'screen_asset_ids.*' => [
                'required',
                Rule::exists('assets', 'id')->where(fn ($q) => $q
                    ->where('asset_type', AssetType::DigitalScreen->value)
                    ->where('store_id', $storeId)
                    ->whereNotIn('id', function ($q2) {
                        $q2->select('asset_id')->from('display_group_screens')
                            ->join('display_groups', 'display_groups.id', '=', 'display_group_screens.display_group_id')
                            ->whereNull('display_groups.deleted_at');
                    })
                ),
            ],

            'layout_description' => ['nullable', 'string', 'max:500'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'player_asset_id.exists'    => 'The selected player is invalid, already assigned to another group, or does not belong to this store.',
            'screen_asset_ids.*.exists' => 'One or more selected screens are invalid, already assigned to another group, or do not belong to this store.',
            'screen_asset_ids.required' => 'At least one screen must be selected.',
            'screen_asset_ids.min'      => 'At least one screen must be selected.',
        ];
    }
}

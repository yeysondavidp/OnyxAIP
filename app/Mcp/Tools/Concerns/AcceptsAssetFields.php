<?php

namespace App\Mcp\Tools\Concerns;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\ContentChangeFrequency;
use App\Enums\LightType;
use App\Enums\Orientation;
use App\Enums\PlayerType;
use App\Enums\TotemSuppliedBy;
use App\Models\Asset;
use App\Models\Store;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

/**
 * Asset form fields as MCP tool input (US-20.3). Agents address stores and
 * related assets by code; router passwords can only be set in the web app.
 */
trait AcceptsAssetFields
{
    /** Fields an agent may send. Router passwords are deliberately absent. */
    private const AGENT_ASSET_FIELDS = [
        'asset_code', 'asset_type', 'store_code', 'asset_name', 'manufacturer', 'model',
        'serial_number', 'purchase_date', 'warranty_expiry', 'install_date', 'asset_status',
        'location_notes', 'notes',
        'screen_size_inches', 'resolution_width', 'resolution_height', 'orientation', 'mount_type', 'totem_supplied_by',
        'player_type', 'cms_platform', 'ip_address', 'mac_address', 'firmware_version',
        'lightbox_dimensions', 'light_type', 'content_change_frequency',
        'cable_type', 'length', 'connected_from_asset_code', 'connected_to_asset_code',
        'imei', 'wifi_ssid', 'sim_carrier', 'sim_number',
        'fixture_dimensions',
    ];

    /**
     * Agent input → asset form input: codes become ids, secrets are refused.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function assetFormInput(Request $request): array
    {
        foreach (['wifi_password', 'admin_password'] as $secret) {
            if ($request->get($secret) !== null) {
                throw ValidationException::withMessages([$secret => 'Router passwords can only be set by a PM in the web app.']);
            }
        }

        $input = array_intersect_key($request->all(), array_flip(self::AGENT_ASSET_FIELDS));

        if (array_key_exists('store_code', $input)) {
            $store = Store::where('store_code', $input['store_code'])->first();

            if ($store === null) {
                throw ValidationException::withMessages(['store_code' => "No store with code {$input['store_code']} is visible to this access key."]);
            }

            $input['store_id']  = $store->id;
            $input['client_id'] = $store->client_id;
            unset($input['store_code']);
        }

        foreach (['connected_from_asset_code' => 'connected_from_asset_id', 'connected_to_asset_code' => 'connected_to_asset_id'] as $codeKey => $idKey) {
            if (! array_key_exists($codeKey, $input)) {
                continue;
            }

            $input[$idKey] = $input[$codeKey] === null ? null : Asset::where('asset_code', $input[$codeKey])->value('id');

            if ($input[$codeKey] !== null && $input[$idKey] === null) {
                throw ValidationException::withMessages([$codeKey => "No asset with code {$input[$codeKey]} is visible to this access key."]);
            }

            unset($input[$codeKey]);
        }

        return $input;
    }

    /** @return array<string, Type> */
    private function assetFieldSchema(JsonSchema $schema): array
    {
        $enum = fn (string $class) => array_column($class::cases(), 'value');

        return [
            'asset_name'                => $schema->string(),
            'manufacturer'              => $schema->string()->description('e.g. "Beat", "Samsung". Use "Unknown" only when the label is not readable.'),
            'model'                     => $schema->string()->description('e.g. "MIB 19", "QH98C".'),
            'serial_number'             => $schema->string(),
            'purchase_date'             => $schema->string()->format('date'),
            'warranty_expiry'           => $schema->string()->format('date'),
            'install_date'              => $schema->string()->format('date'),
            'location_notes'            => $schema->string()->description('Where it is in the store, max 500 characters.'),
            'notes'                     => $schema->string()->description('Internal notes, e.g. where the information came from.'),
            'screen_size_inches'        => $schema->number()->description('Digital screens: required.'),
            'resolution_width'          => $schema->integer()->description('Digital screens: required, pixels.'),
            'resolution_height'         => $schema->integer()->description('Digital screens: required, pixels.'),
            'orientation'               => $schema->string()->enum($enum(Orientation::class)),
            'mount_type'                => $schema->string()->description('Digital screens: required, e.g. "Floor Totem", "Wall Mount".'),
            'totem_supplied_by'         => $schema->string()->enum($enum(TotemSuppliedBy::class)),
            'player_type'               => $schema->string()->enum($enum(PlayerType::class))->description('Media players: required.'),
            'cms_platform'              => $schema->string()->description('e.g. "BEAT CMS".'),
            'ip_address'                => $schema->string(),
            'mac_address'               => $schema->string()->description('Format 00:CE:39:D8:2A:51.'),
            'firmware_version'          => $schema->string(),
            'lightbox_dimensions'       => $schema->string(),
            'light_type'                => $schema->string()->enum($enum(LightType::class)),
            'content_change_frequency'  => $schema->string()->enum($enum(ContentChangeFrequency::class)),
            'cable_type'                => $schema->string(),
            'length'                    => $schema->number(),
            'connected_from_asset_code' => $schema->string()->description('Infrastructure: asset code it is connected from.'),
            'connected_to_asset_code'   => $schema->string()->description('Infrastructure: asset code it is connected to.'),
            'imei'                      => $schema->string(),
            'wifi_ssid'                 => $schema->string(),
            'sim_carrier'               => $schema->string(),
            'sim_number'                => $schema->string(),
            'fixture_dimensions'        => $schema->string(),
            'dry_run'                   => $schema->boolean()->description('true = validate and show what would change, without saving. Use it first.'),
        ];
    }

    /** @return list<string> */
    private static function assetTypeValues(): array
    {
        return array_column(AssetType::cases(), 'value');
    }

    /** @return list<string> */
    private static function assetStatusValues(): array
    {
        return array_column(AssetStatus::selectable(), 'value');
    }
}

<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Services\QrCodeService;
use App\Traits\Auditable;
use App\Traits\ClientScoped;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property AssetType $asset_type
 * @property AssetStatus $asset_status
 */
class Asset extends BaseModel
{
    /** @use HasFactory<AssetFactory> */
    use Auditable, ClientScoped, HasFactory;

    protected $fillable = [
        'asset_code',
        'asset_type',
        'client_id',
        'store_id',
        'asset_name',
        'manufacturer',
        'model',
        'serial_number',
        'purchase_date',
        'warranty_expiry',
        'install_date',
        'asset_status',
        'location_notes',
        'parent_asset_id',
        'notes',
        'qr_code_path',
    ];

    protected function casts(): array
    {
        return [
            'asset_type'      => AssetType::class,
            'asset_status'    => AssetStatus::class,
            'purchase_date'   => 'date',
            'warranty_expiry' => 'date',
            'install_date'    => 'date',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_asset_id');
    }

    public function screenDetail(): HasOne
    {
        return $this->hasOne(AssetScreenDetail::class);
    }

    public function playerDetail(): HasOne
    {
        return $this->hasOne(AssetPlayerDetail::class);
    }

    public function lightboxDetail(): HasOne
    {
        return $this->hasOne(AssetLightboxDetail::class);
    }

    public function infrastructureDetail(): HasOne
    {
        return $this->hasOne(AssetInfrastructureDetail::class);
    }

    public function windowFixtureDetail(): HasOne
    {
        return $this->hasOne(AssetWindowFixtureDetail::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(AssetHistory::class)->orderByDesc('transitioned_at');
    }

    // ── QR code ────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Generate QR code after the asset record is first persisted (US-07.1).
        // Runs outside the main createWithDetail() transaction — failure is non-fatal;
        // the QR can be regenerated on demand via QrCodeService::generateForAsset().
        static::created(function (self $asset) {
            try {
                app(QrCodeService::class)->generateForAsset($asset);
            } catch (\Throwable) {
                // Non-fatal — asset is usable without the QR image.
            }
        });
    }

    // ── Detail helpers ─────────────────────────────────────────────────────────

    /**
     * Create this asset together with its type-specific detail row in one transaction.
     *
     * @param  array<string, mixed>  $baseData
     * @param  array<string, mixed>  $detailData
     */
    public static function createWithDetail(array $baseData, array $detailData): self
    {
        DB::beginTransaction();
        try {
            $asset = static::create($baseData);
            $asset->createDetail($detailData);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $asset;
    }

    /**
     * Update this asset and replace its type-specific detail row in one transaction.
     *
     * @param  array<string, mixed>  $baseData
     * @param  array<string, mixed>  $detailData
     */
    public function updateWithDetail(array $baseData, array $detailData): void
    {
        DB::beginTransaction();
        try {
            $this->update($baseData);
            $this->deleteAllDetailRows();
            $this->createDetail($detailData);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create the type-specific detail row from the supplied data.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDetail(array $data): void
    {
        $type = $this->asset_type;

        match ($type) {
            AssetType::DigitalScreen => $this->screenDetail()->create([
                'screen_size_inches' => $data['screen_size_inches'],
                'resolution_width'   => $data['resolution_width'],
                'resolution_height'  => $data['resolution_height'],
                'orientation'        => $data['orientation'],
                'mount_type'         => $data['mount_type'],
                'totem_supplied_by'  => $data['totem_supplied_by'],
            ]),
            AssetType::MediaPlayer => $this->playerDetail()->create([
                'player_type'      => $data['player_type'],
                'cms_platform'     => $data['cms_platform']     ?? null,
                'ip_address'       => $data['ip_address']       ?? null,
                'mac_address'      => $data['mac_address']      ?? null,
                'firmware_version' => $data['firmware_version'] ?? null,
            ]),
            AssetType::Lightbox => $this->lightboxDetail()->create([
                'lightbox_dimensions'      => $data['lightbox_dimensions'],
                'light_type'               => $data['light_type'],
                'content_change_frequency' => $data['content_change_frequency'],
            ]),
            AssetType::Infrastructure => $this->infrastructureDetail()->create([
                'cable_type'              => $data['cable_type']              ?? null,
                'length'                  => $data['length']                  ?? null,
                'connected_from_asset_id' => $data['connected_from_asset_id'] ?? null,
                'connected_to_asset_id'   => $data['connected_to_asset_id']   ?? null,
            ]),
            AssetType::WindowFixture => $this->windowFixtureDetail()->create([
                'fixture_dimensions' => $data['fixture_dimensions'] ?? null,
            ]),
        };
    }

    private function deleteAllDetailRows(): void
    {
        $this->screenDetail()->delete();
        $this->playerDetail()->delete();
        $this->lightboxDetail()->delete();
        $this->infrastructureDetail()->delete();
        $this->windowFixtureDetail()->delete();
    }

    /**
     * Eager-load the correct detail relation for the asset's type.
     *
     * @param  Collection<int, static>  $assets
     */
    public static function loadTypeDetails(Collection $assets): void
    {
        $byType = $assets->groupBy(fn (self $a) => $a->asset_type->value);

        foreach ($byType as $typeValue => $group) {
            $relation = match (AssetType::from((string) $typeValue)) {
                AssetType::DigitalScreen  => 'screenDetail',
                AssetType::MediaPlayer    => 'playerDetail',
                AssetType::Lightbox       => 'lightboxDetail',
                AssetType::Infrastructure => 'infrastructureDetail',
                AssetType::WindowFixture  => 'windowFixtureDetail',
            };
            $group->first()->newCollection($group->all())->load($relation);
        }
    }
}

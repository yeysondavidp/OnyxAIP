<?php

namespace App\Models;

use App\Casts\RegionCast;
use App\Enums\Country;
use App\Enums\Region;
use App\Enums\StoreType;
use App\Traits\Auditable;
use App\Traits\ClientScoped;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Region $state
 * @property Country $country
 * @property StoreType $store_type
 */
class Store extends BaseModel
{
    /** @use HasFactory<StoreFactory> */
    use Auditable, ClientScoped, HasFactory;

    protected $fillable = [
        'client_id',
        'store_name',
        'store_code',
        'store_type',
        'address_line1',
        'suburb',
        'state',
        'postcode',
        'country',
        'store_timezone',
        'store_manager_name',
        'store_manager_phone',
        'store_manager_email',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'store_type' => StoreType::class,
            'country'    => Country::class,
            'state'      => RegionCast::class,
            'is_active'  => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Build a unique store code from the client's code and suburb, e.g. "PAN-SYD-001".
     * Used when the PM leaves the Store Code field blank on creation.
     */
    public static function generateCode(Client $client, string $suburb): string
    {
        $prefix     = strtoupper($client->client_code);
        $suburbCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $suburb) ?? '', 0, 3));
        $suburbCode = $suburbCode !== '' ? $suburbCode : 'GEN';

        $sequence = 1;
        do {
            $candidate = sprintf('%s-%s-%03d', $prefix, $suburbCode, $sequence);
            $sequence++;
        } while (self::where('store_code', $candidate)->exists());

        return $candidate;
    }
}

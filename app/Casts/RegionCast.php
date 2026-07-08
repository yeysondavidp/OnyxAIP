<?php

namespace App\Casts;

use App\Enums\AustralianState;
use App\Enums\Country;
use App\Enums\NewZealandRegion;
use App\Enums\Region;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts `stores.state` to the enum matching the store's own `country` —
 * AustralianState for Australia, NewZealandRegion for New Zealand (US-03.5).
 * A single string column can't carry two different native enum casts, so
 * this reads the sibling `country` attribute to pick the right one; every
 * existing consumer of `$store->state->value` / `->label()` keeps working
 * unchanged since both enums implement Region.
 *
 * @implements CastsAttributes<Region|null, Region|string|null>
 */
class RegionCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Region
    {
        if ($value === null) {
            return null;
        }

        $country = Country::tryFrom((string) ($attributes['country'] ?? '')) ?? Country::Australia;

        return match ($country) {
            Country::Australia  => AustralianState::from($value),
            Country::NewZealand => NewZealandRegion::from($value),
        };
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof Region ? $value->value : $value;
    }
}

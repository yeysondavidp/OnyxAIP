<?php

namespace App\Scopes;

use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Devices found on site and not yet verified by a PM are invisible to every asset query —
 * lists, dashboards, reports, alerts, agents (D7, EPIC-21). Only the places that must see
 * them opt out explicitly via Asset::pendingDevices().
 */
class VerifiedAssetScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('asset_status'), '!=', AssetStatus::PendingVerification->value);
    }
}

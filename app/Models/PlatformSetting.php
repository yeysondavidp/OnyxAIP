<?php

namespace App\Models;

use Database\Factories\PlatformSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Read-side model only — writes go through App\Services\Settings\PlatformSettings::set(),
 * which upserts via DB::table() (not Eloquent) so the caller can write one explicit,
 * per-key audit entry instead of a generic model-level diff (US-16.1). No Auditable trait
 * here: an Eloquent event that can never fire is dead weight, not defence in depth.
 */
class PlatformSetting extends BaseModel
{
    /** @use HasFactory<PlatformSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'setting_key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}

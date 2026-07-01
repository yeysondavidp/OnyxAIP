<?php

namespace App\Models;

use App\Enums\MonitoringCoverage;
use App\Traits\Auditable;
use Database\Factories\SlaProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property MonitoringCoverage $monitoring_coverage
 */
class SlaProfile extends BaseModel
{
    /** @use HasFactory<SlaProfileFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'acknowledgement_hours',
        'onsite_response_metro_hours',
        'onsite_response_regional_hours',
        'resolution_hours',
        'monitoring_coverage',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'acknowledgement_hours'          => 'integer',
            'onsite_response_metro_hours'    => 'integer',
            'onsite_response_regional_hours' => 'integer',
            'resolution_hours'               => 'integer',
            'monitoring_coverage'            => MonitoringCoverage::class,
            'is_active'                      => 'boolean',
        ];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}

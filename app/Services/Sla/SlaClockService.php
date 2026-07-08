<?php

namespace App\Services\Sla;

use App\Enums\Country;
use App\Enums\PlatformSettingKey;
use App\Enums\SlaStatus;
use App\Models\Client;
use App\Models\Store;
use App\Services\Settings\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the SLA clock columns for a new fault job (US-12.2). One entry
 * point reused by ServiceJobController::store() and
 * JobValidationService::flagRemediation() — no duplicated holiday/business-
 * hours logic (Engineering Bar: Clean).
 *
 * The clock/breach machinery tracks the resolution target only (SRA §5.1's
 * single SLABreached field, §10.1's ResolutionTarget as the umbrella
 * deadline) — acknowledgement/on-site-response windows live on the profile
 * for reference but don't drive separate breach flags in v1.
 */
class SlaClockService
{
    public function __construct(
        private readonly BusinessHoursCalculator $calculator,
        private readonly PlatformSettings $settings,
    ) {}

    /**
     * @return array<string, mixed> sla_profile_id / sla_clock_started_at /
     *                              sla_resolution_target_at / sla_at_risk_at —
     *                              all null when the client has no active SLA profile.
     */
    public function resolveClockFields(Client $client, Store $store): array
    {
        $profile = $client->slaProfile;

        if ($profile === null || ! $profile->is_active) {
            return [
                'sla_profile_id'           => null,
                'sla_clock_started_at'     => null,
                'sla_resolution_target_at' => null,
                'sla_at_risk_at'           => null,
            ];
        }

        if ($store->country !== Country::Australia) {
            // No dedicated public-holiday calendar for this country yet (US-12.4) —
            // the bound PublicHolidayProvider falls back to weekends-only, which is
            // documented, not silent (US-03.5). Logged here, once per clock start,
            // rather than per business day scanned inside the calculator.
            Log::info("SLA business-hours clock for store #{$store->id} ({$store->store_code}) in {$store->country->value} is using a weekends-only placeholder calendar — no dedicated public-holiday provider yet.");
        }

        $startedAt = CarbonImmutable::now();

        $targetAt = $this->calculator->addBusinessHours(
            $startedAt,
            $profile->resolution_hours,
            $store->state,
            $store->store_timezone,
        );

        $thresholdPct = max(0, min(100, (int) $this->settings->get(PlatformSettingKey::SlaAtRiskThresholdPct->value, 80)));
        $atRiskAt     = $startedAt->addSeconds(
            (int) round($startedAt->diffInSeconds($targetAt) * ($thresholdPct / 100))
        );

        return [
            'sla_profile_id'           => $profile->id,
            'sla_clock_started_at'     => $startedAt,
            'sla_resolution_target_at' => $targetAt,
            'sla_at_risk_at'           => $atRiskAt,
        ];
    }

    /** Pure read of the stored columns — no live computation (no per-request recompute). */
    public static function statusFor(?string $startedAt, bool $atRisk, bool $breached): SlaStatus
    {
        if ($startedAt === null) {
            return SlaStatus::NotTracked;
        }

        if ($breached) {
            return SlaStatus::Breached;
        }

        if ($atRisk) {
            return SlaStatus::AtRisk;
        }

        return SlaStatus::OnTrack;
    }
}

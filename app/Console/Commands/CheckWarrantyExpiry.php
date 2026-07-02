<?php

namespace App\Console\Commands;

use App\Enums\AssetStatus;
use App\Enums\PlatformSettingKey;
use App\Models\Asset;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Settings\PlatformSettings;
use App\Support\DurationCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily warranty-expiry check (US-13.3, SRA §4.2). Fires once per asset per
 * configured threshold (default 30/60/90 days, PM-editable — US-16.1) —
 * idempotency enforced at the DB layer via warranty_notification_log's unique
 * (asset_id, threshold_days) index, not just this command's own exists() check.
 */
class CheckWarrantyExpiry extends Command
{
    protected $signature = 'warranty:check-expiry';

    protected $description = 'Notify PMs when an asset warranty is approaching expiry (US-13.3)';

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly NotificationDispatcher $notifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var list<int> $thresholds */
        $thresholds = $this->settings->get(PlatformSettingKey::WarrantyAlertDays->value, [30, 60, 90]);
        $today      = now()->startOfDay();

        $assets = Asset::whereNotNull('warranty_expiry')
            ->where('asset_status', '!=', AssetStatus::Decommissioned->value)
            ->get();

        $sent = 0;

        foreach ($assets as $asset) {
            $daysRemaining = DurationCalculator::daysUntil($today, $asset->warranty_expiry);

            if ($daysRemaining < 0) {
                continue; // already expired — not an "approaching" notification
            }

            foreach ($thresholds as $threshold) {
                if ($daysRemaining > $threshold) {
                    continue;
                }

                $alreadyNotified = DB::table('warranty_notification_log')
                    ->where('asset_id', $asset->id)
                    ->where('threshold_days', $threshold)
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                $this->notifications->warrantyExpiryApproaching($asset, $daysRemaining);

                DB::table('warranty_notification_log')->insert([
                    'asset_id'       => $asset->id,
                    'threshold_days' => $threshold,
                    'notified_at'    => now(),
                ]);

                $sent++;
            }
        }

        $this->info("Warranty expiry check complete: {$sent} notification(s) sent.");

        return self::SUCCESS;
    }
}

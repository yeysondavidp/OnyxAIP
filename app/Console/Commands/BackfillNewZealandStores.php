<?php

namespace App\Console\Commands;

use App\Enums\Country;
use App\Enums\NewZealandRegion;
use App\Models\Store;
use Illuminate\Console\Command;

/**
 * One-off, idempotent correction for the 4 stores created by
 * ImportVendorStorePlayers on 2026-07-08 before non-AU stores were
 * supported (US-03.5). Those stores are real Dior/Sephora locations in
 * Auckland, NZ, but were saved with `state = NSW`, `country = "Australia"`,
 * and `store_timezone = "Australia/Sydney"` — which also meant their SLA
 * clock was silently computed against the Sydney public-holiday calendar
 * (see SlaClockService).
 *
 * Targets rows by the exact placeholder marker ImportVendorStorePlayers
 * wrote into `notes`, not by store code/name — codes can change, and this
 * marker is the one thing guaranteed to only exist on the affected rows.
 * Safe to run more than once: a store with no matching marker is left alone.
 */
class BackfillNewZealandStores extends Command
{
    protected $signature = 'stores:backfill-nz
        {--dry-run : Preview which stores would change without writing anything}';

    protected $description = 'Correct the 4 Auckland, NZ stores that were imported with an Australian placeholder state/timezone';

    private const PLACEHOLDER_MARKER = 'state stored as an Australian placeholder only';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stores = Store::allClients()
            ->where('notes', 'like', '%'.self::PLACEHOLDER_MARKER.'%')
            ->get();

        if ($stores->isEmpty()) {
            $this->info('No stores found with the Australian-placeholder marker — nothing to backfill.');

            return self::SUCCESS;
        }

        foreach ($stores as $store) {
            if (! str_contains((string) $store->notes, 'Auckland')) {
                $this->error("Store #{$store->id} ({$store->store_code}) has the placeholder marker but its notes don't mention Auckland — skipped. Confirm its true location manually before correcting it.");

                continue;
            }

            $this->line(sprintf(
                '%s store #%d (%s, %s): %s/%s/%s → %s/%s/%s',
                $dryRun ? 'Would update' : 'Updating',
                $store->id,
                $store->store_code,
                $store->store_name,
                $store->country->value,
                $store->state->value,
                $store->store_timezone,
                Country::NewZealand->value,
                NewZealandRegion::Auckland->value,
                'Pacific/Auckland',
            ));

            if ($dryRun) {
                continue;
            }

            $store->update([
                'country'        => Country::NewZealand->value,
                'state'          => NewZealandRegion::Auckland->value,
                'store_timezone' => 'Pacific/Auckland',
                'notes'          => trim(str_replace(
                    ' — '.self::PLACEHOLDER_MARKER.'; the system does not yet support non-AU stores.',
                    '.',
                    (string) $store->notes,
                )),
            ]);
        }

        $this->info($dryRun
            ? "Dry run complete — {$stores->count()} store(s) would be corrected."
            : "Done — {$stores->count()} store(s) corrected to their real New Zealand location.");

        return self::SUCCESS;
    }
}

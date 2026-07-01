<?php

namespace App\Services\Sla;

use App\Contracts\PublicHolidayProvider;
use App\Enums\AustralianState;
use Carbon\CarbonImmutable;

/**
 * Adds a number of business hours to a UTC instant, evaluated in the store's
 * own IANA timezone and skipping weekends + AU public holidays (SRA §10.2).
 * Reused by US-12.2 (SLA clock) and US-12.3 (breach-risk) — one calculator,
 * no duplicated holiday/business-hours logic.
 */
class BusinessHoursCalculator
{
    /** Safety valve against a misconfigured/empty business-hours window. */
    private const MAX_DAYS_SCANNED = 3650;

    public function __construct(private readonly PublicHolidayProvider $holidays) {}

    public function addBusinessHours(
        CarbonImmutable $fromUtc,
        int $hours,
        AustralianState $state,
        string $timezone,
    ): CarbonImmutable {
        $startHour = (int) config('sla.business_hours_start', 8);
        $endHour   = (int) config('sla.business_hours_end', 18);
        $dayLength = $endHour - $startHour;

        if ($dayLength <= 0) {
            throw new \InvalidArgumentException('sla.business_hours_end must be after sla.business_hours_start.');
        }

        $local            = $fromUtc->setTimezone($timezone);
        $remainingMinutes = $hours * 60;
        $scanned          = 0;

        // Position the cursor at the next available business instant.
        $cursor = $this->nextBusinessInstant($local, $startHour, $endHour, $state);

        while ($remainingMinutes > 0) {
            if (++$scanned > self::MAX_DAYS_SCANNED) {
                throw new \RuntimeException('BusinessHoursCalculator exceeded its scan safety limit.');
            }

            $dayEnd                = $cursor->setTime($endHour, 0);
            $minutesAvailableToday = (int) $cursor->diffInMinutes($dayEnd, false);

            if ($minutesAvailableToday >= $remainingMinutes) {
                $cursor           = $cursor->addMinutes($remainingMinutes);
                $remainingMinutes = 0;

                break;
            }

            $remainingMinutes -= $minutesAvailableToday;
            $cursor = $this->nextBusinessInstant(
                $cursor->addDay()->setTime($startHour, 0),
                $startHour,
                $endHour,
                $state,
            );
        }

        return $cursor->setTimezone('UTC');
    }

    /** Move a local instant forward to the next point that is inside a business day/window. */
    private function nextBusinessInstant(
        CarbonImmutable $local,
        int $startHour,
        int $endHour,
        AustralianState $state,
    ): CarbonImmutable {
        $cursor  = $local;
        $scanned = 0;

        while (true) {
            if (++$scanned > self::MAX_DAYS_SCANNED) {
                throw new \RuntimeException('BusinessHoursCalculator exceeded its scan safety limit.');
            }

            if (! $this->isBusinessDay($cursor, $state)) {
                $cursor = $cursor->addDay()->setTime($startHour, 0);

                continue;
            }

            if ($cursor->hour < $startHour) {
                $cursor = $cursor->setTime($startHour, 0);
            } elseif ($cursor->hour >= $endHour) {
                $cursor = $cursor->addDay()->setTime($startHour, 0);

                continue;
            }

            return $cursor;
        }
    }

    private function isBusinessDay(CarbonImmutable $date, AustralianState $state): bool
    {
        return ! $date->isWeekend() && ! $this->holidays->isHoliday($date, $state);
    }
}

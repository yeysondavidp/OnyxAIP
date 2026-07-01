<?php

namespace App\Services\Sla;

use App\Contracts\PublicHolidayProvider;
use App\Enums\AustralianState;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Rule-computed AU public holiday calendar — no external API dependency
 * (ADR-002 lean-server constraint). Covers the nationally-consistent fixed
 * and Easter-derived holidays plus each state's Labour Day / King's Birthday
 * equivalent.
 *
 * Accuracy note: WA's King's Birthday is gazetted per year with no fixed
 * formula — approximated here as "last Monday of September" and should be
 * sanity-checked annually by ONYX. Every other date is either a fixed
 * calendar date (with standard weekend "Mondayisation") or a stable
 * nth-weekday-of-month rule. Sits behind PublicHolidayProvider so a
 * licensed data source can replace this with no calculator changes.
 */
class StaticAuPublicHolidayProvider implements PublicHolidayProvider
{
    public function isHoliday(CarbonInterface $date, AustralianState $state): bool
    {
        $dates = $this->holidaysForYear((int) $date->year, $state);

        return in_array($date->format('Y-m-d'), $dates, strict: true);
    }

    /** @return list<string> Y-m-d dates, cached per state+year. */
    private function holidaysForYear(int $year, AustralianState $state): array
    {
        return Cache::remember(
            "sla.au_public_holidays.{$state->value}.{$year}",
            now()->addMonth(),
            fn () => $this->computeHolidaysForYear($year, $state),
        );
    }

    /** @return list<string> */
    private function computeHolidaysForYear(int $year, AustralianState $state): array
    {
        $dates = [];

        // ── Nationally-consistent fixed dates (Mondayised if on a weekend) ──
        array_push($dates, ...$this->mondayised(Carbon::create($year, 1, 1)));   // New Year's Day
        array_push($dates, ...$this->mondayised(Carbon::create($year, 1, 26)));  // Australia Day
        $dates[] = Carbon::create($year, 4, 25)->format('Y-m-d');               // ANZAC Day (no shift)
        array_push($dates, ...$this->christmasAndBoxingDay($year));

        // ── Easter-derived ──
        $easterSunday = $this->easterSunday($year);
        $goodFriday   = $easterSunday->copy()->subDays(2);
        $easterSat    = $easterSunday->copy()->subDay();
        $easterMon    = $easterSunday->copy()->addDay();

        $dates[] = $goodFriday->format('Y-m-d'); // all states
        $dates[] = $easterMon->format('Y-m-d');  // all states

        // Easter Saturday/Sunday: NSW, VIC, QLD, ACT, NT observe both; SA/WA/TAS do not.
        if (in_array($state, [AustralianState::Nsw, AustralianState::Vic, AustralianState::Qld, AustralianState::Act, AustralianState::Nt], strict: true)) {
            $dates[] = $easterSat->format('Y-m-d');
            $dates[] = $easterSunday->format('Y-m-d');
        }

        // ── State Labour Day / King's Birthday equivalents ──
        $dates[] = $this->stateSpecificDay($year, $state);
        $dates[] = $this->kingsBirthday($year, $state);

        return array_values(array_unique($dates));
    }

    /** New Year's Day / Australia Day: Sat → observed Mon; Sun → observed Mon. */
    private function mondayised(Carbon $date): array
    {
        $dates = [$date->format('Y-m-d')];

        if ($date->isWeekend()) {
            $dates[] = $date->copy()->next(Carbon::MONDAY)->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * Christmas Day / Boxing Day observed-Monday/Tuesday pairing per the
     * standard AU "double Mondayisation" rule.
     *
     * @return list<string>
     */
    private function christmasAndBoxingDay(int $year): array
    {
        $christmas = Carbon::create($year, 12, 25);
        $boxing    = Carbon::create($year, 12, 26);

        if ($christmas->isSaturday()) {
            // Christmas → Mon 27, Boxing (Sun 26) → Tue 28
            return [Carbon::create($year, 12, 27)->format('Y-m-d'), Carbon::create($year, 12, 28)->format('Y-m-d')];
        }

        if ($christmas->isSunday()) {
            // Christmas → Tue 27, Boxing already falls on Mon 26
            return [Carbon::create($year, 12, 27)->format('Y-m-d'), $boxing->format('Y-m-d')];
        }

        return [$christmas->format('Y-m-d'), $boxing->format('Y-m-d')];
    }

    /** Anonymous Gregorian algorithm (Meeus/Jones/Butcher) — no ext-calendar dependency. */
    private function easterSunday(int $year): Carbon
    {
        $a     = $year % 19;
        $b     = intdiv($year, 100);
        $c     = $year % 100;
        $d     = intdiv($b, 4);
        $e     = $b % 4;
        $f     = intdiv($b + 8, 25);
        $g     = intdiv($b - $f + 1, 3);
        $h     = (19 * $a + $b - $d - $g + 15) % 30;
        $i     = intdiv($c, 4);
        $k     = $c                               % 4;
        $l     = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m     = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }

    /** Labour Day / Eight Hours Day / May Day / Adelaide Cup Day equivalent, by state. */
    private function stateSpecificDay(int $year, AustralianState $state): string
    {
        return match ($state) {
            AustralianState::Nsw => $this->nthWeekdayOfMonth($year, 10, Carbon::MONDAY, 1),
            AustralianState::Vic => $this->nthWeekdayOfMonth($year, 3, Carbon::MONDAY, 2),
            AustralianState::Qld => $this->nthWeekdayOfMonth($year, 5, Carbon::MONDAY, 1),
            AustralianState::Wa  => $this->nthWeekdayOfMonth($year, 3, Carbon::MONDAY, 1),
            AustralianState::Sa  => $this->nthWeekdayOfMonth($year, 3, Carbon::MONDAY, 2), // Adelaide Cup Day
            AustralianState::Tas => $this->nthWeekdayOfMonth($year, 3, Carbon::MONDAY, 2), // Eight Hours Day
            AustralianState::Act => $this->nthWeekdayOfMonth($year, 10, Carbon::MONDAY, 1),
            AustralianState::Nt  => $this->nthWeekdayOfMonth($year, 5, Carbon::MONDAY, 1),  // May Day
        };
    }

    /** King's Birthday equivalent, by state. WA is an approximation — see class docblock. */
    private function kingsBirthday(int $year, AustralianState $state): string
    {
        return match ($state) {
            AustralianState::Qld => $this->nthWeekdayOfMonth($year, 10, Carbon::MONDAY, 1),
            AustralianState::Wa  => $this->lastWeekdayOfMonth($year, 9, Carbon::MONDAY),
            default              => $this->nthWeekdayOfMonth($year, 6, Carbon::MONDAY, 2),
        };
    }

    /** e.g. nthWeekdayOfMonth(2026, 10, Carbon::MONDAY, 1) = first Monday of October 2026. */
    private function nthWeekdayOfMonth(int $year, int $month, int $dayOfWeek, int $nth): string
    {
        $date = Carbon::create($year, $month, 1);

        if ($date->dayOfWeek !== $dayOfWeek) {
            $date->next($dayOfWeek);
        }

        return $date->addWeeks($nth - 1)->format('Y-m-d');
    }

    /** e.g. lastWeekdayOfMonth(2026, 9, Carbon::MONDAY) = last Monday of September 2026. */
    private function lastWeekdayOfMonth(int $year, int $month, int $dayOfWeek): string
    {
        $lastDay = Carbon::create($year, $month, 1)->endOfMonth();

        if ($lastDay->dayOfWeek !== $dayOfWeek) {
            $lastDay->previous($dayOfWeek);
        }

        return $lastDay->format('Y-m-d');
    }
}

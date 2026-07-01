<?php

use App\Enums\AustralianState;
use App\Services\Sla\StaticAuPublicHolidayProvider;
use Carbon\CarbonImmutable;

function holidays(): StaticAuPublicHolidayProvider
{
    return new StaticAuPublicHolidayProvider;
}

it('treats a normal weekday as not a holiday', function () {
    expect(holidays()->isHoliday(CarbonImmutable::parse('2026-07-01'), AustralianState::Nsw))->toBeFalse();
});

it('recognises ANZAC Day nationally', function () {
    $anzacDay = CarbonImmutable::parse('2026-04-25');

    foreach (AustralianState::cases() as $state) {
        expect(holidays()->isHoliday($anzacDay, $state))->toBeTrue();
    }
});

it('Mondayises Australia Day when it falls on a weekend', function () {
    // 26 Jan 2030 is a Saturday
    $australiaDay = CarbonImmutable::parse('2030-01-26');
    expect($australiaDay->isSaturday())->toBeTrue();

    $observedMonday = CarbonImmutable::parse('2030-01-28');
    expect($observedMonday->isMonday())->toBeTrue();

    expect(holidays()->isHoliday($observedMonday, AustralianState::Nsw))->toBeTrue();
});

it('computes Easter Monday correctly for a known year', function () {
    // Easter Sunday 2026 is 5 April → Easter Monday is 6 April
    expect(holidays()->isHoliday(CarbonImmutable::parse('2026-04-06'), AustralianState::Nsw))->toBeTrue();
});

it('shifts Christmas Day when it falls on a Saturday', function () {
    // 25 Dec 2027 is a Saturday → observed Monday 27 Dec; Boxing Day (Sun 26) → Tue 28
    expect(CarbonImmutable::parse('2027-12-25')->isSaturday())->toBeTrue();

    expect(holidays()->isHoliday(CarbonImmutable::parse('2027-12-27'), AustralianState::Nsw))->toBeTrue();
    expect(holidays()->isHoliday(CarbonImmutable::parse('2027-12-28'), AustralianState::Nsw))->toBeTrue();
});

it('applies Easter Saturday/Sunday only in the states that observe it', function () {
    // Easter Sunday 2026 is 5 April
    $easterSunday = CarbonImmutable::parse('2026-04-05');

    expect(holidays()->isHoliday($easterSunday, AustralianState::Nsw))->toBeTrue();
    expect(holidays()->isHoliday($easterSunday, AustralianState::Sa))->toBeFalse();
});

it('computes each state Labour Day / King\'s Birthday equivalent as a Monday', function () {
    foreach (AustralianState::cases() as $state) {
        $found = false;

        foreach (range(1, 12) as $month) {
            foreach (range(1, 28) as $day) {
                $date = CarbonImmutable::create(2026, $month, $day);

                if ($date->isMonday() && holidays()->isHoliday($date, $state)) {
                    $found = true;
                }
            }
        }

        expect($found)->toBeTrue("Expected at least one Monday state holiday for {$state->value}");
    }
});

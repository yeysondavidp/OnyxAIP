<?php

use App\Enums\AustralianState;
use App\Services\Sla\BusinessHoursCalculator;
use App\Services\Sla\StaticAuPublicHolidayProvider;
use Carbon\CarbonImmutable;

function calculator(): BusinessHoursCalculator
{
    return new BusinessHoursCalculator(new StaticAuPublicHolidayProvider);
}

it('carries remaining hours into the same business day', function () {
    // Wed 2026-07-01 15:00 Sydney (business hours end at 18:00)
    $start = CarbonImmutable::parse('2026-07-01 05:00:00', 'UTC');

    $target = calculator()->addBusinessHours($start, 2, AustralianState::Nsw, 'Australia/Sydney');

    expect($target->setTimezone('Australia/Sydney')->format('Y-m-d H:i'))->toBe('2026-07-01 17:00');
});

it('carries remaining hours over to the next business day', function () {
    // Wed 2026-07-01 15:00 Sydney + 5 hours: 3h today (to 18:00) + 2h tomorrow (8am start)
    $start = CarbonImmutable::parse('2026-07-01 05:00:00', 'UTC');

    $target = calculator()->addBusinessHours($start, 5, AustralianState::Nsw, 'Australia/Sydney');

    expect($target->setTimezone('Australia/Sydney')->format('Y-m-d H:i'))->toBe('2026-07-02 10:00');
});

it('skips the weekend', function () {
    // Fri 2026-07-03 17:00 Sydney + 3 hours: 1h today (to 18:00) + 2h Monday (8am start)
    $start = CarbonImmutable::parse('2026-07-03 07:00:00', 'UTC');

    $target = calculator()->addBusinessHours($start, 3, AustralianState::Nsw, 'Australia/Sydney');

    expect($target->setTimezone('Australia/Sydney')->format('l Y-m-d H:i'))->toBe('Monday 2026-07-06 10:00');
});

it('skips a public holiday', function () {
    // Thu 2026-01-01 (New Year's Day, a holiday all day) 17:00 Sydney (AEDT, UTC+11) + 3 hours.
    // The whole holiday is skipped — the clock starts fresh at 8am on the next business day
    // (Fri 2026-01-02, not itself a holiday), so 8am + 3h = 11am.
    $start = CarbonImmutable::parse('2026-01-01 06:00:00', 'UTC');

    $target = calculator()->addBusinessHours($start, 3, AustralianState::Nsw, 'Australia/Sydney');

    expect($target->setTimezone('Australia/Sydney')->format('l Y-m-d H:i'))->toBe('Friday 2026-01-02 11:00');
});

it('starts from the next business instant when the clock starts outside business hours', function () {
    // Wed 2026-07-01 21:00 Sydney (after hours) + 1 hour → next day 9am
    $start = CarbonImmutable::parse('2026-07-01 11:00:00', 'UTC');

    $target = calculator()->addBusinessHours($start, 1, AustralianState::Nsw, 'Australia/Sydney');

    expect($target->setTimezone('Australia/Sydney')->format('Y-m-d H:i'))->toBe('2026-07-02 09:00');
});

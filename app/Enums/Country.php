<?php

namespace App\Enums;

/**
 * The countries a Store can be located in (US-03.5). Backing values match the
 * `stores.country` strings already in use since Sprint 2 ('Australia'), so no
 * data migration is needed. Each country owns its own state/region list and
 * timezone options — single source of truth reused by the Store form,
 * filters, and reports instead of scattering AU/NZ lists across the app.
 */
enum Country: string
{
    case Australia  = 'Australia';
    case NewZealand = 'New Zealand';

    public function label(): string
    {
        return $this->value;
    }

    /** @return list<Region> */
    public function regions(): array
    {
        return match ($this) {
            self::Australia  => AustralianState::cases(),
            self::NewZealand => NewZealandRegion::cases(),
        };
    }

    /** @return array<string, string> IANA timezone => human label */
    public function timezones(): array
    {
        return match ($this) {
            self::Australia => [
                'Australia/Sydney'    => 'Sydney / Canberra (AEST/AEDT)',
                'Australia/Melbourne' => 'Melbourne (AEST/AEDT)',
                'Australia/Brisbane'  => 'Brisbane (AEST — no daylight saving)',
                'Australia/Perth'     => 'Perth (AWST)',
                'Australia/Adelaide'  => 'Adelaide (ACST/ACDT)',
                'Australia/Darwin'    => 'Darwin (ACST — no daylight saving)',
                'Australia/Hobart'    => 'Hobart (AEST/AEDT)',
                'Australia/Lord_Howe' => 'Lord Howe Island (LHST/LHDT)',
            ],
            self::NewZealand => [
                'Pacific/Auckland' => 'Auckland (NZST/NZDT)',
            ],
        };
    }
}

{{--
  ONYX SLA Badge — plain-language SLA status indicator (§10.2, §14.7).
  Colour + text (never colour alone) so it stays accessible.

  Props:
    status  App\Enums\SlaStatus (required)

  Usage:
    <x-onyx.sla-badge :status="$job->slaStatus()" />
--}}

@props(['status'])

<x-onyx.badge :tone="$status->tone()" variant="soft">
    {{ $status->label() }}
</x-onyx.badge>

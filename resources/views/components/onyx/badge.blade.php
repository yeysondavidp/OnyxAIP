{{--
  ONYX Badge — status / category marker

  Props:
    tone      neutral | accent | positive | caution | critical | info  (default: neutral)
    variant   soft | solid | outline                                    (default: soft)
    uppercase bool                                                      (default: true)

  Usage:
    <x-onyx.badge>Active</x-onyx.badge>
    <x-onyx.badge tone="positive">Validated</x-onyx.badge>
    <x-onyx.badge tone="critical" variant="solid">Faulty</x-onyx.badge>
    <x-onyx.badge tone="caution" :uppercase="false">Under Maintenance</x-onyx.badge>
--}}

@props([
    'tone'      => 'neutral',
    'variant'   => 'soft',
    'uppercase' => true,
])

@php
$toneVariantClass = "onyx-badge--{$tone}-{$variant}";
$caseClass = $uppercase ? 'onyx-badge--upper' : '';
@endphp

<style>
.onyx-badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 3px 9px;
  border: 1px solid transparent;
  border-radius: var(--radius-xs);
  font-family: var(--font-sans);
  font-weight: var(--weight-medium);
  font-size: var(--fs-12);
  line-height: 1.4;
  letter-spacing: var(--tracking-wide);
  white-space: nowrap;
}
.onyx-badge--upper { text-transform: uppercase; letter-spacing: var(--tracking-wide); }

/* neutral */
.onyx-badge--neutral-soft    { background: var(--surface-sunken);  color: var(--text-secondary); }
.onyx-badge--neutral-solid   { background: var(--onyx-900);        color: var(--onyx-25); }
.onyx-badge--neutral-outline { background: transparent; color: var(--text-secondary); border-color: currentColor; }

/* accent */
.onyx-badge--accent-soft     { background: var(--bronze-100); color: var(--bronze-700); }
.onyx-badge--accent-solid    { background: var(--bronze-500); color: var(--onyx-25); }
.onyx-badge--accent-outline  { background: transparent; color: var(--text-accent); border-color: currentColor; }

/* positive */
.onyx-badge--positive-soft   { background: var(--positive-soft); color: var(--positive); }
.onyx-badge--positive-solid  { background: var(--positive);      color: var(--onyx-25); }
.onyx-badge--positive-outline{ background: transparent; color: var(--positive); border-color: currentColor; }

/* caution */
.onyx-badge--caution-soft    { background: var(--caution-soft); color: var(--caution); }
.onyx-badge--caution-solid   { background: var(--caution);      color: var(--onyx-25); }
.onyx-badge--caution-outline { background: transparent; color: var(--caution); border-color: currentColor; }

/* critical */
.onyx-badge--critical-soft   { background: var(--critical-soft); color: var(--critical); }
.onyx-badge--critical-solid  { background: var(--critical);      color: var(--onyx-25); }
.onyx-badge--critical-outline{ background: transparent; color: var(--critical); border-color: currentColor; }

/* info */
.onyx-badge--info-soft       { background: var(--info-soft); color: var(--info); }
.onyx-badge--info-solid      { background: var(--info);      color: var(--onyx-25); }
.onyx-badge--info-outline    { background: transparent; color: var(--info); border-color: currentColor; }
</style>

<span {{ $attributes->merge(['class' => "onyx-badge {$toneVariantClass} {$caseClass}"]) }}>
  {{ $slot }}
</span>

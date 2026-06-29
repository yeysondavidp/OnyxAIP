{{--
  ONYX Eyebrow — the uppercase, wide-tracked label above headings.
  A signature of the ONYX voice.

  Props:
    tone   muted | accent | primary               (default: muted)
    tick   bool — prefix with a hairline tick     (default: false)
    index  string|null — numeric prefix e.g. "01" (default: null)
    as     p | span | div | h2 …                  (default: p)

  Usage:
    <x-onyx.eyebrow>Asset Registry</x-onyx.eyebrow>
    <x-onyx.eyebrow tone="accent" :tick="true">New Feature</x-onyx.eyebrow>
    <x-onyx.eyebrow index="01">Step one</x-onyx.eyebrow>
--}}

@props([
    'tone'  => 'muted',
    'tick'  => false,
    'index' => null,
    'as'    => 'p',
])

@php
$toneClass = 'onyx-eyebrow--' . $tone;
@endphp

<style>
.onyx-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: var(--space-3);
  margin: 0;
  font: var(--type-label);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wider);
}
.onyx-eyebrow--muted   { color: var(--text-muted); }
.onyx-eyebrow--accent  { color: var(--text-accent); }
.onyx-eyebrow--primary { color: var(--text-primary); }

.onyx-eyebrow__tick  { width: 18px; height: 1px; background: currentColor; opacity: 0.6; }
.onyx-eyebrow__index { font-variant-numeric: tabular-nums; opacity: 0.6; }
</style>

<{{ $as }} {{ $attributes->merge(['class' => "onyx-eyebrow {$toneClass}"]) }}>
  @if ($tick)
    <span class="onyx-eyebrow__tick" aria-hidden="true"></span>
  @endif
  @if ($index !== null)
    <span class="onyx-eyebrow__index">{{ $index }}</span>
  @endif
  <span>{{ $slot }}</span>
</{{ $as }}>

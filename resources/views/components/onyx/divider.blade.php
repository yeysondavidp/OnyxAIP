{{--
  ONYX Divider — hairline "vein" rule.

  Props:
    orientation  horizontal | vertical   (default: horizontal)
    tone         subtle | strong | accent (default: subtle)
    label        string — centred label  (default: null)

  Usage:
    <x-onyx.divider />
    <x-onyx.divider tone="strong" />
    <x-onyx.divider label="Or continue with" />
--}}

@props([
    'orientation' => 'horizontal',
    'tone'        => 'subtle',
    'label'       => null,
])

@php
$toneClass = 'onyx-divider--' . $tone;
@endphp

<style>
.onyx-divider--subtle  { --divider-color: var(--border-subtle); }
.onyx-divider--strong  { --divider-color: var(--border-strong); }
.onyx-divider--accent  { --divider-color: var(--border-accent); }

hr.onyx-divider { border: none; height: 1px; margin: 0; background: var(--divider-color); }

span.onyx-divider {
  display: inline-block;
  width: 1px;
  align-self: stretch;
  background: var(--divider-color);
}

.onyx-divider--labelled {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}
.onyx-divider--labelled .onyx-divider__line {
  flex: 1;
  height: 1px;
  background: var(--divider-color);
}
.onyx-divider--labelled .onyx-divider__label {
  font: var(--type-label);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wider);
  color: var(--text-muted);
}
</style>

@if ($orientation === 'vertical')
  <span role="separator" aria-orientation="vertical"
    {{ $attributes->merge(['class' => "onyx-divider {$toneClass}"]) }}></span>
@elseif ($label)
  <div role="separator" {{ $attributes->merge(['class' => "onyx-divider--labelled {$toneClass}"]) }}>
    <span class="onyx-divider__line"></span>
    <span class="onyx-divider__label">{{ $label }}</span>
    <span class="onyx-divider__line"></span>
  </div>
@else
  <hr role="separator" {{ $attributes->merge(['class' => "onyx-divider {$toneClass}"]) }} />
@endif

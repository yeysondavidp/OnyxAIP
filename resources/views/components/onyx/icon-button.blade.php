{{--
  ONYX IconButton — square control carrying a single glyph or SVG.

  Props:
    variant   ghost | outline | solid   (default: ghost)
    size      sm | md | lg             (default: md)
    round     bool — circular          (default: false)
    disabled  bool                     (default: false)
    type      button | submit          (default: button)
    aria-label string — required for accessibility

  Usage:
    <x-onyx.icon-button aria-label="Edit asset">
      <svg …/>
    </x-onyx.icon-button>

    <x-onyx.icon-button variant="outline" size="sm" aria-label="Delete" type="submit">
      <svg …/>
    </x-onyx.icon-button>
--}}

@props([
    'variant'  => 'ghost',
    'size'     => 'md',
    'round'    => false,
    'disabled' => false,
    'type'     => 'button',
])

@php
$variantClass = 'onyx-icon-btn--' . $variant;
$sizeClass    = 'onyx-icon-btn--' . $size;
$shapeClass   = $round ? 'onyx-icon-btn--round' : '';
@endphp

<style>
.onyx-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 1px solid transparent;
  cursor: pointer;
  transition:
    background-color var(--duration-fast) var(--ease-standard),
    transform var(--duration-fast) var(--ease-standard);
  border-radius: var(--radius-sm);
}
.onyx-icon-btn:active:not(:disabled) { transform: scale(0.94); }
.onyx-icon-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.onyx-icon-btn--round { border-radius: var(--radius-pill); }

.onyx-icon-btn--sm { width: 34px; height: 34px; }
.onyx-icon-btn--md { width: 44px; height: 44px; } /* WCAG 2.1 AA min touch target */
.onyx-icon-btn--lg { width: 50px; height: 50px; }

.onyx-icon-btn--ghost   { background: transparent; color: var(--text-primary); }
.onyx-icon-btn--ghost:hover:not(:disabled)   { background: var(--surface-sunken); }

.onyx-icon-btn--outline { background: transparent; color: var(--text-primary); border-color: var(--border-strong); }
.onyx-icon-btn--outline:hover:not(:disabled) { background: var(--surface-sunken); }

.onyx-icon-btn--solid   { background: var(--onyx-900); color: var(--onyx-25); }
.onyx-icon-btn--solid:hover:not(:disabled)   { background: var(--onyx-700); }
</style>

<button
  type="{{ $type }}"
  {{ $attributes->merge(['class' => "onyx-icon-btn {$variantClass} {$sizeClass} {$shapeClass}"]) }}
  @if ($disabled) disabled @endif
>{{ $slot }}</button>

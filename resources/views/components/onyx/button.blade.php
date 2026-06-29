{{--
  ONYX Button

  Props:
    variant  solid | outline | ghost | accent   (default: solid)
    size     sm | md | lg                        (default: md)
    type     button | submit | reset             (default: button)
    fullWidth bool                               (default: false)
    disabled  bool                               (default: false)
    href      string — renders an <a> when set

  Usage:
    <x-onyx.button>Save changes</x-onyx.button>
    <x-onyx.button variant="outline" size="sm">Cancel</x-onyx.button>
    <x-onyx.button variant="accent" type="submit">Confirm</x-onyx.button>
    <x-onyx.button href="{{ route('jobs.index') }}" variant="ghost">Back</x-onyx.button>
--}}

@props([
    'variant'   => 'solid',
    'size'      => 'md',
    'type'      => 'button',
    'fullWidth' => false,
    'disabled'  => false,
    'href'      => null,
])

@php
$sizes = [
    'sm' => 'onyx-btn--sm',
    'md' => 'onyx-btn--md',
    'lg' => 'onyx-btn--lg',
];
$sizeClass  = $sizes[$size] ?? $sizes['md'];
$variantCls = 'onyx-btn--' . $variant;
$widthCls   = $fullWidth ? 'onyx-btn--full' : '';
$tag        = $href ? 'a' : 'button';
@endphp

<style>
.onyx-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  font-family: var(--font-sans);
  font-weight: var(--weight-medium);
  letter-spacing: 0.01em;
  line-height: 1;
  border-radius: var(--radius-sm);
  border: 1px solid transparent;
  cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
  transition:
    background-color var(--duration-fast) var(--ease-standard),
    color var(--duration-fast) var(--ease-standard),
    border-color var(--duration-fast) var(--ease-standard),
    transform var(--duration-fast) var(--ease-standard);
}
.onyx-btn:active:not(:disabled) { transform: scale(0.98); }
.onyx-btn:disabled, .onyx-btn[aria-disabled="true"] { opacity: 0.45; cursor: not-allowed; pointer-events: none; }

.onyx-btn--sm { padding: 8px 16px; font-size: var(--fs-13); min-height: 36px; }
.onyx-btn--md { padding: 11px 22px; font-size: var(--fs-14); min-height: 44px; }
.onyx-btn--lg { padding: 15px 30px; font-size: var(--fs-16); min-height: 54px; }
.onyx-btn--full { width: 100%; }

.onyx-btn--solid   { background: var(--onyx-900); color: var(--onyx-25); }
.onyx-btn--solid:hover:not(:disabled) { background: var(--onyx-700); }

.onyx-btn--outline { background: transparent; color: var(--text-primary); border-color: var(--border-strong); }
.onyx-btn--outline:hover:not(:disabled) { background: var(--surface-sunken); }

.onyx-btn--ghost   { background: transparent; color: var(--text-primary); }
.onyx-btn--ghost:hover:not(:disabled) { background: var(--surface-sunken); }

/* bronze-600 (#79613f) on onyx-25 = 5.48:1 — passes WCAG AA */
.onyx-btn--accent  { background: var(--bronze-600); color: var(--onyx-25); }
.onyx-btn--accent:hover:not(:disabled) { background: var(--bronze-700); }
</style>

@if ($href)
  <a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => "onyx-btn {$sizeClass} {$variantCls} {$widthCls}"]) }}
    @if ($disabled) aria-disabled="true" @endif
  >{{ $slot }}</a>
@else
  <button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "onyx-btn {$sizeClass} {$variantCls} {$widthCls}"]) }}
    @if ($disabled) disabled @endif
  >{{ $slot }}</button>
@endif

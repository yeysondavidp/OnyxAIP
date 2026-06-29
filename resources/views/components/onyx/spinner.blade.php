{{--
  ONYX Spinner — indeterminate loading indicator.

  Props:
    size   xs | sm | md | lg | xl    (default: md)
    tone   default | accent | inverse (default: default)
    label  string — sr-only label    (default: "Loading")

  Usage:
    <x-onyx.spinner />
    <x-onyx.spinner size="lg" tone="accent" label="Saving changes" />

  For wire:loading integration:
    <x-onyx.spinner wire:loading />
--}}

@props([
    'size'  => 'md',
    'tone'  => 'default',
    'label' => 'Loading',
])

@php
$sizes = ['xs' => 14, 'sm' => 18, 'md' => 24, 'lg' => 36, 'xl' => 48];
$px = $sizes[$size] ?? 24;
$stroke = $px <= 18 ? 2 : ($px <= 36 ? 2.5 : 3);
$colorMap = [
    'default' => 'var(--text-secondary)',
    'accent'  => 'var(--bronze-500)',
    'inverse' => 'var(--text-inverse)',
];
$color = $colorMap[$tone] ?? $colorMap['default'];
@endphp

<style>
@keyframes onyx-spin { to { transform: rotate(360deg); } }
.onyx-spinner { display: inline-flex; align-items: center; }
.onyx-spinner svg { animation: onyx-spin 700ms linear infinite; }
</style>

<span
  role="status"
  class="onyx-spinner"
  {{ $attributes }}
>
  <svg
    width="{{ $px }}"
    height="{{ $px }}"
    viewBox="0 0 24 24"
    fill="none"
    style="color: {{ $color }};"
  >
    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-opacity="0.18" />
    <path d="M12 2 A10 10 0 0 1 22 12"
          stroke="currentColor"
          stroke-width="{{ $stroke }}"
          stroke-linecap="round" />
  </svg>
  <span class="sr-only">{{ $label }}</span>
</span>

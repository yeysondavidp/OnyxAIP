{{--
  ONYX Skeleton — animated loading placeholder shaped like incoming content.
  Pair with x-show / wire:loading to swap in once data arrives.

  Props:
    lines   int  — text-line placeholders to render   (default: 3)
    avatar  bool — prepend a circular avatar shape    (default: false)
    card    bool — wrap in a card surface             (default: false)

  Usage:
    <x-onyx.skeleton :lines="4" />
    <x-onyx.skeleton :avatar="true" :lines="2" :card="true" />

    {{-- Swap with Livewire: --}}
    <div wire:loading><x-onyx.skeleton :lines="3" /></div>
    <div wire:loading.remove>{{ $slot }}</div>
--}}

@props([
    'lines'  => 3,
    'avatar' => false,
    'card'   => false,
])

<style>
@keyframes onyx-shimmer {
  0%   { background-position: -400px 0; }
  100% { background-position: 400px 0; }
}
.onyx-skeleton-line {
  height: 14px;
  border-radius: var(--radius-sm);
  background: linear-gradient(
    90deg,
    var(--surface-sunken) 25%,
    var(--onyx-150) 50%,
    var(--surface-sunken) 75%
  );
  background-size: 800px 100%;
  animation: onyx-shimmer 1.4s ease-in-out infinite;
}
.onyx-skeleton-avatar {
  width: 36px; height: 36px;
  border-radius: var(--radius-circle);
  background: linear-gradient(
    90deg,
    var(--surface-sunken) 25%,
    var(--onyx-150) 50%,
    var(--surface-sunken) 75%
  );
  background-size: 800px 100%;
  animation: onyx-shimmer 1.4s ease-in-out infinite;
  flex-shrink: 0;
}
@media (prefers-reduced-motion: reduce) {
  .onyx-skeleton-line, .onyx-skeleton-avatar { animation: none; }
}
</style>

@php
$widths = ['100%', '85%', '70%', '90%', '60%', '80%', '75%', '95%'];
@endphp

<div
    role="status"
    aria-label="Loading…"
    {{ $attributes->merge([
        'style' => $card
            ? 'background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: var(--space-5);'
            : ''
    ]) }}
>
    <div style="display: flex; align-items: flex-start; gap: var(--space-3);">

        @if ($avatar)
            <div class="onyx-skeleton-avatar" aria-hidden="true"></div>
        @endif

        <div style="flex: 1; display: flex; flex-direction: column; gap: var(--space-3);">
            @for ($i = 0; $i < $lines; $i++)
                <div
                    class="onyx-skeleton-line"
                    aria-hidden="true"
                    style="width: {{ $widths[$i % count($widths)] }};"
                ></div>
            @endfor
        </div>

    </div>
    <span class="sr-only">Loading…</span>
</div>

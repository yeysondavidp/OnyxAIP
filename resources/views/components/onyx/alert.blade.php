{{--
  ONYX Alert — inline contextual message tied to an action or region.
  For transient floating feedback use the Toast helper instead.

  Props:
    tone       info | positive | caution | critical    (default: info)
    title      string — optional bold header line
    dismissible bool — shows an × button                (default: false)

  Usage:
    <x-onyx.alert tone="caution" title="Unsaved changes">
      Your edits will be lost if you navigate away.
    </x-onyx.alert>

    <x-onyx.alert tone="positive" :dismissible="true">
      Job validated successfully.
    </x-onyx.alert>
--}}

@props([
    'tone'        => 'info',
    'title'       => null,
    'dismissible' => false,
])

@php
$icons = [
    'info'     => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 7v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="8" cy="5" r="0.75" fill="currentColor"/></svg>',
    'positive' => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M5 8l2.5 2.5L11 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'caution'  => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 2L14.5 13.5H1.5L8 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 7v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="8" cy="11.5" r="0.75" fill="currentColor"/></svg>',
    'critical' => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
];
$icon = $icons[$tone] ?? $icons['info'];
$toneClass = "onyx-alert--{$tone}";
@endphp

<style>
.onyx-alert {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-md);
  font-family: var(--font-sans);
  border: 1px solid transparent;
}
.onyx-alert__icon {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 1px;
}
.onyx-alert__body  { flex: 1; min-width: 0; }
.onyx-alert__title {
  font-weight: var(--weight-medium);
  font-size: var(--fs-14);
  margin-bottom: var(--space-1);
}
.onyx-alert__desc  { font-size: var(--fs-14); color: var(--text-secondary); line-height: var(--leading-normal); }
.onyx-alert__dismiss {
  flex-shrink: 0;
  background: none;
  border: none;
  cursor: pointer;
  opacity: 0.6;
  font-size: 14px;
  padding: 2px 4px;
  line-height: 1;
  border-radius: var(--radius-sm);
  color: inherit;
}
.onyx-alert__dismiss:hover { opacity: 1; }

.onyx-alert--info     { background: var(--info-soft);     border-color: var(--info);     color: var(--info); }
.onyx-alert--positive { background: var(--positive-soft); border-color: var(--positive); color: var(--positive); }
.onyx-alert--caution  { background: var(--caution-soft);  border-color: var(--caution);  color: var(--caution); }
.onyx-alert--critical { background: var(--critical-soft); border-color: var(--critical); color: var(--critical); }

.onyx-alert--info     .onyx-alert__desc,
.onyx-alert--positive .onyx-alert__desc,
.onyx-alert--caution  .onyx-alert__desc,
.onyx-alert--critical .onyx-alert__desc { color: var(--text-secondary); }
</style>

<div
  role="alert"
  x-data="{ visible: true }"
  x-show="visible"
  {{ $attributes->merge(['class' => "onyx-alert {$toneClass}"]) }}
>
  <span class="onyx-alert__icon">{!! $icon !!}</span>

  <div class="onyx-alert__body">
    @if ($title)
      <div class="onyx-alert__title">{{ $title }}</div>
    @endif
    @if ($slot->isNotEmpty())
      <div class="onyx-alert__desc">{{ $slot }}</div>
    @endif
  </div>

  @if ($dismissible)
    <button
      type="button"
      class="onyx-alert__dismiss"
      aria-label="Dismiss"
      @click="visible = false"
    >&#x2715;</button>
  @endif
</div>

{{--
  ONYX Progress — determinate progress bar.

  Props:
    value      int|float — current value              (default: 0)
    max        int|float — maximum value              (default: 100)
    tone       default | accent | positive | critical (default: default)
    size       xs | sm | md | lg                      (default: md)
    label      string — optional label                (default: null)
    showValue  bool — show percentage                 (default: false)

  Usage:
    <x-onyx.progress :value="$job->completion_percent" />
    <x-onyx.progress :value="65" tone="accent" label="Uploading" :show-value="true" />
--}}

@props([
    'value'     => 0,
    'max'       => 100,
    'tone'      => 'default',
    'size'      => 'md',
    'label'     => null,
    'showValue' => false,
])

@php
$pct = max(0, min(100, ($value / max($max, 1)) * 100));
$fillMap = [
    'default'  => 'var(--surface-inverse)',
    'accent'   => 'var(--bronze-500)',
    'positive' => 'var(--positive)',
    'critical' => 'var(--critical)',
];
$fill = $fillMap[$tone] ?? $fillMap['default'];
$heights = ['xs' => '2px', 'sm' => '3px', 'md' => '6px', 'lg' => '10px'];
$height = $heights[$size] ?? $heights['md'];
@endphp

<style>
.onyx-progress__header {
  display: flex;
  justify-content: space-between;
  margin-bottom: var(--space-2);
  font-size: var(--fs-13);
  color: var(--text-secondary);
  font-family: var(--font-sans);
}
.onyx-progress__header-value { color: var(--text-muted); }
.onyx-progress__track {
  width: 100%;
  background: var(--surface-sunken);
  border-radius: var(--radius-pill);
  overflow: hidden;
}
.onyx-progress__fill {
  height: 100%;
  border-radius: var(--radius-pill);
  transition: width var(--duration-slow) var(--ease-out);
}
</style>

<div {{ $attributes->merge(['class' => 'onyx-progress']) }}>
  @if ($label || $showValue)
    <div class="onyx-progress__header">
      @if ($label) <span>{{ $label }}</span> @endif
      @if ($showValue) <span class="onyx-progress__header-value">{{ round($pct) }}%</span> @endif
    </div>
  @endif

  <div
    class="onyx-progress__track"
    style="height: {{ $height }};"
    role="progressbar"
    aria-valuenow="{{ $value }}"
    aria-valuemin="0"
    aria-valuemax="{{ $max }}"
  >
    <div
      class="onyx-progress__fill"
      style="width: {{ $pct }}%; background: {{ $fill }};"
    ></div>
  </div>
</div>

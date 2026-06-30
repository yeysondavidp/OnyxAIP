{{--
  ONYX Select — styled native <select> with label, helper, and error.

  Props:
    label    string — uppercase label above the field
    helper   string — hint text below (shown when no error)
    error    string — validation error message (overrides helper, colours border)
    size     sm | md | lg   (default: md)
    id       string — auto-generated if omitted

  Usage:
    <x-onyx.select name="state" label="State" :error="$errors->first('state')">
        <option value="">— Select —</option>
        @foreach (AustralianState::cases() as $s)
            <option value="{{ $s->value }}" @selected(old('state') === $s->value)>
                {{ $s->label() }}
            </option>
        @endforeach
    </x-onyx.select>
--}}

@props([
    'label'  => null,
    'helper' => null,
    'error'  => null,
    'size'   => 'md',
    'id'     => null,
])

@php
$inputId   = $id ?? 'select-' . uniqid();
$sizeClass = 'onyx-select--' . $size;
$stateClass = $error ? 'onyx-select--error' : '';
@endphp

<style>
.onyx-select-wrap {
  position: relative;
  display: flex;
  align-items: center;
  background: var(--surface-raised);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-sm);
  transition: var(--transition-color), box-shadow var(--duration-fast) var(--ease-standard);
}
.onyx-select-wrap:focus-within {
  border-color: var(--bronze-500);
  box-shadow: 0 1px 0 var(--bronze-500);
}
.onyx-select-wrap.onyx-select--error {
  border-color: var(--critical);
}
.onyx-select-wrap.onyx-select--error:focus-within {
  box-shadow: 0 1px 0 var(--critical);
}
.onyx-select-wrap select {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  color: var(--text-primary);
  font-family: var(--font-sans);
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
}
.onyx-select-wrap select:disabled { opacity: 0.6; cursor: not-allowed; }
.onyx-select-wrap.onyx-select--sm select { padding: 8px 36px 8px 12px; font-size: var(--fs-14); }
.onyx-select-wrap.onyx-select--md select { padding: 11px 40px 11px 14px; font-size: var(--fs-16); }
.onyx-select-wrap.onyx-select--lg select { padding: 14px 44px 14px 16px; font-size: var(--fs-18); }
.onyx-select-chevron {
  position: absolute;
  right: 12px;
  pointer-events: none;
  color: var(--text-muted);
  flex-shrink: 0;
}
</style>

<div class="onyx-field">
  @if ($label)
    <label for="{{ $inputId }}" class="onyx-field__label">{{ $label }}</label>
  @endif

  <div class="onyx-select-wrap {{ $sizeClass }} {{ $stateClass }}">
    <select id="{{ $inputId }}" {{ $attributes->except(['class']) }}>
      {{ $slot }}
    </select>
    <svg class="onyx-select-chevron" width="16" height="16" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="6 9 12 15 18 9"/>
    </svg>
  </div>

  @if ($error)
    <span class="onyx-field__hint onyx-field__hint--error" role="alert">{{ $error }}</span>
  @elseif ($helper)
    <span class="onyx-field__hint">{{ $helper }}</span>
  @endif
</div>

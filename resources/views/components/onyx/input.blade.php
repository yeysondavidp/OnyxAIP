{{--
  ONYX Input — refined text field with optional label, helper, and error.

  Props:
    label    string — floating uppercase label above the field
    helper   string — hint text below (shown when no error)
    error    string — validation error message (overrides helper, colours border)
    size     sm | md | lg                                   (default: md)
    id       string — auto-generated if omitted
    name     string — passed through
    type     text | email | password | number | search …   (default: text)

  Usage:
    <x-onyx.input
      name="store_name"
      label="Store name"
      :value="old('store_name', $store->name)"
      :error="$errors->first('store_name')"
    />

    <x-onyx.input name="email" type="email" label="Email address" helper="We'll never share your email." />
--}}

@props([
    'label'  => null,
    'helper' => null,
    'error'  => null,
    'size'   => 'md',
    'id'     => null,
])

@php
$inputId = $id ?? 'input-' . uniqid();
$sizeClass = 'onyx-input--' . $size;
$stateClass = $error ? 'onyx-input--error' : '';
@endphp

<style>
.onyx-field { display: flex; flex-direction: column; gap: var(--space-2); }

.onyx-field__label {
  font: var(--type-label);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wide);
  color: var(--text-secondary);
}

.onyx-input-wrap {
  display: flex;
  align-items: center;
  background: var(--surface-raised);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-sm);
  transition: var(--transition-color), box-shadow var(--duration-fast) var(--ease-standard);
}
.onyx-input-wrap:focus-within {
  border-color: var(--bronze-500);
  box-shadow: 0 1px 0 var(--bronze-500);
}
.onyx-input-wrap.onyx-input--error {
  border-color: var(--critical);
}
.onyx-input-wrap.onyx-input--error:focus-within {
  box-shadow: 0 1px 0 var(--critical);
}

.onyx-input-wrap input {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  background: transparent;
  color: var(--text-primary);
  font-family: var(--font-sans);
}
.onyx-input-wrap input:disabled { opacity: 0.6; cursor: not-allowed; }
.onyx-input-wrap.onyx-input--sm input { padding: 8px 12px; font-size: var(--fs-14); }
.onyx-input-wrap.onyx-input--md input { padding: 11px 14px; font-size: var(--fs-16); }
.onyx-input-wrap.onyx-input--lg input { padding: 14px 16px; font-size: var(--fs-18); }

.onyx-field__hint {
  font: var(--type-caption);
  color: var(--text-muted);
}
.onyx-field__hint--error { color: var(--critical); }
</style>

<div class="onyx-field">
  @if ($label)
    <label for="{{ $inputId }}" class="onyx-field__label">{{ $label }}</label>
  @endif

  <div class="onyx-input-wrap {{ $sizeClass }} {{ $stateClass }}">
    <input
      id="{{ $inputId }}"
      {{ $attributes->except(['class']) }}
    />
  </div>

  @if ($error)
    <span class="onyx-field__hint onyx-field__hint--error" role="alert">{{ $error }}</span>
  @elseif ($helper)
    <span class="onyx-field__hint">{{ $helper }}</span>
  @endif
</div>

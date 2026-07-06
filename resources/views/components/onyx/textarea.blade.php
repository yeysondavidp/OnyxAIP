{{--
  ONYX Textarea — styled multi-line text area with label, helper, and error.

  Props:
    label   string — uppercase label above the field
    helper  string — hint text below (shown when no error)
    error   string — validation error message
    rows    int    — visible rows (default: 4)
    id      string — auto-generated if omitted

  Usage:
    <x-onyx.textarea name="notes" label="Notes" :error="$errors->first('notes')"
                     :value="old('notes', $client->notes)" rows="5" />
--}}

@props([
    'label'  => null,
    'helper' => null,
    'error'  => null,
    'rows'   => 4,
    'id'     => null,
    'value'  => null,
])

@php
$inputId    = $id ?? 'textarea-' . uniqid();
$stateClass = $error ? 'onyx-input--error' : '';
@endphp

<style>
.onyx-input-wrap textarea {
  flex: 1;
  width: 100%;
  min-width: 0;
  border: none;
  outline: none;
  background: transparent;
  color: var(--text-primary);
  font-family: var(--font-sans);
}
.onyx-field__required { color: var(--critical); margin-left: 2px; }
</style>

<div class="onyx-field">
  @if ($label)
    <label for="{{ $inputId }}" class="onyx-field__label">
      {{ $label }}@if ($attributes->has('required'))<span class="onyx-field__required" aria-hidden="true">*</span>@endif
    </label>
  @endif

  <div class="onyx-input-wrap onyx-input--md {{ $stateClass }}" style="align-items: flex-start;">
    <textarea
      id="{{ $inputId }}"
      rows="{{ $rows }}"
      style="padding: 11px 14px; font-size: var(--fs-16); resize: vertical; min-height: 90px;"
      {{ $attributes->except(['class']) }}
    >{{ $slot->isNotEmpty() ? $slot : $value }}</textarea>
  </div>

  @if ($error)
    <span class="onyx-field__hint onyx-field__hint--error" role="alert">{{ $error }}</span>
  @elseif ($helper)
    <span class="onyx-field__hint">{{ $helper }}</span>
  @endif
</div>

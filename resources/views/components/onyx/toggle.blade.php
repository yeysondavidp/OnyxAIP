{{--
  ONYX Toggle — on/off switch. Requires Alpine.js.

  Props:
    name          string — form field name
    label         string — visible label text
    labelPosition left | right                  (default: right)
    size          sm | md | lg                  (default: md)
    checked       bool                          (default: false)
    disabled      bool                          (default: false)
    id            string — auto-generated if omitted

  Usage:
    <x-onyx.toggle name="notifications" label="Enable notifications" />
    <x-onyx.toggle name="active" label="Active" :checked="$store->is_active" />

    Wire it to Livewire:
    <x-onyx.toggle name="is_active" label="Active" wire:model="is_active" />
--}}

@props([
    'name'          => '',
    'label'         => null,
    'labelPosition' => 'right',
    'size'          => 'md',
    'checked'       => false,
    'disabled'      => false,
    'id'            => null,
])

@php
$toggleId  = $id ?? 'toggle-' . uniqid();
$sizeClass = 'onyx-toggle--' . $size;
$dirClass  = $label && $labelPosition === 'left' ? 'onyx-toggle-wrap--reverse' : '';
$checkedJs = $checked ? 'true' : 'false';
@endphp

<style>
.onyx-toggle-wrap {
  display: inline-flex;
  align-items: center;
  gap: var(--space-3);
  cursor: pointer;
  font-family: var(--font-sans);
  font-size: var(--fs-14);
  color: var(--text-primary);
  user-select: none;
}
.onyx-toggle-wrap--reverse { flex-direction: row-reverse; }
.onyx-toggle-wrap[data-disabled] { cursor: not-allowed; opacity: 0.45; }

.onyx-toggle {
  position: relative;
  flex-shrink: 0;
  border-radius: 999px;
  border: 1px solid var(--border-default);
  background: var(--surface-sunken);
  cursor: pointer;
  transition: background var(--duration-fast) var(--ease-standard),
              border-color var(--duration-fast) var(--ease-standard);
}
.onyx-toggle[aria-checked="true"]  { background: var(--bronze-500); border-color: transparent; }
.onyx-toggle:focus-visible         { outline: 2px solid var(--focus-ring); outline-offset: 2px; }

.onyx-toggle--sm { width: 28px; height: 16px; }
.onyx-toggle--md { width: 36px; height: 20px; }
.onyx-toggle--lg { width: 44px; height: 24px; }

.onyx-toggle__thumb {
  position: absolute;
  border-radius: 50%;
  background: var(--text-muted);
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
  transition: transform var(--duration-base) var(--ease-out),
              background var(--duration-fast) var(--ease-standard);
}
.onyx-toggle[aria-checked="true"] .onyx-toggle__thumb { background: #fff; }

.onyx-toggle--sm .onyx-toggle__thumb { width: 10px; height: 10px; top: 2px; left: 2px; }
.onyx-toggle--md .onyx-toggle__thumb { width: 14px; height: 14px; top: 2px; left: 2px; }
.onyx-toggle--lg .onyx-toggle__thumb { width: 18px; height: 18px; top: 2px; left: 2px; }

.onyx-toggle--sm[aria-checked="true"] .onyx-toggle__thumb { transform: translateX(12px); }
.onyx-toggle--md[aria-checked="true"] .onyx-toggle__thumb { transform: translateX(16px); }
.onyx-toggle--lg[aria-checked="true"] .onyx-toggle__thumb { transform: translateX(20px); }
</style>

<div
  class="onyx-toggle-wrap {{ $dirClass }}"
  @if ($disabled) data-disabled @endif
  x-data="{ on: {{ $checkedJs }} }"
>
  <button
    type="button"
    id="{{ $toggleId }}"
    role="switch"
    :aria-checked="on.toString()"
    class="onyx-toggle {{ $sizeClass }}"
    @if ($disabled) disabled @endif
    @click="on = !on"
  >
    <span class="onyx-toggle__thumb"></span>
  </button>

  {{-- hidden input carries the value for form submission --}}
  <input
    type="hidden"
    name="{{ $name }}"
    :value="on ? '1' : '0'"
    {{ $attributes->only(['wire:model', 'x-model']) }}
  />

  @if ($label)
    <label for="{{ $toggleId }}">{{ $label }}</label>
  @endif
</div>

{{--
  ONYX Dialog — modal overlay. Requires Alpine.js.

  This component provides a reusable modal shell. Control it via Alpine
  or Livewire from the parent. Pass a triggering x-data model on the parent.

  Named slots:
    trigger — the element that opens the dialog
    footer  — custom footer actions (overrides default confirm/cancel)
    default — body content

  Props:
    title        string                                   (default: null)
    description  string                                   (default: null)
    size         sm | md | lg | xl                        (default: md)
    confirmLabel string                                   (default: "Confirm")
    cancelLabel  string                                   (default: "Cancel")
    confirmTone  solid | accent | critical                (default: solid)
    hideCancel   bool                                     (default: false)

  Usage:
    <x-onyx.dialog
      title="Delete asset?"
      description="This action cannot be undone."
      confirm-label="Delete"
      confirm-tone="critical"
    >
      <x-slot:trigger>
        <x-onyx.button variant="outline" size="sm" @click="$dispatch('open-dialog', 'delete-asset')">
          Delete
        </x-onyx.button>
      </x-slot:trigger>
    </x-onyx.dialog>

  For Livewire modal pattern, listen to $wire events or use a wire:click on the confirm button.
--}}

@props([
    'title'        => null,
    'description'  => null,
    'size'         => 'md',
    'confirmLabel' => 'Confirm',
    'cancelLabel'  => 'Cancel',
    'confirmTone'  => 'solid',
    'hideCancel'   => false,
])

@php
$widths = ['sm' => '360px', 'md' => '480px', 'lg' => '600px', 'xl' => '760px'];
$maxWidth = $widths[$size] ?? $widths['md'];
$confirmBgMap = [
    'solid'    => 'var(--surface-inverse)',
    'accent'   => 'var(--bronze-500)',
    'critical' => 'var(--critical)',
];
$confirmBg    = $confirmBgMap[$confirmTone] ?? $confirmBgMap['solid'];
$confirmColor = 'var(--text-inverse)';
@endphp

<style>
.onyx-dialog-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(14, 12, 10, 0.6);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  padding: var(--space-6);
}
.onyx-dialog {
  width: 100%;
  background: var(--surface-card);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-xl);
  font-family: var(--font-sans);
  overflow: hidden;
}
.onyx-dialog__header { padding: var(--space-6) var(--space-6) 0; }
.onyx-dialog__title {
  margin: 0;
  font-size: var(--fs-20);
  font-weight: var(--weight-medium);
  color: var(--text-primary);
  line-height: var(--leading-snug);
}
.onyx-dialog__desc {
  margin: var(--space-2) 0 0;
  font-size: var(--fs-14);
  color: var(--text-secondary);
  line-height: var(--leading-normal);
}
.onyx-dialog__body { padding: var(--space-4) var(--space-6); }
.onyx-dialog__footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
  padding: var(--space-5) var(--space-6) var(--space-6);
  border-top: 1px solid var(--border-subtle);
}
.onyx-dialog__btn {
  height: 36px;
  padding: 0 var(--space-5);
  border-radius: var(--radius-sm);
  font-family: var(--font-sans);
  font-size: var(--fs-14);
  font-weight: var(--weight-medium);
  cursor: pointer;
  border: 1px solid transparent;
  transition: var(--transition-color);
}
.onyx-dialog__btn--cancel {
  background: transparent;
  border-color: var(--border-default);
  color: var(--text-primary);
}
.onyx-dialog__btn--cancel:hover { background: var(--surface-sunken); }
</style>

<div x-data="{ open: false }" {{ $attributes }}>
  {{-- Trigger --}}
  @isset ($trigger)
    <span @click="open = true">{{ $trigger }}</span>
  @endisset

  {{-- Overlay --}}
  <div
    x-show="open"
    x-cloak
    class="onyx-dialog-overlay"
    @keydown.escape.window="open = false"
    @click.self="open = false"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-labelledby="onyx-dialog-title-{{ uniqid() }}" @endif
  >
    <div class="onyx-dialog" style="max-width: {{ $maxWidth }};">
      <div class="onyx-dialog__header">
        @if ($title)
          <h2 class="onyx-dialog__title">{{ $title }}</h2>
        @endif
        @if ($description)
          <p class="onyx-dialog__desc">{{ $description }}</p>
        @endif
      </div>

      @if ($slot->isNotEmpty())
        <div class="onyx-dialog__body">{{ $slot }}</div>
      @endif

      <div class="onyx-dialog__footer">
        @isset ($footer)
          {{ $footer }}
        @else
          @if (!$hideCancel)
            <button type="button" class="onyx-dialog__btn onyx-dialog__btn--cancel" @click="open = false">
              {{ $cancelLabel }}
            </button>
          @endif
          <button
            type="button"
            class="onyx-dialog__btn"
            style="background: {{ $confirmBg }}; color: {{ $confirmColor }};"
            @click="$dispatch('onyx-dialog-confirm'); open = false"
          >
            {{ $confirmLabel }}
          </button>
        @endisset
      </div>
    </div>
  </div>
</div>

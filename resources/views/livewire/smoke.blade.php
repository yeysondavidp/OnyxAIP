<div style="display: flex; flex-direction: column; gap: var(--space-6);">

    {{-- Livewire smoke: server-driven counter --}}
    <div style="display: flex; align-items: center; gap: var(--space-4);">
        <x-onyx.eyebrow>Livewire</x-onyx.eyebrow>
        <span style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); min-width: 2ch; text-align: center;">
            {{ $count }}
        </span>
        <x-onyx.button wire:click="increment" variant="accent" size="sm">
            Increment
        </x-onyx.button>
        @if ($count > 0)
            <x-onyx.badge tone="positive" variant="soft">Working ✓</x-onyx.badge>
        @endif
    </div>

</div>

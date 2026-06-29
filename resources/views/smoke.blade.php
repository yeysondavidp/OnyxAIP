<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Smoke Test — ONYX AIP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body style="background: var(--surface-page); color: var(--text-primary); padding: var(--space-12); font-family: var(--font-sans);">

<div style="max-width: 640px; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-8);">

    <div>
        <img src="{{ asset('images/onyx/wordmark-black.svg') }}" alt="ONYX AIP" style="height: 24px; margin-bottom: var(--space-4);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); margin-bottom: var(--space-2);">Smoke Test</h1>
        <p style="color: var(--text-secondary); font-size: var(--fs-14);">
            Confirms Laravel, Livewire, and Alpine all render correctly.
        </p>
    </div>

    <x-onyx.divider />

    {{-- Livewire section --}}
    <div>
        <x-onyx.eyebrow style="margin-bottom: var(--space-3);">Livewire (server-driven)</x-onyx.eyebrow>
        <x-onyx.card variant="default" padding="md">
            <livewire:smoke />
        </x-onyx.card>
    </div>

    {{-- Alpine section --}}
    <div>
        <x-onyx.eyebrow style="margin-bottom: var(--space-3);">Alpine.js (client-side)</x-onyx.eyebrow>
        <x-onyx.card variant="default" padding="md">
            <div x-data="{ visible: false, count: 0 }" style="display: flex; flex-direction: column; gap: var(--space-4);">

                <div style="display: flex; align-items: center; gap: var(--space-4);">
                    <x-onyx.button @click="visible = !visible" variant="outline" size="sm">
                        Toggle (Alpine)
                    </x-onyx.button>
                    <span x-show="visible" x-transition style="font-size: var(--fs-14); color: var(--text-secondary);">
                        Alpine x-show is working ✓
                    </span>
                </div>

                <div style="display: flex; align-items: center; gap: var(--space-4);">
                    <x-onyx.eyebrow>Alpine counter</x-onyx.eyebrow>
                    <span x-text="count" style="font-size: var(--fs-24); font-weight: var(--weight-bold); min-width: 2ch;"></span>
                    <x-onyx.button @click="count++" variant="accent" size="sm">
                        Increment
                    </x-onyx.button>
                    <x-onyx.badge x-show="count > 0" tone="positive" variant="soft">Working ✓</x-onyx.badge>
                </div>

            </div>
        </x-onyx.card>
    </div>

    {{-- Timezone helper --}}
    <div>
        <x-onyx.eyebrow style="margin-bottom: var(--space-3);">Timezone helper</x-onyx.eyebrow>
        <x-onyx.card variant="default" padding="md">
            <div style="display: flex; flex-direction: column; gap: var(--space-2); font-size: var(--fs-14);">
                <div style="display: flex; gap: var(--space-4);">
                    <span style="color: var(--text-secondary); width: 120px;">UTC stored:</span>
                    <code style="color: var(--text-primary);">{{ now()->toIso8601String() }}</code>
                </div>
                <div style="display: flex; gap: var(--space-4);">
                    <span style="color: var(--text-secondary); width: 120px;">Sydney:</span>
                    <code style="color: var(--text-primary);">{{ \App\Support\Tz::display(now(), 'Australia/Sydney') }}</code>
                </div>
                <div style="display: flex; gap: var(--space-4);">
                    <span style="color: var(--text-secondary); width: 120px;">Perth:</span>
                    <code style="color: var(--text-primary);">{{ \App\Support\Tz::display(now(), 'Australia/Perth') }}</code>
                </div>
            </div>
        </x-onyx.card>
    </div>

    {{-- DB connection --}}
    <div>
        <x-onyx.eyebrow style="margin-bottom: var(--space-3);">Database</x-onyx.eyebrow>
        <x-onyx.card variant="default" padding="md">
            @php
                try {
                    $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
                    $dbStatus = 'MySQL connected — ' . count($tables) . ' tables';
                    $dbOk = true;
                } catch (\Exception $e) {
                    $dbStatus = 'DB error: ' . $e->getMessage();
                    $dbOk = false;
                }
            @endphp
            <x-onyx.badge :tone="$dbOk ? 'positive' : 'critical'" variant="soft">{{ $dbStatus }}</x-onyx.badge>
        </x-onyx.card>
    </div>

</div>

@livewireScripts
</body>
</html>

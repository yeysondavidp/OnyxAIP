@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title . ' — ONYX AIP' : 'ONYX Asset Intelligence Platform' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full" data-theme="onyx" style="background: var(--surface-page);">

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--space-6);">
    <div style="width: 100%; max-width: 420px;">

        {{-- Logo --}}
        <div style="text-align: center; margin-bottom: var(--space-10);">
            <img src="{{ asset('images/onyx/mark-light.svg') }}" alt="ONYX" style="width: 48px; height: auto; margin: 0 auto var(--space-4);">
            <p style="font-size: var(--fs-12); color: var(--onyx-400); text-transform: uppercase; letter-spacing: var(--tracking-wider); font-weight: var(--weight-medium);">
                Asset Intelligence Platform
            </p>
        </div>

        {{-- Auth card --}}
        <div style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-xl); padding: var(--space-8); box-shadow: var(--shadow-xl);">
            {{ $slot }}
        </div>

    </div>
</div>

@livewireScripts
</body>
</html>

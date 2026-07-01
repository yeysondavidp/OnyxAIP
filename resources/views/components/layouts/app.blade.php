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
<body class="h-full" style="background: var(--surface-page); color: var(--text-primary);">

<div style="display: flex; height: 100%;">

    {{-- ---- Sidebar ---- --}}
    <aside style="width: 240px; flex-shrink: 0; background: var(--onyx-900); color: var(--onyx-50); display: flex; flex-direction: column; position: fixed; top: 0; bottom: 0; left: 0; z-index: 40;">

        {{-- Logo --}}
        <div style="padding: var(--space-6) var(--space-5) var(--space-5); border-bottom: 1px solid var(--onyx-800);">
            <a href="{{ route('dashboard') }}" style="display: block;">
                <img src="{{ asset('images/onyx/wordmark-light.svg') }}" alt="ONYX" style="height: 20px; width: auto;">
            </a>
            <p style="font-size: var(--fs-12); color: var(--onyx-400); margin-top: var(--space-2); letter-spacing: var(--tracking-wide); text-transform: uppercase; font-weight: var(--weight-medium);">
                Asset Intelligence
            </p>
        </div>

        {{-- Primary navigation --}}
        <nav style="flex: 1; overflow-y: auto; padding: var(--space-4) 0;" aria-label="Main navigation">

            <div style="padding: var(--space-2) var(--space-5) var(--space-1);">
                <x-onyx.eyebrow as="span" tone="muted" style="color: var(--onyx-500); font-size: 10px;">Operations</x-onyx.eyebrow>
            </div>

            <x-layouts.nav-item route="dashboard" icon="grid">Dashboard</x-layouts.nav-item>
            <x-layouts.nav-item route="clients.index" icon="briefcase">Clients</x-layouts.nav-item>
            <x-layouts.nav-item route="stores.index" icon="map-pin">Stores</x-layouts.nav-item>
            <x-layouts.nav-item route="sla-profiles.index" icon="shield">SLA Profiles</x-layouts.nav-item>

            <div style="padding: var(--space-5) var(--space-5) var(--space-1);">
                <x-onyx.eyebrow as="span" tone="muted" style="color: var(--onyx-500); font-size: 10px;">Assets</x-onyx.eyebrow>
            </div>

            <x-layouts.nav-item route="assets.index" icon="monitor">Asset Registry</x-layouts.nav-item>

            <div style="padding: var(--space-5) var(--space-5) var(--space-1);">
                <x-onyx.eyebrow as="span" tone="muted" style="color: var(--onyx-500); font-size: 10px;">Service</x-onyx.eyebrow>
            </div>

            <x-layouts.nav-item route="jobs.index" icon="tool">Service Jobs</x-layouts.nav-item>
            <x-layouts.nav-item route="technicians.index" icon="users">Technicians</x-layouts.nav-item>

            <div style="padding: var(--space-5) var(--space-5) var(--space-1);">
                <x-onyx.eyebrow as="span" tone="muted" style="color: var(--onyx-500); font-size: 10px;">Reporting</x-onyx.eyebrow>
            </div>

            <x-layouts.nav-item route="reports.index" icon="bar-chart-2">Reports</x-layouts.nav-item>

            <div style="padding: var(--space-5) var(--space-5) var(--space-1);">
                <x-onyx.eyebrow as="span" tone="muted" style="color: var(--onyx-500); font-size: 10px;">Admin</x-onyx.eyebrow>
            </div>

            <x-layouts.nav-item route="settings.edit" icon="settings">Settings</x-layouts.nav-item>

        </nav>

        {{-- User block --}}
        <div style="padding: var(--space-4) var(--space-5); border-top: 1px solid var(--onyx-800);">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <x-onyx.avatar :name="auth()->user()->name ?? 'PM'" size="sm" tone="accent" />
                <div style="flex: 1; min-width: 0;">
                    <p style="font-size: var(--fs-13); font-weight: var(--weight-medium); color: var(--onyx-50); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ auth()->user()->name ?? 'Project Manager' }}
                    </p>
                    <p style="font-size: var(--fs-12); color: var(--onyx-400); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ auth()->user()->email ?? '' }}
                    </p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-onyx.icon-button type="submit" variant="ghost" size="sm" aria-label="Sign out" style="color: var(--onyx-400);">
                        <x-icon name="log-out" size="16" />
                    </x-onyx.icon-button>
                </form>
            </div>
        </div>

    </aside>

    {{-- ---- Main content ---- --}}
    <div style="flex: 1; margin-left: 240px; display: flex; flex-direction: column; min-height: 100%;">

        {{-- Top header --}}
        <header style="position: sticky; top: 0; z-index: 30; background: var(--surface-page); border-bottom: 1px solid var(--border-subtle); padding: 0 var(--space-8);">
            <div style="display: flex; align-items: center; justify-content: space-between; height: 60px; gap: var(--space-4);">

                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    @isset($breadcrumbs)
                        {{ $breadcrumbs }}
                    @else
                        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">
                            {{ $title ?? 'Dashboard' }}
                        </span>
                    @endisset
                </div>

                @isset($headerActions)
                    <div style="display: flex; align-items: center; gap: var(--space-3);">
                        {{ $headerActions }}
                    </div>
                @endisset

            </div>
        </header>

        {{-- Page body --}}
        <main style="flex: 1; padding: var(--space-8);">

            @if (session('success'))
                <div style="margin-bottom: var(--space-6);">
                    <x-onyx.alert tone="positive" :dismissible="true">{{ session('success') }}</x-onyx.alert>
                </div>
            @endif
            @if (session('error'))
                <div style="margin-bottom: var(--space-6);">
                    <x-onyx.alert tone="critical" :dismissible="true">{{ session('error') }}</x-onyx.alert>
                </div>
            @endif

            {{ $slot }}
        </main>

    </div>
</div>

@livewireScripts
</body>
</html>

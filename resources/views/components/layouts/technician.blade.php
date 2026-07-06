@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#141414">

    <title>{{ $title ? $title . ' — ONYX' : 'ONYX Service' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            background: var(--surface-page);
            min-height: 100dvh;
            padding-bottom: env(safe-area-inset-bottom);
        }
        .tech-shell {
            display: flex;
            flex-direction: column;
            min-height: 100dvh;
            max-width: 480px;
            margin: 0 auto;
        }
        /* Shared so .tech-screen-header's sticky offset always lines up with
           this header's actual rendered height, including the safe-area
           inset on notched devices — both stick at the same time, so a
           mismatched offset would overlap or leave a gap while scrolling. */
        :root {
            --tech-header-height: calc(20px + var(--space-4) * 2 + env(safe-area-inset-top));
        }
        .tech-header {
            position: sticky;
            top: 0;
            z-index: 30;
            height: var(--tech-header-height);
            box-sizing: border-box;
            background: var(--onyx-900);
            color: var(--onyx-50);
            padding: var(--space-4) var(--space-5);
            padding-top: calc(var(--space-4) + env(safe-area-inset-top));
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        .tech-header__logo { height: 20px; width: auto; }
        .tech-header__title {
            flex: 1;
            font-size: var(--fs-14);
            font-weight: var(--weight-medium);
            color: var(--onyx-50);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Each screen's own title block (step label + h1) reuses the dark sticky
           header treatment but needs a vertical stack, not the logo bar's flex
           row — sharing .tech-header directly squeezed both lines onto one row
           and could clip long job/client names. */
        .tech-screen-header {
            position: sticky;
            top: var(--tech-header-height);
            z-index: 20;
            background: var(--onyx-900);
            padding: var(--space-4) var(--space-5);
        }

        /* The design system's base reset (h1, h2, ... { color: var(--text-primary) })
           is an element selector — it beats plain inheritance, so an unstyled
           <h1> on this dark background renders dark-on-dark and disappears.
           Match its specificity to win back the light color. */
        .tech-screen-header h1 {
            color: var(--onyx-50);
        }
        .tech-main { flex: 1; padding: var(--space-5); }
        .tech-footer {
            position: sticky;
            bottom: 0;
            background: var(--surface-page);
            border-top: 1px solid var(--border-subtle);
            padding: var(--space-4) var(--space-5);
            padding-bottom: calc(var(--space-4) + env(safe-area-inset-bottom));
        }
        .tech-cta { display: flex; flex-direction: column; gap: var(--space-3); }
    </style>
</head>
<body>

<div class="tech-shell">

    <header class="tech-header">
        <img src="{{ asset('images/onyx/wordmark-light.svg') }}" alt="ONYX" class="tech-header__logo">

        @isset($headerTitle)
            <span class="tech-header__title">{{ $headerTitle }}</span>
        @endisset

        @isset($headerActions)
            {{ $headerActions }}
        @endisset
    </header>

    @if (session('error'))
        <div style="padding: var(--space-4) var(--space-5) 0;">
            <x-onyx.alert tone="critical">{{ session('error') }}</x-onyx.alert>
        </div>
    @endif

    <main class="tech-main">
        {{ $slot }}
    </main>

    @isset($footer)
        <footer class="tech-footer">
            <div class="tech-cta">{{ $footer }}</div>
        </footer>
    @endisset

</div>

@livewireScripts
</body>
</html>

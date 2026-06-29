{{--
  Sidebar nav item.

  Props:
    route  string — named route
    icon   string — Feather icon name (stroke, 1.5px)
--}}

@props(['route', 'icon' => null])

@php
$isActive = request()->routeIs($route) || request()->routeIs($route . '.*');
$activeBg   = $isActive ? 'background: var(--onyx-800);' : '';
$activeColor = $isActive ? 'color: var(--onyx-50);' : 'color: var(--onyx-400);';
$iconColor   = $isActive ? 'color: var(--bronze-400);' : 'color: var(--onyx-500);';
@endphp

<a
    href="{{ route($route) }}"
    style="
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-2) var(--space-5);
        font-size: var(--fs-14);
        font-weight: {{ $isActive ? 'var(--weight-medium)' : 'var(--weight-regular)' }};
        text-decoration: none;
        transition: var(--transition-color);
        border-radius: 0;
        {{ $activeBg }}
        {{ $activeColor }}
    "
    @if ($isActive) aria-current="page" @endif
>
    @if ($icon)
        <span style="flex-shrink: 0; width: 16px; {{ $iconColor }}">
            <x-icon :name="$icon" size="16" />
        </span>
    @endif
    <span>{{ $slot }}</span>

    @if ($isActive)
        <span style="margin-left: auto; width: 3px; height: 16px; background: var(--bronze-500); border-radius: var(--radius-pill);"></span>
    @endif
</a>

{{--
  ONYX Empty — vacant-state panel: icon, heading, explanatory body, optional CTA slot.
  Use in any list, table, or card region that has no data yet.

  Props:
    icon     string — Feather icon name (default: 'inbox')
    heading  string — brief statement    (default: 'Nothing here yet')
    body     string — explanatory copy   (optional; slot content shown below heading)
    size     sm | md | lg               (default: md)

  Usage:
    <x-onyx.empty icon="tool" heading="No service jobs yet"
                  body="Create a job to dispatch a technician.">
        <x-onyx.button href="{{ route('jobs.create') }}" variant="accent" size="sm">
            Create service job
        </x-onyx.button>
    </x-onyx.empty>
--}}

@props([
    'icon'    => 'inbox',
    'heading' => 'Nothing here yet',
    'body'    => null,
    'size'    => 'md',
])

@php
$paddings = ['sm' => 'var(--space-8)', 'md' => 'var(--space-12)', 'lg' => 'var(--space-16)'];
$iconSizes = ['sm' => 24, 'md' => 32, 'lg' => 40];
$padding  = $paddings[$size] ?? $paddings['md'];
$iconSize = $iconSizes[$size] ?? $iconSizes['md'];
@endphp

<div
    role="status"
    {{ $attributes->merge(['style' => "text-align: center; padding: {$padding};"]) }}
>
    <div style="display: inline-flex; align-items: center; justify-content: center;
                width: {{ $iconSize * 2 }}px; height: {{ $iconSize * 2 }}px;
                border-radius: var(--radius-circle);
                background: var(--surface-sunken);
                margin: 0 auto var(--space-4);">
        <x-icon :name="$icon" :size="$iconSize" style="color: var(--text-muted);" />
    </div>

    <p style="font-size: var(--fs-15); font-weight: var(--weight-medium);
              color: var(--text-primary); margin-bottom: var(--space-2);">
        {{ $heading }}
    </p>

    @if ($body)
        <p style="font-size: var(--fs-14); color: var(--text-secondary);
                  max-width: 36ch; margin: 0 auto var(--space-5);">
            {{ $body }}
        </p>
    @endif

    @if ($slot->isNotEmpty())
        <div style="display: inline-flex; flex-direction: column; align-items: center;
                    gap: var(--space-3); margin-top: {{ $body ? '0' : 'var(--space-4)' }};">
            {{ $slot }}
        </div>
    @endif
</div>

{{--
  ONYX Avatar — initials or image.

  Props:
    name  string — used to derive initials and alt text
    src   string — image URL (optional)
    size  xs | sm | md | lg | xl                       (default: md)
    round bool — circular rather than square-ish       (default: false)
    tone  neutral | ink | accent                       (default: neutral)

  Usage:
    <x-onyx.avatar name="Sarah Chen" />
    <x-onyx.avatar name="James O'Brien" size="lg" tone="ink" />
    <x-onyx.avatar name="Pandora" :src="$client->logo_url" />
--}}

@props([
    'name'  => '',
    'src'   => null,
    'size'  => 'md',
    'round' => false,
    'tone'  => 'neutral',
])

@php
$initials = collect(explode(' ', trim($name)))
    ->filter()
    ->take(2)
    ->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))
    ->implode('');

$sizeClass  = 'onyx-avatar--' . $size;
$toneClass  = 'onyx-avatar--' . $tone;
$shapeClass = $round ? 'onyx-avatar--round' : '';
@endphp

<style>
.onyx-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-family: var(--font-sans);
  font-weight: var(--weight-medium);
  letter-spacing: 0.02em;
  border-radius: var(--radius-md);
  overflow: hidden;
  user-select: none;
}
.onyx-avatar img { width: 100%; height: 100%; object-fit: cover; }
.onyx-avatar--round { border-radius: var(--radius-pill); }

.onyx-avatar--xs { width: 24px; height: 24px; font-size: 8px; }
.onyx-avatar--sm { width: 32px; height: 32px; font-size: 11px; }
.onyx-avatar--md { width: 40px; height: 40px; font-size: 14px; }
.onyx-avatar--lg { width: 52px; height: 52px; font-size: 18px; }
.onyx-avatar--xl { width: 72px; height: 72px; font-size: 26px; }

.onyx-avatar--neutral { background: var(--onyx-100); color: var(--onyx-700); }
.onyx-avatar--ink     { background: var(--onyx-900); color: var(--onyx-25); }
.onyx-avatar--accent  { background: var(--bronze-500); color: var(--onyx-25); }
</style>

<span {{ $attributes->merge(['class' => "onyx-avatar {$sizeClass} {$toneClass} {$shapeClass}"]) }}
      title="{{ $name }}">
  @if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" />
  @else
    {{ $initials }}
  @endif
</span>

{{--
  ONYX Tabs — horizontal tab navigation. Requires Alpine.js.

  Props:
    items    array — each item: ['key' => string, 'label' => string, 'count' => int|null]
    active   string — currently active key
    variant  underline | pill                               (default: underline)
    size     sm | md | lg                                   (default: md)

  Usage (stateless — controlled by URL or Livewire):
    <x-onyx.tabs
      :items="[
        ['key' => 'overview', 'label' => 'Overview'],
        ['key' => 'assets',   'label' => 'Assets', 'count' => $assetCount],
        ['key' => 'jobs',     'label' => 'Service Jobs'],
      ]"
      active="{{ request('tab', 'overview') }}"
    />

  Each tab generates a link: ?tab={key}. Override by passing custom items with 'href'.
--}}

@props([
    'items'   => [],
    'active'  => null,
    'variant' => 'underline',
    'size'    => 'md',
])

@php
$variantCls = 'onyx-tabs--' . $variant;
$sizeCls    = 'onyx-tabs--' . $size;
@endphp

<style>
.onyx-tabs {
  display: flex;
  align-items: stretch;
  font-family: var(--font-sans);
}
.onyx-tabs--underline {
  border-bottom: 1px solid var(--border-subtle);
  gap: 0;
}
.onyx-tabs--pill {
  background: var(--surface-sunken);
  border-radius: var(--radius-md);
  padding: var(--space-1);
  gap: var(--space-1);
}

.onyx-tab {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  text-decoration: none;
  color: var(--text-secondary);
  font-weight: var(--weight-regular);
  white-space: nowrap;
  border: none;
  background: none;
  cursor: pointer;
  transition: color var(--duration-fast) var(--ease-standard),
              background var(--duration-fast) var(--ease-standard);
}
.onyx-tab--sm { padding: var(--space-2) var(--space-3); font-size: var(--fs-13); }
.onyx-tab--md { padding: var(--space-3) var(--space-4); font-size: var(--fs-14); }
.onyx-tab--lg { padding: var(--space-3) var(--space-5); font-size: var(--fs-16); }

/* underline variant */
.onyx-tabs--underline .onyx-tab {
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}
.onyx-tabs--underline .onyx-tab--active {
  color: var(--text-primary);
  font-weight: var(--weight-medium);
  border-bottom-color: var(--surface-inverse);
}
.onyx-tabs--underline .onyx-tab:hover:not(.onyx-tab--active) { color: var(--text-primary); }

/* pill variant */
.onyx-tabs--pill .onyx-tab { border-radius: var(--radius-sm); }
.onyx-tabs--pill .onyx-tab--active {
  background: var(--surface-card);
  color: var(--text-primary);
  font-weight: var(--weight-medium);
  box-shadow: var(--shadow-xs);
}
.onyx-tabs--pill .onyx-tab:hover:not(.onyx-tab--active) { background: rgba(0,0,0,0.04); }

.onyx-tab__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: var(--radius-pill);
  font-size: 11px;
  font-weight: var(--weight-medium);
}
.onyx-tab--active .onyx-tab__count {
  background: var(--surface-inverse);
  color: var(--text-inverse);
}
.onyx-tab:not(.onyx-tab--active) .onyx-tab__count {
  background: var(--surface-sunken);
  color: var(--text-muted);
}
</style>

<div role="tablist" {{ $attributes->merge(['class' => "onyx-tabs {$variantCls} {$sizeCls}"]) }}>
  @foreach ($items as $item)
    @php
      $isActive = ($item['key'] ?? '') === $active;
      $activeCls = $isActive ? 'onyx-tab--active' : '';
      $href = $item['href'] ?? (request()->fullUrlWithQuery(['tab' => $item['key']]));
    @endphp
    <a
      href="{{ $href }}"
      role="tab"
      aria-selected="{{ $isActive ? 'true' : 'false' }}"
      class="onyx-tab onyx-tab--{{ $size }} {{ $activeCls }}"
    >
      {{ $item['label'] }}
      @if (!empty($item['count']))
        <span class="onyx-tab__count">{{ $item['count'] }}</span>
      @endif
    </a>
  @endforeach
</div>

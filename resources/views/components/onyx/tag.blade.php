{{--
  ONYX Tag / Chip — selectable or removable token. Pill-shaped.
  Use for filters, categories, keywords.

  Props:
    selected   bool   (default: false)
    removable  bool — shows an × remove button  (default: false)
    href       string — makes the tag a link    (default: null)

  Events (when removable):
    @remove — emitted on × click; wire it to Livewire or Alpine

  Usage:
    <x-onyx.tag>Digital Screen</x-onyx.tag>
    <x-onyx.tag :selected="$filter === 'faulty'" href="?status=faulty">Faulty</x-onyx.tag>
    <x-onyx.tag :removable="true" @remove="removeTag('nsw')">NSW</x-onyx.tag>
--}}

@props([
    'selected' => false,
    'removable' => false,
    'href'     => null,
])

@php
$selectedCls = $selected ? 'onyx-tag--selected' : '';
$tag = $href ? 'a' : 'span';
@endphp

<style>
.onyx-tag {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 5px 12px;
  background: transparent;
  color: var(--text-secondary);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-pill);
  font-family: var(--font-sans);
  font-weight: var(--weight-regular);
  font-size: var(--fs-13);
  line-height: 1.3;
  text-decoration: none;
  transition: var(--transition-color);
  cursor: default;
}
a.onyx-tag, button.onyx-tag { cursor: pointer; }
a.onyx-tag:hover { background: var(--surface-sunken); }

.onyx-tag--selected {
  background: var(--onyx-900);
  color: var(--onyx-25);
  border-color: var(--onyx-900);
}

.onyx-tag__remove {
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
  color: inherit;
  opacity: 0.6;
  font-size: var(--fs-14);
  line-height: 1;
  display: flex;
  align-items: center;
}
.onyx-tag__remove:hover { opacity: 1; }
</style>

<{{ $tag }}
  @if ($href) href="{{ $href }}" @endif
  {{ $attributes->merge(['class' => "onyx-tag {$selectedCls}"]) }}
>
  {{ $slot }}
  @if ($removable)
    <button type="button" class="onyx-tag__remove" aria-label="Remove">&#x00D7;</button>
  @endif
</{{ $tag }}>

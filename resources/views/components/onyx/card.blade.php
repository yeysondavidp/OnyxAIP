{{--
  ONYX Card — quiet surface with hairline border and soft warm shadow.

  Props:
    variant     default | raised | outline | inverse    (default: default)
    padding     none | sm | md | lg | xl               (default: lg)
    interactive bool — lifts on hover                  (default: false)
    as          div | article | section | li …         (default: div)

  Usage:
    <x-onyx.card>…content…</x-onyx.card>
    <x-onyx.card variant="raised" :interactive="true">…</x-onyx.card>
    <x-onyx.card padding="sm" variant="outline">…</x-onyx.card>
--}}

@props([
    'variant'     => 'default',
    'padding'     => 'lg',
    'interactive' => false,
    'as'          => 'div',
])

@php
$variantClass = "onyx-card--{$variant}";
$padClass     = "onyx-card--pad-{$padding}";
$interCls     = $interactive ? 'onyx-card--interactive' : '';
@endphp

<style>
.onyx-card {
  border-radius: var(--radius-lg);
  transition:
    box-shadow var(--duration-base) var(--ease-out),
    transform var(--duration-base) var(--ease-out);
}
.onyx-card--interactive { cursor: pointer; }
.onyx-card--interactive:hover {
  transform: translateY(-2px);
}

.onyx-card--pad-none { padding: 0; }
.onyx-card--pad-sm   { padding: var(--space-4); }
.onyx-card--pad-md   { padding: var(--space-5); }
.onyx-card--pad-lg   { padding: var(--pad-card); }
.onyx-card--pad-xl   { padding: var(--space-8); }

.onyx-card--default  {
  background: var(--surface-card);
  border: 1px solid var(--border-subtle);
  box-shadow: var(--shadow-xs);
}
.onyx-card--default.onyx-card--interactive:hover { box-shadow: var(--shadow-md); }

.onyx-card--raised   {
  background: var(--surface-raised);
  border: 1px solid var(--border-subtle);
  box-shadow: var(--shadow-sm);
}
.onyx-card--raised.onyx-card--interactive:hover { box-shadow: var(--shadow-lg); }

.onyx-card--outline  {
  background: transparent;
  border: 1px solid var(--border-default);
  box-shadow: none;
}

.onyx-card--inverse  {
  background: var(--surface-inverse);
  border: 1px solid var(--border-inverse);
  color: var(--text-inverse);
  box-shadow: none;
}
</style>

<{{ $as }} {{ $attributes->merge(['class' => "onyx-card {$variantClass} {$padClass} {$interCls}"]) }}>
  {{ $slot }}
</{{ $as }}>

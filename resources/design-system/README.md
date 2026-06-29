# ONYX Design System — Vendored

This directory contains the complete, self-contained ONYX design system as vendored in the
ONYX Asset Intelligence Platform (AIP). No runtime dependency on the Claude Design project,
org membership, or any external service is required. Anyone who clones this repo gets a fully
working ONYX component library.

---

## What is vendored

| Asset | Location | Notes |
|---|---|---|
| Gordita font (14 OTF files, all weights + italics) | `public/fonts/gordita/` | Served as static files |
| Logo SVGs (wordmark + mark, ink + light) | `public/images/onyx/` | Served as static files |
| CSS design tokens (colours, type, spacing, effects, base, fonts) | `resources/design-system/tokens/` | Imported by `onyx.css` |
| Master CSS entry | `resources/design-system/onyx.css` | Link this in your layout |
| Blade components (13 production components) | `resources/views/components/onyx/` | `<x-onyx.button>` etc. |

---

## Quick start

### 1 — Link the CSS in your Blade layout

If using Vite, import in `resources/css/app.css`:

```css
@import "../design-system/onyx.css";
```

Or link directly from the layout `<head>`:

```html
<link rel="stylesheet" href="{{ asset('css/onyx.css') }}">
```

### 2 — Use Blade components

```blade
<x-onyx.button variant="solid">Save changes</x-onyx.button>

<x-onyx.badge tone="positive">Active</x-onyx.badge>

<x-onyx.card padding="lg">
  <x-onyx.eyebrow>Store</x-onyx.eyebrow>
  <h2>Pandora Pitt St Mall</h2>
</x-onyx.card>

<x-onyx.alert tone="caution" title="SLA at risk">
  This job must be validated before 5pm Friday.
</x-onyx.alert>

<x-onyx.input
  name="store_name"
  label="Store name"
  :value="old('store_name')"
  :error="$errors->first('store_name')"
/>
```

Interactive components (Toggle, Dialog) require **Alpine.js** — already bundled with Laravel Breeze.

---

## Design principles

These are non-negotiable. Every surface built for AIP must follow them.

### Palette

| Role | Variable | Value |
|---|---|---|
| Page background | `--surface-page` | `#f4f1ec` (alabaster bone) |
| Card surface | `--surface-card` | `#faf8f3` |
| Primary ink | `--text-primary` | `#141414` |
| Secondary text | `--text-secondary` | `#47423c` |
| Muted text | `--text-muted` | `#6a635a` |
| Bronze accent | `--bronze-500` | `#997a4f` |
| Focus ring | `--focus-ring` | `var(--bronze-500)` |

Dark hero theme: add `data-theme="onyx"` to `<body>` or any container.

Status colours are **muted** — they sit inside the palette, not outside it:

| Status | Soft bg | Ink |
|---|---|---|
| Positive | `--positive-soft` `#e7ebe0` | `--positive` `#5c6f54` |
| Caution | `--caution-soft` `#f0e7d2` | `--caution` `#9a7b3f` |
| Critical | `--critical-soft` `#efdcd6` | `--critical` `#8c4a3f` |
| Info | `--info-soft` `#dde6ec` | `--info` `#4a5d6e` |

### Typography

**One typeface: Gordita** — used exclusively across the system.

| Role | Token | Weight / Size |
|---|---|---|
| Display XL | `--type-display-xl` | Light 300 / 96px |
| Display LG | `--type-display-lg` | Light 300 / 72px |
| Display MD | `--type-display-md` | Light 300 / 48px |
| Heading XL | `--type-heading-xl` | Regular 400 / 36px |
| Heading MD | `--type-heading-md` | Medium 500 / 24px |
| Body MD | `--type-body-md` | Regular 400 / 16px |
| Body SM | `--type-body-sm` | Regular 400 / 14px |
| Label | `--type-label` | Medium 500 / 13px uppercase |
| Caption | `--type-caption` | Regular 400 / 12px |

**Eyebrow pattern** — use `<x-onyx.eyebrow>` above section headings; uppercase, wide tracking,
bronze accent (`tone="accent"`) for the one accent-per-view rule.

### Spacing

4px base grid. Semantic aliases:

| Token | Value | Use |
|---|---|---|
| `--gap-inline` | `0.5rem` (8px) | Gap between inline elements |
| `--gap-stack` | `1rem` (16px) | Vertical stacking gap |
| `--pad-control` | `0.75rem` (12px) | Internal padding for controls |
| `--pad-card` | `1.5rem` (24px) | Card padding |
| `--pad-section` | `6rem` (96px) | Section vertical rhythm |

### Elevation

```
--shadow-xs   0 1px 2px   rgba(20,18,16, 0.06)  — resting card
--shadow-sm   0 2px 6px   rgba(20,18,16, 0.07)  — raised card
--shadow-md   0 8px 24px  rgba(20,18,16, 0.09)  — hover lift
--shadow-lg   0 18px 48px rgba(20,18,16, 0.12)  — dropdown / tooltip
--shadow-xl   0 32px 80px rgba(20,18,16, 0.16)  — dialog / modal
```

No coloured borders. No heavy shadows. Always warm-tinted.

### Motion

```
--duration-fast   150ms  — colour transitions
--duration-base   250ms  — most interactions
--duration-slow   400ms  — entry/exit animations
--ease-out        cubic-bezier(0.16, 1, 0.3, 1)
--ease-standard   cubic-bezier(0.4, 0, 0.2, 1)
```

Press state: `scale(0.98)` on buttons, `scale(0.94)` on icon buttons. No bounce. No spring.

### Border radius

```
--radius-xs  2px   — badges
--radius-sm  4px   — buttons, inputs
--radius-md  6px   — alerts, chips
--radius-lg  10px  — cards, dropdowns
--radius-xl  16px  — dialogs
--radius-pill 999px — tags, toggles, avatars
```

### Voice

- Sentence case. No emoji. No exclamation marks.
- Plain-language errors ("Store name is required" not "Invalid input").
- One bronze accent per view.
- Labels: uppercase, wide tracking, muted (`--text-muted`).

---

## Blade component reference

### `<x-onyx.button>`

```blade
<x-onyx.button>Save</x-onyx.button>
<x-onyx.button variant="outline" size="sm">Cancel</x-onyx.button>
<x-onyx.button variant="accent" type="submit">Confirm</x-onyx.button>
<x-onyx.button variant="ghost" href="{{ route('back') }}">Go back</x-onyx.button>
```

Props: `variant` (solid|outline|ghost|accent), `size` (sm|md|lg), `type`, `fullWidth`, `disabled`, `href`.

---

### `<x-onyx.badge>`

```blade
<x-onyx.badge>Neutral</x-onyx.badge>
<x-onyx.badge tone="positive">Active</x-onyx.badge>
<x-onyx.badge tone="critical" variant="solid">Faulty</x-onyx.badge>
<x-onyx.badge tone="caution" :uppercase="false">Under Maintenance</x-onyx.badge>
```

Props: `tone` (neutral|accent|positive|caution|critical|info), `variant` (soft|solid|outline), `uppercase`.

Asset status → badge tone mapping:
- Active → positive
- Faulty → critical
- Offline → caution
- Under Maintenance → info
- Decommissioned → neutral

---

### `<x-onyx.card>`

```blade
<x-onyx.card>…</x-onyx.card>
<x-onyx.card variant="raised" :interactive="true">…</x-onyx.card>
<x-onyx.card padding="sm" variant="inverse">…</x-onyx.card>
```

Props: `variant` (default|raised|outline|inverse), `padding` (none|sm|md|lg|xl), `interactive`, `as`.

---

### `<x-onyx.alert>`

```blade
<x-onyx.alert tone="caution" title="SLA at risk">
  This job must be validated within 4 hours.
</x-onyx.alert>
<x-onyx.alert tone="positive" :dismissible="true">Saved.</x-onyx.alert>
```

Props: `tone` (info|positive|caution|critical), `title`, `dismissible`. Requires Alpine.js.

---

### `<x-onyx.input>`

```blade
<x-onyx.input
  name="serial_number"
  label="Serial number"
  placeholder="e.g. SN-0012345"
  :value="old('serial_number')"
  :error="$errors->first('serial_number')"
/>
```

Props: `label`, `helper`, `error`, `size` (sm|md|lg). All native `<input>` attrs pass through.

---

### `<x-onyx.toggle>`

```blade
<x-onyx.toggle name="is_active" label="Active" :checked="$asset->is_active" />
```

Emits a hidden input `name` with value `"1"` or `"0"`. Requires Alpine.js.

---

### `<x-onyx.tabs>`

```blade
<x-onyx.tabs
  :items="[
    ['key' => 'overview', 'label' => 'Overview'],
    ['key' => 'assets',   'label' => 'Assets', 'count' => $store->assets()->count()],
    ['key' => 'jobs',     'label' => 'Service Jobs'],
  ]"
  active="{{ request('tab', 'overview') }}"
/>
```

Props: `items`, `active`, `variant` (underline|pill), `size`.

---

### `<x-onyx.avatar>`

```blade
<x-onyx.avatar name="Sarah Chen" />
<x-onyx.avatar name="James O'Brien" size="lg" tone="ink" />
```

Props: `name`, `src`, `size` (xs|sm|md|lg|xl), `round`, `tone` (neutral|ink|accent).

---

### `<x-onyx.eyebrow>`

```blade
<x-onyx.eyebrow>Asset Registry</x-onyx.eyebrow>
<x-onyx.eyebrow tone="accent" :tick="true">New</x-onyx.eyebrow>
<x-onyx.eyebrow index="01">Step one</x-onyx.eyebrow>
```

Props: `tone` (muted|accent|primary), `tick`, `index`, `as`.

---

### `<x-onyx.divider>`

```blade
<x-onyx.divider />
<x-onyx.divider label="Or" />
<x-onyx.divider orientation="vertical" />
```

---

### `<x-onyx.tag>`

```blade
<x-onyx.tag :selected="$filter === 'screen'">Digital Screen</x-onyx.tag>
<x-onyx.tag href="?state=NSW">NSW</x-onyx.tag>
```

---

### `<x-onyx.spinner>`

```blade
<x-onyx.spinner />
<x-onyx.spinner size="lg" tone="accent" label="Uploading photos" />
<x-onyx.spinner wire:loading />
```

---

### `<x-onyx.progress>`

```blade
<x-onyx.progress :value="65" />
<x-onyx.progress :value="$job->completion" tone="accent" label="Job complete" :show-value="true" />
```

---

### `<x-onyx.icon-button>`

```blade
<x-onyx.icon-button aria-label="Edit asset" variant="outline">
  <svg width="16" height="16">…</svg>
</x-onyx.icon-button>
```

---

### `<x-onyx.dialog>`

```blade
<x-onyx.dialog
  title="Decommission asset?"
  description="The asset will be removed from the active registry."
  confirm-label="Decommission"
  confirm-tone="critical"
>
  <x-slot:trigger>
    <x-onyx.button variant="outline" size="sm">Decommission</x-onyx.button>
  </x-slot:trigger>
</x-onyx.dialog>
```

Requires Alpine.js. Listen for `onyx-dialog-confirm` on the page for the confirm action.

---

## File tree

```
resources/design-system/
  README.md                  ← this file
  onyx.css                   ← master CSS entry point
  tokens/
    fonts.css                ← @font-face declarations (points to public/fonts/gordita/)
    colors.css               ← onyx scale + bronze + semantic aliases
    typography.css           ← type scale, weights, role tokens
    spacing.css              ← 4px grid + semantic spacing
    effects.css              ← radius, shadow, motion tokens
    base.css                 ← reset, defaults

public/
  fonts/
    gordita/
      Gordita-Thin.otf
      Gordita-ThinItalic.otf
      Gordita-Light.otf
      Gordita-LightItalic.otf
      Gordita-Regular.otf
      Gordita-RegularItalic.otf
      Gordita-Medium.otf
      Gordita-MediumItalic.otf
      Gordita-Bold.otf
      Gordita-BoldItalic.otf
      Gordita-Black.otf
      Gordita-BlackItalic.otf
      Gordita-Ultra.otf
      Gordita-UltraItalic.otf
  images/
    onyx/
      wordmark-black.svg     ← ink on light backgrounds
      wordmark-light.svg     ← bone/light on dark backgrounds
      mark-black.svg         ← O monogram, ink
      mark-light.svg         ← O monogram, light

resources/views/components/onyx/
  alert.blade.php
  avatar.blade.php
  badge.blade.php
  button.blade.php
  card.blade.php
  dialog.blade.php
  divider.blade.php
  eyebrow.blade.php
  icon-button.blade.php
  input.blade.php
  progress.blade.php
  spinner.blade.php
  tabs.blade.php
  tag.blade.php
  toggle.blade.php
```

---

## Using logos

```blade
{{-- Ink wordmark on light backgrounds --}}
<img src="{{ asset('images/onyx/wordmark-black.svg') }}" alt="ONYX" height="24">

{{-- Bone wordmark on dark/hero backgrounds --}}
<img src="{{ asset('images/onyx/wordmark-light.svg') }}" alt="ONYX" height="24">

{{-- O monogram mark only --}}
<img src="{{ asset('images/onyx/mark-black.svg') }}" alt="ONYX" width="32" height="32">
```

Never recreate the logo in code. Always use these SVG files.

---

## Updating this vendor

This design system is vendored from `https://claude.ai/design/p/019e19e1-e5db-74db-a51d-7da28c679fed`.
To update, re-run the vendoring import via Claude Code using the DesignSync tool. The process
fetches the latest token files, font binaries, and logo SVGs, then rewrites this directory.
Blade components are hand-authored and should not be overwritten — review diffs before replacing.

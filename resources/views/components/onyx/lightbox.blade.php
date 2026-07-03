{{--
  ONYX Lightbox — shared modal for previewing images/documents in-platform,
  with Next/Prev navigation across a group of items. Include ONE instance
  per page; thumbnails open it by dispatching a window event.

  Item shape: { url: string, name: string, type: 'image' | 'document' }

  Usage — open from a thumbnail's @click:
    @click="window.dispatchEvent(new CustomEvent('onyx-lightbox:open', {
        detail: { items: [{ url: '...', name: 'Photo #1', type: 'image' }], index: 0 }
    }))"

  Include once, e.g. near the end of the page body:
    <x-onyx.lightbox />
--}}

<style>
/* x-show removes (not restores) an inline display value when revealing an
   element, so the flex layout must live in a class rather than inline. */
.onyx-lightbox {
  position: fixed;
  inset: 0;
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(20, 16, 12, 0.85);
  padding: var(--space-6);
}
</style>

<div
    class="onyx-lightbox"
    x-data="{
        open: false,
        items: [],
        index: 0,
        get current() { return this.items[this.index] || null; },
        next() { this.index = (this.index + 1) % this.items.length; },
        prev() { this.index = (this.index - 1 + this.items.length) % this.items.length; },
    }"
    @onyx-lightbox:open.window="open = true; items = $event.detail.items; index = $event.detail.index || 0;"
    @keydown.escape.window="open = false"
    @keydown.arrow-right.window="if (open) next();"
    @keydown.arrow-left.window="if (open) prev();"
    x-show="open"
    x-cloak
    @click.self="open = false"
>
    <button type="button" @click="open = false" aria-label="Close"
        style="position: absolute; top: var(--space-5); right: var(--space-5); width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.1); border: none; border-radius: var(--radius-circle); color: white; cursor: pointer;">
        <x-icon name="x" size="24" />
    </button>

    <template x-if="items.length > 1">
        <button type="button" @click="prev()" aria-label="Previous"
            style="position: absolute; left: var(--space-5); top: 50%; transform: translateY(-50%); width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.1); border: none; border-radius: var(--radius-circle); color: white; cursor: pointer;">
            <x-icon name="chevron-left" size="24" />
        </button>
    </template>

    <template x-if="items.length > 1">
        <button type="button" @click="next()" aria-label="Next"
            style="position: absolute; right: var(--space-5); top: 50%; transform: translateY(-50%); width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.1); border: none; border-radius: var(--radius-circle); color: white; cursor: pointer;">
            <x-icon name="chevron-right" size="24" />
        </button>
    </template>

    <div style="max-width: min(90vw, 900px); max-height: 85vh; display: flex; flex-direction: column; align-items: center; gap: var(--space-3);" @click.stop>
        <template x-if="current && current.type === 'image'">
            <img :src="current.url" :alt="current.name"
                style="max-width: 100%; max-height: 75vh; border-radius: var(--radius-md); object-fit: contain; background: var(--surface-raised);">
        </template>

        <template x-if="current && current.type !== 'image'">
            <iframe :src="current.url" title="Document preview"
                style="width: min(90vw, 900px); height: 75vh; border: none; border-radius: var(--radius-md); background: white;"></iframe>
        </template>

        <div style="color: white; font-size: var(--fs-13); display: flex; align-items: center; gap: var(--space-3);">
            <span x-text="current?.name"></span>
            <span x-show="items.length > 1" x-text="(index + 1) + ' / ' + items.length"></span>
        </div>
    </div>
</div>

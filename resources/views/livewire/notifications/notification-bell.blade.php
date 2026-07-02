<div x-data="{ open: @entangle('open') }" @click.outside="open = false" style="position: relative;" wire:poll.60s>

    <x-onyx.icon-button wire:click="toggle" aria-label="Notifications" variant="ghost" round style="position: relative;">
        <x-icon name="bell" size="18" />
        @if ($unreadCount > 0)
            <span style="position: absolute; top: 4px; right: 4px; background: var(--critical); color: #fff; font-size: 10px; font-weight: 700; line-height: 1; border-radius: 9999px; min-width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; padding: 0 3px;">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </x-onyx.icon-button>

    <div
        x-show="open"
        x-cloak
        style="position: absolute; right: 0; top: calc(100% + 8px); width: 380px; max-height: 440px; overflow-y: auto; background: var(--surface-raised); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 50;"
    >
        <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-4); border-bottom: 1px solid var(--border-subtle);">
            <span style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary);">Notifications</span>
            @if ($unreadCount > 0)
                <button wire:click="markAllAsRead" style="font-size: var(--fs-13); color: var(--bronze-600); background: none; border: none; cursor: pointer; padding: var(--space-2);">
                    Mark all as read
                </button>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <div style="padding: var(--space-8);">
                <x-onyx.empty icon="bell" heading="You're all caught up" body="Nothing needs your attention right now." size="sm" />
            </div>
        @else
            <div>
                @foreach ($notifications as $notification)
                    <div
                        wire:click="openNotification('{{ $notification->id }}', '{{ $notification->data['url'] ?? '' }}')"
                        wire:key="notification-{{ $notification->id }}"
                        style="display: block; padding: var(--space-4); border-bottom: 1px solid var(--border-subtle); cursor: pointer; {{ $notification->read_at ? '' : 'background: var(--bronze-50);' }}"
                        onmouseover="this.style.background='var(--surface-sunken)'"
                        onmouseout="this.style.background='{{ $notification->read_at ? 'transparent' : 'var(--bronze-50)' }}'"
                    >
                        <div style="display: flex; align-items: center; gap: var(--space-2);">
                            @unless ($notification->read_at)
                                <span style="width: 6px; height: 6px; border-radius: 9999px; background: var(--bronze-500); flex-shrink: 0;"></span>
                            @endunless
                            <span style="font-size: var(--fs-13); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $notification->data['label'] ?? 'Notification' }}</span>
                        </div>
                        {{-- subject is pre-sanitised by EmailTemplateRenderer — rendered raw to avoid double-escaping (same convention as the email preview panel) --}}
                        <div style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">{!! $notification->data['subject'] ?? '' !!}</div>
                        <div style="font-size: var(--fs-12); color: var(--text-muted); margin-top: var(--space-1);">{{ $notification->created_at->diffForHumans() }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

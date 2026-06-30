{{--
    Self-contained topology card for one Display Group.

    Props:
      $group        — DisplayGroup (with player + screens loaded)
      $showActions  — bool, show Edit/Delete buttons (PM only)

    Reusable standalone: can be rendered with any DisplayGroup collection
    independent of the full store dashboard (US-05.2 DoD).
--}}
<x-onyx.card variant="default" padding="lg">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-4);">
        <div>
            <h3 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">{{ $group->group_name }}</h3>
            @if ($group->layout_description)
                <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">{{ $group->layout_description }}</p>
            @endif
        </div>
        @if (!empty($showActions))
            <div style="display: flex; gap: var(--space-2); flex-shrink: 0;">
                <x-onyx.button href="{{ route('stores.display-groups.edit', [$group->store_id, $group]) }}" variant="outline" size="xs">Edit</x-onyx.button>
                <form method="POST" action="{{ route('stores.display-groups.destroy', [$group->store_id, $group]) }}" onsubmit="return confirm('Delete display group "{{ addslashes($group->group_name) }}"? The player and screens will be unassigned but not deleted.')">
                    @csrf
                    @method('DELETE')
                    <x-onyx.button type="submit" variant="danger-outline" size="xs">Delete</x-onyx.button>
                </form>
            </div>
        @endif
    </div>

    {{-- Topology: player → screens --}}
    <div style="display: flex; align-items: flex-start; gap: var(--space-4); flex-wrap: wrap;" role="group" aria-label="Display group topology: {{ $group->group_name }}">

        {{-- Player --}}
        <div style="flex-shrink: 0;">
            <div style="font-size: var(--fs-11); font-weight: var(--weight-semibold); color: var(--text-tertiary); text-transform: uppercase; letter-spacing: var(--tracking-wide); margin-bottom: var(--space-2);">Player</div>
            @if ($group->player)
                <a href="{{ route('assets.show', $group->player) }}"
                   style="display: block; background: var(--surface-raised); border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); text-decoration: none; min-width: 180px; min-height: 44px;"
                   aria-label="Player: {{ $group->player->asset_name }} ({{ $group->player->asset_code }}, {{ $group->player->asset_status->label() }})">
                    <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-1);">
                        <span style="font-size: var(--fs-13); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $group->player->asset_name }}</span>
                        <x-onyx.badge :tone="$group->player->asset_status->tone()" variant="soft" size="xs">{{ $group->player->asset_status->label() }}</x-onyx.badge>
                    </div>
                    <div style="font-size: var(--fs-12); color: var(--text-secondary); font-family: monospace;">{{ $group->player->asset_code }}</div>
                    @if ($group->player->model)
                        <div style="font-size: var(--fs-12); color: var(--text-tertiary);">{{ $group->player->manufacturer }} {{ $group->player->model }}</div>
                    @endif
                </a>
            @else
                <div style="font-size: var(--fs-13); color: var(--text-tertiary);">No player assigned</div>
            @endif
        </div>

        {{-- Connector arrow --}}
        <div style="display: flex; align-items: center; padding-top: 28px; color: var(--text-tertiary);" aria-hidden="true">
            <svg width="24" height="16" viewBox="0 0 24 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 8H20M20 8L14 2M20 8L14 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        {{-- Screens --}}
        <div style="flex: 1; min-width: 0;">
            <div style="font-size: var(--fs-11); font-weight: var(--weight-semibold); color: var(--text-tertiary); text-transform: uppercase; letter-spacing: var(--tracking-wide); margin-bottom: var(--space-2);">
                Screens ({{ $group->screens->count() }})
            </div>
            @if ($group->screens->isNotEmpty())
                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                    @foreach ($group->screens as $screen)
                        <a href="{{ route('assets.show', $screen) }}"
                           style="display: block; background: var(--surface-raised); border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); text-decoration: none; min-width: 160px; min-height: 44px;"
                           aria-label="Screen: {{ $screen->asset_name }} ({{ $screen->asset_code }}, {{ $screen->asset_status->label() }})">
                            <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-1);">
                                <span style="font-size: var(--fs-13); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $screen->asset_name }}</span>
                                <x-onyx.badge :tone="$screen->asset_status->tone()" variant="soft" size="xs">{{ $screen->asset_status->label() }}</x-onyx.badge>
                            </div>
                            <div style="font-size: var(--fs-12); color: var(--text-secondary); font-family: monospace;">{{ $screen->asset_code }}</div>
                            @if ($screen->model)
                                <div style="font-size: var(--fs-12); color: var(--text-tertiary);">{{ $screen->manufacturer }} {{ $screen->model }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>
            @else
                <div style="font-size: var(--fs-13); color: var(--text-tertiary);">No screens assigned</div>
            @endif
        </div>

    </div>
</x-onyx.card>

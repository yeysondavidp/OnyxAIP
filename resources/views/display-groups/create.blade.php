<x-layouts.app title="Add Display Group — {{ $store->store_name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('stores.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Stores</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('stores.show', $store) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $store->store_name }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('stores.display-groups.index', $store) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Display Groups</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Add</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 720px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Add Display Group</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Map a media player to one or more screens at {{ $store->store_name }}.</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('stores.display-groups.store', $store) }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.input
                        name="group_name"
                        label="Group name"
                        type="text"
                        :value="old('group_name')"
                        :error="$errors->first('group_name')"
                        helper="e.g. Window Bay — North"
                        required
                        autocomplete="off"
                    />

                    {{-- Player --}}
                    <div>
                        <x-onyx.select name="player_asset_id" label="Media player" :error="$errors->first('player_asset_id')" required>
                            <option value="">— Select player —</option>
                            @foreach ($players as $player)
                                <option value="{{ $player->id }}" @selected(old('player_asset_id') == $player->id)>
                                    {{ $player->asset_code }} — {{ $player->asset_name }}
                                    @if ($player->model) ({{ $player->manufacturer }} {{ $player->model }}) @endif
                                </option>
                            @endforeach
                        </x-onyx.select>
                        @if ($players->isEmpty())
                            <p style="font-size: var(--fs-13); color: var(--text-warning); margin-top: var(--space-2);">
                                No unassigned media players at this store. <a href="{{ route('assets.create', ['store_id' => $store->id]) }}" style="color: var(--bronze-600);">Add one first.</a>
                            </p>
                        @endif
                    </div>

                    {{-- Screens (multi-select) --}}
                    <div>
                        <label style="display: block; font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary); margin-bottom: var(--space-2);">
                            Screens <span style="color: var(--text-critical);">*</span>
                        </label>
                        @if ($screens->isEmpty())
                            <p style="font-size: var(--fs-13); color: var(--text-warning);">
                                No unassigned digital screens at this store. <a href="{{ route('assets.create', ['store_id' => $store->id]) }}" style="color: var(--bronze-600);">Add one first.</a>
                            </p>
                        @else
                            <div style="border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: var(--space-3); display: flex; flex-direction: column; gap: var(--space-2); max-height: 240px; overflow-y: auto;">
                                @foreach ($screens as $screen)
                                    <label style="display: flex; align-items: center; gap: var(--space-3); cursor: pointer; padding: var(--space-1) 0; min-height: 44px;">
                                        <input
                                            type="checkbox"
                                            name="screen_asset_ids[]"
                                            value="{{ $screen->id }}"
                                            @checked(in_array($screen->id, (array) old('screen_asset_ids', [])))
                                            style="width: 16px; height: 16px; flex-shrink: 0;"
                                        >
                                        <span style="font-size: var(--fs-14); color: var(--text-primary);">
                                            <span style="font-family: monospace; font-size: var(--fs-13);">{{ $screen->asset_code }}</span>
                                            — {{ $screen->asset_name }}
                                            @if ($screen->model)
                                                <span style="color: var(--text-secondary);">({{ $screen->manufacturer }} {{ $screen->model }})</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('screen_asset_ids')
                            <p style="font-size: var(--fs-13); color: var(--text-critical); margin-top: var(--space-1);">{{ $message }}</p>
                        @enderror
                        @error('screen_asset_ids.*')
                            <p style="font-size: var(--fs-13); color: var(--text-critical); margin-top: var(--space-1);">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-onyx.input
                        name="layout_description"
                        label="Layout description"
                        type="text"
                        :value="old('layout_description')"
                        :error="$errors->first('layout_description')"
                        helper="Optional. e.g. 3 screens portrait, horizontal array. Max 500 characters."
                        autocomplete="off"
                    />

                    <x-onyx.textarea
                        name="notes"
                        label="Notes"
                        :value="old('notes')"
                        :error="$errors->first('notes')"
                        rows="3"
                        helper="Optional internal notes."
                    />

                    <div style="display: flex; gap: var(--space-3); padding-top: var(--space-2); border-top: 1px solid var(--border-subtle);">
                        <x-onyx.button type="submit" variant="primary">Save display group</x-onyx.button>
                        <x-onyx.button href="{{ route('stores.display-groups.index', $store) }}" variant="outline">Cancel</x-onyx.button>
                    </div>

                </div>
            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>

<x-layouts.app title="Edit Asset — {{ $asset->asset_name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('assets.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Asset Registry</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('assets.show', $asset) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $asset->asset_name }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Edit</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 720px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Edit Asset</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary); font-family: monospace;">{{ $asset->asset_code }}</p>
        </div>

        @php
            $storesByClient = $clients->mapWithKeys(fn ($c) => [
                $c->id => $c->stores->map(fn ($s) => ['id' => $s->id, 'name' => $s->store_name])->values(),
            ]);
            $sd = $asset->screenDetail;
            $pd = $asset->playerDetail;
            $ld = $asset->lightboxDetail;
            $id = $asset->infrastructureDetail;
            $fd = $asset->windowFixtureDetail;
        @endphp

        <form method="POST" action="{{ route('assets.update', $asset) }}" novalidate
            x-data="{
                assetType: '{{ old('asset_type', $asset->asset_type->value) }}',
                clientId: '{{ old('client_id', $asset->client_id) }}',
                storeId: '{{ old('store_id', $asset->store_id) }}',
                storesByClient: {{ $storesByClient->toJson() }},
                get filteredStores() {
                    return this.storesByClient[this.clientId] || [];
                },
                isScreen()   { return this.assetType === 'digital_screen'; },
                isPlayer()   { return this.assetType === 'media_player'; },
                isLightbox() { return this.assetType === 'lightbox'; },
                isInfra()    { return this.assetType === 'infrastructure'; },
                isFixture()  { return this.assetType === 'window_fixture'; },
            }">
            @csrf
            @method('PATCH')

            <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                {{-- Identity --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Asset identity</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <x-onyx.select name="asset_type" label="Asset type" :error="$errors->first('asset_type')" required
                            x-model="assetType">
                            <option value="">— Select type —</option>
                            @foreach ($assetTypes as $type)
                                <option value="{{ $type->value }}" @selected(old('asset_type', $asset->asset_type->value) === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </x-onyx.select>

                        <x-onyx.input name="asset_name" label="Asset name" type="text" :value="old('asset_name', $asset->asset_name)" :error="$errors->first('asset_name')" required autocomplete="off" />
                        <x-onyx.input name="asset_code" label="Asset code" type="text" :value="old('asset_code', $asset->asset_code)" :error="$errors->first('asset_code')" required maxlength="40" />

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="manufacturer" label="Manufacturer" type="text" :value="old('manufacturer', $asset->manufacturer)" :error="$errors->first('manufacturer')" required />
                            <x-onyx.input name="model"        label="Model"        type="text" :value="old('model', $asset->model)"               :error="$errors->first('model')"        required />
                        </div>

                        <x-onyx.input name="serial_number" label="Serial number" type="text" :value="old('serial_number', $asset->serial_number)" :error="$errors->first('serial_number')" />

                    </div>
                </x-onyx.card>

                {{-- Placement --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Placement</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <x-onyx.select name="client_id" label="Client" :error="$errors->first('client_id')" required x-model="clientId">
                            <option value="">— Select client —</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected(old('client_id', $asset->client_id) == $client->id)>{{ $client->client_name }}</option>
                            @endforeach
                        </x-onyx.select>

                        <div class="onyx-field">
                            <label class="onyx-field__label">Store <span style="color: var(--critical);">*</span></label>
                            <div class="onyx-select-wrap onyx-select--md {{ $errors->has('store_id') ? 'onyx-select--error' : '' }}">
                                <select name="store_id" x-model="storeId" required>
                                    <option value="">— Select store —</option>
                                    <template x-for="store in filteredStores" :key="store.id">
                                        <option :value="store.id" x-text="store.name" :selected="storeId == store.id"></option>
                                    </template>
                                </select>
                                <svg class="onyx-select-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                            @error('store_id')
                                <span class="onyx-field__hint onyx-field__hint--error" role="alert">{{ $message }}</span>
                            @enderror
                        </div>

                        <x-onyx.input name="location_notes" label="Location notes" type="text" :value="old('location_notes', $asset->location_notes)" :error="$errors->first('location_notes')" />

                    </div>
                </x-onyx.card>

                {{-- Status & dates --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Status &amp; dates</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <x-onyx.select name="asset_status" label="Status" :error="$errors->first('asset_status')" required>
                            <option value="">— Select status —</option>
                            @foreach ($assetStatuses as $status)
                                <option value="{{ $status->value }}" @selected(old('asset_status', $asset->asset_status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </x-onyx.select>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="purchase_date"   label="Purchase date"   type="date" :value="old('purchase_date',   $asset->purchase_date?->format('Y-m-d'))"   :error="$errors->first('purchase_date')" />
                            <x-onyx.input name="install_date"    label="Install date"    type="date" :value="old('install_date',    $asset->install_date?->format('Y-m-d'))"    :error="$errors->first('install_date')" />
                            <x-onyx.input name="warranty_expiry" label="Warranty expiry" type="date" :value="old('warranty_expiry', $asset->warranty_expiry?->format('Y-m-d'))" :error="$errors->first('warranty_expiry')" />
                        </div>

                    </div>
                </x-onyx.card>

                {{-- Digital Screen detail --}}
                <x-onyx.card variant="default" padding="xl" x-show="isScreen()" x-cloak>
                    <x-onyx.eyebrow>Screen detail</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">
                        <div style="display: grid; grid-template-columns: 120px 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="screen_size_inches" label="Size (inches)" type="number" step="0.1" min="1" :value="old('screen_size_inches', $sd?->screen_size_inches)" :error="$errors->first('screen_size_inches')" />
                            <x-onyx.input name="resolution_width"   label="Width (px)"   type="number" min="1"   :value="old('resolution_width',   $sd?->resolution_width)"   :error="$errors->first('resolution_width')" />
                            <x-onyx.input name="resolution_height"  label="Height (px)"  type="number" min="1"   :value="old('resolution_height',  $sd?->resolution_height)"  :error="$errors->first('resolution_height')" />
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.select name="orientation" label="Orientation" :error="$errors->first('orientation')">
                                <option value="">— Select —</option>
                                @foreach ($orientations as $o)
                                    <option value="{{ $o->value }}" @selected(old('orientation', $sd?->orientation?->value) === $o->value)>{{ $o->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                            <x-onyx.select name="totem_supplied_by" label="Totem supplied by" :error="$errors->first('totem_supplied_by')">
                                <option value="">— Select —</option>
                                @foreach ($totemSuppliedByOptions as $opt)
                                    <option value="{{ $opt->value }}" @selected(old('totem_supplied_by', $sd?->totem_supplied_by?->value) === $opt->value)>{{ $opt->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                        </div>
                        <x-onyx.input name="mount_type" label="Mount type" type="text" :value="old('mount_type', $sd?->mount_type)" :error="$errors->first('mount_type')" helper="e.g. Floor Totem, Wall Mount, Window Flush" />
                    </div>
                </x-onyx.card>

                {{-- Media Player detail --}}
                <x-onyx.card variant="default" padding="xl" x-show="isPlayer()" x-cloak>
                    <x-onyx.eyebrow>Player detail</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">
                        <x-onyx.select name="player_type" label="Player type" :error="$errors->first('player_type')">
                            <option value="">— Select —</option>
                            @foreach ($playerTypes as $pt)
                                <option value="{{ $pt->value }}" @selected(old('player_type', $pd?->player_type?->value) === $pt->value)>{{ $pt->label() }}</option>
                            @endforeach
                        </x-onyx.select>
                        <x-onyx.input name="cms_platform"     label="CMS platform"     type="text" :value="old('cms_platform',     $pd?->cms_platform)"     :error="$errors->first('cms_platform')"     helper="e.g. Navori QL, Samsung MagicInfo, Beat CMS" />
                        <x-onyx.input name="ip_address"       label="IP address"       type="text" :value="old('ip_address',       $pd?->ip_address)"       :error="$errors->first('ip_address')" />
                        <x-onyx.input name="mac_address"      label="MAC address"      type="text" :value="old('mac_address',      $pd?->mac_address)"      :error="$errors->first('mac_address')"      helper="Format: AA:BB:CC:DD:EE:FF" />
                        <x-onyx.input name="firmware_version" label="Firmware version" type="text" :value="old('firmware_version', $pd?->firmware_version)" :error="$errors->first('firmware_version')" />
                        <x-onyx.alert tone="info">Screen connectivity (which screens this player drives) is managed via Display Groups and is not stored here.</x-onyx.alert>
                    </div>
                </x-onyx.card>

                {{-- Lightbox detail --}}
                <x-onyx.card variant="default" padding="xl" x-show="isLightbox()" x-cloak>
                    <x-onyx.eyebrow>Lightbox detail</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">
                        <x-onyx.input name="lightbox_dimensions" label="Dimensions" type="text" :value="old('lightbox_dimensions', $ld?->lightbox_dimensions)" :error="$errors->first('lightbox_dimensions')" helper="W × H × D in mm, e.g. 1200×800×50" />
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.select name="light_type" label="Light type" :error="$errors->first('light_type')">
                                <option value="">— Select —</option>
                                @foreach ($lightTypes as $lt)
                                    <option value="{{ $lt->value }}" @selected(old('light_type', $ld?->light_type?->value) === $lt->value)>{{ $lt->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                            <x-onyx.select name="content_change_frequency" label="Content change" :error="$errors->first('content_change_frequency')">
                                <option value="">— Select —</option>
                                @foreach ($contentChangeFrequencies as $cf)
                                    <option value="{{ $cf->value }}" @selected(old('content_change_frequency', $ld?->content_change_frequency?->value) === $cf->value)>{{ $cf->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                        </div>
                    </div>
                </x-onyx.card>

                {{-- Infrastructure detail --}}
                <x-onyx.card variant="default" padding="xl" x-show="isInfra()" x-cloak>
                    <x-onyx.eyebrow>Infrastructure detail</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">
                        <div style="display: grid; grid-template-columns: 1fr 150px; gap: var(--space-4);">
                            <x-onyx.input name="cable_type" label="Cable type" type="text" :value="old('cable_type', $id?->cable_type)" :error="$errors->first('cable_type')" helper="e.g. HDMI, RS232, Cat6" />
                            <x-onyx.input name="length"     label="Length (m)" type="number" step="0.01" min="0" :value="old('length', $id?->length)" :error="$errors->first('length')" />
                        </div>
                    </div>
                </x-onyx.card>

                {{-- Window Fixture detail --}}
                <x-onyx.card variant="default" padding="xl" x-show="isFixture()" x-cloak>
                    <x-onyx.eyebrow>Fixture detail</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">
                        <x-onyx.input name="fixture_dimensions" label="Fixture dimensions" type="text" :value="old('fixture_dimensions', $fd?->fixture_dimensions)" :error="$errors->first('fixture_dimensions')" helper="e.g. 1500×1110 mm" />
                    </div>
                </x-onyx.card>

                {{-- Notes --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Internal notes</x-onyx.eyebrow>
                    <div style="margin-top: var(--space-4);">
                        <x-onyx.textarea name="notes" label="Notes" :value="old('notes', $asset->notes)" :error="$errors->first('notes')" helper="Internal PM notes. Not visible to clients." rows="4" />
                    </div>
                </x-onyx.card>

                {{-- Danger zone --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Decommission</x-onyx.eyebrow>
                    <div style="margin-top: var(--space-4); display: flex; align-items: center; justify-content: space-between; gap: var(--space-4);">
                        <p style="font-size: var(--fs-14); color: var(--text-secondary);">
                            Decommissioning sets the asset status to Decommissioned and removes it from active views. The asset record is preserved for history.
                        </p>
                        @if ($asset->asset_status->value !== 'decommissioned')
                            <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Decommission this asset? The record will be preserved for history.');">
                                @csrf
                                @method('DELETE')
                                <x-onyx.button type="submit" variant="destructive" size="sm">Decommission</x-onyx.button>
                            </form>
                        @endif
                    </div>
                </x-onyx.card>

                {{-- Actions --}}
                <div style="display: flex; gap: var(--space-3); justify-content: flex-end; padding-bottom: var(--space-6);">
                    <x-onyx.button href="{{ route('assets.show', $asset) }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="primary">Save changes</x-onyx.button>
                </div>

            </div>
        </form>
    </div>

</x-layouts.app>

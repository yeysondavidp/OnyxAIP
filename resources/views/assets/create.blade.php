<x-layouts.app title="Add Asset">

    <x-slot:breadcrumbs>
        <a href="{{ route('assets.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Asset Registry</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Add asset</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 720px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Add Asset</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Register a new piece of hardware in the asset registry.</p>
        </div>

        @php
            $storesByClient = $clients->mapWithKeys(fn ($c) => [
                $c->id => $c->stores->map(fn ($s) => ['id' => $s->id, 'name' => $s->store_name])->values(),
            ]);
        @endphp

        <form method="POST" action="{{ route('assets.store') }}" novalidate
            x-data="{
                assetType: '{{ old('asset_type', '') }}',
                clientId: '{{ old('client_id', request('client_id', '')) }}',
                storeId: '{{ old('store_id', request('store_id', '')) }}',
                storesByClient: {{ $storesByClient->toJson() }},
                get filteredStores() {
                    return this.storesByClient[this.clientId] || [];
                },
                isScreen()    { return this.assetType === 'digital_screen'; },
                isPlayer()    { return this.assetType === 'media_player'; },
                isLightbox()  { return this.assetType === 'lightbox'; },
                isInfra()     { return this.assetType === 'infrastructure'; },
                isFixture()   { return this.assetType === 'window_fixture'; },
            }">
            @csrf

            <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                {{-- Identity --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Asset identity</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <x-onyx.select name="asset_type" label="Asset type" :error="$errors->first('asset_type')" required
                            x-model="assetType">
                            <option value="">— Select type —</option>
                            @foreach ($assetTypes as $type)
                                <option value="{{ $type->value }}" @selected(old('asset_type') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </x-onyx.select>

                        <x-onyx.input
                            name="asset_name"
                            label="Asset name"
                            type="text"
                            :value="old('asset_name')"
                            :error="$errors->first('asset_name')"
                            required
                            autocomplete="off"
                        />

                        <x-onyx.input
                            name="asset_code"
                            label="Asset code"
                            type="text"
                            :value="old('asset_code')"
                            :error="$errors->first('asset_code')"
                            helper="Unique identifier for QR labels, e.g. PAN-SCR-001. Max 40 characters."
                            required
                            maxlength="40"
                        />

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input
                                name="manufacturer"
                                label="Manufacturer"
                                type="text"
                                :value="old('manufacturer')"
                                :error="$errors->first('manufacturer')"
                                required
                            />
                            <x-onyx.input
                                name="model"
                                label="Model"
                                type="text"
                                :value="old('model')"
                                :error="$errors->first('model')"
                                required
                            />
                        </div>

                        <x-onyx.input
                            name="serial_number"
                            label="Serial number"
                            type="text"
                            :value="old('serial_number')"
                            :error="$errors->first('serial_number')"
                        />

                    </div>
                </x-onyx.card>

                {{-- Placement --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Placement</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        {{-- Client select --}}
                        <x-onyx.select name="client_id" label="Client" :error="$errors->first('client_id')" required
                            x-model="clientId">
                            <option value="">— Select client —</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->client_name }}</option>
                            @endforeach
                        </x-onyx.select>

                        {{-- Store select — filtered client-side by Alpine --}}
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
                            <span class="onyx-field__hint" x-show="clientId === ''">Select a client first to see its stores.</span>
                        </div>

                        <x-onyx.input
                            name="location_notes"
                            label="Location notes"
                            type="text"
                            :value="old('location_notes')"
                            :error="$errors->first('location_notes')"
                            helper="e.g. Left window bay, facing Queen St"
                        />

                    </div>
                </x-onyx.card>

                {{-- Status & dates --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Status &amp; dates</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <x-onyx.select name="asset_status" label="Status" :error="$errors->first('asset_status')" required>
                            <option value="">— Select status —</option>
                            @foreach ($assetStatuses as $status)
                                <option value="{{ $status->value }}" @selected(old('asset_status', 'active') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </x-onyx.select>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="purchase_date"   label="Purchase date"  type="date" :value="old('purchase_date')"   :error="$errors->first('purchase_date')" />
                            <x-onyx.input name="install_date"    label="Install date"   type="date" :value="old('install_date')"    :error="$errors->first('install_date')" />
                            <x-onyx.input name="warranty_expiry" label="Warranty expiry" type="date" :value="old('warranty_expiry')" :error="$errors->first('warranty_expiry')" />
                        </div>

                    </div>
                </x-onyx.card>

                {{-- Digital Screen detail --}}
                <x-onyx.card variant="default" padding="xl" x-show="isScreen()" x-cloak>
                    <x-onyx.eyebrow>Screen detail</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <div style="display: grid; grid-template-columns: 120px 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="screen_size_inches" label="Size (inches)" type="number" step="0.1" min="1" :value="old('screen_size_inches')" :error="$errors->first('screen_size_inches')" />
                            <x-onyx.input name="resolution_width"   label="Width (px)"   type="number" min="1" :value="old('resolution_width')"   :error="$errors->first('resolution_width')" />
                            <x-onyx.input name="resolution_height"  label="Height (px)"  type="number" min="1" :value="old('resolution_height')"  :error="$errors->first('resolution_height')" />
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.select name="orientation" label="Orientation" :error="$errors->first('orientation')">
                                <option value="">— Select —</option>
                                @foreach ($orientations as $o)
                                    <option value="{{ $o->value }}" @selected(old('orientation') === $o->value)>{{ $o->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                            <x-onyx.select name="totem_supplied_by" label="Totem supplied by" :error="$errors->first('totem_supplied_by')">
                                <option value="">— Select —</option>
                                @foreach ($totemSuppliedByOptions as $opt)
                                    <option value="{{ $opt->value }}" @selected(old('totem_supplied_by') === $opt->value)>{{ $opt->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                        </div>

                        <x-onyx.input name="mount_type" label="Mount type" type="text" :value="old('mount_type')" :error="$errors->first('mount_type')" helper="e.g. Floor Totem, Wall Mount, Window Flush" />

                    </div>
                </x-onyx.card>

                {{-- Media Player detail --}}
                <x-onyx.card variant="default" padding="xl" x-show="isPlayer()" x-cloak>
                    <x-onyx.eyebrow>Player detail</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <x-onyx.select name="player_type" label="Player type" :error="$errors->first('player_type')">
                            <option value="">— Select —</option>
                            @foreach ($playerTypes as $pt)
                                <option value="{{ $pt->value }}" @selected(old('player_type') === $pt->value)>{{ $pt->label() }}</option>
                            @endforeach
                        </x-onyx.select>

                        <x-onyx.input name="cms_platform"     label="CMS platform"     type="text" :value="old('cms_platform')"     :error="$errors->first('cms_platform')"     helper="e.g. Navori QL, Samsung MagicInfo, Beat CMS" />
                        <x-onyx.input name="ip_address"       label="IP address"       type="text" :value="old('ip_address')"       :error="$errors->first('ip_address')" />
                        <x-onyx.input name="mac_address"      label="MAC address"      type="text" :value="old('mac_address')"      :error="$errors->first('mac_address')"      helper="Format: AA:BB:CC:DD:EE:FF" />
                        <x-onyx.input name="firmware_version" label="Firmware version" type="text" :value="old('firmware_version')" :error="$errors->first('firmware_version')" />

                        <x-onyx.alert tone="info">
                            Screen connectivity (which screens this player drives) is managed via Display Groups and is not stored here.
                        </x-onyx.alert>

                    </div>
                </x-onyx.card>

                {{-- Lightbox detail --}}
                <x-onyx.card variant="default" padding="xl" x-show="isLightbox()" x-cloak>
                    <x-onyx.eyebrow>Lightbox detail</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <x-onyx.input name="lightbox_dimensions" label="Dimensions" type="text" :value="old('lightbox_dimensions')" :error="$errors->first('lightbox_dimensions')" helper="W × H × D in mm, e.g. 1200×800×50" />

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.select name="light_type" label="Light type" :error="$errors->first('light_type')">
                                <option value="">— Select —</option>
                                @foreach ($lightTypes as $lt)
                                    <option value="{{ $lt->value }}" @selected(old('light_type') === $lt->value)>{{ $lt->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                            <x-onyx.select name="content_change_frequency" label="Content change frequency" :error="$errors->first('content_change_frequency')">
                                <option value="">— Select —</option>
                                @foreach ($contentChangeFrequencies as $cf)
                                    <option value="{{ $cf->value }}" @selected(old('content_change_frequency') === $cf->value)>{{ $cf->label() }}</option>
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
                            <x-onyx.input name="cable_type" label="Cable type" type="text" :value="old('cable_type')" :error="$errors->first('cable_type')" helper="e.g. HDMI, RS232, Cat6" />
                            <x-onyx.input name="length"     label="Length (m)" type="number" step="0.01" min="0" :value="old('length')" :error="$errors->first('length')" />
                        </div>

                    </div>
                </x-onyx.card>

                {{-- Window Fixture detail --}}
                <x-onyx.card variant="default" padding="xl" x-show="isFixture()" x-cloak>
                    <x-onyx.eyebrow>Fixture detail</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <x-onyx.input name="fixture_dimensions" label="Fixture dimensions" type="text" :value="old('fixture_dimensions')" :error="$errors->first('fixture_dimensions')" helper="e.g. 1500×1110 mm" />

                    </div>
                </x-onyx.card>

                {{-- Notes --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Internal notes</x-onyx.eyebrow>
                    <div style="margin-top: var(--space-4);">
                        <x-onyx.textarea name="notes" label="Notes" :value="old('notes')" :error="$errors->first('notes')" helper="Internal PM notes. Not visible to clients." rows="4" />
                    </div>
                </x-onyx.card>

                {{-- Actions --}}
                <div style="display: flex; gap: var(--space-3); justify-content: flex-end; padding-bottom: var(--space-6);">
                    <x-onyx.button href="{{ route('assets.index') }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="primary">Save asset</x-onyx.button>
                </div>

            </div>
        </form>
    </div>

</x-layouts.app>

<x-layouts.app title="Edit — {{ $store->store_name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('stores.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Stores</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('stores.show', $store) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $store->store_name }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Edit</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 680px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Edit Store</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">{{ $store->store_name }} — {{ $store->store_code }}</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('stores.update', $store) }}" novalidate>
                @csrf
                @method('PATCH')

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.eyebrow>Store identity</x-onyx.eyebrow>

                    {{-- Client is fixed at creation — read-only display --}}
                    <div>
                        <p class="onyx-field__label" style="font: var(--type-label); text-transform: uppercase; letter-spacing: var(--tracking-wide); color: var(--text-secondary); margin-bottom: var(--space-2);">CLIENT</p>
                        <p style="font-size: var(--fs-16); color: var(--text-secondary); padding: 11px 14px; background: var(--surface-sunken); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                            {{ $store->client?->client_name ?? '—' }}
                            <span style="font-size: var(--fs-13); color: var(--text-muted); margin-left: var(--space-2);">(fixed at creation)</span>
                        </p>
                    </div>

                    <x-onyx.input
                        name="store_name"
                        label="Store name"
                        type="text"
                        :value="old('store_name', $store->store_name)"
                        :error="$errors->first('store_name')"
                        required
                    />

                    <x-onyx.input
                        name="store_code"
                        label="Store code"
                        type="text"
                        :value="old('store_code', $store->store_code)"
                        :error="$errors->first('store_code')"
                        required
                        maxlength="20"
                    />

                    <x-onyx.select name="store_type" label="Store type" :error="$errors->first('store_type')" required>
                        <option value="">— Select type —</option>
                        @foreach ($storeTypes as $type)
                            <option value="{{ $type->value }}" @selected(old('store_type', $store->store_type?->value) === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.divider />
                    <x-onyx.eyebrow>Address</x-onyx.eyebrow>

                    <x-onyx.input
                        name="address_line1"
                        label="Street address"
                        type="text"
                        :value="old('address_line1', $store->address_line1)"
                        :error="$errors->first('address_line1')"
                        required
                    />

                    <div style="display: grid; grid-template-columns: 1fr 100px; gap: var(--space-4);">
                        <x-onyx.input
                            name="suburb"
                            label="Suburb"
                            type="text"
                            :value="old('suburb', $store->suburb)"
                            :error="$errors->first('suburb')"
                            required
                        />
                        <x-onyx.input
                            name="postcode"
                            label="Postcode"
                            type="text"
                            :value="old('postcode', $store->postcode)"
                            :error="$errors->first('postcode')"
                            required
                        />
                    </div>

                    <x-onyx.select name="state" label="State / territory" :error="$errors->first('state')" required>
                        <option value="">— Select state —</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->value }}" @selected(old('state', $store->state?->value) === $state->value)>
                                {{ $state->value }} — {{ $state->label() }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                    <input type="hidden" name="country" value="{{ old('country', $store->country ?? 'Australia') }}">

                    <x-onyx.select name="store_timezone" label="Timezone" :error="$errors->first('store_timezone')" required>
                        <option value="">— Select timezone —</option>
                        @foreach ($timezones as $tz => $label)
                            <option value="{{ $tz }}" @selected(old('store_timezone', $store->store_timezone) === $tz)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.divider />
                    <x-onyx.eyebrow>Store manager contact <span style="font-weight: normal; text-transform: none; font-size: var(--fs-12); color: var(--text-muted);">(optional)</span></x-onyx.eyebrow>

                    <x-onyx.input
                        name="store_manager_name"
                        label="Manager name"
                        type="text"
                        :value="old('store_manager_name', $store->store_manager_name)"
                        :error="$errors->first('store_manager_name')"
                    />

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <x-onyx.input
                            name="store_manager_phone"
                            label="Phone"
                            type="tel"
                            :value="old('store_manager_phone', $store->store_manager_phone)"
                            :error="$errors->first('store_manager_phone')"
                        />
                        <x-onyx.input
                            name="store_manager_email"
                            label="Email"
                            type="email"
                            :value="old('store_manager_email', $store->store_manager_email)"
                            :error="$errors->first('store_manager_email')"
                        />
                    </div>

                    <x-onyx.divider />

                    <x-onyx.textarea
                        name="notes"
                        label="Internal notes"
                        :error="$errors->first('notes')"
                        rows="3"
                    >{{ old('notes', $store->notes) }}</x-onyx.textarea>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-7);">
                    <x-onyx.button href="{{ route('stores.show', $store) }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="accent">Save changes</x-onyx.button>
                </div>

            </form>
        </x-onyx.card>

        {{-- Deactivate --}}
        @if ($store->is_active)
            <div style="margin-top: var(--space-6);">
                <x-onyx.card variant="outline" padding="md">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4);">
                        <div>
                            <p style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Deactivate store</p>
                            <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">
                                The store record and all its history are preserved. Deactivated stores are hidden from active lists.
                            </p>
                        </div>
                        <form method="POST" action="{{ route('stores.destroy', $store) }}"
                              onsubmit="return confirm('Deactivate {{ addslashes($store->store_name) }}? The record will be preserved but hidden from active lists.')">
                            @csrf
                            @method('DELETE')
                            <x-onyx.button type="submit" variant="outline" size="sm"
                                           style="color: var(--critical); border-color: var(--critical);">
                                Deactivate
                            </x-onyx.button>
                        </form>
                    </div>
                </x-onyx.card>
            </div>
        @endif
    </div>

</x-layouts.app>

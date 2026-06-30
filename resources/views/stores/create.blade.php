<x-layouts.app title="Add Store">

    <x-slot:breadcrumbs>
        <a href="{{ route('stores.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Stores</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Add store</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 680px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Add Store</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Register a new retail location under a client.</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('stores.store') }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    {{-- Store identity --}}
                    <x-onyx.eyebrow>Store identity</x-onyx.eyebrow>

                    <x-onyx.select name="client_id" label="Client" :error="$errors->first('client_id')" required>
                        <option value="">— Select client —</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id', request('client_id')) == $client->id)>
                                {{ $client->client_name }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.input
                        name="store_name"
                        label="Store name"
                        type="text"
                        :value="old('store_name')"
                        :error="$errors->first('store_name')"
                        required
                        autocomplete="off"
                    />

                    <x-onyx.input
                        name="store_code"
                        label="Store code"
                        type="text"
                        :value="old('store_code')"
                        :error="$errors->first('store_code')"
                        helper="Unique identifier, e.g. PAN-SYD-001. Max 20 characters."
                        required
                        maxlength="20"
                    />

                    <x-onyx.select name="store_type" label="Store type" :error="$errors->first('store_type')" required>
                        <option value="">— Select type —</option>
                        @foreach ($storeTypes as $type)
                            <option value="{{ $type->value }}" @selected(old('store_type') === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.divider />

                    {{-- Address --}}
                    <x-onyx.eyebrow>Address</x-onyx.eyebrow>

                    <x-onyx.input
                        name="address_line1"
                        label="Street address"
                        type="text"
                        :value="old('address_line1')"
                        :error="$errors->first('address_line1')"
                        required
                        autocomplete="address-line1"
                    />

                    <div style="display: grid; grid-template-columns: 1fr 100px; gap: var(--space-4);">
                        <x-onyx.input
                            name="suburb"
                            label="Suburb"
                            type="text"
                            :value="old('suburb')"
                            :error="$errors->first('suburb')"
                            required
                            autocomplete="address-level2"
                        />
                        <x-onyx.input
                            name="postcode"
                            label="Postcode"
                            type="text"
                            :value="old('postcode')"
                            :error="$errors->first('postcode')"
                            required
                            maxlength="10"
                        />
                    </div>

                    <x-onyx.select name="state" label="State / territory" :error="$errors->first('state')" required>
                        <option value="">— Select state —</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->value }}" @selected(old('state') === $state->value)>
                                {{ $state->value }} — {{ $state->label() }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                    <input type="hidden" name="country" value="Australia">

                    <x-onyx.select name="store_timezone" label="Timezone" :error="$errors->first('store_timezone')" required>
                        <option value="">— Select timezone —</option>
                        @foreach ($timezones as $tz => $label)
                            <option value="{{ $tz }}" @selected(old('store_timezone') === $tz)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.divider />

                    {{-- Store manager contact --}}
                    <x-onyx.eyebrow>Store manager contact <span style="font-weight: normal; text-transform: none; font-size: var(--fs-12); color: var(--text-muted);">(optional)</span></x-onyx.eyebrow>

                    <x-onyx.input
                        name="store_manager_name"
                        label="Manager name"
                        type="text"
                        :value="old('store_manager_name')"
                        :error="$errors->first('store_manager_name')"
                        autocomplete="off"
                    />

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <x-onyx.input
                            name="store_manager_phone"
                            label="Phone"
                            type="tel"
                            :value="old('store_manager_phone')"
                            :error="$errors->first('store_manager_phone')"
                        />
                        <x-onyx.input
                            name="store_manager_email"
                            label="Email"
                            type="email"
                            :value="old('store_manager_email')"
                            :error="$errors->first('store_manager_email')"
                        />
                    </div>

                    <x-onyx.divider />

                    <x-onyx.textarea
                        name="notes"
                        label="Internal notes"
                        :error="$errors->first('notes')"
                        rows="3"
                        helper="Internal PM notes — not visible to the client or technician."
                    >{{ old('notes') }}</x-onyx.textarea>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-7);">
                    <x-onyx.button href="{{ route('stores.index') }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="accent">Save store</x-onyx.button>
                </div>

            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>

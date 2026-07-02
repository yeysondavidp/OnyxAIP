<x-layouts.app title="Asset Register Report">

    <x-slot:breadcrumbs>
        <a href="{{ route('reports.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Reports</a>
        <span style="color: var(--text-muted); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Asset Register</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 560px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Asset Register</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Export a full inventory (CSV or PDF), or a status summary (CSV), for one client.</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('reports.asset-register.store') }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.select name="client_id" label="Client" :error="$errors->first('client_id')" required>
                        <option value="">— Select a client —</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->client_name }}</option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.select name="store_id" label="Store (optional)" :error="$errors->first('store_id')" helper="Leave blank to include every store for the selected client.">
                        <option value="">— All stores —</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" data-client="{{ $store->client_id }}" @selected(old('store_id') == $store->id)>{{ $store->store_name }}</option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.select name="state" label="State (optional)" :error="$errors->first('state')">
                        <option value="">— All states —</option>
                        @foreach (\App\Enums\AustralianState::cases() as $state)
                            <option value="{{ $state->value }}" @selected(old('state') === $state->value)>{{ $state->label() }}</option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.select name="asset_type" label="Asset type (optional)" :error="$errors->first('asset_type')">
                        <option value="">— All types —</option>
                        @foreach (\App\Enums\AssetType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('asset_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.divider />

                    <div class="onyx-field">
                        <label class="onyx-field__label">Report</label>
                        <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-2);">
                            @foreach ([
                                'register_csv'       => 'Asset Register — CSV',
                                'register_pdf'       => 'Asset Register — PDF',
                                'status_summary_csv' => 'Asset Status Summary — CSV',
                            ] as $value => $label)
                                <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-primary); cursor: pointer;">
                                    <input type="radio" name="report_kind" value="{{ $value }}" @checked(old('report_kind', 'register_csv') === $value) required style="width: 16px; height: 16px; cursor: pointer;">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('report_kind')
                            <span class="onyx-field__hint onyx-field__hint--error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-2);">
                        <x-onyx.button href="{{ route('reports.index') }}" variant="outline">Cancel</x-onyx.button>
                        <x-onyx.button type="submit" variant="accent">Run report</x-onyx.button>
                    </div>

                </div>
            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>

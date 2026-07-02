<x-layouts.app title="Service History Report">

    <x-slot:breadcrumbs>
        <a href="{{ route('reports.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Reports</a>
        <span style="color: var(--text-muted); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Service History</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 560px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Service History</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Documented maintenance record for a single asset, or a whole store.</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('reports.service-history.store') }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <div class="onyx-field">
                        <label class="onyx-field__label">Report</label>
                        <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-2);">
                            @foreach ([
                                'asset_pdf' => 'Per asset — PDF',
                                'store_pdf' => 'Per store — PDF',
                                'store_csv' => 'Per store — CSV',
                            ] as $value => $label)
                                <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-primary); cursor: pointer;">
                                    <input type="radio" name="report_kind" value="{{ $value }}" @checked(old('report_kind', 'asset_pdf') === $value) required style="width: 16px; height: 16px; cursor: pointer;">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('report_kind')
                            <span class="onyx-field__hint onyx-field__hint--error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>

                    <x-onyx.divider />

                    <x-onyx.select name="asset_id" label="Asset (for per-asset report)" :error="$errors->first('asset_id')">
                        <option value="">— Select an asset —</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}" @selected(old('asset_id') == $asset->id)>{{ $asset->asset_code }} — {{ $asset->asset_name }}</option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.select name="store_id" label="Store (for per-store report)" :error="$errors->first('store_id')">
                        <option value="">— Select a store —</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>{{ $store->store_name }}</option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.select name="asset_type" label="Asset type filter (store report only, optional)" :error="$errors->first('asset_type')">
                        <option value="">— All types —</option>
                        @foreach (\App\Enums\AssetType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('asset_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </x-onyx.select>

                    <div style="display: flex; gap: var(--space-4);">
                        <x-onyx.input name="date_from" type="date" label="From (optional)" :value="old('date_from')" :error="$errors->first('date_from')" />
                        <x-onyx.input name="date_to" type="date" label="To (optional)" :value="old('date_to')" :error="$errors->first('date_to')" />
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

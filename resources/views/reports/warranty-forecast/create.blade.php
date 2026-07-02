<x-layouts.app title="Warranty Expiry Forecast Report">

    <x-slot:breadcrumbs>
        <a href="{{ route('reports.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Reports</a>
        <span style="color: var(--text-muted); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Warranty Expiry Forecast</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 560px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Warranty Expiry Forecast</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Assets whose warranty falls within a date range (CSV).</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('reports.warranty-forecast.store') }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.select name="client_id" label="Client" :error="$errors->first('client_id')" required>
                        <option value="">— Select a client —</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->client_name }}</option>
                        @endforeach
                    </x-onyx.select>

                    <div style="display: flex; gap: var(--space-4);">
                        <x-onyx.input name="date_from" type="date" label="From" :value="old('date_from')" :error="$errors->first('date_from')" required />
                        <x-onyx.input name="date_to" type="date" label="To" :value="old('date_to')" :error="$errors->first('date_to')" required />
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

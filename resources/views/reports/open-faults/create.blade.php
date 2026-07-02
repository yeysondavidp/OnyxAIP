<x-layouts.app title="Open Faults Report">

    <x-slot:breadcrumbs>
        <a href="{{ route('reports.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Reports</a>
        <span style="color: var(--text-muted); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Open Faults</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 560px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Open Faults</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Every asset currently Faulty or Offline across a client's estate (CSV).</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('reports.open-faults.store') }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.select name="client_id" label="Client" :error="$errors->first('client_id')" required>
                        <option value="">— Select a client —</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->client_name }}</option>
                        @endforeach
                    </x-onyx.select>

                    <x-onyx.select name="state" label="State (optional)" :error="$errors->first('state')">
                        <option value="">— All states —</option>
                        @foreach (\App\Enums\AustralianState::cases() as $state)
                            <option value="{{ $state->value }}" @selected(old('state') === $state->value)>{{ $state->label() }}</option>
                        @endforeach
                    </x-onyx.select>

                    <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-2);">
                        <x-onyx.button href="{{ route('reports.index') }}" variant="outline">Cancel</x-onyx.button>
                        <x-onyx.button type="submit" variant="accent">Run report</x-onyx.button>
                    </div>

                </div>
            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>

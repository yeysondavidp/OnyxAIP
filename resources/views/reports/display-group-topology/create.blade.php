<x-layouts.app title="Display Group Topology Report">

    <x-slot:breadcrumbs>
        <a href="{{ route('reports.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Reports</a>
        <span style="color: var(--text-muted); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Display Group Topology</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 560px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Display Group Topology</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Player-to-screen map for a store's client reporting pack (PDF).</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('reports.display-group-topology.store') }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.select name="store_id" label="Store" :error="$errors->first('store_id')" required>
                        <option value="">— Select a store —</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>{{ $store->store_name }}</option>
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

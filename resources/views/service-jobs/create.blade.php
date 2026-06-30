<x-layouts.app title="Create Service Job">

    <x-slot:breadcrumbs>
        <a href="{{ route('jobs.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Service Jobs</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Create job</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 760px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Create Service Job</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Create a field service visit record for a store.</p>
        </div>

        @php
            $storesByClientJson = $storesByClient->toJson();

            // Assets keyed by store_id for the asset picker
            $allStores = \App\Models\Store::with(['assets' => fn ($q) => $q->orderBy('asset_name')])->get();
            $assetsByStore = $allStores->mapWithKeys(fn ($s) => [
                $s->id => $s->assets->map(fn ($a) => [
                    'id'   => $a->id,
                    'name' => $a->asset_name.' ('.$a->asset_code.')',
                    'status' => $a->asset_status->label(),
                ])->values(),
            ])->toJson();
        @endphp

        <form method="POST" action="{{ route('jobs.store') }}" novalidate
            x-data="{
                clientId: '{{ old('client_id', $selectedStore?->client_id ?? '') }}',
                storeId: '{{ old('store_id', $selectedStore?->id ?? '') }}',
                storesByClient: {{ $storesByClientJson }},
                assetsByStore: {{ $assetsByStore }},
                selectedAssets: {{ json_encode(old('asset_ids', [])) }},
                selectedTechs: {{ json_encode(old('technician_ids', [])) }},
                get filteredStores() {
                    return this.storesByClient[this.clientId] || [];
                },
                get storeAssets() {
                    return this.assetsByStore[this.storeId] || [];
                },
                toggleAsset(id) {
                    const idx = this.selectedAssets.indexOf(id);
                    if (idx === -1) this.selectedAssets.push(id);
                    else this.selectedAssets.splice(idx, 1);
                },
                toggleTech(id) {
                    const idx = this.selectedTechs.indexOf(id);
                    if (idx === -1) this.selectedTechs.push(id);
                    else this.selectedTechs.splice(idx, 1);
                },
                isAssetSelected(id) { return this.selectedAssets.includes(id); },
                isTechSelected(id) { return this.selectedTechs.includes(id); },
            }">
            @csrf

            <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                {{-- Section: Job identity --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Job identity</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="job_reference" label="Job reference" :value="old('job_reference')"
                                :error="$errors->first('job_reference')" required placeholder="e.g. JOB-2026-001" />

                            <x-onyx.select name="job_type" label="Job type" :error="$errors->first('job_type')" required>
                                <option value="">— Select type —</option>
                                @foreach ($jobTypes as $type)
                                    <option value="{{ $type->value }}" @selected(old('job_type') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                        </div>

                        <x-onyx.input name="job_name" label="Job name" :value="old('job_name')"
                            :error="$errors->first('job_name')" required placeholder="e.g. Pandora Pitt St Mall — Q3 Maintenance" />

                        <x-onyx.textarea name="job_description" label="Job description" :value="old('job_description')"
                            :error="$errors->first('job_description')" required rows="4"
                            placeholder="Describe the scope and tasks for this visit…" />

                    </div>
                </x-onyx.card>

                {{-- Section: Location --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Location</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <div>
                                <label style="display: block; font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary); margin-bottom: var(--space-1);">
                                    Client <span style="color: var(--critical-600);">*</span>
                                </label>
                                <select name="client_id" x-model="clientId" @change="storeId = ''; selectedAssets = [];"
                                    style="width: 100%; height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-14); background: var(--surface-primary); color: var(--text-primary);">
                                    <option value="">— Select client —</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->client_name }}</option>
                                    @endforeach
                                </select>
                                @error('client_id')
                                    <p style="font-size: var(--fs-12); color: var(--critical-600); margin-top: var(--space-1);">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label style="display: block; font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary); margin-bottom: var(--space-1);">
                                    Store <span style="color: var(--critical-600);">*</span>
                                </label>
                                <select name="store_id" x-model="storeId" @change="selectedAssets = []"
                                    :disabled="filteredStores.length === 0"
                                    style="width: 100%; height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-14); background: var(--surface-primary); color: var(--text-primary);">
                                    <option value="">— Select store —</option>
                                    <template x-for="store in filteredStores" :key="store.id">
                                        <option :value="store.id" :selected="storeId == store.id" x-text="store.name"></option>
                                    </template>
                                </select>
                                @error('store_id')
                                    <p style="font-size: var(--fs-12); color: var(--critical-600); margin-top: var(--space-1);">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                    </div>
                </x-onyx.card>

                {{-- Section: Schedule --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Schedule</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="scheduled_date" label="Scheduled date" type="date"
                                :value="old('scheduled_date')" :error="$errors->first('scheduled_date')" />

                            <x-onyx.input name="scheduled_time" label="Scheduled time" type="time"
                                :value="old('scheduled_time')" :error="$errors->first('scheduled_time')" />

                            <x-onyx.select name="early_start_window" label="Early start window"
                                :error="$errors->first('early_start_window')" required>
                                @foreach ($earlyStartWindows as $window)
                                    <option value="{{ $window->value }}" @selected(old('early_start_window', 'anytime') === $window->value)>{{ $window->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                        </div>

                    </div>
                </x-onyx.card>

                {{-- Section: Affected assets --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Affected assets</x-onyx.eyebrow>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">
                        Select assets from the chosen store. Active or Faulty assets will be moved to Under Maintenance when the job is created.
                    </p>
                    @error('asset_ids')
                        <x-onyx.alert tone="critical" style="margin-top: var(--space-3);">{{ $message }}</x-onyx.alert>
                    @enderror

                    <div style="margin-top: var(--space-4);">
                        <template x-if="storeId === '' || storeAssets.length === 0">
                            <p style="font-size: var(--fs-13); color: var(--text-tertiary); padding: var(--space-3); border: 1px dashed var(--border-default); border-radius: var(--radius-md); text-align: center;">
                                Select a store to see its assets.
                            </p>
                        </template>

                        <template x-if="storeId !== '' && storeAssets.length > 0">
                            <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                                <template x-for="asset in storeAssets" :key="asset.id">
                                    <label style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); cursor: pointer; min-height: 44px;"
                                        :style="isAssetSelected(asset.id) ? 'border-color: var(--bronze-500); background: var(--bronze-50);' : ''">
                                        <input type="checkbox" :value="asset.id" name="asset_ids[]"
                                            :checked="isAssetSelected(asset.id)"
                                            @change="toggleAsset(asset.id)"
                                            style="accent-color: var(--bronze-600); flex-shrink: 0;">
                                        <span>
                                            <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);" x-text="asset.name"></span>
                                            <span style="font-size: var(--fs-12); color: var(--text-secondary); margin-left: var(--space-2);" x-text="'• ' + asset.status"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </template>
                    </div>
                </x-onyx.card>

                {{-- Section: Assign technicians --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Assigned technicians</x-onyx.eyebrow>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">
                        Multiple technicians can be assigned. Each manages their own accept → start → complete lifecycle.
                    </p>
                    @error('technician_ids')
                        <x-onyx.alert tone="critical" style="margin-top: var(--space-3);">{{ $message }}</x-onyx.alert>
                    @enderror

                    <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-top: var(--space-4);">
                        @forelse ($technicians as $tech)
                            <label style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); cursor: pointer; min-height: 44px;"
                                :style="isTechSelected({{ $tech->id }}) ? 'border-color: var(--bronze-500); background: var(--bronze-50);' : ''">
                                <input type="checkbox" name="technician_ids[]" value="{{ $tech->id }}"
                                    :checked="isTechSelected({{ $tech->id }})"
                                    @change="toggleTech({{ $tech->id }})"
                                    style="accent-color: var(--bronze-600); flex-shrink: 0;">
                                <span>
                                    <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $tech->name }}</span>
                                    <span style="font-size: var(--fs-12); color: var(--text-secondary); margin-left: var(--space-2);">{{ $tech->email }}</span>
                                </span>
                            </label>
                        @empty
                            <p style="font-size: var(--fs-13); color: var(--text-tertiary); padding: var(--space-3); border: 1px dashed var(--border-default); border-radius: var(--radius-md); text-align: center;">
                                No technicians have been added yet. <a href="{{ route('technicians.create') }}" style="color: var(--bronze-600);">Add a technician</a>.
                            </p>
                        @endforelse
                    </div>
                </x-onyx.card>

                {{-- Section: Campaign parent (optional) --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Campaign (optional)</x-onyx.eyebrow>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">
                        Attach this job as a sub-job under a campaign. Leave blank for a standalone job.
                    </p>
                    @error('parent_job_id')
                        <x-onyx.alert tone="critical" style="margin-top: var(--space-3);">{{ $message }}</x-onyx.alert>
                    @enderror

                    <div style="margin-top: var(--space-4);">
                        <x-onyx.select name="parent_job_id" label="Parent campaign" :error="$errors->first('parent_job_id')">
                            <option value="">— Standalone job —</option>
                            @foreach ($parentJobs as $parent)
                                <option value="{{ $parent->id }}" @selected(old('parent_job_id') == $parent->id)>
                                    {{ $parent->job_reference }} — {{ $parent->job_name }}
                                </option>
                            @endforeach
                        </x-onyx.select>
                    </div>
                </x-onyx.card>

                {{-- Section: Client contact (optional) --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Client contact (optional)</x-onyx.eyebrow>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-top: var(--space-4);">
                        <x-onyx.input name="client_name" label="Client contact name" :value="old('client_name')"
                            :error="$errors->first('client_name')" placeholder="e.g. Jane Smith" />
                        <x-onyx.input name="client_email" label="Client contact email" type="email" :value="old('client_email')"
                            :error="$errors->first('client_email')" placeholder="e.g. jane@client.com" />
                    </div>
                </x-onyx.card>

                {{-- Submit --}}
                <div style="display: flex; gap: var(--space-3);">
                    <x-onyx.button type="submit" variant="primary">Create job</x-onyx.button>
                    <x-onyx.button href="{{ route('jobs.index') }}" variant="ghost">Cancel</x-onyx.button>
                </div>

            </div>
        </form>
    </div>

</x-layouts.app>

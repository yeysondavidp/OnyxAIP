<x-layouts.app title="Edit Job — {{ $job->job_name }}">

    <style>
        /* Base row styles live in a class (not inline) so .onyx-row-selected's
           border-color can win the cascade — an inline border shorthand would
           always beat a class, regardless of specificity. */
        .onyx-row {
            display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3);
            border: 1px solid var(--border-default); border-radius: var(--radius-md);
            cursor: pointer; min-height: 44px;
        }
        .onyx-row-selected { border-color: var(--bronze-500); background: var(--bronze-100); }
    </style>

    <x-slot:breadcrumbs>
        <a href="{{ route('jobs.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Service Jobs</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('jobs.show', $job) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $job->job_reference }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Edit</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 760px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Edit Service Job</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Store and client cannot be changed after creation.</p>
        </div>

        @php
            $currentAssetIds = $job->assets->pluck('id')->toArray();
            $currentTechIds  = $job->technicians->pluck('id')->toArray();

            $storeAssets = \App\Models\Asset::where('store_id', $job->store_id)
                ->orderBy('asset_name')
                ->get()
                ->map(fn ($a) => [
                    'id'     => $a->id,
                    'name'   => $a->asset_name.' ('.$a->asset_code.')',
                    'status' => $a->asset_status->label(),
                ])->values()->toJson();
        @endphp

        <form method="POST" action="{{ route('jobs.update', $job) }}" novalidate
            x-data="{
                selectedAssets: {{ json_encode(old('asset_ids', $currentAssetIds)) }},
                selectedTechs: {{ json_encode(old('technician_ids', $currentTechIds)) }},
                storeAssets: {{ $storeAssets }},
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
                flexible: {{ old('is_flexible', (! $job->scheduled_date && ! $job->scheduled_time) ? '1' : '') ? 'true' : 'false' }},
                scheduledDate: '{{ old('scheduled_date', $job->scheduled_date?->format('Y-m-d')) }}',
                scheduledTime: '{{ old('scheduled_time', $job->scheduled_time) }}',
                earlyStartWindow: '{{ old('early_start_window', $job->early_start_window->value) }}',
                setFlexible(value) {
                    this.flexible = value;
                    if (value) {
                        this.scheduledDate = '';
                        this.scheduledTime = '';
                        this.earlyStartWindow = 'anytime';
                    }
                },
            }">
            @csrf
            @method('PUT')

            <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                {{-- Store is read-only after creation --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Location (read-only)</x-onyx.eyebrow>
                    <div style="display: flex; gap: var(--space-6); margin-top: var(--space-3); font-size: var(--fs-14);">
                        <div>
                            <span style="color: var(--text-secondary);">Client</span>
                            <span style="margin-left: var(--space-3); color: var(--text-primary); font-weight: var(--weight-medium);">{{ $job->client?->client_name }}</span>
                        </div>
                        <div>
                            <span style="color: var(--text-secondary);">Store</span>
                            <span style="margin-left: var(--space-3); color: var(--text-primary); font-weight: var(--weight-medium);">{{ $job->store?->store_name }}</span>
                        </div>
                        <div>
                            <span style="color: var(--text-secondary);">Timezone</span>
                            <span style="margin-left: var(--space-3); color: var(--text-primary); font-family: monospace; font-size: var(--fs-13);">{{ $job->job_timezone }}</span>
                        </div>
                    </div>
                </x-onyx.card>

                {{-- Job identity --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Job identity</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="job_reference" label="Job reference"
                                :value="old('job_reference', $job->job_reference)"
                                :error="$errors->first('job_reference')" required />

                            <x-onyx.select name="job_type" label="Job type" :error="$errors->first('job_type')" required>
                                <option value="">— Select type —</option>
                                @foreach ($jobTypes as $type)
                                    <option value="{{ $type->value }}"
                                        @selected(old('job_type', $job->job_type->value) === $type->value)>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </x-onyx.select>
                        </div>

                        <x-onyx.input name="job_name" label="Job name"
                            :value="old('job_name', $job->job_name)"
                            :error="$errors->first('job_name')" required />

                        <x-onyx.textarea name="job_description" label="Job description"
                            :value="old('job_description', $job->job_description)"
                            :error="$errors->first('job_description')" required rows="4" />

                    </div>
                </x-onyx.card>

                {{-- Schedule --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Schedule</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">

                        <label style="display: flex; align-items: center; gap: var(--space-2); min-height: 44px; cursor: pointer; width: fit-content;">
                            <input type="checkbox" name="is_flexible" value="1"
                                :checked="flexible" @change="setFlexible($event.target.checked)"
                                style="width: 20px; height: 20px;">
                            <span style="font-size: var(--fs-14); color: var(--text-primary);">Flexible — no fixed date or time</span>
                        </label>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-4);"
                            :style="flexible ? 'opacity: 0.5; pointer-events: none;' : ''">
                            <x-onyx.input name="scheduled_date" label="Scheduled date" type="date"
                                x-model="scheduledDate" x-bind:disabled="flexible"
                                :error="$errors->first('scheduled_date')" />

                            <x-onyx.input name="scheduled_time" label="Scheduled time" type="time"
                                x-model="scheduledTime" x-bind:disabled="flexible"
                                :error="$errors->first('scheduled_time')" />

                            <x-onyx.select name="early_start_window" label="Early start window"
                                x-model="earlyStartWindow" x-bind:disabled="flexible"
                                :error="$errors->first('early_start_window')">
                                @foreach ($earlyStartWindows as $window)
                                    <option value="{{ $window->value }}">{{ $window->label() }}</option>
                                @endforeach
                            </x-onyx.select>
                        </div>

                        <p style="font-size: var(--fs-13); color: var(--text-tertiary);" x-show="flexible" x-cloak>
                            This job will be saved without a scheduled date or time — the technician can be assigned a time later.
                        </p>
                    </div>
                </x-onyx.card>

                {{-- Affected assets --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Affected assets</x-onyx.eyebrow>
                    @error('asset_ids')
                        <x-onyx.alert tone="critical" style="margin-top: var(--space-3);">{{ $message }}</x-onyx.alert>
                    @enderror

                    <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-top: var(--space-4);">
                        @if ($job->store && \App\Models\Asset::where('store_id', $job->store_id)->exists())
                            <template x-for="asset in storeAssets" :key="asset.id">
                                <label class="onyx-row"
                                    :class="isAssetSelected(asset.id) ? 'onyx-row-selected' : ''">
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
                        @else
                            <p style="font-size: var(--fs-13); color: var(--text-tertiary); padding: var(--space-3); border: 1px dashed var(--border-default); border-radius: var(--radius-md); text-align: center;">
                                No assets registered for this store yet.
                            </p>
                        @endif
                    </div>
                </x-onyx.card>

                {{-- Assign technicians --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Assigned technicians</x-onyx.eyebrow>
                    @error('technician_ids')
                        <x-onyx.alert tone="critical" style="margin-top: var(--space-3);">{{ $message }}</x-onyx.alert>
                    @enderror
                    <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-top: var(--space-4);">
                        @forelse ($technicians as $tech)
                            <label class="onyx-row"
                                :class="isTechSelected({{ $tech->id }}) ? 'onyx-row-selected' : ''">
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
                                No technicians have been added yet.
                            </p>
                        @endforelse
                    </div>
                </x-onyx.card>

                {{-- Client contact --}}
                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Client contact (optional)</x-onyx.eyebrow>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-top: var(--space-4);">
                        <x-onyx.input name="client_name" label="Client contact name"
                            :value="old('client_name', $job->client_name)"
                            :error="$errors->first('client_name')" />
                        <x-onyx.input name="client_email" label="Client contact email" type="email"
                            :value="old('client_email', $job->client_email)"
                            :error="$errors->first('client_email')" />
                    </div>
                </x-onyx.card>

                <div style="display: flex; gap: var(--space-3);">
                    <x-onyx.button type="submit" variant="primary">Save changes</x-onyx.button>
                    <x-onyx.button href="{{ route('jobs.show', $job) }}" variant="ghost">Cancel</x-onyx.button>
                </div>

            </div>
        </form>
    </div>

</x-layouts.app>

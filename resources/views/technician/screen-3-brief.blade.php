<x-layouts.technician title="Brief — {{ $jobModel->job_name }}">

    @php
        $urlService      = app(\App\Services\TechnicianUrlService::class);
        $afterPhotosUrl  = $urlService->resignFromRequest(request(), 'technician.job.after-photos', ['job' => $jobModel->id]);
        $assetStatusBase = $urlService->resignFromRequest(request(), 'technician.job.asset-status', ['job' => $jobModel->id, 'asset' => '__ASSET_ID__']);
        $foundDeviceUrl  = $urlService->resignFromRequest(request(), 'technician.job.devices.store', ['job' => $jobModel->id]);
        $foundDevices    = \App\Models\Asset::pendingDevices()->where('found_on_job_id', $jobModel->id)->latest()->get();
        $duplicate       = session('possible_duplicate');
        $csrfToken       = csrf_token();
    @endphp

    <style>
        /* Static styling lives in classes, with only the selected state toggled
           via :class — an x-bind:style string replaces (not merges with) a
           static style attribute on the same element, which was wiping out the
           base look (size, padding, radius) in every state. */
        .tech-status-chip {
            min-height: 44px; padding: var(--space-2) var(--space-3); border-radius: var(--radius-md);
            font-size: var(--fs-13); cursor: pointer;
            background: var(--surface-tertiary); border: 1px solid var(--border-default); color: var(--text-primary);
        }
        .tech-status-chip--active {
            background: var(--bronze-100); border: 1px solid var(--bronze-400); color: var(--bronze-800); font-weight: 600;
        }
    </style>

    <div class="tech-shell">

        {{-- Header --}}
        <div class="tech-screen-header">
            <p style="font-size: var(--fs-12); color: var(--onyx-400); margin-bottom: var(--space-1);">Step 2 of 4</p>
            <h1 style="font-size: var(--fs-18); font-weight: var(--weight-semibold);">Job brief</h1>
        </div>

        <div style="flex: 1; padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-5); padding-bottom: 100px;">

            {{-- Store address --}}
            @if ($jobModel->store)
                <div style="background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: var(--space-4);">
                    <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-1);">Location</p>
                    <p style="font-size: var(--fs-15); font-weight: var(--weight-semibold);">{{ $jobModel->store->store_name }}</p>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary);">{{ $jobModel->store->address_line1 }}, {{ $jobModel->store->suburb }} {{ $jobModel->store->state?->value }}</p>
                </div>
            @endif

            {{-- Description --}}
            <div>
                <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">Scope</p>
                <p style="font-size: var(--fs-14); color: var(--text-primary); white-space: pre-wrap; line-height: 1.6;">{{ $jobModel->job_description }}</p>
            </div>

            {{-- PM attachments (signed, read-only) --}}
            @if ($jobModel->attachments->isNotEmpty())
                <div>
                    <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">Attachments</p>
                    @foreach ($jobModel->attachments as $att)
                        <a href="{{ $urlService->resignFromRequest(request(), 'technician.job.attachment', ['job' => $jobModel->id, 'attachment' => $att->id]) }}"
                            target="_blank"
                            style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3); background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); margin-bottom: var(--space-2); text-decoration: none; min-height: 44px;">
                            <span style="font-size: var(--fs-13); color: var(--text-primary);">{{ $att->original_filename }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Devices found on site (US-21.1) --}}
            <style>
                .tech-field { display: flex; flex-direction: column; gap: 4px; font-size: var(--fs-13); color: var(--text-secondary); }
                /* 16px stops iOS Safari zooming into the field on focus */
                .tech-field input, .tech-field select { border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: 0 var(--space-3); font-size: 16px; background: var(--surface-primary); color: var(--text-primary); }
            </style>
            <div id="found-devices" x-data="{ adding: {{ $errors->any() || $duplicate ? 'true' : 'false' }} }" style="margin-bottom: var(--space-5);">
                <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">Devices found on site</p>

                @if (session('found_device_added'))
                    <div role="status" style="background: rgba(22,163,74,.08); border: 1px solid rgba(22,163,74,.25); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-3); font-size: var(--fs-13);">{{ session('found_device_added') }}</div>
                @endif

                @foreach ($foundDevices as $found)
                    <div style="display: flex; justify-content: space-between; gap: var(--space-3); padding: var(--space-3) var(--space-4); border: 1px dashed var(--border-default); border-radius: var(--radius-md); margin-bottom: var(--space-2); font-size: var(--fs-13);">
                        <span><strong>{{ $found->asset_name }}</strong><br><span style="font-family: monospace; color: var(--text-secondary);">{{ $found->asset_code }}</span></span>
                        <span style="color: var(--text-secondary); white-space: nowrap;">Waiting for PM</span>
                    </div>
                @endforeach

                <button type="button" x-show="! adding" @click="adding = true" class="tech-status-chip" style="width: 100%; min-height: 44px;">+ Add a device I found</button>

                <form x-show="adding" x-cloak method="POST" action="{{ $foundDeviceUrl }}" enctype="multipart/form-data"
                    style="display: flex; flex-direction: column; gap: var(--space-3); background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: var(--space-4);">
                    @csrf

                    @if ($duplicate)
                        <div role="alert" style="background: rgba(234,179,8,.1); border: 1px solid rgba(234,179,8,.3); border-radius: var(--radius-md); padding: var(--space-3); font-size: var(--fs-13); color: #854d0e;">
                            This may already be registered as <strong>{{ $duplicate['asset_code'] }}</strong>{{ $duplicate['store_name'] ? ' at '.$duplicate['store_name'] : '' }}.
                            @if ($duplicate['same_store'])
                                <button type="submit" formaction="{{ $urlService->resignFromRequest(request(), 'technician.job.devices.link', ['job' => $jobModel->id, 'asset' => $duplicate['id']]) }}" formenctype="application/x-www-form-urlencoded" formnovalidate
                                    class="tech-start-btn" style="margin-top: var(--space-2);">Yes, it's {{ $duplicate['asset_code'] }} — add it to this visit</button>
                            @endif
                            <label style="display: flex; gap: var(--space-2); align-items: center; margin-top: var(--space-2); min-height: 44px;">
                                <input type="checkbox" name="confirm_new" value="1" style="width: 20px; height: 20px;"> No, it's a different device — save it as new
                            </label>
                            <span style="display: block; margin-top: var(--space-1);">Take the photo again before saving.</span>
                        </div>
                    @endif

                    <label class="tech-field">Photo of the device or its label *
                        <input type="file" name="photo" accept="image/*" capture="environment" required style="min-height: 44px;">
                    </label>
                    @error('photo')<span role="alert" style="color: var(--critical); font-size: var(--fs-12);">{{ $message }}</span>@enderror

                    <label class="tech-field">What is it? *
                        <select name="asset_type" required style="min-height: 44px; width: 100%;">
                            <option value="">Choose…</option>
                            @foreach (\App\Enums\AssetType::cases() as $t)
                                <option value="{{ $t->value }}" @selected(old('asset_type') === $t->value)>{{ $t->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('asset_type')<span role="alert" style="color: var(--critical); font-size: var(--fs-12);">{{ $message }}</span>@enderror

                    <label class="tech-field">Where is it?
                        <input type="text" name="location_notes" maxlength="500" value="{{ old('location_notes') }}" placeholder="e.g. Front window, left" style="min-height: 44px; width: 100%;">
                    </label>
                    <label class="tech-field">Brand / manufacturer
                        <input type="text" name="manufacturer" maxlength="255" value="{{ old('manufacturer') }}" style="min-height: 44px; width: 100%;">
                    </label>
                    <label class="tech-field">Model
                        <input type="text" name="model" maxlength="255" value="{{ old('model') }}" style="min-height: 44px; width: 100%;">
                    </label>
                    <label class="tech-field">Serial number
                        <input type="text" name="serial_number" maxlength="100" value="{{ old('serial_number') }}" autocapitalize="characters" style="min-height: 44px; width: 100%;">
                    </label>
                    <label class="tech-field">MAC address (players)
                        <input type="text" name="mac_address" value="{{ old('mac_address') }}" placeholder="00:CE:39:D8:2A:51" autocapitalize="characters" style="min-height: 44px; width: 100%;">
                    </label>
                    @error('mac_address')<span role="alert" style="color: var(--critical); font-size: var(--fs-12);">{{ $message }}</span>@enderror
                    <label class="tech-field">Screen size in inches (screens)
                        <input type="number" name="screen_size_inches" step="0.1" min="1" value="{{ old('screen_size_inches') }}" inputmode="decimal" style="min-height: 44px; width: 100%;">
                    </label>
                    <label class="tech-field">Orientation (screens)
                        <select name="orientation" style="min-height: 44px; width: 100%;">
                            <option value="">—</option>
                            @foreach (\App\Enums\Orientation::cases() as $o)
                                <option value="{{ $o->value }}" @selected(old('orientation') === $o->value)>{{ ucfirst($o->value) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <p style="font-size: var(--fs-12); color: var(--text-secondary);">Fill in what you can read. A project manager checks everything before it goes into the register.</p>
                    <button type="submit" class="tech-start-btn">Save device</button>
                    <button type="button" @click="adding = false" class="tech-status-chip" style="min-height: 44px;">Cancel</button>
                </form>
            </div>

            {{-- Asset panel --}}
            @if ($jobModel->assets->isNotEmpty())
                <div>
                    <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">Assets</p>

                    @foreach ($jobModel->assets as $asset)
                        <div x-data="{ open: false, status: '{{ $asset->asset_status->value }}', statusLabel: '{{ $asset->asset_status->label() }}', updating: false, updateError: null }"
                            style="background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); margin-bottom: var(--space-3); overflow: hidden;">

                            {{-- Row header (tappable, ≥44px) --}}
                            <button type="button" @click="open = !open"
                                style="width: 100%; display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3) var(--space-4); background: none; border: none; cursor: pointer; text-align: left; min-height: 52px;">
                                <div style="flex: 1;">
                                    <p style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary);">{{ $asset->asset_name }}</p>
                                    <p style="font-size: var(--fs-12); color: var(--text-secondary); font-family: monospace;">{{ $asset->asset_code }}</p>
                                </div>
                                <span x-text="statusLabel" style="font-size: var(--fs-11); background: rgba(107,114,128,.1); color: var(--text-secondary); padding: 2px 8px; border-radius: 100px; white-space: nowrap;"></span>
                                <span x-text="open ? '▲' : '▼'" style="font-size: var(--fs-12); color: var(--text-tertiary);"></span>
                            </button>

                            {{-- Expandable detail --}}
                            <div x-show="open" x-collapse style="padding: 0 var(--space-4) var(--space-4);">
                                <div style="display: flex; flex-direction: column; gap: var(--space-2); font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-3);">
                                    @if ($asset->model) <span>Model: <strong style="color:var(--text-primary);">{{ $asset->model }}</strong></span> @endif
                                    @if ($asset->serial_number) <span>Serial: <strong style="color:var(--text-primary); font-family:monospace;">{{ $asset->serial_number }}</strong></span> @endif
                                    @if ($asset->location_notes) <span>Location: <strong style="color:var(--text-primary);">{{ $asset->location_notes }}</strong></span> @endif
                                </div>

                                {{-- In-flow status update --}}
                                <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">Update status (optional)</p>
                                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                                    @foreach (\App\Enums\AssetStatus::selectable() as $s)
                                        @if ($s !== \App\Enums\AssetStatus::UnderMaintenance)
                                            <button type="button"
                                                @click="
                                                    updating = true; updateError = null;
                                                    fetch('{{ str_replace('__ASSET_ID__', $asset->id, $assetStatusBase) }}', {
                                                        method: 'POST',
                                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ $csrfToken }}', 'Accept': 'application/json' },
                                                        body: JSON.stringify({ status: '{{ $s->value }}' })
                                                    })
                                                    .then(r => r.json())
                                                    .then(d => {
                                                        if (d.success) { status = d.new_status; statusLabel = d.label; }
                                                        else { updateError = d.error; }
                                                        updating = false;
                                                    })
                                                    .catch(() => { updateError = 'Update failed. Try again.'; updating = false; })
                                                "
                                                :disabled="updating || status === '{{ $s->value }}'"
                                                class="tech-status-chip"
                                                :class="status === '{{ $s->value }}' ? 'tech-status-chip--active' : ''">
                                                {{ $s->label() }}
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                                <p x-show="updateError" x-text="updateError" style="font-size: var(--fs-12); color: #dc2626; margin-top: var(--space-2);"></p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

        {{-- Sticky CTA --}}
        <div class="tech-sticky-bar">
            <a href="{{ $afterPhotosUrl }}"
                style="display: flex; align-items: center; justify-content: center; width: 100%; height: 56px; background: var(--bronze-700); color: #fff; font-size: var(--fs-16); font-weight: var(--weight-bold); border-radius: var(--radius-lg); text-decoration: none;">
                Complete job
            </a>
        </div>

    </div>

</x-layouts.technician>

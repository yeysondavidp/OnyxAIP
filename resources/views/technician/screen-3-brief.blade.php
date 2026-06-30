<x-layouts.technician title="Brief — {{ $jobModel->job_name }}">

    @php
        $signedParams    = request()->only(['token', 'technician_profile_id', 'expires', 'signature']);
        $afterPhotosUrl  = route('technician.job.after-photos', array_merge(['job' => $jobModel->id], $signedParams));
        $assetStatusBase = route('technician.job.asset-status', array_merge(['job' => $jobModel->id, 'asset' => '__ASSET_ID__'], $signedParams));
        $csrfToken       = csrf_token();
    @endphp

    <div class="tech-shell">

        {{-- Header --}}
        <div class="tech-header">
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
                        <a href="{{ route('jobs.attachments.download', [$jobModel, $att]) }}"
                            target="_blank"
                            style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3); background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); margin-bottom: var(--space-2); text-decoration: none; min-height: 44px;">
                            <span style="font-size: var(--fs-13); color: var(--text-primary);">{{ $att->original_filename }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

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
                                    @foreach (\App\Enums\AssetStatus::cases() as $s)
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
                                                style="min-height: 44px; padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); font-size: var(--fs-13); cursor: pointer;"
                                                :style="status === '{{ $s->value }}' ? 'background: var(--bronze-100); border: 1px solid var(--bronze-400); color: var(--bronze-800); font-weight:600;' : 'background: var(--surface-tertiary); border: 1px solid var(--border-default); color: var(--text-primary);'">
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

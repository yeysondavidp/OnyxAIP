<x-layouts.app title="Review &amp; Validate — {{ $job->job_reference }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('jobs.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Service Jobs</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('jobs.show', $job) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $job->job_reference }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Review &amp; validate</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 900px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Review &amp; Validate</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">{{ $job->job_name }} · {{ $job->store?->store_name }}</p>
        </div>

        @error('decisions')
            <x-onyx.alert tone="critical" style="margin-bottom: var(--space-5);">{{ $message }}</x-onyx.alert>
        @enderror
        @error('reason')
            <x-onyx.alert tone="critical" style="margin-bottom: var(--space-5);">{{ $message }}</x-onyx.alert>
        @enderror

        {{-- Evidence: technician checkpoints --}}
        <x-onyx.card variant="default" padding="lg" style="margin-bottom: var(--space-5);">
            <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Visit evidence</h2>

            @forelse ($checkpoints as $cp)
                <div style="padding: var(--space-3) 0; border-bottom: 1px solid var(--border-subtle);">
                    <p style="font-size: var(--fs-13); font-weight: var(--weight-medium); color: var(--text-primary); margin-bottom: var(--space-2);">
                        {{ $cp->profile?->name ?? 'Technician' }}
                    </p>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-3); font-size: var(--fs-13); color: var(--text-secondary);">
                        <div>
                            <span style="color: var(--text-tertiary);">Started</span>
                            <div style="color: var(--text-primary);">
                                {{ $cp->start_timestamp_utc?->setTimezone($job->job_timezone)->format('d M Y, g:i A') ?? '—' }}
                                @if ($cp->start_lat && $cp->start_lng)
                                    <span style="font-size: var(--fs-11); color: var(--text-tertiary);">({{ number_format($cp->start_lat, 4) }}, {{ number_format($cp->start_lng, 4) }})</span>
                                @elseif ($cp->start_gps_status && $cp->start_gps_status !== 'granted')
                                    <span style="font-size: var(--fs-11); color: var(--text-tertiary);">(GPS {{ $cp->start_gps_status }})</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <span style="color: var(--text-tertiary);">Completed</span>
                            <div style="color: var(--text-primary);">
                                {{ $cp->end_timestamp_utc?->setTimezone($job->job_timezone)->format('d M Y, g:i A') ?? '—' }}
                                @if ($cp->end_lat && $cp->end_lng)
                                    <span style="font-size: var(--fs-11); color: var(--text-tertiary);">({{ number_format($cp->end_lat, 4) }}, {{ number_format($cp->end_lng, 4) }})</span>
                                @elseif ($cp->end_gps_status && $cp->end_gps_status !== 'granted')
                                    <span style="font-size: var(--fs-11); color: var(--text-tertiary);">(GPS {{ $cp->end_gps_status }})</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if ($cp->completion_notes)
                        <p style="font-size: var(--fs-13); color: var(--text-primary); margin-top: var(--space-2);">{{ $cp->completion_notes }}</p>
                    @endif
                </div>
            @empty
                <p style="font-size: var(--fs-13); color: var(--text-tertiary);">No checkpoint data recorded.</p>
            @endforelse

            {{-- Photos --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-top: var(--space-4);">
                <div>
                    <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">Before photos ({{ $beforePhotos->count() }})</p>
                    <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                        @forelse ($beforePhotos as $photo)
                            <a href="{{ route('jobs.photos.download', [$job, $photo]) }}" target="_blank"
                                style="font-size: var(--fs-11); color: var(--bronze-600); text-decoration: none; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: var(--space-2) var(--space-3);">
                                Photo #{{ $photo->id }}
                            </a>
                        @empty
                            <span style="font-size: var(--fs-12); color: var(--text-tertiary);">None</span>
                        @endforelse
                    </div>
                </div>
                <div>
                    <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">After photos ({{ $afterPhotos->count() }})</p>
                    <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                        @forelse ($afterPhotos as $photo)
                            <a href="{{ route('jobs.photos.download', [$job, $photo]) }}" target="_blank"
                                style="font-size: var(--fs-11); color: var(--bronze-600); text-decoration: none; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: var(--space-2) var(--space-3);">
                                Photo #{{ $photo->id }}
                            </a>
                        @empty
                            <span style="font-size: var(--fs-12); color: var(--text-tertiary);">None</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-onyx.card>

        {{-- Per-asset outcomes + Validate --}}
        <form method="POST" action="{{ route('jobs.validate', $job) }}">
            @csrf

            <x-onyx.card variant="default" padding="lg" style="margin-bottom: var(--space-5);">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-2);">Asset outcomes</h2>
                <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-4);">
                    The technician's suggested outcome is pre-selected. Adjust any asset before validating —
                    the default return-to-Active only applies where no override is set.
                </p>

                @forelse ($job->assets as $asset)
                    @php
                        $outcome = $outcomes->get($asset->id);
                        $default = $outcome?->post_service_status?->value ?? \App\Enums\PostServiceStatus::Active->value;
                    @endphp
                    <div style="padding: var(--space-4) 0; border-bottom: 1px solid var(--border-subtle);">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-4); flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <p style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $asset->asset_name }}</p>
                                <p style="font-size: var(--fs-12); color: var(--text-secondary); font-family: monospace;">{{ $asset->asset_code }}</p>
                                <p style="font-size: var(--fs-12); color: var(--text-tertiary); margin-top: var(--space-1);">
                                    Currently <x-onyx.badge :tone="$asset->asset_status->tone()" variant="soft">{{ $asset->asset_status->label() }}</x-onyx.badge>
                                </p>
                                @if ($outcome?->technician_notes)
                                    <p style="font-size: var(--fs-13); color: var(--text-primary); margin-top: var(--space-2);">"{{ $outcome->technician_notes }}"</p>
                                @endif
                            </div>
                            <div style="min-width: 220px;">
                                <select name="decisions[{{ $asset->id }}]"
                                    style="width: 100%; height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-14); background: var(--surface-primary); color: var(--text-primary);">
                                    @foreach ($postStatuses as $ps)
                                        <option value="{{ $ps->value }}" @selected($default === $ps->value)>{{ $ps->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                @empty
                    <p style="font-size: var(--fs-13); color: var(--text-tertiary);">No affected assets on this job.</p>
                @endforelse
            </x-onyx.card>

            <div style="display: flex; gap: var(--space-3);">
                <x-onyx.button type="submit" variant="primary">Validate job</x-onyx.button>
                <x-onyx.button href="{{ route('jobs.show', $job) }}" variant="ghost">Cancel</x-onyx.button>
            </div>
        </form>

        {{-- Flag for remediation (secondary, distinct action) --}}
        <x-onyx.card variant="outline" padding="lg" style="margin-top: var(--space-6);">
            <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-2);">Flag for remediation</h2>
            <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-3);">
                If the visit didn't resolve the issue, flag it instead of validating. A remediation
                sub-job will be created in Draft, carrying the same assets forward for you to dispatch.
            </p>
            <form method="POST" action="{{ route('jobs.flag-remediation', $job) }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                    <x-onyx.textarea name="reason" label="Reason" rows="2" required
                        placeholder="Explain what still needs to be resolved…" />
                    <div>
                        <x-onyx.button type="submit" variant="outline">Flag for remediation</x-onyx.button>
                    </div>
                </div>
            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>

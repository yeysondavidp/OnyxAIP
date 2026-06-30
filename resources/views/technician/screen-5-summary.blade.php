<x-layouts.technician title="Job Summary — {{ $jobModel->job_reference }}">

    @php
        $tz         = $jobModel->job_timezone;
        $hasAccount = $profile->hasAccount();

        $scheduledLocal = null;
        if ($jobModel->scheduled_date && $jobModel->scheduled_time) {
            $scheduledLocal = \Carbon\Carbon::parse(
                $jobModel->scheduled_date->format('Y-m-d').' '.$jobModel->scheduled_time, 'UTC'
            )->setTimezone($tz);
        }

        $startLocal = $checkpoint?->start_timestamp_utc
            ? $checkpoint->start_timestamp_utc->setTimezone($tz)
            : null;

        $endLocal = $checkpoint?->end_timestamp_utc
            ? $checkpoint->end_timestamp_utc->setTimezone($tz)
            : null;

        $duration = ($startLocal && $endLocal)
            ? $startLocal->diffInMinutes($endLocal).' min'
            : null;
    @endphp

    <div class="tech-shell">

        {{-- Header --}}
        <div class="tech-header">
            <p style="font-size: var(--fs-12); color: var(--onyx-400); margin-bottom: var(--space-1);">Job complete</p>
            <h1 style="font-size: var(--fs-18); font-weight: var(--weight-semibold);">Summary</h1>
        </div>

        <div style="flex: 1; padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-5);">

            {{-- Confirmation banner --}}
            <div style="background: rgba(22,163,74,.1); border: 1px solid rgba(22,163,74,.3); border-radius: var(--radius-lg); padding: var(--space-4); text-align: center;">
                <p style="font-size: var(--fs-20); margin-bottom: var(--space-1);">✓</p>
                <p style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: #15803d;">Job submitted successfully</p>
                <p style="font-size: var(--fs-13); color: var(--text-secondary);">Your project manager will review and validate this visit.</p>
            </div>

            {{-- Times --}}
            <div style="background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: var(--space-4);">
                <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-3);">Times</p>
                <div style="display: flex; flex-direction: column; gap: var(--space-2); font-size: var(--fs-13);">
                    @if ($scheduledLocal)
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Scheduled</span>
                            <span style="color: var(--text-primary);">{{ $scheduledLocal->format('d M Y, g:i A') }}</span>
                        </div>
                    @endif
                    @if ($startLocal)
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Started</span>
                            <span style="color: var(--text-primary);">{{ $startLocal->format('d M Y, g:i A') }}</span>
                        </div>
                    @endif
                    @if ($endLocal)
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Completed</span>
                            <span style="color: var(--text-primary);">{{ $endLocal->format('d M Y, g:i A') }}</span>
                        </div>
                    @endif
                    @if ($duration)
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Duration</span>
                            <span style="color: var(--text-primary); font-weight: var(--weight-semibold);">{{ $duration }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Asset outcomes --}}
            @if ($jobModel->assets->isNotEmpty())
                <div>
                    <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">Asset outcomes</p>
                    @foreach ($jobModel->assets as $asset)
                        @php $outcome = $outcomes->get($asset->id); @endphp
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-3); background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); margin-bottom: var(--space-2); gap: var(--space-3);">
                            <div style="flex: 1;">
                                <p style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $asset->asset_name }}</p>
                                @if ($outcome?->technician_notes)
                                    <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-top: var(--space-1);">{{ $outcome->technician_notes }}</p>
                                @endif
                            </div>
                            @if ($outcome)
                                <span style="font-size: var(--fs-12); background: rgba(107,114,128,.1); color: var(--text-secondary); padding: 2px 8px; border-radius: 100px; white-space: nowrap;">{{ $outcome->post_service_status->label() }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Completion notes --}}
            @if ($checkpoint?->completion_notes)
                <div>
                    <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">Your notes</p>
                    <p style="font-size: var(--fs-14); color: var(--text-primary); white-space: pre-wrap; line-height: 1.6; background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: var(--space-3);">{{ $checkpoint->completion_notes }}</p>
                </div>
            @endif

            {{-- Before / after photo counts --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
                <div style="background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: var(--space-3); text-align: center;">
                    <p style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary);">{{ $beforePhotos->count() }}</p>
                    <p style="font-size: var(--fs-12); color: var(--text-secondary);">Before photos</p>
                </div>
                <div style="background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: var(--space-3); text-align: center;">
                    <p style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary);">{{ $afterPhotos->count() }}</p>
                    <p style="font-size: var(--fs-12); color: var(--text-secondary);">After photos</p>
                </div>
            </div>

            {{-- Account / guest CTA --}}
            <div style="text-align: center; padding: var(--space-4) 0;">
                @if ($hasAccount)
                    <p style="font-size: var(--fs-14); color: var(--text-secondary); margin-bottom: var(--space-3);">View your job history in your account.</p>
                    <a href="{{ route('dashboard') }}"
                        style="display: inline-flex; align-items: center; justify-content: center; height: 44px; padding: 0 var(--space-6); background: var(--bronze-700); color: #fff; border-radius: var(--radius-md); font-size: var(--fs-14); font-weight: var(--weight-semibold); text-decoration: none;">
                        Go to dashboard
                    </a>
                @else
                    <p style="font-size: var(--fs-14); color: var(--text-secondary); margin-bottom: var(--space-3);">
                        Want to see your job history? Create a free ONYX account.
                    </p>
                    <a href="{{ route('login') }}"
                        style="display: inline-flex; align-items: center; justify-content: center; height: 44px; padding: 0 var(--space-6); border: 1px solid var(--border-default); color: var(--text-primary); border-radius: var(--radius-md); font-size: var(--fs-14); text-decoration: none;">
                        Sign in or create account
                    </a>
                @endif
            </div>

        </div>

    </div>

</x-layouts.technician>

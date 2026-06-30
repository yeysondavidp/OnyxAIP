<x-layouts.technician title="Job invitation — {{ $job->job_name }}">

    <div style="min-height: 100dvh; display: flex; flex-direction: column; padding: var(--space-6) var(--space-5); max-width: 480px; margin: 0 auto;">

        {{-- Header --}}
        <div style="margin-bottom: var(--space-6);">
            <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-2);">Job invitation</p>
            <h1 style="font-size: var(--fs-22); font-weight: var(--weight-bold); color: var(--text-primary); line-height: 1.3;">{{ $job->job_name }}</h1>
        </div>

        {{-- Already actioned --}}
        @if ($currentStatus && $currentStatus->value !== 'invited')
            <x-onyx.card variant="default" padding="lg" style="margin-bottom: var(--space-5);">
                @if ($currentStatus->value === 'accepted')
                    <p style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--positive-700);">You have accepted this job.</p>
                    <p style="font-size: var(--fs-14); color: var(--text-secondary); margin-top: var(--space-2);">Your project manager will be in touch with further details.</p>
                @elseif ($currentStatus->value === 'declined')
                    <p style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--critical-600);">You have declined this job.</p>
                    <p style="font-size: var(--fs-14); color: var(--text-secondary); margin-top: var(--space-2);">If you've changed your mind, contact your ONYX project manager.</p>
                @endif
            </x-onyx.card>
        @else
            {{-- Flash messages --}}
            @if (session('accepted'))
                <x-onyx.alert tone="positive" style="margin-bottom: var(--space-4);">You have accepted this job. Your project manager has been notified.</x-onyx.alert>
            @endif
            @if (session('declined'))
                <x-onyx.alert tone="neutral" style="margin-bottom: var(--space-4);">You have declined this job.</x-onyx.alert>
            @endif
        @endif

        {{-- Job details --}}
        <x-onyx.card variant="default" padding="lg" style="margin-bottom: var(--space-5);">
            <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                @if ($job->store)
                    <div>
                        <dt style="color: var(--text-secondary); font-size: var(--fs-12); margin-bottom: var(--space-1);">Location</dt>
                        <dd style="font-weight: var(--weight-medium);">{{ $job->store->store_name }}</dd>
                        <dd style="color: var(--text-secondary); font-size: var(--fs-13);">{{ $job->store->address_line1 }}, {{ $job->store->suburb }} {{ $job->store->state?->value }}</dd>
                    </div>
                @endif
                @if ($job->scheduled_date)
                    @php
                        $tz  = $job->job_timezone;
                        $dt  = \Carbon\Carbon::parse($job->scheduled_date->format('Y-m-d').($job->scheduled_time ? ' '.$job->scheduled_time : ''), 'UTC')->setTimezone($tz);
                    @endphp
                    <div>
                        <dt style="color: var(--text-secondary); font-size: var(--fs-12); margin-bottom: var(--space-1);">Scheduled</dt>
                        <dd style="font-weight: var(--weight-medium);">{{ $dt->format('l, d F Y') }}</dd>
                        @if ($job->scheduled_time)
                            <dd style="color: var(--text-secondary); font-size: var(--fs-13);">{{ $dt->format('g:i A') }} {{ $tz }}</dd>
                        @endif
                    </div>
                @endif
                <div>
                    <dt style="color: var(--text-secondary); font-size: var(--fs-12); margin-bottom: var(--space-1);">Reference</dt>
                    <dd style="font-family: monospace; font-size: var(--fs-13);">{{ $job->job_reference }}</dd>
                </div>
            </dl>
        </x-onyx.card>

        {{-- Accept / Decline actions --}}
        @if (! $currentStatus || $currentStatus->value === 'invited')
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <form method="POST" action="{{ route('technician.job.accept', $job) }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button type="submit"
                        style="width: 100%; min-height: 52px; background: var(--bronze-700); color: #fff; font-size: var(--fs-16); font-weight: var(--weight-semibold); border: none; border-radius: var(--radius-md); cursor: pointer;">
                        Accept job
                    </button>
                </form>
                <form method="POST" action="{{ route('technician.job.decline', $job) }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button type="submit"
                        style="width: 100%; min-height: 52px; background: transparent; color: var(--text-secondary); font-size: var(--fs-15); border: 1px solid var(--border-default); border-radius: var(--radius-md); cursor: pointer;">
                        Decline
                    </button>
                </form>
            </div>
        @endif

        <p style="font-size: var(--fs-12); color: var(--text-tertiary); margin-top: auto; padding-top: var(--space-8);">
            ONYX Visual · This link is private to you.
        </p>

    </div>

</x-layouts.technician>

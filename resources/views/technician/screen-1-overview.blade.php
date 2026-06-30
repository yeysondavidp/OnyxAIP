<x-layouts.technician title="{{ $jobModel->job_name }}">

    @php
        $tz   = $jobModel->job_timezone;
        $hasSchedule = $jobModel->scheduled_date && $jobModel->scheduled_time;
        $startUtc = $hasSchedule
            ? \Carbon\Carbon::parse($jobModel->scheduled_date->format('Y-m-d').' '.$jobModel->scheduled_time, 'UTC')
            : null;
        $startLocal = $startUtc?->copy()->setTimezone($tz);

        // Early-start window enforcement (client-side gate — re-checked server-side)
        $windowMins  = $jobModel->early_start_window->minutes(); // null = anytime
        $earliestUtc = $startUtc && $windowMins
            ? $startUtc->copy()->subMinutes($windowMins)->toIso8601String()
            : null;

        // Google Calendar URL
        $gcalUrl = null;
        if ($startUtc) {
            $endUtc   = $startUtc->copy()->addHours(2);
            $loc      = $jobModel->store
                ? "{$jobModel->store->address_line1}, {$jobModel->store->suburb} {$jobModel->store->state?->value}"
                : '';
            $gcalUrl  = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
                .'&text='.rawurlencode($jobModel->job_name)
                .'&dates='.$startUtc->format('Ymd\THis\Z').'/'.$endUtc->format('Ymd\THis\Z')
                .'&details='.rawurlencode('Job reference: '.$jobModel->job_reference)
                .($loc ? '&location='.rawurlencode($loc) : '');
        }
    @endphp

    <div class="tech-shell"
        x-data="{
            canStart: {{ $earliestUtc ? 'new Date() >= new Date(\''.$earliestUtc.'\')' : 'true' }},
            earliestLocal: '{{ $startLocal && $windowMins ? $startLocal->copy()->subMinutes($windowMins)->format('g:i A') : '' }}',
            checkWindow() {
                @if ($earliestUtc)
                    this.canStart = new Date() >= new Date('{{ $earliestUtc }}');
                    setTimeout(() => this.checkWindow(), 30000); // recheck every 30s
                @endif
            },
        }"
        x-init="checkWindow()">

        {{-- Header --}}
        <div class="tech-header">
            <p style="font-size: var(--fs-11); color: var(--onyx-400); text-transform: uppercase; letter-spacing: .06em; margin-bottom: var(--space-1);">{{ $jobModel->client?->client_name }}</p>
            <h1 style="font-size: var(--fs-18); font-weight: var(--weight-semibold);">{{ $jobModel->job_name }}</h1>
        </div>

        <div style="flex: 1; padding: var(--space-5);">

            {{-- Flash errors --}}
            @if ($errors->any())
                <div style="background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.3); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4); font-size: var(--fs-13); color: #dc2626;">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Job details --}}
            <div style="background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: var(--space-4); margin-bottom: var(--space-4);">
                @if ($jobModel->store)
                    <div style="margin-bottom: var(--space-3);">
                        <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-1);">Location</p>
                        <p style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">{{ $jobModel->store->store_name }}</p>
                        <p style="font-size: var(--fs-13); color: var(--text-secondary);">{{ $jobModel->store->address_line1 }}, {{ $jobModel->store->suburb }} {{ $jobModel->store->state?->value }}</p>
                    </div>
                @endif
                @if ($hasSchedule && $startLocal)
                    <div>
                        <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-1);">Scheduled</p>
                        <p style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">{{ $startLocal->format('l, d F Y') }}</p>
                        <p style="font-size: var(--fs-13); color: var(--text-secondary);">{{ $startLocal->format('g:i A') }} · {{ $tz }}</p>
                    </div>
                @endif
            </div>

            {{-- Affected assets --}}
            @if ($jobModel->assets->isNotEmpty())
                <div style="margin-bottom: var(--space-4);">
                    <p style="font-size: var(--fs-12); color: var(--text-secondary); margin-bottom: var(--space-2);">Assets for this visit</p>
                    @foreach ($jobModel->assets as $asset)
                        <div style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3) 0; border-bottom: 1px solid var(--border-subtle); min-height: 44px;">
                            <div style="flex: 1;">
                                <p style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $asset->asset_name }}</p>
                                <p style="font-size: var(--fs-12); color: var(--text-secondary); font-family: monospace;">{{ $asset->asset_code }}</p>
                                @if ($asset->location_notes)
                                    <p style="font-size: var(--fs-12); color: var(--text-secondary);">{{ $asset->location_notes }}</p>
                                @endif
                            </div>
                            <span style="font-size: var(--fs-11); background: {{ $asset->asset_status->tone() === 'positive' ? 'rgba(22,163,74,.12)' : ($asset->asset_status->tone() === 'critical' ? 'rgba(220,38,38,.12)' : 'rgba(107,114,128,.1)') }}; color: {{ $asset->asset_status->tone() === 'positive' ? '#16a34a' : ($asset->asset_status->tone() === 'critical' ? '#dc2626' : '#6b7280') }}; padding: var(--space-1) var(--space-2); border-radius: 100px;">{{ $asset->asset_status->label() }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div style="padding: var(--space-4); text-align: center; color: var(--text-tertiary); font-size: var(--fs-13); margin-bottom: var(--space-4);">No assets listed for this job.</div>
            @endif

            {{-- Calendar saves --}}
            @if ($hasSchedule && $gcalUrl)
                <div style="display: flex; gap: var(--space-3); margin-bottom: var(--space-5); flex-wrap: wrap;">
                    <a href="{{ $gcalUrl }}" target="_blank" rel="noopener"
                        style="flex: 1; min-width: 140px; display: flex; align-items: center; justify-content: center; gap: var(--space-2); height: 44px; border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-13); color: var(--text-secondary); text-decoration: none; background: var(--surface-primary);">
                        Google Calendar
                    </a>
                    @if ($icsContent)
                        <a href="data:text/calendar;charset=utf-8,{{ rawurlencode($icsContent) }}"
                            download="{{ Str::slug($jobModel->job_reference) }}.ics"
                            style="flex: 1; min-width: 140px; display: flex; align-items: center; justify-content: center; gap: var(--space-2); height: 44px; border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-13); color: var(--text-secondary); text-decoration: none; background: var(--surface-primary);">
                            Download .ics
                        </a>
                    @endif
                </div>
            @endif

            {{-- GPS callout --}}
            <div style="background: rgba(146,64,14,.08); border: 1px solid rgba(146,64,14,.2); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-5); font-size: var(--fs-13); color: var(--bronze-800);">
                Start Job only when you are on site. Your GPS location will be recorded.
            </div>

            {{-- Early-start gate message --}}
            <div x-show="! canStart" x-cloak
                style="background: rgba(234,179,8,.1); border: 1px solid rgba(234,179,8,.3); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4); font-size: var(--fs-13); color: #854d0e;">
                <template x-if="earliestLocal">
                    <span>You may start this job from <strong x-text="earliestLocal"></strong>. Please return at that time.</span>
                </template>
            </div>

        </div>

        {{-- Sticky Start CTA --}}
        <div class="tech-sticky-bar">
            <form method="POST" action="{{ route('technician.job.start', array_merge(['job' => $jobModel->id], request()->only(['token', 'technician_profile_id', 'expires', 'signature']))) }}"
                @submit.prevent="
                    if (!canStart) return;
                    const form = $el;
                    navigator.geolocation
                        ? navigator.geolocation.getCurrentPosition(
                            pos => {
                                form.querySelector('[name=gps_lat]').value = pos.coords.latitude;
                                form.querySelector('[name=gps_lng]').value = pos.coords.longitude;
                                form.querySelector('[name=gps_status]').value = 'granted';
                                form.submit();
                            },
                            () => {
                                if (confirm('Location access was denied. Proceed without GPS?')) {
                                    form.querySelector('[name=gps_status]').value = 'denied';
                                    form.submit();
                                }
                            },
                            { timeout: 10000 }
                          )
                        : (() => {
                              form.querySelector('[name=gps_status]').value = 'failed';
                              form.submit();
                          })()
                ">
                @csrf
                <input type="hidden" name="gps_lat" value="">
                <input type="hidden" name="gps_lng" value="">
                <input type="hidden" name="gps_status" value="skipped">
                <button type="submit"
                    :disabled="!canStart"
                    :style="!canStart ? 'opacity:.4;cursor:not-allowed;' : ''"
                    style="width: 100%; height: 56px; background: var(--bronze-700); color: #fff; font-size: var(--fs-16); font-weight: var(--weight-bold); border: none; border-radius: var(--radius-lg); cursor: pointer; letter-spacing: .01em;">
                    Start Job
                </button>
            </form>
        </div>

    </div>

</x-layouts.technician>

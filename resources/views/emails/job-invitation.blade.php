<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job invitation: {{ $job->job_name }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1a1a1a; background: #f5f5f4; margin: 0; padding: 32px 16px; }
        .card { background: #ffffff; border-radius: 12px; max-width: 520px; margin: 0 auto; padding: 40px 36px; }
        h1 { font-size: 22px; font-weight: 700; margin: 0 0 8px; }
        p { font-size: 15px; line-height: 1.6; color: #444; margin: 0 0 16px; }
        .meta { background: #fafaf9; border: 1px solid #e7e5e4; border-radius: 8px; padding: 16px 20px; margin: 24px 0; font-size: 14px; line-height: 1.8; }
        .cta { display: inline-block; background: #92400e; color: #ffffff; font-weight: 600; font-size: 15px; text-decoration: none; padding: 14px 28px; border-radius: 8px; margin: 8px 0 24px; }
        .calendar { font-size: 13px; color: #78716c; margin-top: 8px; }
        .calendar a { color: #92400e; }
        .footer { font-size: 12px; color: #a8a29e; margin-top: 32px; border-top: 1px solid #e7e5e4; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">

        <h1>You've been invited to a job</h1>
        <p>Hi {{ $profile->name }}, you have been invited to attend the following service visit.</p>

        <div class="meta">
            <strong>{{ $job->job_name }}</strong><br>
            @if ($job->store)
                {{ $job->store->store_name }}<br>
                {{ $job->store->address_line1 }}, {{ $job->store->suburb }} {{ $job->store->state?->value }}<br>
            @endif
            @if ($job->scheduled_date)
                @php
                    $tz  = $job->job_timezone;
                    $dt  = \Carbon\Carbon::parse($job->scheduled_date->format('Y-m-d').($job->scheduled_time ? ' '.$job->scheduled_time : ''), 'UTC');
                    $dtTz = $dt->setTimezone($tz);
                @endphp
                {{ $dtTz->format('l, d F Y') }}
                @if ($job->scheduled_time) at {{ $dtTz->format('g:i A') }} ({{ $tz }}) @endif
            @endif
        </div>

        <a href="{{ $invitationUrl }}" class="cta">View job &amp; respond</a>

        @if ($job->scheduled_date && $job->scheduled_time)
            @php
                $startUtc = \Carbon\Carbon::parse($job->scheduled_date->format('Y-m-d').' '.$job->scheduled_time, 'UTC');
                $endUtc   = $startUtc->copy()->addHours(2);
                $gcalUrl  = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
                    .'&text='.rawurlencode($job->job_name)
                    .'&dates='.$startUtc->format('Ymd\THis\Z').'/'.$endUtc->format('Ymd\THis\Z')
                    .'&details='.rawurlencode('Job reference: '.$job->job_reference."\n\n".$job->job_description)
                    .($job->store ? '&location='.rawurlencode($job->store->address_line1.', '.$job->store->suburb) : '');
            @endphp
            <div class="calendar">
                Save to your calendar:
                <a href="{{ $gcalUrl }}" target="_blank">Google Calendar</a>
            </div>
        @endif

        <p style="margin-top: 24px; font-size: 13px; color: #78716c;">
            This link is valid for {{ \App\Services\JobInvitationService::TTL_HOURS }} hours.
            If you need a new link, contact your ONYX project manager.
        </p>

        <div class="footer">
            This invitation was sent by ONYX Visual. If you were not expecting this email, please disregard it.
        </div>

    </div>
</body>
</html>

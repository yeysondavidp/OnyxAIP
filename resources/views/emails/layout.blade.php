<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1a1a1a; background: #f5f5f4; margin: 0; padding: 32px 16px; }
        .card { background: #ffffff; border-radius: 12px; max-width: 520px; margin: 0 auto; padding: 40px 36px; }
        h1 { font-size: 22px; font-weight: 700; margin: 0 0 16px; }
        p { font-size: 15px; line-height: 1.6; color: #444; margin: 0 0 16px; }
        .cta { display: inline-block; background: #92400e; color: #ffffff; font-weight: 600; font-size: 15px; text-decoration: none; padding: 14px 28px; border-radius: 8px; margin: 8px 0 24px; }
        .footer { font-size: 12px; color: #a8a29e; margin-top: 32px; border-top: 1px solid #e7e5e4; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">

        <h1>{{ $subject }}</h1>

        <p>{!! $body !!}</p>

        @if ($ctaUrl)
            <a href="{{ $ctaUrl }}" class="cta">{{ $ctaLabel }}</a>
        @endif

        <div class="footer">
            This email was sent by ONYX Visual. If you were not expecting it, please disregard it.
        </div>

    </div>
</body>
</html>

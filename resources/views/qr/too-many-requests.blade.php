<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Too Many Requests — ONYX AIP</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f9f9f9;
            color: #111;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 40px 32px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 1px 6px rgba(0,0,0,.08);
        }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 20px; font-weight: 700; margin-bottom: 8px; color: #111; }
        p  { font-size: 15px; color: #666; line-height: 1.5; }
        .retry { margin-top: 24px; font-size: 14px; font-weight: 600; color: #8a5a0f; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⏳</div>
        <h1>Too many requests</h1>
        <p>Please try again shortly.</p>
        @if (request()->hasHeader('Retry-After') && (int) request()->header('Retry-After') <= 60)
            <p class="retry">Try again in {{ request()->header('Retry-After') }} seconds</p>
        @endif
    </div>
</body>
</html>

{{--
  Shared chrome for every EPIC-14 PDF report: client/store name, filter
  summary, "Generated at" timestamp, ONYX branding placeholder. Every PDF
  report view wraps its table/content in this component so headers are
  consistent across reports (Engineering Bar — Clean).

  Props:
    title          string
    client         App\Models\Client|null
    filterSummary  string|null
    generatedAt    Illuminate\Support\Carbon
    timezone       string  IANA timezone for the "Generated at" stamp
--}}

@props([
    'title',
    'client'        => null,
    'filterSummary' => null,
    'generatedAt',
    'timezone'      => 'Australia/Sydney',
])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #111; padding: 10mm; }
        .header { border-bottom: 2px solid #79613f; padding-bottom: 4mm; margin-bottom: 6mm; }
        .header__brand { font-size: 8pt; letter-spacing: 0.1em; text-transform: uppercase; color: #79613f; font-weight: bold; margin-bottom: 2mm; }
        .header__title { font-size: 16pt; font-weight: bold; color: #111; margin-bottom: 2mm; }
        .header__meta { font-size: 8pt; color: #555; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        th, td { text-align: left; padding: 2mm 3mm; border-bottom: 1px solid #eee; }
        th { background: #f7f5f2; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; font-size: 7pt; color: #555; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header__brand">ONYX Visual — Asset Intelligence Platform</div>
        <div class="header__title">{{ $title }}</div>
        <div class="header__meta">
            @if ($client)
                Client: {{ $client->client_name }}<br>
            @endif
            @if ($filterSummary)
                {{ $filterSummary }}<br>
            @endif
            Generated {{ $generatedAt->timezone($timezone)->format('j F Y \a\t g:ia T') }}
        </div>
    </div>

    {{ $slot }}
</body>
</html>

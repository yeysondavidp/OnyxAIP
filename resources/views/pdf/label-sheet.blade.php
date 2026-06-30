<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #111;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8mm;
            padding: 10mm;
        }
        .label {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 6mm;
            page-break-inside: avoid;
            display: flex;
            gap: 4mm;
            align-items: flex-start;
        }
        .label__qr {
            width: 28mm;
            height: 28mm;
            flex-shrink: 0;
        }
        .label__qr img {
            width: 28mm;
            height: 28mm;
        }
        .label__info { flex: 1; min-width: 0; }
        .label__code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 9pt;
            font-weight: bold;
            color: #222;
            word-break: break-all;
            margin-bottom: 2mm;
        }
        .label__name {
            font-size: 10pt;
            font-weight: bold;
            color: #111;
            margin-bottom: 1mm;
        }
        .label__meta {
            font-size: 8pt;
            color: #555;
            line-height: 1.4;
        }
        .label__store {
            font-size: 8pt;
            color: #888;
            margin-top: 2mm;
            border-top: 1px solid #eee;
            padding-top: 2mm;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="grid">
        @foreach ($labels as $label)
            <div class="label">
                <div class="label__qr">
                    <img src="{{ $label['qr_data_uri'] }}" alt="QR code for {{ $label['asset_code'] }}">
                </div>
                <div class="label__info">
                    <div class="label__code">{{ $label['asset_code'] }}</div>
                    <div class="label__name">{{ $label['asset_name'] }}</div>
                    <div class="label__meta">
                        {{ $label['asset_type'] }}<br>
                        {{ $label['manufacturer'] }} {{ $label['model'] }}
                    </div>
                    <div class="label__store">{{ $label['store_name'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>

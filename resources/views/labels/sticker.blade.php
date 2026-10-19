<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: {{ $widthMm }}mm {{ $heightMm }}mm; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #14231f; margin: 16px; background: #f4f6f5; }
        .actions { margin-bottom: 16px; display: flex; gap: 12px; }
        .actions a, .actions button { border: 1px solid #1f6f68; background: #1f6f68; color: white; padding: 8px 14px; border-radius: 10px; text-decoration: none; font-size: 14px; }
        .sheet { display: flex; flex-wrap: wrap; gap: 8px; }
        .label {
            width: {{ $widthMm }}mm;
            height: {{ $heightMm }}mm;
            background: #fff;
            border: 1px solid #d7e0dc;
            padding: 1.2mm 1.4mm 1.4mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            page-break-after: always;
            break-after: page;
        }
        .barcode {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            width: 100%;
            flex: 0 0 auto;
        }
        .barcode svg { width: 100%; height: 11mm; display: block; }
        .code { font-size: 6px; letter-spacing: 0.06em; text-align: center; margin: 0.4mm 0 0; }
        .details { display: flex; flex-direction: column; align-items: center; gap: 0.6mm; margin-top: 1.2mm; min-height: 0; width: 100%; }
        .name { font-size: 7.5px; font-weight: 700; line-height: 1.15; margin: 0; text-align: center; }
        .variant { font-size: 6.5px; color: #5b6b66; margin: 0; line-height: 1.15; text-align: center; }
        .price { font-size: 10px; font-weight: 700; margin: 0; text-align: center; }
        .dates { font-size: 6.5px; color: #5b6b66; margin: 0; text-align: center; }
        @media print {
            body { margin: 0; background: #fff; }
            .actions { display: none; }
            .sheet { display: block; gap: 0; }
            .label { border: 0; width: {{ $widthMm }}mm; height: {{ $heightMm }}mm; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ $downloadUrl }}">Download</a>
    </div>

    <div class="sheet">
        @for ($index = 0; $index < $copies; $index++)
            <article class="label">
                <div class="barcode">
                    {!! $barcodeSvg !!}
                    @if ($barcodeValue)
                        <p class="code">{{ $barcodeValue }}</p>
                    @endif
                </div>
                <div class="details">
                    <p class="name">{{ $name }}{{ filled($variant) ? ' · '.$variant : '' }}</p>
                    <p class="price">{{ $price }}</p>
                    <p class="dates">Supply Date: {{ $dateLine }}</p>
                </div>
            </article>
        @endfor
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Test print · {{ $printer->name }}</title>
    <style>
        @page { size: {{ $widthMm }}mm {{ $heightMm }}mm; margin: 4mm; }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #14231f; margin: 16px; background: #f4f6f5; }
        .actions { margin-bottom: 16px; }
        .actions button { border: 1px solid #1f6f68; background: #1f6f68; color: white; padding: 8px 14px; border-radius: 10px; font-size: 14px; }
        .ticket {
            width: {{ $widthMm }}mm;
            min-height: {{ $heightMm }}mm;
            background: #fff;
            border: 1px solid #d7e0dc;
            padding: 6mm 4mm;
            text-align: center;
        }
        h1 { font-size: 16px; margin: 0 0 8px; }
        p { font-size: 12px; margin: 0 0 6px; }
        @media print {
            body { margin: 0; background: #fff; }
            .actions { display: none; }
            .ticket { border: 0; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <article class="ticket">
        <h1>Pluf Pets</h1>
        <p>{{ $printer->name }}</p>
        <p>{{ $printer->role->label() }} · {{ $printer->paperLabel() }}</p>
        <p>{{ now()->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>
        <p>Test page</p>
    </article>
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>
</html>

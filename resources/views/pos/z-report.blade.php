<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Z-Report {{ $shift->id }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #14231f; margin: 40px; }
        .receipt-text { display: none; margin: 0; white-space: pre-wrap; font: 12px/1.2 ui-monospace, monospace; }
        @page { margin: 0; size: {{ is_array($printer) ? (int) ($printer['paper_width_mm'] ?? 80) : 80 }}mm auto; }
        @media print {
            html, body { margin: 0; height: auto; }
            body { padding: 2mm 2mm 0; }
            .actions, .screen-report { display: none; }
            .receipt-text { display: block; }
        }
        h1 { font-size: 28px; margin: 0 0 4px; }
        .muted { color: #5b6b66; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #d7e0dc; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #5b6b66; }
        td.num, th.num { text-align: right; }
        .actions { margin-bottom: 24px; display: flex; gap: 12px; align-items: center; }
        .actions a, .actions button { border: 1px solid #1f6f68; background: #1f6f68; color: white; padding: 8px 14px; border-radius: 10px; text-decoration: none; font-size: 14px; }
    </style>
    @php
        $zReportPrintManifest = json_decode((string) @file_get_contents(public_path('build/manifest.json')), true) ?? [];
        $zReportPrintReady = file_exists(public_path('hot'))
            || array_key_exists('resources/js/zReportPrint.js', $zReportPrintManifest);
    @endphp
    @if ($zReportPrintReady)
        @vite(['resources/js/zReportPrint.js'])
    @endif
</head>
<body>
    <div
        id="z-report-print"
        class="actions"
        data-autoprint="{{ ($autoprint ?? false) ? '1' : '0' }}"
    >
        @unless ($hidePrintButton ?? false)
            <button id="print-z-report" type="button">Print</button>
        @endunless
        <a href="{{ route('pos.index', $shift->branch) }}">Back to POS</a>
    </div>
    <script type="application/json" id="z-report-printer">@json($printer)</script>
    <script type="application/json" id="z-report-receipt">@json($receiptText)</script>

    <pre class="receipt-text">{{ $receiptText }}</pre>
    <div class="screen-report">
    <p class="muted">Z-Report</p>
    <h1>{{ $shift->branch?->name }}</h1>
    <p class="muted">Opened by {{ $shift->opened_by_name }} · {{ $shift->opened_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>
    <p class="muted">Closed by {{ $shift->closed_by_name }} · {{ $shift->closed_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>

    <table>
        <thead>
            <tr>
                <th>Payment method</th>
                <th class="num">Sales</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($salesByPayment as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ $row['count'] }}</td>
                    <td class="num">{{ $row['total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No sales this shift.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table>
        <tbody>
            <tr><td>Items sold</td><td class="num">{{ $itemCount }}</td></tr>
            <tr><td>Sales total</td><td class="num">{{ $salesTotal }}</td></tr>
            <tr><td>Starting cash</td><td class="num">{{ $startingCash }}</td></tr>
            <tr><td>Cash sales</td><td class="num">{{ $cashSales }}</td></tr>
            <tr><td>Cash in</td><td class="num">{{ $cashIn }}</td></tr>
            <tr><td>Cash out</td><td class="num">{{ $cashOut }}</td></tr>
            <tr><td>Expected cash</td><td class="num">{{ $expectedCash }}</td></tr>
            <tr><td>Counted cash</td><td class="num">{{ $countedCash }}</td></tr>
            <tr><td>Variance</td><td class="num">{{ $variance }}</td></tr>
            <tr><td>Amount to remit</td><td class="num">{{ $remitAmount }}</td></tr>
        </tbody>
    </table>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $purchaseOrder->number }}</title>
    <style>
        @page { margin: 16px 20px; }
        body { font-family: Calibri, "DejaVu Sans", sans-serif; color: #14231f; background: #fff; margin: 12px; font-size: 12px; line-height: 1.3; }
        p, h1, h2 { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .actions { margin: 0 0 10px; }
        .actions a, .actions button { border: 1px solid #1f6f68; background: #1f6f68; color: white; padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 13px; margin-right: 8px; }
        table.head { margin: 0 0 10px; }
        table.head td { padding: 0; border: 0; }
        table.head td.doc { width: 34%; text-align: right; }
        .logo { height: 32px; margin: 0 0 2px; }
        .name { font-size: 13px; font-weight: 700; }
        .muted { color: #5b6b66; }
        .kicker { font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #5b6b66; }
        .number { font-size: 16px; font-weight: 700; }
        table.supplier { margin: 0 0 8px; }
        table.supplier td { padding: 0; border: 0; }
        table.supplier td.label { width: 72px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #5b6b66; }
        table.supplier td.note { width: 42%; }
        table.lines { margin: 0; }
        table.lines th, table.lines td { padding: 3px 6px; border-bottom: 1px solid #d7e0dc; text-align: left; }
        table.lines th { background: #f3f7f5; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #5b6b66; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr.totals td { border-bottom: 0; font-weight: 700; padding-top: 5px; }
        .notes { margin: 8px 0 0; font-size: 11px; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    @unless ($forDownload ?? false)
        <div class="actions">
            <button type="button" onclick="window.print()">Print</button>
            <a href="{{ route('inventory.purchase-orders.print', ['purchase_order' => $purchaseOrder, 'download' => 1]) }}">Download</a>
        </div>
    @endunless

    <table class="head">
        <tr>
            <td>
                @if (! empty($business['logo_data_uri'] ?? $business['logo_url'] ?? null))
                    <img class="logo" src="{{ $business['logo_data_uri'] ?? $business['logo_url'] }}" alt="">
                @endif
                @if (filled($business['registered_business_name'] ?? null) || filled($business['address'] ?? null) || filled($business['phone'] ?? null) || filled($business['email'] ?? null) || filled($business['tin'] ?? null))
                    @if (filled($business['registered_business_name'] ?? null))
                        <p class="name">{{ $business['registered_business_name'] }}</p>
                    @endif
                    @if (! empty($business['address']))
                        <p>{{ $business['address'] }}</p>
                    @endif
                    @if (! empty($business['phone']) || ! empty($business['email']))
                        <p>{{ collect([$business['phone'] ?? null, $business['email'] ?? null])->filter()->implode(' · ') }}</p>
                    @endif
                    @if (! empty($business['tin']))
                        <p>TIN {{ $business['tin'] }}</p>
                    @endif
                @else
                    <p class="name">{{ $appName }}</p>
                @endif
            </td>
            <td class="doc">
                <p class="kicker">Purchase order</p>
                <p class="number">{{ $purchaseOrder->number }}</p>
                <p class="muted">{{ $purchaseOrder->status->label() }}</p>
                @if ($purchaseOrder->ordered_at)
                    <p class="muted">{{ $purchaseOrder->ordered_at->timezone(config('app.timezone'))->format('M j, Y') }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="supplier">
        <tr>
            <td class="label">Supplier</td>
            <td>
                <p class="name">{{ $purchaseOrder->supplier?->name ?: '—' }}</p>
                @if ($purchaseOrder->supplier?->tin)
                    <p>TIN {{ $purchaseOrder->supplier->tin }}</p>
                @endif
                @if ($purchaseOrder->supplier?->address)
                    <p>{{ $purchaseOrder->supplier->address }}</p>
                @endif
            </td>
            <td class="label">Notes</td>
            <td class="note">{{ $purchaseOrder->notes ?: '—' }}</td>
        </tr>
    </table>

    @if ($purchaseOrder->closed_reason)
        <p class="notes">Closed: {{ $purchaseOrder->closed_reason }}</p>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th class="num">Qty</th>
                <th class="num">Unit cost</th>
                <th class="num">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $item->variant?->catalogItem?->name }} @if ($item->variant?->name)({{ $item->variant->name }})@endif</td>
                    <td>{{ $item->variant?->sku ?: '—' }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ \App\Support\Peso::format($item->unit_cost) }}</td>
                    <td class="num">{{ \App\Support\Peso::format($item->lineTotal()) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals">
                <td colspan="2">Total quantity</td>
                <td class="num">{{ $purchaseOrder->orderedQuantity() }}</td>
                <td colspan="2"></td>
            </tr>
            <tr class="totals">
                <td colspan="4">Total amount</td>
                <td class="num">{{ \App\Support\Peso::format($purchaseOrder->totalAmount()) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>

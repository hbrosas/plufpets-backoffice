<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $purchaseOrder->number }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #14231f; margin: 40px; }
        h1 { font-size: 28px; margin: 0 0 4px; }
        .muted { color: #5b6b66; }
        .status { font-size: 13px; color: #5b6b66; margin: 0 0 16px; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 20px 0 8px; }
        .party { border: 1px solid #d7e0dc; border-radius: 16px; padding: 16px; }
        .party .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #5b6b66; margin: 0 0 8px; }
        .party h2 { font-size: 16px; font-weight: 600; margin: 0 0 6px; }
        .party p { margin: 0 0 4px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #d7e0dc; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #5b6b66; }
        td.num, th.num { text-align: right; }
        .summary { margin-top: 16px; width: 280px; margin-left: auto; }
        .summary td { border-bottom: 0; padding: 6px 8px; }
        .notes { margin-top: 16px; font-size: 14px; }
        .actions { margin-bottom: 24px; display: flex; gap: 12px; }
        .actions a, .actions button { border: 1px solid #1f6f68; background: #1f6f68; color: white; padding: 8px 14px; border-radius: 10px; text-decoration: none; font-size: 14px; }
        @media print { .actions { display: none; } }
        @media (max-width: 700px) { .parties { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('inventory.purchase-orders.print', ['purchase_order' => $purchaseOrder, 'download' => 1]) }}">Download</a>
    </div>

    <p class="status">{{ $purchaseOrder->status->label() }}</p>
    <h1>Purchase Order {{ $purchaseOrder->number }}</h1>

    <div class="parties">
        <section class="party">
            <p class="label">Supplier</p>
            <h2>{{ $purchaseOrder->supplier?->name ?: '—' }}</h2>
            @if ($purchaseOrder->supplier?->contact_name)
                <p>{{ $purchaseOrder->supplier->contact_name }}</p>
            @endif
            @if ($purchaseOrder->supplier?->email)
                <p class="muted">{{ $purchaseOrder->supplier->email }}</p>
            @endif
            @if ($purchaseOrder->supplier?->phone)
                <p class="muted">{{ $purchaseOrder->supplier->phone }}</p>
            @endif
            @if ($purchaseOrder->supplier?->address)
                <p class="muted">{{ $purchaseOrder->supplier->address }}</p>
            @endif
        </section>

        <section class="party">
            <p class="label">Business information</p>
            @if ($purchaseOrder->branch)
                <h2>{{ $purchaseOrder->branch->registered_business_name ?: $purchaseOrder->branch->name }}</h2>
                @if ($purchaseOrder->branch->registered_business_name && $purchaseOrder->branch->name)
                    <p>{{ $purchaseOrder->branch->name }}</p>
                @endif
                @if ($purchaseOrder->branch->address)
                    <p class="muted">{{ $purchaseOrder->branch->address }}</p>
                @endif
                @if ($purchaseOrder->branch->tin)
                    <p class="muted">TIN {{ $purchaseOrder->branch->tin }}</p>
                @endif
            @else
                <h2>{{ $appName }}</h2>
                <p class="muted">Choose a branch on this purchase order to print registered business name, address, and TIN.</p>
            @endif
        </section>
    </div>

    @if ($purchaseOrder->notes)
        <p class="notes">{{ $purchaseOrder->notes }}</p>
    @endif

    <table>
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
                    <td>{{ $item->variant?->catalogItem?->name }} @if ($item->variant?->name) ({{ $item->variant->name }}) @endif</td>
                    <td>{{ $item->variant?->sku ?: '—' }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ \App\Support\Peso::format($item->unit_cost) }}</td>
                    <td class="num">{{ \App\Support\Peso::format($item->lineTotal()) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td>Total quantity</td>
            <td class="num">{{ $purchaseOrder->orderedQuantity() }}</td>
        </tr>
        <tr>
            <td>Total amount</td>
            <td class="num">{{ \App\Support\Peso::format($purchaseOrder->totalAmount()) }}</td>
        </tr>
    </table>
</body>
</html>

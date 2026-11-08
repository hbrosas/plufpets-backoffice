<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $label }}</title>
    <style>
        @page { margin: 10px 12px; }
        body { font-family: DejaVu Sans, sans-serif; color: #14231f; margin: 0; font-size: 9px; line-height: 1.25; }
        h1, p { margin: 0; }
        h1 { font-size: 12px; }
        .meta { margin: 0 0 6px; color: #5b6b66; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 4px; border-bottom: 1px solid #d7e0dc; text-align: left; vertical-align: top; }
        th { font-size: 8px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #5b6b66; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .muted { color: #5b6b66; }
    </style>
</head>
<body>
    <h1>{{ $label }}</h1>
    <p class="meta">{{ collect([$branch, count($orders).' '.(count($orders) === 1 ? 'order' : 'orders')])->filter()->implode(' · ') }}</p>
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Address</th>
                <th>Items</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>
                        {{ $order['number'] }}
                        <br><span class="muted">{{ $order['status'] }}</span>
                    </td>
                    <td>
                        {{ $order['customer'] }}
                        @if ($order['phone'] !== '')
                            <br><span class="muted">{{ $order['phone'] }}</span>
                        @endif
                        @if ($order['preferred_on'] !== '')
                            <br><span class="muted">{{ $order['preferred_on'] }}</span>
                        @endif
                    </td>
                    <td>{{ $order['address'] !== '' ? $order['address'] : '—' }}</td>
                    <td>{{ $order['items'] !== '' ? $order['items'] : '—' }}</td>
                    <td class="num">{{ $order['total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No orders assigned.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

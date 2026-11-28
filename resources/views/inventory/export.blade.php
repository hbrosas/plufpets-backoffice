<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: {{ $stockCount ? 'legal' : 'A4' }} landscape; margin: {{ $stockCount ? '4px 6px' : '8px 10px' }}; }
        body { font-family: DejaVu Sans, sans-serif; color: #14231f; font-size: 8px; line-height: 1.15; margin: 0; }
        h1 { font-size: 11px; margin: 0; }
        p { margin: 1px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #d7e0dc; padding: 1px 3px; text-align: left; vertical-align: top; }
        th { font-size: 7px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        td.write { height: 28px; border: 1px solid #14231f; }
        th.center, td.center { text-align: center; vertical-align: middle; }
        @if ($stockCount)
            body { font-size: 7px; }
            h1 { font-size: 10px; }
            p { margin: 0 0 2px; }
            th, td { padding: 0 2px; }
            td.write { height: 16px; }
        @endif
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{{ $subtitle }}</p>
    <table>
        @if ($stockCount)
            <colgroup>
                <col style="width: 12%">
                <col style="width: 20%">
                <col style="width: 10%">
                <col style="width: 10%">
                <col style="width: 12%">
                <col style="width: 8%">
                <col style="width: 28%">
            </colgroup>
            <thead>
                <tr>
                    <th>Brand</th>
                    <th>Product</th>
                    <th>Variant</th>
                    <th>SKU</th>
                    <th>Location</th>
                    <th class="center">Expected</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td>{{ $line[0] }}</td>
                        <td>{{ $line[1] }}</td>
                        <td>{{ $line[2] }}</td>
                        <td>{{ $line[3] }}</td>
                        <td>{{ $line[4] }}</td>
                        <td class="center">{{ $line[5] }}</td>
                        <td class="write"></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No stock.</td>
                    </tr>
                @endforelse
            </tbody>
        @else
            <colgroup>
                <col style="width: 22%">
                <col style="width: 12%">
                <col style="width: 12%">
                <col style="width: 16%">
                <col style="width: 7%">
                <col style="width: 11%">
                <col style="width: 11%">
                <col style="width: 9%">
            </colgroup>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Variant</th>
                    <th>SKU</th>
                    <th>Location</th>
                    <th class="num">Qty</th>
                    <th class="num">Purchase</th>
                    <th class="num">Retail</th>
                    <th class="num">Markup</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td>{{ $line[0] }}</td>
                        <td>{{ $line[1] }}</td>
                        <td>{{ $line[2] }}</td>
                        <td>{{ $line[3] }}</td>
                        <td class="num">{{ $line[4] }}</td>
                        <td class="num">{{ $line[5] }}</td>
                        <td class="num">{{ $line[6] }}</td>
                        <td class="num">{{ $line[7] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No stock.</td>
                    </tr>
                @endforelse
            </tbody>
        @endif
    </table>
</body>
</html>

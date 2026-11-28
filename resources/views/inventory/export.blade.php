<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: {{ $stockCount ? 'legal' : 'A4' }} landscape; margin: {{ $stockCount ? '0.4in' : '8px 10px' }}; }
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
            html, body { margin: 0; padding: 0; }
            body { font-size: 8px; line-height: 1; }
            h1 { font-size: 13px; margin: 0; font-weight: 700; }
            p.date { margin: 0 0 6px; font-size: 9px; line-height: 1; }
            p.mast { margin: 0 0 4px; padding: 0; font-size: 8px; line-height: 1; }
            p.section { margin: 8px 0 2px; padding: 0; font-size: 9px; font-weight: 700; line-height: 1; page-break-after: avoid; }
            table { width: 100%; table-layout: fixed; border-spacing: 0; }
            th { font-size: 8px; font-weight: 700; letter-spacing: 0; text-transform: none; }
            th, td { padding: 1px 3px; line-height: 1.05; vertical-align: middle; white-space: nowrap; overflow: hidden; }
            td.product { white-space: normal; }
            td.write { height: auto; padding: 1px 3px; border: none; border-bottom: 1px solid #d7e0dc; }
        @endif
    </style>
</head>
<body>
    @if ($stockCount)
        <h1>Inventory Count</h1>
        <p class="date">{{ $countedOn }}</p>
        @if ($subtitle !== '')
            <p class="mast">{{ $subtitle }}</p>
        @endif
    @else
        <h1>{{ $title }}</h1>
        <p>{{ $subtitle }}</p>
    @endif
    @if ($stockCount)
        @foreach ([
            ['heading' => null, 'lines' => $lines, 'empty' => 'No products on hand.'],
            ['heading' => 'No stock', 'lines' => $unstockedLines, 'empty' => 'No products without stock.'],
        ] as $section)
            @if ($section['heading'])
                <p class="section">{{ $section['heading'] }}</p>
            @endif
            <table>
                <colgroup>
                    <col style="width: 11%">
                    <col style="width: 20%">
                    <col style="width: 9%">
                    <col style="width: 11%">
                    <col style="width: 12%">
                    <col style="width: 7%">
                    <col style="width: 30%">
                </colgroup>
                <thead>
                    <tr>
                        <th style="width: 11%">Brand</th>
                        <th style="width: 20%">Product</th>
                        <th style="width: 9%">Variant</th>
                        <th style="width: 11%">SKU</th>
                        <th style="width: 12%">Location</th>
                        <th class="center" style="width: 7%">Expected</th>
                        <th style="width: 30%">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['lines'] as $line)
                        <tr>
                            <td>{{ $line[0] }}</td>
                            <td class="product">{{ $line[1] }}</td>
                            <td>{{ $line[2] }}</td>
                            <td>{{ $line[3] }}</td>
                            <td>{{ $line[4] }}</td>
                            <td class="center">{{ $line[5] }}</td>
                            <td class="write">&nbsp;</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">{{ $section['empty'] }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    @else
    <table>
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
    </table>
    @endif
</body>
</html>

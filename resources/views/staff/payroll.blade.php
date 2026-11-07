<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payroll</title>
    <style>
        body { font-family: Calibri, "DejaVu Sans", sans-serif; color: #14231f; font-size: 12px; }
        h1 { font-size: 20px; letter-spacing: 0.14em; text-align: center; margin: 0 0 16px; }
        .letterhead { text-align: center; margin: 0 0 16px; }
        .letterhead img { height: 56px; margin: 0 0 6px; }
        .letterhead .name { font-size: 14px; font-weight: bold; margin: 0; }
        .letterhead p { margin: 2px 0; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin: 0 0 16px; }
        th, td { border: 1px solid #d7e0dc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f7f5; font-size: 11px; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        table.split { margin: 0 0 16px; }
        td.side { width: 50%; border: 0; padding: 0 8px 0 0; vertical-align: top; }
        td.side-right { width: 50%; border: 0; padding: 0 0 0 8px; vertical-align: top; }
        td.side table, td.side-right table { margin: 0; }
        .section { font-size: 14px; font-weight: bold; margin: 0 0 6px; }
        .summary { width: 50%; margin-left: auto; }
        .net td { font-weight: bold; }
    </style>
</head>
<body>
    @isset($letterhead)
        @if (filled($letterhead['name'] ?? null) || filled($letterhead['logo_data_uri'] ?? null) || filled($letterhead['address'] ?? null) || filled($letterhead['phone'] ?? null) || filled($letterhead['email'] ?? null) || filled($letterhead['tin'] ?? null))
            <div class="letterhead">
                @if (filled($letterhead['logo_data_uri'] ?? null))
                    <img src="{{ $letterhead['logo_data_uri'] }}" alt="">
                @endif
                @if (filled($letterhead['name'] ?? null))
                    <p class="name">{{ $letterhead['name'] }}</p>
                @endif
                @if (filled($letterhead['address'] ?? null))
                    <p>{{ $letterhead['address'] }}</p>
                @endif
                @if (filled($letterhead['phone'] ?? null) || filled($letterhead['email'] ?? null))
                    <p>{{ collect([$letterhead['phone'] ?? null, $letterhead['email'] ?? null])->filter()->implode(' · ') }}</p>
                @endif
                @if (filled($letterhead['tin'] ?? null))
                    <p>TIN {{ $letterhead['tin'] }}</p>
                @endif
            </div>
        @endif
    @endisset

    <h1>PAYROLL</h1>

    <table class="split">
        <tr>
            <td class="side">
                <table>
                    <tr>
                        <th>Name</th>
                        <td>{{ $employeeName }}</td>
                    </tr>
                    <tr>
                        <th>Position</th>
                        <td>{{ filled($jobTitle) ? $jobTitle : '—' }}</td>
                    </tr>
                    <tr>
                        <th>No. of days worked</th>
                        <td>{{ $payroll['days_worked_with_holidays'] ?? $payroll['days_worked'] }}</td>
                    </tr>
                </table>
            </td>
            <td class="side-right">
                <table>
                    <tr>
                        <th>Pay period start</th>
                        <td>{{ \Illuminate\Support\Carbon::parse($payroll['period_start'])->format('M j, Y') }}</td>
                    </tr>
                    <tr>
                        <th>Pay period end</th>
                        <td>{{ \Illuminate\Support\Carbon::parse($payroll['period_end'])->format('M j, Y') }}</td>
                    </tr>
                    <tr>
                        <th>Employee status</th>
                        <td>{{ filled($employmentStatus) ? $employmentStatus : '—' }}</td>
                    </tr>
                    <tr>
                        <th>Date hired</th>
                        <td>{{ filled($dateHired) ? \Illuminate\Support\Carbon::parse($dateHired)->format('M j, Y') : '—' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="split">
        <tr>
            <td class="side">
                <p class="section">Earnings</p>
                <table>
                    <tr>
                        <th></th>
                        <th class="num">Amount</th>
                        <th class="num">Unit</th>
                        <th class="num">Subtotal</th>
                    </tr>
                    @php $previousGroup = null; @endphp
                    @foreach ($payroll['earnings'] as $line)
                        @if ((float) ($line['unit'] ?? 1) == 0)
                            @continue
                        @endif
                        @if (! empty($line['group']) && $line['group'] !== $previousGroup)
                            <tr>
                                <td colspan="4">{{ $line['group'] }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td @if (! empty($line['group'])) style="padding-left: 18px;" @endif>{{ $line['label'] ?? $line['name'] }}</td>
                            <td class="num">{{ number_format((float) ($line['rate'] ?? $line['amount']), 2) }}</td>
                            <td class="num">{{ number_format((float) ($line['unit'] ?? 1), 2) }}</td>
                            <td class="num">{{ number_format((float) $line['amount'], 2) }}</td>
                        </tr>
                        @php $previousGroup = $line['group'] ?? null; @endphp
                    @endforeach
                </table>
            </td>
            <td class="side-right">
                <p class="section">Deductions</p>
                <table>
                    <tr>
                        <th></th>
                        <th class="num">Amount</th>
                        <th class="num">Unit</th>
                        <th class="num">Subtotal</th>
                    </tr>
                    @foreach ($payroll['deductions'] as $line)
                        @if ((float) ($line['unit'] ?? 1) == 0)
                            @continue
                        @endif
                        <tr>
                            <td>{{ $line['label'] ?? $line['name'] }}</td>
                            <td class="num">{{ number_format((float) ($line['rate'] ?? $line['amount']), 2) }}</td>
                            <td class="num">{{ number_format((float) ($line['unit'] ?? 1), 2) }}</td>
                            <td class="num">{{ number_format((float) $line['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <th colspan="2">Summary</th>
        </tr>
        <tr>
            <td>Gross pay</td>
            <td class="num">{{ \App\Support\Peso::format($payroll['gross']) }}</td>
        </tr>
        <tr>
            <td>Deductions</td>
            <td class="num">{{ \App\Support\Peso::format($payroll['total_deductions']) }}</td>
        </tr>
        <tr class="net">
            <td>Net pay</td>
            <td class="num">{{ \App\Support\Peso::format($payroll['net']) }}</td>
        </tr>
    </table>
</body>
</html>

<?php

namespace App\Support;

use App\Models\Business;
use Carbon\CarbonImmutable;

class ReportCsv
{
    /**
     * @param  list<array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}>  $sections
     */
    public static function render(string $title, ?ReportPeriod $period, array $sections): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [Business::documentName()]);
        fputcsv($handle, [$title]);

        if ($period instanceof ReportPeriod) {
            $range = $period->toArray();
            fputcsv($handle, ['Period', $period->label]);
            fputcsv($handle, ['From', $range['from']]);
            fputcsv($handle, ['To', $range['to']]);
        }

        fputcsv($handle, ['Generated', CarbonImmutable::now(ReportPeriod::ZONE)->format('Y-m-d H:i')]);
        fputcsv($handle, []);

        foreach ($sections as [$heading, $headers, $rows]) {
            fputcsv($handle, [$heading]);
            fputcsv($handle, $headers);

            if ($rows === []) {
                fputcsv($handle, ['None']);
            }

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn (string|int|float|null $value): string|int|float => $value ?? '', $row));
            }

            fputcsv($handle, []);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    public static function filename(string $name, ?ReportPeriod $period): string
    {
        $slug = str($name)->slug()->toString();

        if (! $period instanceof ReportPeriod) {
            return $slug.'-'.CarbonImmutable::now(ReportPeriod::ZONE)->toDateString().'.csv';
        }

        $range = $period->toArray();

        return $slug.'-'.$range['from'].'-to-'.$range['to'].'.csv';
    }
}

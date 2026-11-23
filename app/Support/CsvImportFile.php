<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CsvImportFile
{
    /**
     * @param  list<string>  $requiredColumns
     * @param  list<string>  $optionalColumns
     * @return array{columns: array<string, int>, rows: list<array{number: int, cells: array<string, string>}>}
     */
    public static function read(
        UploadedFile $file,
        array $requiredColumns,
        array $optionalColumns,
        int $maxRows,
        string $rowLabel,
    ): array {
        $grid = self::isXlsx($file)
            ? XlsxSheet::rows($file->getRealPath(), $maxRows, $rowLabel)
            : self::csvGrid($file);

        if ($grid === []) {
            throw ValidationException::withMessages([
                'file' => 'The file is empty.',
            ]);
        }

        $header = array_shift($grid);
        $columns = self::columns($header ?? []);
        $missing = array_values(array_filter(
            $requiredColumns,
            fn (string $column): bool => ! array_key_exists($column, $columns),
        ));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'The file must include these columns: '.implode(', ', $requiredColumns).'.',
            ]);
        }

        $accepted = array_flip([...$requiredColumns, ...$optionalColumns]);
        $rows = [];
        $count = 0;

        foreach ($grid as $row) {
            $count++;

            if ($count > $maxRows) {
                throw ValidationException::withMessages([
                    'file' => 'Upload at most '.$maxRows.' '.$rowLabel.' at a time.',
                ]);
            }

            $cells = [];

            foreach ($columns as $name => $index) {
                if (! array_key_exists($name, $accepted)) {
                    continue;
                }

                $cells[$name] = Str::of((string) ($row[$index] ?? ''))->trim()->toString();
            }

            if (self::cellsAreEmpty($cells)) {
                continue;
            }

            $rows[] = [
                'number' => $count + 1,
                'cells' => $cells,
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<list<string|null>>
     */
    private static function csvGrid(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'The CSV file could not be read.',
            ]);
        }

        try {
            $grid = [];

            while (($row = fgetcsv($handle)) !== false) {
                $grid[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $grid;
    }

    private static function isXlsx(UploadedFile $file): bool
    {
        return Str::lower($file->getClientOriginalExtension()) === 'xlsx'
            || $file->getMimeType() === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /**
     * @param  array<string, int>  $columns
     * @param  list<string>  $requiredColumns
     * @param  list<string>  $optionalColumns
     * @return list<string>
     */
    public static function orderedColumns(array $columns, array $requiredColumns, array $optionalColumns): array
    {
        $accepted = array_flip([...$requiredColumns, ...$optionalColumns]);
        $present = [];

        foreach ($columns as $name => $index) {
            if (array_key_exists($name, $accepted)) {
                $present[$index] = $name;
            }
        }

        ksort($present);

        return array_values($present);
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private static function columns(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $column) {
            $name = Str::of((string) $column)->trim()->lower()->toString();

            if ($index === 0) {
                $name = preg_replace('/^\xEF\xBB\xBF/', '', $name) ?? $name;
            }

            if ($name === '') {
                continue;
            }

            $columns[$name] = $index;
        }

        return $columns;
    }

    /**
     * @param  array<string, string>  $cells
     */
    private static function cellsAreEmpty(array $cells): bool
    {
        foreach ($cells as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }
}

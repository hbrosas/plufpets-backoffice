<?php

namespace App\Support;

class CsvImportResult
{
    /**
     * @param  list<array{number: int, cells: array<string, string>}>  $rows
     * @param  list<string>  $columns
     * @return list<array{row: int, values: list<string>}>
     */
    public static function table(array $rows, array $columns): array
    {
        return array_map(fn (array $row): array => [
            'row' => $row['number'],
            'values' => self::values($row['cells'], $columns),
        ], $rows);
    }

    /**
     * @param  list<array{number: int, cells: array<string, string>}>  $rows
     * @param  list<string>  $columns
     * @param  list<array{row: int, message: string}>  $errors
     * @param  list<int>  $savedRows
     * @return list<array{row: int, values: list<string>, imported: bool, message: string}>
     */
    public static function logs(array $rows, array $columns, array $errors, array $savedRows): array
    {
        $saved = array_fill_keys($savedRows, true);
        $messages = [];

        foreach ($errors as $error) {
            $messages[$error['row']] ??= $error['message'];
        }

        return array_map(function (array $row) use ($columns, $messages, $saved): array {
            $imported = isset($saved[$row['number']]);

            return [
                'row' => $row['number'],
                'values' => self::values($row['cells'], $columns),
                'imported' => $imported,
                'message' => $messages[$row['number']] ?? ($imported ? '' : 'This row was not imported.'),
            ];
        }, $rows);
    }

    /**
     * @param  array<string, string>  $cells
     * @param  list<string>  $columns
     * @return list<string>
     */
    private static function values(array $cells, array $columns): array
    {
        return array_map(
            fn (string $column): string => $cells[$column] ?? '',
            $columns,
        );
    }
}

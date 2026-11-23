<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class XlsxSheet
{
    private const MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const OFFICE_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return list<list<string>>
     */
    public static function rows(string $path, int $maxRows, string $rowLabel): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'file' => 'The Excel file could not be read.',
            ]);
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheet = $zip->getFromName(self::firstSheet($zip));

            if ($sheet === false) {
                throw ValidationException::withMessages([
                    'file' => 'The Excel file could not be read.',
                ]);
            }

            return self::grid($sheet, $shared, $maxRows, $rowLabel);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = self::xml($xml);
        $strings = [];

        foreach (self::main($document)->si as $item) {
            $strings[] = self::text($item);
        }

        return $strings;
    }

    private static function firstSheet(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relationships = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $relationships === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheets = self::main(self::xml($workbook))->sheets;
        $sheet = $sheets->sheet[0] ?? null;

        if (! $sheet instanceof SimpleXMLElement) {
            return 'xl/worksheets/sheet1.xml';
        }

        $id = (string) $sheet->attributes(self::OFFICE_REL)['id'];
        $target = 'xl/worksheets/sheet1.xml';

        foreach (self::xml($relationships)->children(self::REL)->Relationship as $relationship) {
            if ((string) $relationship['Id'] === $id) {
                $target = self::sheetPath((string) $relationship['Target']);
            }
        }

        return $target;
    }

    private static function sheetPath(string $target): string
    {
        $target = ltrim($target, '/');

        if (! str_starts_with($target, 'xl/')) {
            $target = 'xl/'.ltrim($target, '/');
        }

        return $target;
    }

    /**
     * @param  list<string>  $shared
     * @return list<list<string>>
     */
    private static function grid(string $sheet, array $shared, int $maxRows, string $rowLabel): array
    {
        $cells = [];
        $width = 0;
        $fallback = 0;

        foreach (self::main(self::xml($sheet))->sheetData->row as $row) {
            $fallback++;
            $number = (int) $row->attributes()['r'] ?: $fallback;

            if ($number > $maxRows + 1) {
                throw ValidationException::withMessages([
                    'file' => 'Upload at most '.$maxRows.' '.$rowLabel.' at a time.',
                ]);
            }

            foreach (self::main($row)->c as $cell) {
                $column = self::columnIndex((string) $cell->attributes()['r']);

                if ($column > 63) {
                    continue;
                }

                $cells[$number][$column] = self::cellValue($cell, $shared);
                $width = max($width, $column);
            }
        }

        if ($cells === []) {
            return [];
        }

        $grid = [];

        for ($row = 1; $row <= max(array_keys($cells)); $row++) {
            $line = [];

            for ($column = 0; $column <= $width; $column++) {
                $line[] = $cells[$row][$column] ?? '';
            }

            $grid[] = $line;
        }

        return $grid;
    }

    /**
     * @param  list<string>  $shared
     */
    private static function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) $cell->attributes()['t'];
        $node = self::main($cell);

        if ($type === 'inlineStr') {
            return self::text($node->is);
        }

        $value = (string) $node->v;

        if ($type === 's') {
            return $shared[(int) $value] ?? '';
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }

        return $value;
    }

    private static function text(SimpleXMLElement $item): string
    {
        $node = self::main($item);

        if ($node->r->count() === 0) {
            return (string) $node->t;
        }

        $text = '';

        foreach ($node->r as $run) {
            $text .= (string) self::main($run)->t;
        }

        return $text;
    }

    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($reference)) ?? '';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    private static function xml(string $xml): SimpleXMLElement
    {
        $document = simplexml_load_string($xml);

        if (! $document instanceof SimpleXMLElement) {
            throw ValidationException::withMessages([
                'file' => 'The Excel file could not be read.',
            ]);
        }

        return $document;
    }

    private static function main(SimpleXMLElement $element): SimpleXMLElement
    {
        return $element->children(self::MAIN);
    }
}

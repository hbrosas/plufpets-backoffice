<?php

namespace App\Support;

use ZipArchive;

class XlsxWorkbook
{
    /**
     * @param  list<list<int|float|string|null>>  $rows
     * @param  list<float>|null  $columnWidths
     */
    public static function binary(string $sheetName, array $rows, ?float $dataRowHeight = null, ?int $centerColumn = null, ?array $columnWidths = null, ?int $printTitleRow = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            return '';
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::contentTypes($centerColumn !== null));
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName, $printTitleRow));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels($centerColumn !== null));
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($rows, $dataRowHeight, $centerColumn, $columnWidths));

        if ($centerColumn !== null) {
            $zip->addFromString('xl/styles.xml', self::styles());
        }

        $zip->close();

        $contents = file_get_contents($path);
        unlink($path);

        return $contents === false ? '' : $contents;
    }

    /**
     * @param  list<list<int|float|string|null>>  $rows
     * @param  list<float>|null  $columnWidths
     */
    private static function sheet(array $rows, ?float $dataRowHeight, ?int $centerColumn, ?array $columnWidths): string
    {
        $columns = '';
        $page = '';

        if ($columnWidths !== null) {
            $columns = '<cols>';

            foreach (array_values($columnWidths) as $index => $width) {
                $number = $index + 1;
                $columns .= '<col min="'.$number.'" max="'.$number.'" width="'.$width.'" customWidth="1"/>';
            }

            $columns .= '</cols>';
            $page = '<pageMargins left="0.4" right="0.4" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
                .'<pageSetup paperSize="5" orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .($columnWidths !== null ? '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>' : '')
            .$columns
            .'<sheetData>';

        foreach ($rows as $index => $row) {
            $number = $index + 1;
            $height = $index > 0 && $dataRowHeight !== null
                ? ' ht="'.$dataRowHeight.'" customHeight="1"'
                : '';
            $xml .= '<row r="'.$number.'"'.$height.'>';

            foreach (array_values($row) as $column => $value) {
                $style = $column === $centerColumn ? 1 : null;
                $xml .= self::cell(self::column($column).$number, $value, $style);
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData>'.$page.'</worksheet>';
    }

    private static function cell(string $reference, int|float|string|null $value, ?int $style = null): string
    {
        $styleAttribute = $style === null ? '' : ' s="'.$style.'"';

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"'.$styleAttribute.'><v>'.$value.'</v></c>';
        }

        $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $space = $text !== trim($text) ? ' xml:space="preserve"' : '';

        return '<c r="'.$reference.'"'.$styleAttribute.' t="inlineStr"><is><t'.$space.'>'.$text.'</t></is></c>';
    }

    private static function column(int $index): string
    {
        $letters = '';
        $index++;

        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }

    private static function contentTypes(bool $styled): string
    {
        $styles = $styled
            ? '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .$styles
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(string $sheetName, ?int $printTitleRow): string
    {
        $name = htmlspecialchars(mb_substr($sheetName, 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $titles = '';

        if ($printTitleRow !== null) {
            $quoted = str_replace("'", "''", mb_substr($sheetName, 0, 31));
            $titles = '<definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\''.$quoted.'\'!$'.$printTitleRow.':$'.$printTitleRow.'</definedName></definedNames>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets>'
            .$titles
            .'</workbook>';
    }

    private static function workbookRels(bool $styled): string
    {
        $styles = $styled
            ? '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .$styles
            .'</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }
}

<?php

namespace App\Support;

class BarcodeSvg
{
    /**
     * Code 39 patterns use n = narrow and w = wide.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        '0' => 'nnnwwnwnn',
        '1' => 'wnnwnnnnw',
        '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn',
        '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw',
        'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn',
        'D' => 'nnnnwwnnw',
        'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw',
        'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn',
        'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww',
        'L' => 'nnwnnnnww',
        'M' => 'wnwnnnnwn',
        'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn',
        'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww',
        'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn',
        'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw',
        'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn',
        'X' => 'nwnnwnnnw',
        'Y' => 'wwnnwnnnn',
        'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw',
        '.' => 'wwnnnnwnn',
        ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn',
        '+' => 'nwnnnwnwn',
        '%' => 'nnnwnwnwn',
        '*' => 'nwnnwnwnn',
    ];

    public static function code39(string $value): string
    {
        $encoded = '*'.self::sanitize($value).'*';
        $module = 1.2;
        $height = 42;
        $x = 10.0;
        $bars = '';

        foreach (str_split($encoded) as $index => $character) {
            if ($index > 0) {
                $x += $module;
            }

            $pattern = self::PATTERNS[$character];

            foreach (str_split($pattern) as $position => $width) {
                $barWidth = ($width === 'w' ? 3 : 1) * $module;

                if ($position % 2 === 0) {
                    $bars .= '<rect x="'.$x.'" y="0" width="'.$barWidth.'" height="'.$height.'" fill="#14231f"/>';
                }

                $x += $barWidth;
            }
        }

        $width = $x + 10;

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Barcode" preserveAspectRatio="none" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'.$bars.'</svg>';
    }

    public static function sanitize(string $value): string
    {
        $normalized = strtoupper($value);
        $safe = '';

        foreach (str_split($normalized) as $character) {
            $safe .= array_key_exists($character, self::PATTERNS) && $character !== '*'
                ? $character
                : '-';
        }

        return $safe !== '' ? $safe : '0';
    }
}

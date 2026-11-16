<?php

declare(strict_types=1);

namespace Ai\Knowledge;

/** Разделя текст на части за търсене: по редове и изречения, с малко припокриване между съседните части. */
final class TextChunker
{
    /** @return list<string> */
    public static function split(string $text, int $size = 1200, int $overlap = 150): array
    {
        $units = [];
        foreach (preg_split('/\n+/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            foreach (mb_strlen($line) <= $size ? [$line] : self::splitLong($line, $size) as $piece) {
                $units[] = $piece;
            }
        }

        $chunks = [];
        $current = '';
        foreach ($units as $unit) {
            if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($unit) > $size) {
                $chunks[] = $current;
                // Краят на предишната част се повтаря, за да не се губи смисъл на границата.
                $tail = self::tail($current, $overlap);
                $current = $tail === '' ? $unit : $tail . "\n" . $unit;
            } else {
                $current = $current === '' ? $unit : $current . "\n" . $unit;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** @return list<string> */
    private static function splitLong(string $line, int $size): array
    {
        $pieces = [];
        $current = '';
        foreach (preg_split('/(?<=[.!?;])\s+/u', $line) ?: [$line] as $sentence) {
            if (mb_strlen($sentence) > $size) {
                if ($current !== '') {
                    $pieces[] = $current;
                    $current = '';
                }
                // Текст без граници на изречения (таблица, код) се реже на фиксирани части.
                foreach (mb_str_split($sentence, $size) as $part) {
                    $pieces[] = $part;
                }
                continue;
            }
            if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($sentence) > $size) {
                $pieces[] = $current;
                $current = $sentence;
            } else {
                $current = $current === '' ? $sentence : $current . ' ' . $sentence;
            }
        }
        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    private static function tail(string $text, int $overlap): string
    {
        if ($overlap <= 0 || mb_strlen($text) <= $overlap) {
            return '';
        }
        $tail = mb_substr($text, -$overlap);
        $space = mb_strpos($tail, ' ');

        return $space === false ? $tail : ltrim(mb_substr($tail, $space));
    }
}

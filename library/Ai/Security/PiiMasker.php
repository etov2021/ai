<?php

declare(strict_types=1);

namespace Ai\Security;

/** Маскира ЕГН, IBAN и номера на карти (само валидни по контролна сума, за да не се трият обикновени числа). */
final class PiiMasker
{
    public static function mask(string $text): string
    {
        $text = preg_replace_callback('/\b[A-Z]{2}\d{2}(?: ?[A-Z0-9]{4}){2,7}(?: ?[A-Z0-9]{1,3})?\b/', static fn (array $m): string => self::validIban($m[0]) ? '[IBAN]' : $m[0], $text) ?? $text;
        $text = preg_replace_callback('/\b(?:\d[ -]?){12,18}\d\b/', static fn (array $m): string => self::luhn(preg_replace('/\D/', '', $m[0]) ?? '') ? '[КАРТА]' : $m[0], $text) ?? $text;

        return preg_replace_callback('/(?<!\d)\d{10}(?!\d)/', static fn (array $m): string => self::egn($m[0]) ? '[ЕГН]' : $m[0], $text) ?? $text;
    }

    private static function egn(string $digits): bool
    {
        $month = (int) substr($digits, 2, 2);
        $day = (int) substr($digits, 4, 2);
        // Месецът носи века: +20 за 1800-те, +40 за 2000-те.
        $realMonth = $month > 40 ? $month - 40 : ($month > 20 ? $month - 20 : $month);
        if ($realMonth < 1 || $realMonth > 12 || $day < 1 || $day > 31) {
            return false;
        }

        $sum = 0;
        foreach ([2, 4, 8, 5, 10, 9, 7, 3, 6] as $i => $weight) {
            $sum += (int) $digits[$i] * $weight;
        }
        $check = $sum % 11;

        return ($check === 10 ? 0 : $check) === (int) $digits[9];
    }

    private static function luhn(string $digits): bool
    {
        $length = strlen($digits);
        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;
        foreach (str_split(strrev($digits)) as $i => $d) {
            $n = (int) $d * ($i % 2 === 1 ? 2 : 1);
            $sum += $n > 9 ? $n - 9 : $n;
        }

        return $sum % 10 === 0;
    }

    private static function validIban(string $iban): bool
    {
        $iban = str_replace(' ', '', $iban);
        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return false;
        }

        $numeric = '';
        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $c) {
            $numeric .= ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
        }

        $rest = 0;
        foreach (str_split($numeric, 7) as $part) {
            $rest = (int) ($rest . $part) % 97;
        }

        return $rest === 1;
    }
}

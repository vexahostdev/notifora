<?php

namespace App\Support;

final class Phone
{
    /** Normalisasi ke E.164 (default region Indonesia). Null bila tidak valid. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $hasPlus = str_starts_with(trim($raw), '+');
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            return null;
        }
        if (! $hasPlus) {
            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            } elseif (str_starts_with($digits, '0')) {
                $digits = '62'.substr($digits, 1);
            } elseif (str_starts_with($digits, '8')) {
                $digits = '62'.$digits;
            }
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
    }

    public static function digits(?string $e164): ?string
    {
        $n = self::normalize($e164);

        return $n ? ltrim($n, '+') : null;
    }

    /** Samarkan tujuan untuk log: "+62812****890", "b***@domain.com". */
    public static function mask(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (str_contains($value, '@')) {
            [$u, $d] = explode('@', $value, 2);

            return mb_substr($u, 0, 1).'***@'.$d;
        }
        $len = strlen($value);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, min(6, $len - 3)).'****'.substr($value, -3);
    }
}

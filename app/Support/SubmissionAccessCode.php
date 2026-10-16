<?php

namespace App\Support;

/**
 * The check code a citizen gets with the appeal number: eight characters
 * from an alphabet without look-alikes (no 0/O, 1/I/L), shown as XXXX-XXXX.
 * 31^8 ≈ 8.5·10^11 codes against ten tries a minute — not something to guess.
 */
final class SubmissionAccessCode
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 8;

    public static function generate(): string
    {
        $code = '';
        $last = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }

        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    /**
     * The code as typed — any case, with or without the dash and spaces —
     * in the form its hash was made from.
     */
    public static function normalize(string $typed): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $typed));
    }

    /**
     * The appeal number as typed: «КЧС-2026-00012», «кчс 2026 12» or
     * «2026-12» all mean the same appeal. Null without the year and number.
     */
    public static function trackingNumber(string $typed): ?string
    {
        if (preg_match_all('/\d+/', $typed, $groups) < 2) {
            return null;
        }

        $digits = $groups[0];
        $year = $digits[0];
        $number = ltrim((string) end($digits), '0');

        if (strlen($year) !== 4 || $number === '') {
            return null;
        }

        return 'КЧС-'.$year.'-'.str_pad($number, 5, '0', STR_PAD_LEFT);
    }
}

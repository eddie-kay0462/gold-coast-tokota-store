<?php

namespace App\Support;

/**
 * Phone numbers in the one form WhatsApp uses: international, digits only
 * (a "wa_id", e.g. 233245551234).
 *
 * Customers type their numbers every way there is: "024 555 1234",
 * "+233 24 555 1234", "00233245551234". Matching a WhatsApp sender to a
 * Customer record means reducing both to the same digits. A leading 0 on a
 * ten-digit number is read as a Ghana local number, since the brand is in
 * Accra and that is how its customers write theirs.
 */
final class PhoneNumber
{
    private const GHANA = '233';

    /** Digits in international form, or null if there are not enough to be a number. */
    public static function toWaId(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = self::GHANA.substr($digits, 1);
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    public static function e164(string $waId): string
    {
        return '+'.$waId;
    }

    /** Whether two numbers, however written, are the same number. */
    public static function same(?string $a, ?string $b): bool
    {
        $a = self::toWaId($a);

        return $a !== null && $a === self::toWaId($b);
    }
}

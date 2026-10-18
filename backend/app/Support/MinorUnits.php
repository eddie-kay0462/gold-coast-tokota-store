<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decimal strings from carrier APIs to integer minor units, without floats.
 *
 * Yango returns prices as Decimal(18,4) strings ("45.5000"); a float would
 * turn some of those into 4549.999… and a cast would lose a pesewa. This does
 * the arithmetic on the digits instead, rounding half up on the first
 * dropped digit.
 */
final class MinorUnits
{
    public static function fromDecimal(string $amount, int $exponent = 2): int
    {
        $amount = trim($amount);

        if (! preg_match('/^(\d+)(?:\.(\d*))?$/', $amount, $parts)) {
            throw new InvalidArgumentException("Not a non-negative decimal amount: '{$amount}'.");
        }

        $whole = $parts[1];
        $fraction = $parts[2] ?? '';
        $kept = str_pad(substr($fraction, 0, $exponent), $exponent, '0');
        $roundUp = strlen($fraction) > $exponent && (int) $fraction[$exponent] >= 5;

        return (int) ltrim($whole.$kept, '0') + ($roundUp ? 1 : 0);
    }

    /** Minor units back to a plain decimal string, for carriers that want one. */
    public static function toDecimal(int $minor, int $exponent = 2): string
    {
        $padded = str_pad((string) $minor, $exponent + 1, '0', STR_PAD_LEFT);

        return substr($padded, 0, -$exponent).'.'.substr($padded, -$exponent);
    }
}

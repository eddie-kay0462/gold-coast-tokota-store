<?php

namespace App\Support;

use App\Models\Order;
use Closure;

/**
 * Customer-facing references: order numbers, and the WhatsApp draft code a
 * customer carries from the cart into a chat.
 *
 * Customers read these aloud and type them into WhatsApp, so the alphabet
 * leaves out the characters people confuse: no O/0, no I/1.
 */
final class OrderReference
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** An order reference, `GCT-` plus 12 characters, unique among orders. */
    public static function forOrder(): string
    {
        return self::generate('GCT-', 12, fn (string $ref) => Order::query()->where('reference', $ref)->exists());
    }

    /**
     * @param  Closure(string): bool  $taken  whether a candidate is already in use
     */
    public static function generate(string $prefix, int $length, Closure $taken): string
    {
        $max = strlen(self::ALPHABET) - 1;

        do {
            $reference = $prefix.collect(range(1, $length))
                ->map(fn () => self::ALPHABET[random_int(0, $max)])
                ->implode('');
        } while ($taken($reference));

        return $reference;
    }
}

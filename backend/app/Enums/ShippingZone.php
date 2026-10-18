<?php

namespace App\Enums;

/**
 * Where an order is going, which decides who carries it.
 *
 *   - Accra: the destination pin is inside the Accra service polygon.
 *   - Ghana: in Ghana but outside that polygon, or with no pin at all.
 *   - International: any country but GH.
 *
 * Resolved by App\Services\Shipping\ZoneResolver, never by the client.
 */
enum ShippingZone: string
{
    case Accra = 'accra';
    case Ghana = 'ghana';
    case International = 'international';

    /** Domestic zones are the ones a free-shipping threshold can apply to. */
    public function isDomestic(): bool
    {
        return $this !== self::International;
    }

    public function label(): string
    {
        return match ($this) {
            self::Accra => 'Accra',
            self::Ghana => 'Ghana',
            self::International => 'International',
        };
    }
}

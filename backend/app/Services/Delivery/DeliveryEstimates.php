<?php

namespace App\Services\Delivery;

/**
 * §8 of the brand document — the delivery times Gold Coast Tokota publishes.
 *
 * These are promises made to customers on the shipping policy page, so they
 * live in one place and every surface quotes the same figure: the checkout
 * quote, the order confirmation, the admin delivery panel and the storefront's
 * policy copy. §22.3 forbids changing any of them without instruction, so this
 * class is a transcription with no arithmetic in it.
 *
 * Country → band is the part §8 leaves implicit: it names regions, not
 * countries. The mapping below is the plain reading of those regions, and the
 * default is §8's own catch-all, "Other destinations".
 */
final class DeliveryEstimates
{
    /** §8: "Orders are processed within 48 hours after payment confirmation." */
    public const PROCESSING_HOURS = 48;

    /** §8: "Standard delivery takes 1–2 business days, depending on destination." */
    public const DOMESTIC = '1–2 business days';

    private const WEST_AFRICA = [
        'NG', 'CI', 'SN', 'TG', 'BJ', 'BF', 'ML', 'NE', 'GN', 'GW',
        'SL', 'LR', 'GM', 'MR', 'CV',
    ];

    private const EUROPE = [
        'GB', 'IE', 'FR', 'DE', 'NL', 'BE', 'ES', 'IT', 'PT', 'SE', 'NO',
        'DK', 'FI', 'PL', 'AT', 'CH', 'CZ', 'GR', 'HU', 'RO', 'LU',
    ];

    private const NORTH_AMERICA = ['US', 'CA', 'MX'];

    /** The label §8 gives for a destination, by ISO country code. */
    public static function for(?string $countryCode): string
    {
        $code = strtoupper((string) $countryCode);

        return match (true) {
            $code === 'GH' => self::DOMESTIC,
            in_array($code, self::WEST_AFRICA, true) => '5–10 business days',
            in_array($code, self::EUROPE, true) => '7–14 business days',
            in_array($code, self::NORTH_AMERICA, true) => '7–14 business days',
            default => '10–21 business days',
        };
    }

    /** §8's international table, for the admin delivery panel. */
    public static function bands(): array
    {
        return [
            ['region' => 'West Africa', 'eta' => '5–10 business days'],
            ['region' => 'Europe', 'eta' => '7–14 business days'],
            ['region' => 'North America', 'eta' => '7–14 business days'],
            ['region' => 'Other destinations', 'eta' => '10–21 business days'],
        ];
    }
}

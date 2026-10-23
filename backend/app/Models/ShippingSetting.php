<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The shipping knobs an Admin turns without a deploy: the Accra service
 * polygon, the free-shipping threshold, and the fallback delivery rates.
 * Single row, like SiteSetting.
 */
class ShippingSetting extends Model
{
    protected $fillable = [
        'accra_polygon',
        'free_shipping_threshold_ghs',
        'fallback_accra_ghs',
        'fallback_ghana_ghs',
        'fallback_international_ghs',
    ];

    /** The zones a fallback rate is kept for. */
    public const FALLBACK_ZONES = ['accra', 'ghana', 'international'];

    protected $casts = [
        'accra_polygon' => 'array',
        'free_shipping_threshold_ghs' => 'integer',
        'fallback_accra_ghs' => 'integer',
        'fallback_ghana_ghs' => 'integer',
        'fallback_international_ghs' => 'integer',
    ];

    /** Single-row resource — creates the row on first access if missing. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * What a zone is charged when no courier can quote (and, for `ghana`, a
     * region with no rate of its own), in GHS minor units. Set in admin
     * (Settings › Delivery); unset falls back to config('shipping.fallback_rates').
     */
    public static function fallbackRate(string $zone): int
    {
        return static::current()->{"fallback_{$zone}_ghs"} ?? (int) config("shipping.fallback_rates.{$zone}");
    }
}

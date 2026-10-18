<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The shipping knobs an Admin turns without a deploy: the Accra service
 * polygon and the free-shipping threshold. Single row, like SiteSetting.
 */
class ShippingSetting extends Model
{
    protected $fillable = [
        'accra_polygon',
        'free_shipping_threshold_ghs',
    ];

    protected $casts = [
        'accra_polygon' => 'array',
        'free_shipping_threshold_ghs' => 'integer',
    ];

    /** Single-row resource — creates the row on first access if missing. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}

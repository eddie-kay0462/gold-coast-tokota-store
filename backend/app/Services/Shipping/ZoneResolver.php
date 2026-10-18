<?php

namespace App\Services\Shipping;

use App\Enums\ShippingZone;
use App\Models\ShippingSetting;

/**
 * Decides which zone an address is in. The only place that decision is made.
 *
 *   - country is not GH            → International
 *   - GH, pin inside the polygon    → Accra
 *   - GH, anything else             → Ghana
 *
 * "Anything else" includes a Ghana address with no pin and a deployment with
 * no polygon uploaded yet. Both land on the flat regional rate and manual
 * dispatch, which is the zone that can never book the wrong courier.
 */
class ZoneResolver
{
    /** @param  array<string, mixed>  $address */
    public function resolve(array $address): ShippingZone
    {
        if (strtoupper((string) ($address['country'] ?? '')) !== 'GH') {
            return ShippingZone::International;
        }

        $lat = $address['lat'] ?? null;
        $lng = $address['lng'] ?? null;
        $polygon = ShippingSetting::current()->accra_polygon;

        if (is_numeric($lat) && is_numeric($lng) && is_array($polygon)
            && PointInPolygon::contains($polygon, (float) $lat, (float) $lng)) {
            return ShippingZone::Accra;
        }

        return ShippingZone::Ghana;
    }
}

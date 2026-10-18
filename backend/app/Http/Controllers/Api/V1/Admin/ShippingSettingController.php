<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShippingSettingRequest;
use App\Models\ShippingSetting;
use App\Services\Shipping\PointInPolygon;
use Illuminate\Http\JsonResponse;

/**
 * The Accra service polygon and the free-shipping threshold — the two shipping
 * rules that change without a deploy.
 */
class ShippingSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->respond(ShippingSetting::current());
    }

    public function update(UpdateShippingSettingRequest $request): JsonResponse
    {
        $settings = ShippingSetting::current();
        $settings->update($request->validated());

        return $this->respond($settings);
    }

    private function respond(ShippingSetting $settings): JsonResponse
    {
        $polygons = $settings->accra_polygon ? PointInPolygon::polygons($settings->accra_polygon) : [];

        return response()->json(['data' => [
            'accra_polygon' => $settings->accra_polygon,
            // Enough for the screen to confirm the upload landed where it
            // should without drawing a map.
            'accra_polygon_summary' => $polygons === [] ? null : $this->summary($polygons),
            'free_shipping_threshold' => $settings->free_shipping_threshold_ghs === null
                ? null
                : ['amount' => $settings->free_shipping_threshold_ghs, 'currency' => 'GHS'],
            'updated_at' => $settings->updated_at,
        ]]);
    }

    /** @param  list<list<list<array{0: float, 1: float}>>>  $polygons */
    private function summary(array $polygons): array
    {
        $points = array_merge(...array_map(fn ($rings) => $rings[0] ?? [], $polygons));
        $lngs = array_map(fn ($point) => (float) $point[0], $points);
        $lats = array_map(fn ($point) => (float) $point[1], $points);

        return [
            'polygons' => count($polygons),
            'points' => count($points),
            'bounds' => [
                'south' => min($lats), 'north' => max($lats),
                'west' => min($lngs), 'east' => max($lngs),
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFallbackRatesRequest;
use App\Http\Requests\Admin\UpdateShippingSettingRequest;
use App\Models\ShippingSetting;
use App\Services\Shipping\PointInPolygon;
use Illuminate\Http\JsonResponse;

/**
 * The shipping rules that change without a deploy: the Accra service polygon,
 * the free-shipping threshold, and the fallback delivery rates.
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

    /**
     * The fallback delivery rates. Prices, so `pricing.write` on the route,
     * like the regional rates; the polygon and threshold above stay
     * `settings.write`.
     */
    public function updateFallbackRates(UpdateFallbackRatesRequest $request): JsonResponse
    {
        $settings = ShippingSetting::current();
        $settings->update([
            'fallback_accra_ghs' => $request->integer('accra_ghs'),
            'fallback_ghana_ghs' => $request->integer('ghana_ghs'),
            'fallback_international_ghs' => $request->integer('international_ghs'),
        ]);

        return $this->respond($settings->refresh());
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
            'fallback_rates' => collect(ShippingSetting::FALLBACK_ZONES)
                ->mapWithKeys(fn (string $zone) => [$zone => ['amount' => ShippingSetting::fallbackRate($zone), 'currency' => 'GHS']])
                ->all(),
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

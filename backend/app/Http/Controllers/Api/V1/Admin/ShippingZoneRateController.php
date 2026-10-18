<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShippingZoneRatesRequest;
use App\Http\Resources\Admin\ShippingZoneRateResource;
use App\Models\ShippingZoneRate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Flat delivery rates for Ghana outside the Accra zone, one per region.
 *
 * Gated on `pricing.write`: §18 lets Staff move stock but not set prices, and
 * a delivery rate is a price the customer pays.
 */
class ShippingZoneRateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ShippingZoneRateResource::collection($this->all());
    }

    public function update(UpdateShippingZoneRatesRequest $request): AnonymousResourceCollection
    {
        $by = $request->user('admin');

        DB::transaction(function () use ($request, $by) {
            foreach ($request->validated('rates') as $rate) {
                ShippingZoneRate::query()->updateOrCreate(
                    ['region' => $rate['region']],
                    [
                        'amount_ghs' => $rate['amount_ghs'],
                        'is_active' => $rate['is_active'],
                        'updated_by' => $by->id,
                    ],
                );
            }
        });

        return ShippingZoneRateResource::collection($this->all());
    }

    /**
     * Every region, in the order Ghana lists them, including any not yet
     * saved — the screen is a complete price list, not a list of what exists.
     */
    private function all()
    {
        $saved = ShippingZoneRate::query()->with('updatedBy')->get()->keyBy('region');

        return collect(ShippingZoneRate::REGIONS)->map(
            fn (string $region) => $saved[$region] ?? new ShippingZoneRate(['region' => $region, 'amount_ghs' => 0, 'is_active' => false]),
        );
    }
}

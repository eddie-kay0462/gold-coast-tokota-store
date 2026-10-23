<?php

namespace App\Services\Shipping\Carriers;

use App\Contracts\ShippingCarrier;
use App\Enums\ShippingZone;
use App\Exceptions\RegionNotServedException;
use App\Models\Shipment;
use App\Models\ShippingSetting;
use App\Models\ShippingZoneRate;
use App\Services\Delivery\DeliveryEstimates;
use App\Services\Shipping\CancelResult;
use App\Services\Shipping\DispatchResult;
use App\Services\Shipping\QuoteOption;
use App\Services\Shipping\QuoteRequest;
use App\Services\Shipping\QuoteResult;
use App\Services\Shipping\StatusResult;

/**
 * The semi-automated carrier: a person books the courier and types the
 * tracking number into admin.
 *
 * It prices from the business's own rates — the regional flat rate for the
 * GHANA zone (or the Ghana fallback while a region has none), the configured
 * fallback for the other two — and its dispatch,
 * status and cancel are no-ops that report what staff have recorded. It
 * implements the full contract so a real API driver for regional couriers
 * can replace it with a change to config/shipping.php and nothing else.
 */
class ManualCarrier implements ShippingCarrier
{
    public function name(): string
    {
        return 'manual';
    }

    public function isEnabled(): bool
    {
        // Always available: it is what every zone falls back to.
        return true;
    }

    /** @throws RegionNotServedException */
    public function quote(QuoteRequest $request): QuoteResult
    {
        $eta = DeliveryEstimates::for($request->address['country'] ?? null);

        $option = match ($request->zone) {
            ShippingZone::Ghana => $this->regionalOption($request, $eta),
            ShippingZone::Accra => new QuoteOption(
                id: QuoteOption::idFor($this->name(), null),
                carrier: $this->name(),
                serviceCode: null,
                label: 'Delivery in Accra',
                amountGhs: ShippingSetting::fallbackRate('accra'),
                source: QuoteOption::SOURCE_FLAT,
                eta: $eta,
            ),
            ShippingZone::International => new QuoteOption(
                id: QuoteOption::idFor($this->name(), null),
                carrier: $this->name(),
                serviceCode: null,
                label: 'International delivery',
                amountGhs: ShippingSetting::fallbackRate('international'),
                source: QuoteOption::SOURCE_FLAT,
                eta: $eta,
            ),
        };

        return new QuoteResult($request->zone, [$option]);
    }

    private function regionalOption(QuoteRequest $request, string $eta): QuoteOption
    {
        $region = $request->address['region'] ?? null;
        $rate = ShippingZoneRate::forRegion($region);

        if ($rate === null) {
            if (config('shipping.unpriced_regions') === 'refuse') {
                throw new RegionNotServedException($region);
            }

            return new QuoteOption(
                id: QuoteOption::idFor($this->name(), null),
                carrier: $this->name(),
                serviceCode: null,
                label: 'Delivery in Ghana',
                amountGhs: ShippingSetting::fallbackRate('ghana'),
                source: QuoteOption::SOURCE_FALLBACK,
                eta: $eta,
            );
        }

        return new QuoteOption(
            id: QuoteOption::idFor($this->name(), null),
            carrier: $this->name(),
            serviceCode: null,
            label: "Delivery to {$rate->region}",
            amountGhs: $rate->amount_ghs,
            source: QuoteOption::SOURCE_FLAT,
            eta: $eta,
        );
    }

    public function dispatch(Shipment $shipment): DispatchResult
    {
        // Nothing to call. The shipment waits for staff to book a courier and
        // record it through the admin dispatch form.
        return new DispatchResult(status: $shipment->status);
    }

    public function status(Shipment $shipment): StatusResult
    {
        return new StatusResult(
            status: $shipment->status,
            carrierStatus: $shipment->carrier_status,
            trackingUrl: $shipment->tracking_url,
            actualCost: $shipment->actual_cost,
            actualCurrency: $shipment->actual_currency,
        );
    }

    public function cancel(Shipment $shipment): CancelResult
    {
        return new CancelResult(cancelled: true, message: 'Cancelled by hand; tell the courier directly if one was booked.');
    }
}

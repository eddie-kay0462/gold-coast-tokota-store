<?php

namespace App\Services\Shipping\Carriers;

use App\Contracts\ConfirmsDispatch;
use App\Contracts\ShippingCarrier;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\Delivery\DeliveryEstimates;
use App\Services\Shipping\CancelResult;
use App\Services\Shipping\DispatchResult;
use App\Services\Shipping\QuoteOption;
use App\Services\Shipping\QuoteRequest;
use App\Services\Shipping\QuoteResult;
use App\Services\Shipping\StatusResult;
use Illuminate\Support\Str;

/**
 * Yango without Yango, for running the Accra flow end to end with no token —
 * the same role FakeGateway plays for Paystack. Used only outside production
 * when YANGO_ENABLED is on and YANGO_API_KEY is empty.
 *
 * It walks a claim through Yango's real status names, one step per poll:
 * new → ready_for_approval → (accepted) → performer_found → pickuped →
 * delivery_arrived → delivered. A destination whose address line contains
 * NOCOURIER stops at performer_not_found instead, to exercise the retry and
 * fallback path.
 */
class FakeYangoCarrier implements ConfirmsDispatch, ShippingCarrier
{
    private const NEXT = [
        'new' => 'ready_for_approval',
        'accepted' => 'performer_found',
        'performer_found' => 'pickuped',
        'pickuped' => 'delivery_arrived',
        'delivery_arrived' => 'delivered',
    ];

    public function name(): string
    {
        return 'yango';
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.yango.enabled');
    }

    public function quote(QuoteRequest $request): QuoteResult
    {
        // A flat ₵30 plus ₵5 per item: plausible, and obviously not real.
        $price = 3_000 + 500 * array_sum(array_column($request->lines, 'quantity'));

        return new QuoteResult($request->zone, [new QuoteOption(
            id: QuoteOption::idFor($this->name(), 'courier'),
            carrier: $this->name(),
            serviceCode: 'courier',
            label: 'Courier to your door in Accra',
            amountGhs: $price,
            source: QuoteOption::SOURCE_LIVE,
            rawAmount: $price,
            rawCurrency: 'GHS',
            eta: DeliveryEstimates::for('GH'),
        )]);
    }

    public function dispatch(Shipment $shipment): DispatchResult
    {
        return new DispatchResult(
            status: ShipmentStatus::Booking,
            carrierReference: $shipment->carrier_reference ?? 'fake'.Str::lower(Str::random(28)),
            carrierStatus: $shipment->carrier_status ?? 'new',
            carrierVersion: 1,
        );
    }

    public function confirm(Shipment $shipment): DispatchResult
    {
        return new DispatchResult(
            status: ShipmentStatus::Booking,
            carrierReference: $shipment->carrier_reference,
            carrierStatus: 'accepted',
            carrierVersion: ($shipment->carrier_version ?? 1) + 1,
        );
    }

    public function status(Shipment $shipment): StatusResult
    {
        $current = $shipment->carrier_status ?? 'new';
        $noCourier = str_contains(strtoupper((string) ($shipment->order->shipping_address['line1'] ?? '')), 'NOCOURIER');
        $next = $noCourier && $current === 'accepted' ? 'performer_not_found' : (self::NEXT[$current] ?? $current);
        $status = YangoStatusMap::canonical($next, $shipment->status);

        return new StatusResult(
            status: $status,
            carrierStatus: $next,
            carrierVersion: $shipment->carrier_version,
            trackingUrl: $status->hasShipped()
                ? config('app.storefront_url').'/order-confirmation/'.$shipment->order->reference
                : null,
            actualCost: $status === ShipmentStatus::Delivered ? $shipment->quoted_cost : null,
            actualCurrency: $status === ShipmentStatus::Delivered && $shipment->quoted_cost !== null ? 'GHS' : null,
            needsConfirmation: YangoStatusMap::needsConfirmation($next),
            noCourier: YangoStatusMap::noCourier($next),
        );
    }

    public function cancel(Shipment $shipment): CancelResult
    {
        return new CancelResult(cancelled: true, carrierStatus: 'cancelled');
    }
}

<?php

namespace App\Services\Shipping\Carriers;

use App\Contracts\ShippingCarrier;
use App\Enums\ShipmentStatus;
use App\Exceptions\ShippingDataMissingException;
use App\Models\Shipment;
use App\Services\Delivery\DeliveryEstimates;
use App\Services\Shipping\CancelResult;
use App\Services\Shipping\DispatchResult;
use App\Services\Shipping\QuoteOption;
use App\Services\Shipping\QuoteRequest;
use App\Services\Shipping\QuoteResult;
use App\Services\Shipping\StatusResult;

/**
 * DHL without DHL, for running the international flow with no credentials,
 * outside production only. It enforces the same customs-data rule as the
 * real driver, so a product missing an HS code is caught locally too.
 *
 * Tracking walks forward one checkpoint per poll: picked up, with the
 * delivery courier, delivered.
 */
class FakeDhlCarrier implements ShippingCarrier
{
    private const NEXT = [null => 'PU', 'PU' => 'WC', 'WC' => 'OK'];

    public function name(): string
    {
        return 'dhl';
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.dhl.enabled');
    }

    public function quote(QuoteRequest $request): QuoteResult
    {
        if ($missing = DhlExpressCarrier::missingCustomsData($request->lines)) {
            throw new ShippingDataMissingException($missing);
        }

        $kilos = DhlExpressCarrier::package($request->lines)['weight'];
        $base = 30_000 + (int) ceil($kilos * 8_000);
        $eta = DeliveryEstimates::for($request->address['country'] ?? null);

        return new QuoteResult($request->zone, [
            new QuoteOption(id: 'dhl:P', carrier: 'dhl', serviceCode: 'P', label: 'DHL Express Worldwide',
                amountGhs: $base, source: QuoteOption::SOURCE_LIVE, rawAmount: $base, rawCurrency: 'GHS', eta: $eta),
            new QuoteOption(id: 'dhl:Y', carrier: 'dhl', serviceCode: 'Y', label: 'DHL Express 12:00',
                amountGhs: (int) ($base * 1.4), source: QuoteOption::SOURCE_LIVE, rawAmount: (int) ($base * 1.4), rawCurrency: 'GHS', eta: $eta),
        ]);
    }

    public function dispatch(Shipment $shipment): DispatchResult
    {
        $awb = $shipment->carrier_reference ?? (string) random_int(1_000_000_000, 9_999_999_999);
        // A real, if blank, one-page PDF, so admin's download link works.
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj "
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 288 432]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

        return new DispatchResult(
            status: ShipmentStatus::AwaitingPickup,
            carrierReference: $awb,
            trackingUrl: "https://www.dhl.com/gh-en/home/tracking.html?tracking-id={$awb}",
            pickupReference: 'FAKE-PICKUP-'.$awb,
            documents: $shipment->carrier_reference ? [] : ['label' => $pdf, 'invoice' => $pdf],
        );
    }

    public function status(Shipment $shipment): StatusResult
    {
        $current = $shipment->carrier_status ? strtok($shipment->carrier_status, ' ') : null;
        $next = self::NEXT[$current] ?? $current;
        [$status] = DhlStatusMap::fromEvents([['typeCode' => $next, 'date' => '2026-01-01', 'time' => '00:00:00']], $shipment->status);

        return new StatusResult(status: $status, carrierStatus: $next);
    }

    public function cancel(Shipment $shipment): CancelResult
    {
        return new CancelResult(cancelled: true, message: 'Fake pickup cancelled.');
    }
}

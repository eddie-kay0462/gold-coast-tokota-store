<?php

namespace App\Services\Shipping\Carriers;

use App\Contracts\ConfirmsDispatch;
use App\Contracts\ShippingCarrier;
use App\Enums\ShipmentStatus;
use App\Exceptions\ShippingQuoteUnavailableException;
use App\Exceptions\YangoAcceptBlockedException;
use App\Models\Shipment;
use App\Services\Delivery\DeliveryEstimates;
use App\Services\Shipping\CancelResult;
use App\Services\Shipping\DispatchResult;
use App\Services\Shipping\QuoteOption;
use App\Services\Shipping\QuoteRequest;
use App\Services\Shipping\QuoteResult;
use App\Services\Shipping\StatusResult;
use App\Support\MinorUnits;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;

/**
 * Yango Delivery, the Accra zone's on-demand courier.
 *
 * Built against the Express API reference
 * (yango-delivery.viewer.diplodoc.yandex.ru/en/api/express): every path and
 * field below is as documented there. A booking is two steps — a claim is
 * created and priced, then accepted — and only acceptance sends a courier.
 *
 *   quote()    POST check-price            at checkout
 *   dispatch() POST claims/create          on "Mark as packed", idempotent
 *                                          through request_id
 *   confirm()  POST claims/accept          once the claim is ready_for_approval;
 *                                          **production only**
 *   status()   POST claims/info            by the poller
 *              GET  claims/tracking-links  once a courier is assigned
 *   cancel()   POST claims/cancel-info, then claims/cancel
 *
 * Coordinates are [longitude, latitude] throughout, as the API requires.
 * Prices come back as Decimal(18,4) strings and are parsed without floats.
 */
class YangoCarrier implements ConfirmsDispatch, ShippingCarrier
{
    private const PREFIX = '/b2b/cargo/integration/v2/';

    public static function isConfigured(): bool
    {
        return filled(config('services.yango.key'));
    }

    public function name(): string
    {
        return 'yango';
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.yango.enabled') && self::isConfigured();
    }

    public function quote(QuoteRequest $request): QuoteResult
    {
        [$originLng, $originLat] = $this->originCoordinates();

        if ($request->lat() === null || $request->lng() === null) {
            throw new ShippingQuoteUnavailableException('Yango needs a delivery pin to quote.');
        }

        $response = $this->send('post', 'check-price', body: [
            'route_points' => [
                ['coordinates' => [$originLng, $originLat], 'fullname' => $this->originAddress()],
                ['coordinates' => [$request->lng(), $request->lat()], 'fullname' => $this->addressLine($request->address)],
            ],
            'items' => array_map(fn (array $line) => [
                'quantity' => $line['quantity'],
                ...$this->physical($line),
            ], $request->lines),
            'requirements' => ['taxi_class' => $this->taxiClass()],
        ]);

        if ($response->failed()) {
            // 409 is "no suitable vehicles"; 400 an address Yango can't route.
            throw new ShippingQuoteUnavailableException(
                'Yango could not price this delivery: '.($response->json('message') ?? $response->status()),
            );
        }

        $currency = (string) $response->json('currency_rules.code');

        if ($currency !== 'GHS') {
            throw new ShippingQuoteUnavailableException("Yango quoted in {$currency}, not GHS.");
        }

        $price = MinorUnits::fromDecimal((string) $response->json('price'));

        return new QuoteResult($request->zone, [new QuoteOption(
            id: QuoteOption::idFor($this->name(), $this->taxiClass()),
            carrier: $this->name(),
            serviceCode: $this->taxiClass(),
            label: 'Courier to your door in Accra',
            amountGhs: $price,
            source: QuoteOption::SOURCE_LIVE,
            rawAmount: $price,
            rawCurrency: 'GHS',
            eta: DeliveryEstimates::for('GH'),
        )]);
    }

    /**
     * Creates the claim. Safe to repeat: the request_id is the shipment's
     * idempotency key, so a retried call returns the same claim rather than
     * ordering a second courier, and a shipment that already has a claim
     * never asks for another.
     */
    public function dispatch(Shipment $shipment): DispatchResult
    {
        if ($shipment->carrier_reference !== null) {
            return new DispatchResult(
                status: $shipment->status,
                carrierReference: $shipment->carrier_reference,
                carrierStatus: $shipment->carrier_status,
                carrierVersion: $shipment->carrier_version,
            );
        }

        $payload = $this->claimPayload($shipment);
        self::assertNeverAutoAccepts($payload);

        $response = $this->send('post', 'claims/create', query: ['request_id' => $shipment->idempotencyKey()], body: $payload);
        $this->throwIfFailed($response, 'create the claim');

        return new DispatchResult(
            status: ShipmentStatus::Booking,
            carrierReference: (string) $response->json('id'),
            carrierStatus: $response->json('status'),
            carrierVersion: $response->json('version'),
            payload: $response->json(),
        );
    }

    /**
     * Accepts the priced claim — the call that actually sends a courier.
     *
     * **The environment check is the first line and has no override.** There
     * is no Yango sandbox, so outside production this refuses before any
     * request is built.
     */
    public function confirm(Shipment $shipment): DispatchResult
    {
        if (! app()->environment('production')) {
            throw new YangoAcceptBlockedException;
        }

        $response = $this->send('post', 'claims/accept', query: ['claim_id' => $shipment->carrier_reference], body: [
            'version' => $shipment->carrier_version ?? 1,
        ]);
        $this->throwIfFailed($response, 'accept the claim');

        return new DispatchResult(
            status: ShipmentStatus::Booking,
            carrierReference: $shipment->carrier_reference,
            carrierStatus: $response->json('status'),
            carrierVersion: $response->json('version'),
            payload: $response->json(),
        );
    }

    public function status(Shipment $shipment): StatusResult
    {
        $response = $this->send('post', 'claims/info', query: ['claim_id' => $shipment->carrier_reference]);
        $this->throwIfFailed($response, 'read the claim');

        $raw = (string) $response->json('status');
        $status = YangoStatusMap::canonical($raw, $shipment->status);

        $finalPrice = $response->json('pricing.final_price');
        $currency = $response->json('pricing.currency');

        return new StatusResult(
            status: $status,
            carrierStatus: $raw,
            carrierVersion: $response->json('version'),
            trackingUrl: $shipment->tracking_url ?? ($status->hasShipped() ? $this->trackingLink($shipment) : null),
            actualCost: is_string($finalPrice) && $finalPrice !== '' ? MinorUnits::fromDecimal($finalPrice) : null,
            actualCurrency: is_string($finalPrice) && $finalPrice !== '' ? $currency : null,
            needsConfirmation: YangoStatusMap::needsConfirmation($raw),
            noCourier: YangoStatusMap::noCourier($raw),
            payload: $response->json(),
        );
    }

    public function cancel(Shipment $shipment): CancelResult
    {
        if ($shipment->carrier_reference === null) {
            return new CancelResult(cancelled: true, message: 'No claim had been created.');
        }

        $info = $this->send('post', 'claims/cancel-info', query: ['claim_id' => $shipment->carrier_reference]);
        $this->throwIfFailed($info, 'check the cancellation terms');

        $state = (string) $info->json('cancel_state');

        if ($state === 'unavailable') {
            return new CancelResult(cancelled: false, carrierStatus: $shipment->carrier_status,
                message: 'Yango no longer allows this claim to be cancelled.', payload: $info->json());
        }

        $response = $this->send('post', 'claims/cancel', query: ['claim_id' => $shipment->carrier_reference], body: [
            'version' => $shipment->carrier_version ?? 1,
            'cancel_state' => $state,
        ]);
        $this->throwIfFailed($response, 'cancel the claim');

        return new CancelResult(
            cancelled: true,
            charged: $state === 'paid',
            carrierStatus: $response->json('status'),
            payload: $response->json(),
        );
    }

    /**
     * claims/create documents `auto_accept`, which would book a courier the
     * moment the claim is priced and bypass confirm()'s production guard.
     * It is never sent, and this makes sure no future edit sends it.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function assertNeverAutoAccepts(array $payload): void
    {
        if (! empty($payload['auto_accept'])) {
            throw new LogicException('A Yango claim must never be auto-accepted.');
        }
    }

    /** @return array<string, mixed> */
    private function claimPayload(Shipment $shipment): array
    {
        $order = $shipment->order()->with('items')->first();
        $address = $order->shipping_address ?? [];
        [$originLng, $originLat] = $this->originCoordinates();

        if (! is_numeric($address['lat'] ?? null) || ! is_numeric($address['lng'] ?? null)) {
            throw new RuntimeException("Order {$order->reference} has no delivery pin for Yango.");
        }

        $defaults = (array) config('shipping.default_parcel');

        return [
            'route_points' => [
                [
                    'point_id' => 1,
                    'visit_order' => 1,
                    'type' => 'source',
                    'contact' => [
                        'name' => (string) config('shipping.origin.name'),
                        'phone' => (string) config('shipping.origin.phone'),
                        'email' => (string) config('shipping.origin.email'),
                    ],
                    'address' => [
                        'fullname' => $this->originAddress(),
                        'coordinates' => [$originLng, $originLat],
                    ],
                ],
                [
                    'point_id' => 2,
                    'visit_order' => 2,
                    'type' => 'destination',
                    'contact' => [
                        'name' => (string) ($address['full_name'] ?? $order->customer?->name ?? 'Customer'),
                        'phone' => (string) ($address['phone'] ?? $order->customer?->phone ?? ''),
                    ],
                    'address' => [
                        'fullname' => $this->addressLine($address),
                        'coordinates' => [(float) $address['lng'], (float) $address['lat']],
                    ],
                    'external_order_id' => $order->reference,
                ],
            ],
            'items' => $order->items->map(fn ($item) => [
                'title' => trim($item->product_name.' '.($item->variant_label ?? '')),
                'pickup_point' => 1,
                // Spelled as the API spells it.
                'droppof_point' => 2,
                'quantity' => $item->quantity,
                'cost_value' => MinorUnits::toDecimal($item->unit_price),
                'cost_currency' => $item->currency,
                ...$this->physical([
                    'weight_grams' => $item->product?->getAttribute('weight_grams') ?? $defaults['weight_grams'],
                    'length_mm' => $item->product?->getAttribute('length_mm') ?? $defaults['length_mm'],
                    'width_mm' => $item->product?->getAttribute('width_mm') ?? $defaults['width_mm'],
                    'height_mm' => $item->product?->getAttribute('height_mm') ?? $defaults['height_mm'],
                ]),
            ])->values()->all(),
            'client_requirements' => ['taxi_class' => $this->taxiClass()],
            'emergency_contact' => [
                'name' => (string) config('shipping.origin.name'),
                'phone' => (string) config('shipping.origin.phone'),
            ],
            'comment' => "Gold Coast Tokota order {$order->reference}",
        ];
    }

    /**
     * Weight in kg and size in metres, as Yango wants them, from grams and
     * millimetres, falling back to the default parcel.
     *
     * @param  array<string, mixed>  $line
     * @return array{weight: float, size: array{length: float, width: float, height: float}}
     */
    private function physical(array $line): array
    {
        $defaults = (array) config('shipping.default_parcel');
        $mm = fn (string $key) => round(((int) ($line[$key] ?? $defaults[$key])) / 1000, 3);

        return [
            'weight' => round(((int) ($line['weight_grams'] ?? $defaults['weight_grams'])) / 1000, 3),
            'size' => ['length' => $mm('length_mm'), 'width' => $mm('width_mm'), 'height' => $mm('height_mm')],
        ];
    }

    /** The courier sees the pin; this line is for the courier's eyes. */
    private function addressLine(array $address): string
    {
        return implode(', ', array_filter([
            $address['line1'] ?? null,
            $address['city'] ?? null,
            $address['region'] ?? null,
            'Ghana',
        ]));
    }

    private function originAddress(): string
    {
        return implode(', ', array_filter([
            config('shipping.origin.address'),
            config('shipping.origin.city'),
            'Ghana',
        ]));
    }

    /** @return array{0: float, 1: float} [lng, lat] */
    private function originCoordinates(): array
    {
        $lat = config('shipping.origin.lat');
        $lng = config('shipping.origin.lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            throw new ShippingQuoteUnavailableException('SHIPPING_ORIGIN_LAT and SHIPPING_ORIGIN_LNG are not set.');
        }

        return [(float) $lng, (float) $lat];
    }

    private function taxiClass(): string
    {
        return (string) config('shipping.yango.taxi_class', 'courier');
    }

    /** The destination's courier-tracking link, once one exists. */
    private function trackingLink(Shipment $shipment): ?string
    {
        $response = $this->send('get', 'claims/tracking-links', query: ['claim_id' => $shipment->carrier_reference]);

        // 409 means no link yet ("inactive claim or no courier tracking
        // links"); the next poll will ask again.
        if ($response->failed()) {
            return null;
        }

        foreach ((array) $response->json('route_points') as $point) {
            if (($point['type'] ?? null) === 'destination' && filled($point['sharing_link'] ?? null)) {
                return $point['sharing_link'];
            }
        }

        return null;
    }

    /**
     * Every call goes through here: bearer token, English messages, a short
     * timeout, retries on connection failures only (a 4xx is an answer, not a
     * blip), and a log line per call so a booking can be traced afterwards.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function send(string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $url = rtrim((string) config('services.yango.base_url'), '/').self::PREFIX.$path
            .($query ? '?'.http_build_query($query) : '');

        $client = Http::withToken((string) config('services.yango.key'))
            ->acceptJson()
            ->withHeaders(['Accept-Language' => 'en'])
            ->timeout(15)
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);

        $response = $method === 'get'
            ? $client->get($url)
            : $client->asJson()->post($url, $body ?? (object) []);

        Log::info('Yango API call.', [
            'path' => $path,
            'claim_id' => $query['claim_id'] ?? null,
            'request_id' => $query['request_id'] ?? null,
            'status' => $response->status(),
            'code' => $response->failed() ? $response->json('code') : null,
        ]);

        return $response;
    }

    private function throwIfFailed(Response $response, string $what): void
    {
        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Yango refused to %s (%d %s): %s',
                $what,
                $response->status(),
                $response->json('code') ?? '',
                $response->json('message') ?? $response->body(),
            ));
        }
    }
}

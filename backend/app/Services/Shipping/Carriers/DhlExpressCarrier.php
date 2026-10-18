<?php

namespace App\Services\Shipping\Carriers;

use App\Contracts\ShippingCarrier;
use App\Enums\ShipmentStatus;
use App\Exceptions\ShipmentBookingFailedException;
use App\Exceptions\ShippingDataMissingException;
use App\Exceptions\ShippingQuoteUnavailableException;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Currency\FxRateService;
use App\Services\Delivery\DeliveryEstimates;
use App\Services\Shipping\CancelResult;
use App\Services\Shipping\DispatchResult;
use App\Services\Shipping\QuoteOption;
use App\Services\Shipping\QuoteRequest;
use App\Services\Shipping\QuoteResult;
use App\Services\Shipping\StatusResult;
use App\Support\MinorUnits;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DHL Express (MyDHL API), the International zone's courier.
 *
 *   quote()    POST /rates          at checkout, plus POST /landed-cost when
 *                                   the duties estimate is switched on
 *   dispatch() POST /shipments      on "Mark as packed": the AWB, the label
 *                                   and commercial invoice, and the pickup,
 *                                   in one call
 *   status()   GET  /shipments/{awb}/tracking
 *   cancel()   DELETE /pickups/{dispatchConfirmationNumber}
 *
 * **Payload shapes are not verified against DHL's own spec**, which only
 * renders in a browser. Paths, the base URLs and Basic auth are from DHL's
 * developer portal. The bodies follow the open-source sonnenglas/mydhl-php-sdk
 * (built against spec 3.3.1), and so does the `x-version` header DHL
 * requires. Check them against the test environment before switching DHL on;
 * the tests in tests/Feature/Shipping/DhlExpressCarrierTest.php pin what is
 * sent, so a correction shows up there.
 *
 * Shipments go DAP: §8 makes import duties the customer's, paid on arrival.
 */
class DhlExpressCarrier implements ShippingCarrier
{
    public static function isConfigured(): bool
    {
        return filled(config('services.dhl.username'))
            && filled(config('services.dhl.password'))
            && filled(config('services.dhl.account_number'));
    }

    public function name(): string
    {
        return 'dhl';
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.dhl.enabled') && self::isConfigured();
    }

    /**
     * Products missing what DHL and customs need. A cart with any of these
     * cannot be quoted, booked or cleared.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<string>
     */
    public static function missingCustomsData(array $lines): array
    {
        $missing = [];

        foreach ($lines as $line) {
            foreach (['weight_grams', 'length_mm', 'width_mm', 'height_mm', 'hs_code'] as $field) {
                if (blank($line[$field] ?? null)) {
                    $missing[] = $line['name'];

                    continue 2;
                }
            }
        }

        return array_values(array_unique($missing));
    }

    public function quote(QuoteRequest $request): QuoteResult
    {
        if ($missing = self::missingCustomsData($request->lines)) {
            throw new ShippingDataMissingException($missing);
        }

        $response = $this->send('post', 'rates', body: [
            'customerDetails' => [
                'shipperDetails' => $this->rateAddress($this->origin()),
                'receiverDetails' => $this->rateAddress($request->address),
            ],
            'accounts' => $this->accounts(),
            'plannedShippingDateAndTime' => self::plannedShippingDate(),
            'unitOfMeasurement' => 'metric',
            'isCustomsDeclarable' => true,
            'monetaryAmount' => [[
                'typeCode' => 'declaredValue',
                'value' => (float) MinorUnits::toDecimal($request->subtotalGhs),
                'currency' => 'GHS',
            ]],
            'packages' => [self::package($request->lines)],
        ]);

        if ($response->failed()) {
            throw new ShippingQuoteUnavailableException('DHL could not rate this shipment: '.$this->problem($response));
        }

        $allowed = (array) config('shipping.dhl.products', []);
        $options = [];

        foreach ((array) $response->json('products') as $product) {
            $code = (string) ($product['productCode'] ?? '');

            if ($code === '' || ($allowed !== [] && ! in_array($code, $allowed, true))) {
                continue;
            }

            [$amount, $currency] = $this->billedPrice((array) ($product['totalPrice'] ?? []));

            if ($amount === null) {
                continue;
            }

            $ghs = $this->toGhs($amount, $currency);

            $options[] = new QuoteOption(
                id: QuoteOption::idFor($this->name(), $code),
                carrier: $this->name(),
                serviceCode: $code,
                label: 'DHL '.Str::title(strtolower((string) ($product['productName'] ?? 'Express'))),
                amountGhs: $ghs,
                source: QuoteOption::SOURCE_LIVE,
                rawAmount: $amount,
                rawCurrency: $currency,
                eta: DeliveryEstimates::for($request->address['country'] ?? null),
            );
        }

        if ($options === []) {
            throw new ShippingQuoteUnavailableException('DHL returned no product for this destination.');
        }

        usort($options, fn (QuoteOption $a, QuoteOption $b) => $a->amountGhs <=> $b->amountGhs);

        return new QuoteResult($request->zone, $options, $this->landedCost($request));
    }

    /**
     * Books the shipment and, unless switched off, its pickup. Never repeated
     * for a shipment that already has an AWB; and because DHL has no
     * idempotency key, any failure — a timeout above all, which may have
     * created the shipment anyway — goes to a person instead of a retry.
     */
    public function dispatch(Shipment $shipment): DispatchResult
    {
        if ($shipment->carrier_reference !== null) {
            return new DispatchResult(status: $shipment->status, carrierReference: $shipment->carrier_reference,
                trackingUrl: $shipment->tracking_url, pickupReference: $shipment->pickup_reference);
        }

        $order = $shipment->order()->with('items.product')->first();
        $payload = $this->shipmentPayload($order, $shipment->service_code ?? 'P');

        try {
            $response = $this->send('post', 'shipments', body: $payload, retry: false);
        } catch (ConnectionException $e) {
            throw new ShipmentBookingFailedException(
                "DHL did not answer ({$e->getMessage()}). It may have created the shipment anyway: check MyDHL for order {$order->reference} before booking again.",
            );
        }

        if ($response->failed()) {
            throw new ShipmentBookingFailedException('DHL refused the shipment: '.$this->problem($response));
        }

        $documents = [];
        foreach ((array) $response->json('documents') as $document) {
            $kind = $document['typeCode'] ?? null;

            if (in_array($kind, ['label', 'invoice'], true) && isset($document['content'])) {
                $documents[$kind] = (string) base64_decode((string) $document['content'], true);
            }
        }

        return new DispatchResult(
            status: ShipmentStatus::AwaitingPickup,
            carrierReference: (string) $response->json('shipmentTrackingNumber'),
            trackingUrl: $response->json('trackingUrl'),
            pickupReference: $response->json('dispatchConfirmationNumber'),
            documents: $documents,
            payload: collect($response->json())->except('documents')->all(),
        );
    }

    public function status(Shipment $shipment): StatusResult
    {
        $response = $this->send('get', 'shipments/'.rawurlencode((string) $shipment->carrier_reference).'/tracking', query: [
            'trackingView' => 'all-checkpoints',
            'levelOfDetail' => 'shipment',
        ]);

        if ($response->status() === 404) {
            // Tracking only appears once DHL has scanned the parcel.
            return new StatusResult(status: $shipment->status, carrierStatus: $shipment->carrier_status);
        }

        if ($response->failed()) {
            throw new RuntimeException('DHL tracking failed: '.$this->problem($response));
        }

        $events = (array) ($response->json('shipments.0.events') ?? []);
        [$status, $code, $description] = DhlStatusMap::fromEvents($events, $shipment->status);

        return new StatusResult(
            status: $status,
            carrierStatus: $code ? trim($code.' '.$description) : $shipment->carrier_status,
            payload: $response->json(),
        );
    }

    /**
     * A DHL shipment is not cancelled through the API; what can be called off
     * is its pickup. The label then simply goes unused.
     */
    public function cancel(Shipment $shipment): CancelResult
    {
        if ($shipment->pickup_reference === null) {
            return new CancelResult(cancelled: true, message: 'No pickup was booked. Void the label in MyDHL if it will not be used.');
        }

        $response = $this->send('delete', 'pickups/'.rawurlencode($shipment->pickup_reference), query: [
            'requestorName' => (string) config('shipping.origin.name'),
            'reason' => 'not needed',
        ]);

        return new CancelResult(
            cancelled: $response->successful(),
            message: $response->successful()
                ? 'Pickup cancelled. Void the label in MyDHL if it will not be used.'
                : 'DHL would not cancel the pickup: '.$this->problem($response),
        );
    }

    /** @return array<string, mixed> */
    private function shipmentPayload(Order $order, string $productCode): array
    {
        $address = $order->shipping_address ?? [];
        $lines = $order->items->map(fn ($item) => [
            'name' => $item->product_name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'weight_grams' => $item->product?->weight_grams,
            'length_mm' => $item->product?->length_mm,
            'width_mm' => $item->product?->width_mm,
            'height_mm' => $item->product?->height_mm,
            'hs_code' => $item->product?->hs_code,
            'origin_country' => $item->product?->origin_country ?? 'GH',
            'customs_description' => $item->product?->customs_description,
        ])->all();

        if ($missing = self::missingCustomsData($lines)) {
            throw new ShipmentBookingFailedException('Missing weight, size or HS code for: '.implode(', ', $missing).'. Add them on the product, then dispatch by hand or re-pack.');
        }

        $origin = $this->origin();
        $goodsTotal = array_sum(array_map(fn ($line) => $line['unit_price'] * $line['quantity'], $lines));

        $payload = [
            'plannedShippingDateAndTime' => self::plannedShippingDate(),
            'accounts' => $this->accounts(),
            'productCode' => $productCode,
            'customerDetails' => [
                'shipperDetails' => [
                    'postalAddress' => $this->postalAddress($origin),
                    'contactInformation' => [
                        'phone' => (string) config('shipping.origin.phone'),
                        'companyName' => (string) config('shipping.origin.name'),
                        'fullName' => (string) config('shipping.origin.name'),
                        'email' => (string) config('shipping.origin.email'),
                    ],
                ],
                'receiverDetails' => [
                    'postalAddress' => $this->postalAddress($address),
                    'contactInformation' => array_filter([
                        'phone' => (string) ($address['phone'] ?? ''),
                        'companyName' => (string) ($address['full_name'] ?? 'Customer'),
                        'fullName' => (string) ($address['full_name'] ?? 'Customer'),
                        'email' => $address['email'] ?? null,
                    ]),
                ],
            ],
            'content' => [
                'packages' => [self::package($lines)],
                'isCustomsDeclarable' => true,
                'declaredValue' => (float) MinorUnits::toDecimal($goodsTotal),
                'declaredValueCurrency' => $order->currency,
                'description' => 'Handmade sandals',
                'incoterm' => (string) config('shipping.dhl.incoterm', 'DAP'),
                'unitOfMeasurement' => 'metric',
                'exportDeclaration' => [
                    'lineItems' => array_values(array_map(fn (array $line, int $i) => [
                        'number' => $i + 1,
                        'description' => $line['customs_description'] ?: $line['name'],
                        'price' => (float) MinorUnits::toDecimal($line['unit_price']),
                        'quantity' => ['value' => $line['quantity'], 'unitOfMeasurement' => 'PCS'],
                        'commodityCodes' => [['typeCode' => 'outbound', 'value' => $line['hs_code']]],
                        'exportReasonType' => 'permanent',
                        'manufacturerCountry' => strtoupper((string) $line['origin_country']),
                        'weight' => [
                            'netValue' => round($line['weight_grams'] * $line['quantity'] / 1000, 3),
                            'grossValue' => round($line['weight_grams'] * $line['quantity'] / 1000, 3),
                        ],
                    ], $lines, array_keys($lines))),
                    'invoice' => ['number' => $order->reference, 'date' => now()->format('Y-m-d')],
                    'exportReason' => 'sale',
                    'shipmentType' => 'commercial',
                ],
            ],
            'outputImageProperties' => [
                'encodingFormat' => 'pdf',
                'imageOptions' => [
                    ['typeCode' => 'label'],
                    ['typeCode' => 'invoice', 'invoiceType' => 'commercial', 'isRequested' => true],
                ],
            ],
            'pickup' => ['isRequested' => false],
        ];

        if (config('shipping.dhl.request_pickup')) {
            $payload['pickup'] = [
                'isRequested' => true,
                'closeTime' => (string) config('shipping.dhl.pickup_close_time'),
                'location' => (string) config('shipping.dhl.pickup_location'),
            ];
        }

        return $payload;
    }

    /**
     * Estimated duties and taxes, for display only (DAP: the customer pays
     * them on arrival). Off by default; any failure just means no estimate.
     *
     * @return array{amount: int, currency: string}|null
     */
    private function landedCost(QuoteRequest $request): ?array
    {
        if (! config('shipping.dhl.show_landed_cost')) {
            return null;
        }

        try {
            $response = $this->send('post', 'landed-cost', body: [
                'customerDetails' => [
                    'shipperDetails' => $this->rateAddress($this->origin()),
                    'receiverDetails' => $this->rateAddress($request->address),
                ],
                'accounts' => $this->accounts(),
                'unitOfMeasurement' => 'metric',
                'currencyCode' => 'GHS',
                'isCustomsDeclarable' => true,
                'isDTPRequested' => false,
                'isInsuranceRequested' => false,
                'getCostBreakdown' => true,
                'packages' => [self::package($request->lines)],
                'items' => array_values(array_map(fn (array $line, int $i) => [
                    'number' => $i + 1,
                    'name' => $line['name'],
                    'description' => $line['customs_description'] ?: $line['name'],
                    'manufacturerCountry' => strtoupper((string) ($line['origin_country'] ?? 'GH')),
                    'quantity' => $line['quantity'],
                    'quantityType' => 'pcs',
                    'unitPrice' => (float) MinorUnits::toDecimal($line['unit_price_ghs']),
                    'unitPriceCurrencyCode' => 'GHS',
                    'commodityCode' => $line['hs_code'],
                    'weight' => round($line['weight_grams'] / 1000, 3),
                    'weightUnitOfMeasurement' => 'metric',
                ], $request->lines, array_keys($request->lines))),
            ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        // Duty and tax charges across the items' breakdowns. The charge type
        // codes are the unverified part of this whole integration.
        $total = 0;
        $currency = 'GHS';

        foreach ((array) $response->json('products.0.items') as $item) {
            foreach ((array) ($item['breakdown'] ?? []) as $charge) {
                if (in_array(strtoupper((string) ($charge['typeCode'] ?? '')), ['DUTY', 'TAX'], true) && isset($charge['price'])) {
                    $total += MinorUnits::fromDecimal(number_format((float) $charge['price'], 2, '.', ''));
                    $currency = (string) ($charge['priceCurrency'] ?? $currency);
                }
            }
        }

        return $total > 0 && $currency === 'GHS' ? ['amount' => $total, 'currency' => 'GHS'] : null;
    }

    /**
     * One package for the order: total weight, the largest footprint, the
     * boxes' heights stacked. Kilograms and centimetres.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{weight: float, dimensions: array{length: int, width: int, height: int}}
     */
    public static function package(array $lines): array
    {
        $grams = 0;
        $length = $width = $height = 0;

        foreach ($lines as $line) {
            $quantity = (int) $line['quantity'];
            $grams += (int) $line['weight_grams'] * $quantity;
            $length = max($length, (int) $line['length_mm']);
            $width = max($width, (int) $line['width_mm']);
            $height += (int) $line['height_mm'] * $quantity;
        }

        $cm = fn (int $mm) => max(1, (int) ceil($mm / 10));

        return [
            'weight' => max(0.1, round($grams / 1000, 3)),
            'dimensions' => ['length' => $cm($length), 'width' => $cm($width), 'height' => $cm($height)],
        ];
    }

    /**
     * When DHL should expect the parcel: within the hour on a weekday
     * afternoon before three, otherwise the next weekday morning. Ghana keeps
     * GMT all year.
     */
    public static function plannedShippingDate(): string
    {
        $now = CarbonImmutable::now('UTC');
        $at = $now->isWeekday() && $now->hour < 15
            ? $now->addHour()->startOfHour()
            : $now->addDay()->setTime(10, 0);

        while ($at->isWeekend()) {
            $at = $at->addDay();
        }

        return $at->format('Y-m-d\TH:i:s \G\M\TP');
    }

    /** @return list<array{typeCode: string, number: string}> */
    private function accounts(): array
    {
        return [['typeCode' => 'shipper', 'number' => (string) config('services.dhl.account_number')]];
    }

    /** @return array<string, mixed> */
    private function origin(): array
    {
        return [
            'line1' => config('shipping.origin.address'),
            'city' => config('shipping.origin.city'),
            'postcode' => config('shipping.origin.postcode'),
            'country' => 'GH',
        ];
    }

    /** @param  array<string, mixed>  $address */
    private function rateAddress(array $address): array
    {
        return [
            'postalCode' => (string) ($address['postcode'] ?? ''),
            'cityName' => (string) ($address['city'] ?? ''),
            'countryCode' => strtoupper((string) ($address['country'] ?? '')),
        ];
    }

    /** @param  array<string, mixed>  $address */
    private function postalAddress(array $address): array
    {
        return array_filter([
            'addressLine1' => (string) ($address['line1'] ?? ''),
            'cityName' => (string) ($address['city'] ?? ''),
            'postalCode' => (string) ($address['postcode'] ?? ''),
            'countryCode' => strtoupper((string) ($address['country'] ?? '')),
        ], fn ($value, $key) => $value !== '' || $key === 'postalCode', ARRAY_FILTER_USE_BOTH);
    }

    /**
     * The billing-currency price (`currencyType: BILLC`) from a product's
     * totalPrice list, in minor units.
     *
     * @param  list<array<string, mixed>>  $prices
     * @return array{0: int|null, 1: string|null}
     */
    private function billedPrice(array $prices): array
    {
        foreach ($prices as $price) {
            if (($price['currencyType'] ?? null) === 'BILLC' && isset($price['price'], $price['priceCurrency'])) {
                return [
                    MinorUnits::fromDecimal(number_format((float) $price['price'], 2, '.', '')),
                    strtoupper((string) $price['priceCurrency']),
                ];
            }
        }

        return [null, null];
    }

    /**
     * The account bills in GHS if it is a Ghana account, but that is not yet
     * confirmed. A USD price is converted at today's usable rate; anything
     * else is not trusted, and checkout charges the fallback instead.
     */
    private function toGhs(int $amount, string $currency): int
    {
        if ($currency === 'GHS') {
            return $amount;
        }

        $rate = $currency === 'USD' ? app(FxRateService::class)->getUsableRate()?->rate : null;

        if ($rate === null || (float) $rate <= 0) {
            throw new ShippingQuoteUnavailableException("DHL quoted in {$currency}, which cannot be converted to cedis right now.");
        }

        return (int) ceil($amount / (float) $rate);
    }

    /** DHL errors are RFC 7807 problem documents. */
    private function problem(Response $response): string
    {
        return trim(($response->json('title') ?? '').' '.($response->json('detail') ?? $response->json('message') ?? $response->status()));
    }

    /**
     * Every call goes through here: Basic auth, the version and message
     * reference headers DHL requires, a timeout, a log line. Retries only on
     * connection failure, and never for creating a shipment.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function send(string $method, string $path, array $query = [], ?array $body = null, bool $retry = true): Response
    {
        $url = rtrim((string) config('services.dhl.base_url'), '/').'/'.$path;

        $client = Http::withBasicAuth((string) config('services.dhl.username'), (string) config('services.dhl.password'))
            ->acceptJson()
            ->withHeaders([
                'x-version' => (string) config('services.dhl.api_version'),
                'Message-Reference' => (string) Str::uuid(),
                'Message-Reference-Date' => gmdate('D, d M Y H:i:s \G\M\T'),
            ])
            ->timeout(30);

        if ($retry) {
            $client = $client->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false);
        }

        $response = match ($method) {
            'get' => $client->get($url, $query),
            'delete' => $client->delete($url.($query ? '?'.http_build_query($query) : '')),
            default => $client->asJson()->post($url, $body ?? []),
        };

        Log::info('DHL API call.', ['path' => $path, 'status' => $response->status()]);

        return $response;
    }
}

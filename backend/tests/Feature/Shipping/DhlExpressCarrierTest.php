<?php

namespace Tests\Feature\Shipping;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingZone;
use App\Jobs\DispatchShipment;
use App\Jobs\PollActiveShipments;
use App\Jobs\SendOpsAlert;
use App\Jobs\SendOrderNotification;
use App\Models\AdminUser;
use App\Models\FxRate;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Services\Shipping\ShipmentService;
use App\Services\Shipping\ShippingManager;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The DHL Express driver against Http::fake().
 *
 * Request and response shapes follow sonnenglas/mydhl-php-sdk (spec 3.3.1),
 * not DHL's own spec, which could not be read. When the test environment
 * disagrees, correct the driver and these fixtures together.
 */
class DhlExpressCarrierTest extends TestCase
{
    use RefreshDatabase;

    private const AWB = '1234567890';

    private const LONDON = [
        'full_name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+447700900000',
        'line1' => '5 Bridge Street', 'city' => 'London', 'postcode' => 'SW1A 2JR', 'country' => 'GB',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.dhl.enabled' => true,
            'services.dhl.username' => 'gct-user',
            'services.dhl.password' => 'gct-pass',
            'services.dhl.account_number' => '123456789',
            'services.dhl.base_url' => 'https://express.api.dhl.com/mydhlapi/test',
            'shipping.origin.address' => '1 Haatso Road',
            'shipping.origin.phone' => '+233300000000',
            'shipping.origin.email' => 'workshop@example.com',
            'shipping.quote_buffer_percent' => 10,
            'shipping.ops.email' => 'ops@example.com',
        ]);
        Storage::fake(MediaStorage::privateDiskName());
        Bus::fake([SendOrderNotification::class, SendOpsAlert::class]);
    }

    // --- fixtures ---------------------------------------------------------

    private function rates(string $currency = 'GHS'): array
    {
        $price = fn (float $amount) => [
            ['currencyType' => 'BILLC', 'priceCurrency' => $currency, 'price' => $amount],
            ['currencyType' => 'PULCL', 'priceCurrency' => $currency, 'price' => $amount],
        ];

        return ['products' => [
            ['productName' => 'EXPRESS 12:00', 'productCode' => 'Y', 'localProductCode' => 'Y', 'totalPrice' => $price(520.00)],
            ['productName' => 'EXPRESS WORLDWIDE', 'productCode' => 'P', 'localProductCode' => 'P', 'totalPrice' => $price(412.50)],
        ]];
    }

    private function shipmentCreated(): array
    {
        return [
            'shipmentTrackingNumber' => self::AWB,
            'trackingUrl' => 'https://express.api.dhl.com/mydhlapi/test/shipments/'.self::AWB.'/tracking',
            'dispatchConfirmationNumber' => 'CBJ201220123456',
            'packages' => [['referenceNumber' => 1, 'trackingNumber' => 'JD014600006281230704']],
            'documents' => [
                ['imageFormat' => 'PDF', 'content' => base64_encode('%PDF label'), 'typeCode' => 'label'],
                ['imageFormat' => 'PDF', 'content' => base64_encode('%PDF invoice'), 'typeCode' => 'invoice'],
            ],
        ];
    }

    private function tracking(string ...$codes): array
    {
        $events = [];
        foreach ($codes as $i => $code) {
            $events[] = ['date' => '2026-10-1'.$i, 'time' => '10:00:00', 'typeCode' => $code, 'description' => "Checkpoint {$code}"];
        }

        return ['shipments' => [['shipmentTrackingNumber' => self::AWB, 'status' => 'transit', 'events' => $events]]];
    }

    private function product(bool $shippable = true): Product
    {
        $factory = Product::factory();

        return ($shippable ? $factory->shippable() : $factory)->create(['base_price_ghs' => 60_000]);
    }

    private function quote(Product $product)
    {
        $item = InventoryItem::factory()->create([
            'product_id' => $product->id, 'variant_attributes' => ['size' => '42'],
            'quantity_available' => 5, 'quantity_reserved' => 0,
        ]);

        return $this->postJson('/api/v1/checkout/shipping-quote', [
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
            'shipping_address' => self::LONDON,
        ]);
    }

    private function internationalShipment(array $attributes = [], string $service = 'Y'): Shipment
    {
        $order = Order::factory()->paid()->create([
            'shipping_zone' => ShippingZone::International,
            'shipping_address' => self::LONDON,
            'shipping_option' => ['carrier' => 'dhl', 'service_code' => $service, 'quoted_raw' => 52_000, 'quoted_currency' => 'GHS'],
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id, 'product_id' => $this->product()->id,
            'product_name' => 'Abrantie Collection', 'unit_price' => 60_000, 'quantity' => 2, 'currency' => 'GHS',
        ]);

        return Shipment::factory()->create([
            'order_id' => $order->id, 'zone' => ShippingZone::International,
            'carrier' => 'dhl', 'service_code' => $service, ...$attributes,
        ]);
    }

    private function pack(Shipment $shipment)
    {
        return $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/pack");
    }

    private function poll(): void
    {
        Shipment::query()->update(['next_poll_at' => null]);
        (new PollActiveShipments)->handle();
    }

    // --- quoting -----------------------------------------------------------

    public function test_checkout_lists_dhl_products_cheapest_first_with_the_buffer(): void
    {
        Http::fake(['*/rates' => Http::response($this->rates())]);

        $this->quote($this->product())
            ->assertOk()
            ->assertJsonPath('data.zone', 'international')
            ->assertJsonPath('data.options.0.id', 'dhl:P')
            ->assertJsonPath('data.options.0.label', 'DHL Express Worldwide')
            // 412.50 + 10% = 453.75, rounded up to a whole cedi.
            ->assertJsonPath('data.options.0.amount_ghs', 45_400)
            ->assertJsonPath('data.options.1.id', 'dhl:Y')
            ->assertJsonPath('data.landed_cost', null);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://express.api.dhl.com/mydhlapi/test/rates'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('gct-user:gct-pass'))
            && $r->hasHeader('x-version', '3.3.1')
            && $r->hasHeader('Message-Reference')
            && $r['accounts'] === [['typeCode' => 'shipper', 'number' => '123456789']]
            && $r['customerDetails']['receiverDetails'] === ['postalCode' => 'SW1A 2JR', 'cityName' => 'London', 'countryCode' => 'GB']
            && $r['isCustomsDeclarable'] === true
            && $r['packages'][0] === ['weight' => 0.9, 'dimensions' => ['length' => 32, 'width' => 20, 'height' => 12]]);
    }

    /** No HS code, no export: refused with the product named, not priced. */
    public function test_a_product_missing_customs_data_blocks_international_checkout(): void
    {
        Http::fake();
        $product = $this->product(shippable: false);

        $this->quote($product)
            ->assertStatus(422)
            ->assertJsonPath('blocked_items', [$product->name]);

        Http::assertNothingSent();
    }

    public function test_with_dhl_off_missing_customs_data_does_not_block(): void
    {
        config(['services.dhl.enabled' => false]);

        $this->quote($this->product(shippable: false))
            ->assertOk()
            ->assertJsonPath('data.options.0.carrier', 'manual');
    }

    public function test_a_usd_billing_price_is_converted_at_the_usable_rate(): void
    {
        FxRate::factory()->create(['rate' => 0.08]);
        Http::fake(['*/rates' => Http::response($this->rates('USD'))]);

        // $412.50 at 0.08 = ₵5,156.25, then the buffer.
        $this->quote($this->product())
            ->assertOk()
            ->assertJsonPath('data.options.0.amount_ghs', 567_200);
    }

    public function test_dhl_failing_to_rate_charges_the_fallback(): void
    {
        Http::fake(['*/rates' => Http::response(['title' => 'Bad request', 'detail' => 'Unsupported destination'], 400)]);

        $this->quote($this->product())
            ->assertOk()
            ->assertJsonPath('data.options.0.amount_ghs', 35_000);
    }

    /** Duties are shown, never charged: §8, DAP. */
    public function test_the_duties_estimate_is_shown_but_never_added_to_the_total(): void
    {
        config(['shipping.dhl.show_landed_cost' => true]);
        Http::fake([
            '*/rates' => Http::response($this->rates()),
            '*/landed-cost' => Http::response(['products' => [['items' => [['number' => 1, 'breakdown' => [
                ['name' => 'Duty', 'typeCode' => 'DUTY', 'price' => 72.10, 'priceCurrency' => 'GHS'],
                ['name' => 'VAT', 'typeCode' => 'TAX', 'price' => 133.40, 'priceCurrency' => 'GHS'],
            ]]]]]]),
        ]);

        $quote = $this->quote($this->product())->assertOk()->json('data');

        $this->assertSame(['amount' => 20_550, 'currency' => 'GHS'], $quote['landed_cost']);
        $this->assertSame(45_400, $quote['options'][0]['amount_ghs']);
    }

    // --- booking -----------------------------------------------------------

    public function test_packing_books_the_shipment_label_invoice_and_pickup_in_one_call(): void
    {
        Http::fake(['*/shipments' => Http::response($this->shipmentCreated(), 201)]);
        $shipment = $this->internationalShipment();

        $this->pack($shipment)->assertOk()->assertJsonPath('data.status', 'awaiting_pickup');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) use ($shipment) {
            $content = $r['content'];
            $line = $content['exportDeclaration']['lineItems'][0];

            return $r->url() === 'https://express.api.dhl.com/mydhlapi/test/shipments'
                && $r['productCode'] === 'Y'
                && $content['incoterm'] === 'DAP'
                && $content['isCustomsDeclarable'] === true
                && $content['declaredValue'] === 1200.0
                && $content['declaredValueCurrency'] === 'GHS'
                && $content['packages'][0]['weight'] === 1.8
                && $content['packages'][0]['dimensions']['height'] === 24
                && $line['commodityCodes'] === [['typeCode' => 'outbound', 'value' => '6403.99']]
                && $line['quantity'] === ['value' => 2, 'unitOfMeasurement' => 'PCS']
                && $line['description'] === 'Leather sandals'
                && $line['manufacturerCountry'] === 'GH'
                && $content['exportDeclaration']['invoice']['number'] === $shipment->order->reference
                && $r['customerDetails']['receiverDetails']['postalAddress']['countryCode'] === 'GB'
                && $r['pickup']['isRequested'] === true
                && $r['outputImageProperties']['imageOptions'][1] === ['typeCode' => 'invoice', 'invoiceType' => 'commercial', 'isRequested' => true];
        });

        $shipment->refresh();
        $this->assertSame(self::AWB, $shipment->carrier_reference);
        $this->assertSame('CBJ201220123456', $shipment->pickup_reference);
        Storage::disk(MediaStorage::privateDiskName())->assertExists("shipments/{$shipment->uuid}/label.pdf");
        $this->assertSame('%PDF invoice', Storage::disk(MediaStorage::privateDiskName())->get($shipment->invoice_path));
        $this->assertSame('shipped', $shipment->order->fresh()->status);
        $this->assertSame(self::AWB, $shipment->order->fresh()->delivery_reference);
        Bus::assertDispatchedTimes(SendOrderNotification::class, 1);
    }

    /** No idempotency key at DHL: a refusal goes to a person, once. */
    public function test_a_refused_booking_is_not_retried(): void
    {
        Http::fake(['*/shipments' => Http::response(['title' => 'Validation', 'detail' => 'Invalid commodity code'], 422)]);
        $shipment = $this->internationalShipment();

        $this->pack($shipment)->assertOk();

        Http::assertSentCount(1);
        $this->assertSame(ShipmentStatus::Failed, $shipment->fresh()->status);
        $this->assertStringContainsString('Invalid commodity code', $shipment->fresh()->failure_reason);
        Bus::assertDispatched(SendOpsAlert::class, fn ($job) => $job->key === 'ops_shipment_failed');
    }

    /** A timeout may have created the shipment: never resend blindly. */
    public function test_a_timed_out_booking_is_handed_to_a_person_with_a_warning(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $shipment = $this->internationalShipment();

        $this->pack($shipment)->assertOk();

        Http::assertSentCount(0);
        $this->assertSame(ShipmentStatus::Failed, $shipment->fresh()->status);
        $this->assertStringContainsString('check MyDHL', $shipment->fresh()->failure_reason);
    }

    public function test_a_shipment_with_an_awb_is_never_booked_again(): void
    {
        Http::fake();
        $shipment = $this->internationalShipment(['carrier_reference' => self::AWB, 'status' => ShipmentStatus::Booking]);

        (new DispatchShipment($shipment))->handle(app(ShippingManager::class), app(ShipmentService::class));

        Http::assertNothingSent();
    }

    public function test_customs_data_removed_after_checkout_fails_the_booking_before_calling_dhl(): void
    {
        Http::fake();
        $shipment = $this->internationalShipment();
        $shipment->order->items->first()->product->update(['hs_code' => null]);

        $this->pack($shipment)->assertOk();

        Http::assertNothingSent();
        $this->assertSame(ShipmentStatus::Failed, $shipment->fresh()->status);
    }

    // --- tracking ----------------------------------------------------------

    public function test_tracking_follows_checkpoints_to_delivered(): void
    {
        Http::fake(['*/tracking*' => Http::sequence()
            ->push($this->tracking('PU'))
            ->push($this->tracking('PU', 'AF', 'WC'))
            ->push($this->tracking('PU', 'AF', 'WC', 'OK')),
        ]);
        $shipment = $this->internationalShipment(['carrier_reference' => self::AWB, 'status' => ShipmentStatus::AwaitingPickup]);
        $shipment->order->update(['status' => 'shipped']);

        $this->poll();
        $this->assertSame(ShipmentStatus::InTransit, $shipment->fresh()->status);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'shipments/'.self::AWB.'/tracking')
            && str_contains($r->url(), 'trackingView=all-checkpoints'));

        $this->poll();
        $this->assertSame(ShipmentStatus::OutForDelivery, $shipment->fresh()->status);
        $this->assertSame('WC Checkpoint WC', $shipment->fresh()->carrier_status);

        $this->poll();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);
        $this->assertSame('delivered', $shipment->order->fresh()->status);
    }

    public function test_an_unknown_checkpoint_keeps_the_parcel_in_transit_and_records_it(): void
    {
        Http::fake(['*/tracking*' => Http::response($this->tracking('PU', 'OH'))]);
        $shipment = $this->internationalShipment(['carrier_reference' => self::AWB, 'status' => ShipmentStatus::InTransit, 'carrier_status' => 'PU']);

        $this->poll();

        $this->assertSame(ShipmentStatus::InTransit, $shipment->fresh()->status);
        $this->assertSame('OH Checkpoint OH', $shipment->fresh()->carrier_status);
    }

    public function test_no_tracking_yet_leaves_the_shipment_as_it_was(): void
    {
        Http::fake(['*/tracking*' => Http::response(['title' => 'Not found'], 404)]);
        $shipment = $this->internationalShipment(['carrier_reference' => self::AWB, 'status' => ShipmentStatus::AwaitingPickup]);

        $this->poll();

        $this->assertSame(ShipmentStatus::AwaitingPickup, $shipment->fresh()->status);
    }

    // --- documents and product data ---------------------------------------

    public function test_staff_get_a_link_to_the_label_and_invoice(): void
    {
        $shipment = $this->internationalShipment(['label_path' => 'shipments/x/label.pdf']);
        Storage::disk(MediaStorage::privateDiskName())->put('shipments/x/label.pdf', '%PDF');

        $this->actingAs(AdminUser::factory()->create(['role' => 'intern']), 'admin')
            ->getJson("/api/v1/admin/shipments/{$shipment->uuid}/documents/label")
            ->assertOk()
            ->assertJsonStructure(['data' => ['url']]);

        $this->actingAs(AdminUser::factory()->create(['role' => 'intern']), 'admin')
            ->getJson("/api/v1/admin/shipments/{$shipment->uuid}/documents/invoice")
            ->assertNotFound();
    }

    public function test_an_admin_records_a_products_shipping_and_customs_data(): void
    {
        $product = $this->product(shippable: false);

        $this->actingAs(AdminUser::factory()->create(['role' => 'admin']), 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'weight_grams' => 850, 'length_mm' => 300, 'width_mm' => 190, 'height_mm' => 110,
                'hs_code' => '6403.99', 'origin_country' => 'gh', 'customs_description' => 'Leather sandals',
            ])
            ->assertOk()
            ->assertJsonPath('data.shipping.ready_for_international', true)
            ->assertJsonPath('data.shipping.hs_code', '6403.99');
    }

    public function test_an_hs_code_must_look_like_one(): void
    {
        $product = $this->product();

        $this->actingAs(AdminUser::factory()->create(['role' => 'admin']), 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}", ['hs_code' => 'sandals'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('hs_code');
    }

    // --- local development ------------------------------------------------

    public function test_without_credentials_the_fake_carries_an_order_to_delivered(): void
    {
        config(['services.dhl.username' => null]);
        Http::fake();
        $shipment = $this->internationalShipment();

        $this->pack($shipment)->assertOk();
        foreach (range(1, 4) as $_) {
            $this->poll();
        }

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        Storage::disk(MediaStorage::privateDiskName())->assertExists($shipment->label_path);
        Http::assertNothingSent();
    }
}

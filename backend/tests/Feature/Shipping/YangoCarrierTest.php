<?php

namespace Tests\Feature\Shipping;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingZone;
use App\Exceptions\YangoAcceptBlockedException;
use App\Jobs\PollActiveShipments;
use App\Jobs\SendOpsAlert;
use App\Jobs\SendOrderNotification;
use App\Models\AdminUser;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShippingSetting;
use App\Services\Shipping\Carriers\YangoCarrier;
use App\Services\Shipping\Carriers\YangoStatusMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

/**
 * The Yango driver against Http::fake(), with request and response shapes
 * taken from the Express API reference. Nothing here reaches Yango.
 */
class YangoCarrierTest extends TestCase
{
    use RefreshDatabase;

    private const CLAIM = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const POLYGON = [
        'type' => 'Polygon',
        'coordinates' => [[[-0.30, 5.50], [-0.10, 5.50], [-0.10, 5.70], [-0.30, 5.70], [-0.30, 5.50]]],
    ];

    private const OSU = [
        'full_name' => 'Ama Serwaa', 'email' => 'ama@example.com', 'phone' => '233200000000',
        'line1' => '4 Oxford Street', 'city' => 'Accra', 'region' => 'Greater Accra',
        'country' => 'GH', 'lat' => 5.556, 'lng' => -0.18,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.yango.enabled' => true,
            'services.yango.key' => 'test-token',
            'services.yango.base_url' => 'https://b2b.delivery.yango.tech',
            'shipping.origin.lat' => 5.6767,
            'shipping.origin.lng' => -0.1661,
            'shipping.origin.phone' => '233300000000',
            'shipping.origin.email' => 'workshop@example.com',
            'shipping.origin.address' => '1 Haatso Road',
            'shipping.quote_buffer_percent' => 10,
            'shipping.ops.email' => 'ops@example.com',
        ]);
        ShippingSetting::current()->update(['accra_polygon' => self::POLYGON]);
        Bus::fake([SendOrderNotification::class, SendOpsAlert::class]);
    }

    // --- fixtures, as the reference documents them ---------------------

    private function priceResponse(string $price = '32.1000', string $currency = 'GHS'): array
    {
        return [
            'price' => $price,
            'currency_rules' => ['code' => $currency, 'text' => 'cedi', 'template' => '$VALUE$ $SIGN$', 'sign' => '₵'],
            'requirements' => ['taxi_class' => 'courier'],
            'distance_meters' => 13400,
            'eta' => 35,
        ];
    }

    private function claim(string $status, int $version = 1, array $extra = []): array
    {
        return ['id' => self::CLAIM, 'status' => $status, 'version' => $version, 'revision' => $version, ...$extra];
    }

    private function trackingLinks(): array
    {
        return ['route_points' => [
            ['id' => 1, 'type' => 'source', 'visit_order' => 1],
            ['id' => 2, 'type' => 'destination', 'visit_order' => 2, 'sharing_link' => 'https://yango.example/track/xyz'],
        ]];
    }

    private function production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
    }

    private function stocked(): InventoryItem
    {
        return InventoryItem::factory()->create([
            'product_id' => Product::factory()->create(['base_price_ghs' => 60_000])->id,
            'variant_attributes' => ['size' => '42'],
            'quantity_available' => 5,
            'quantity_reserved' => 0,
        ]);
    }

    private function accraShipment(array $attributes = []): Shipment
    {
        $order = Order::factory()->paid()->create([
            'shipping_zone' => ShippingZone::Accra,
            'shipping_address' => self::OSU,
            'shipping_option' => ['carrier' => 'yango', 'service_code' => 'courier', 'quoted_raw' => 3_210, 'quoted_currency' => 'GHS'],
        ]);
        OrderItem::factory()->create(['order_id' => $order->id, 'unit_price' => 60_000, 'quantity' => 1, 'currency' => 'GHS']);

        return Shipment::factory()->create([
            'order_id' => $order->id,
            'zone' => ShippingZone::Accra,
            'carrier' => 'yango',
            'service_code' => 'courier',
            ...$attributes,
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

    // --- quote ----------------------------------------------------------

    public function test_checkout_quotes_from_check_price_with_coordinates_as_lng_lat(): void
    {
        Http::fake(['*/check-price' => Http::response($this->priceResponse('32.1000'))]);

        $this->postJson('/api/v1/checkout/shipping-quote', [
            'items' => [['inventory_item_id' => $this->stocked()->id, 'quantity' => 1]],
            'shipping_address' => self::OSU,
        ])->assertOk()
            ->assertJsonPath('data.zone', 'accra')
            ->assertJsonPath('data.options.0.carrier', 'yango')
            // 32.10 + 10% = 35.31, rounded up to a whole cedi.
            ->assertJsonPath('data.options.0.amount_ghs', 3_600);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://b2b.delivery.yango.tech/b2b/cargo/integration/v2/check-price'
            && $r->hasHeader('Authorization', 'Bearer test-token')
            && $r->hasHeader('Accept-Language', 'en')
            && $r['route_points'][0]['coordinates'] === [-0.1661, 5.6767]
            && $r['route_points'][1]['coordinates'] === [-0.18, 5.556]
            && $r['requirements']['taxi_class'] === 'courier'
            && $r['items'][0]['size']['length'] === 0.32);
    }

    public function test_no_courier_available_at_checkout_charges_the_fallback(): void
    {
        Http::fake(['*/check-price' => Http::response(['code' => 'no_cars', 'message' => 'No suitable vehicles'], 409)]);

        $this->postJson('/api/v1/checkout/shipping-quote', [
            'items' => [['inventory_item_id' => $this->stocked()->id, 'quantity' => 1]],
            'shipping_address' => self::OSU,
        ])->assertOk()->assertJsonPath('data.options.0.amount_ghs', 2_500);
    }

    public function test_a_quote_in_another_currency_is_not_trusted(): void
    {
        Http::fake(['*/check-price' => Http::response($this->priceResponse('10.0000', 'USD'))]);

        $this->postJson('/api/v1/checkout/shipping-quote', [
            'items' => [['inventory_item_id' => $this->stocked()->id, 'quantity' => 1]],
            'shipping_address' => self::OSU,
        ])->assertOk()->assertJsonPath('data.options.0.amount_ghs', 2_500);
    }

    public function test_a_timeout_at_checkout_charges_the_fallback(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->postJson('/api/v1/checkout/shipping-quote', [
            'items' => [['inventory_item_id' => $this->stocked()->id, 'quantity' => 1]],
            'shipping_address' => self::OSU,
        ])->assertOk()->assertJsonPath('data.options.0.amount_ghs', 2_500);
    }

    /** Switched off, Yango is skipped and Accra is dispatched by hand. */
    public function test_a_disabled_yango_is_never_called(): void
    {
        config(['services.yango.enabled' => false]);
        Http::fake();

        $this->postJson('/api/v1/checkout/shipping-quote', [
            'items' => [['inventory_item_id' => $this->stocked()->id, 'quantity' => 1]],
            'shipping_address' => self::OSU,
        ])->assertOk()->assertJsonPath('data.options.0.carrier', 'manual');

        Http::assertNothingSent();
    }

    // --- packing creates the claim -----------------------------------------

    public function test_packing_creates_one_claim_with_an_idempotency_key(): void
    {
        Http::fake(['*/claims/create*' => Http::response($this->claim('new'))]);
        $shipment = $this->accraShipment();

        $this->pack($shipment)->assertOk()->assertJsonPath('data.status', 'booking');
        // A double click.
        $this->pack($shipment)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'claims/create?request_id='.$shipment->uuid.'-a1')
            && ! isset($r['auto_accept'])
            && $r['route_points'][1]['address']['coordinates'] === [-0.18, 5.556]
            && $r['route_points'][1]['external_order_id'] === $shipment->order->reference
            && $r['items'][0]['droppof_point'] === 2
            && $r['items'][0]['cost_value'] === '600.00');

        $shipment->refresh();
        $this->assertSame(self::CLAIM, $shipment->carrier_reference);
        $this->assertSame(1, $shipment->carrier_version);
        $this->assertNotNull($shipment->packed_at);
        $this->assertNotNull($shipment->next_poll_at);
        $this->assertSame('processing', $shipment->order->fresh()->status);
    }

    /** A shipment with a claim already never asks for another. */
    public function test_dispatching_again_reuses_the_existing_claim(): void
    {
        Http::fake();
        $shipment = $this->accraShipment(['carrier_reference' => self::CLAIM, 'status' => ShipmentStatus::Booking]);

        $result = app(YangoCarrier::class)->dispatch($shipment);

        $this->assertSame(self::CLAIM, $result->carrierReference);
        Http::assertNothingSent();
    }

    public function test_a_claim_payload_can_never_auto_accept(): void
    {
        $this->expectException(LogicException::class);

        YangoCarrier::assertNeverAutoAccepts(['auto_accept' => true]);
    }

    // --- the production guard ------------------------------------------

    public function test_accepting_a_claim_is_refused_outside_production_before_any_request(): void
    {
        Http::fake();
        $shipment = $this->accraShipment(['carrier_reference' => self::CLAIM, 'carrier_version' => 2, 'status' => ShipmentStatus::Booking]);

        try {
            app(YangoCarrier::class)->confirm($shipment);
            $this->fail('A claim was accepted outside production.');
        } catch (YangoAcceptBlockedException) {
            Http::assertNothingSent();
        }
    }

    /** No environment flag, config value or credential gets round it. */
    public function test_the_guard_ignores_every_other_setting(): void
    {
        config(['app.env' => 'production', 'app.debug' => false]);
        Http::fake();
        $shipment = $this->accraShipment(['carrier_reference' => self::CLAIM, 'status' => ShipmentStatus::Booking]);

        $this->expectException(YangoAcceptBlockedException::class);

        app(YangoCarrier::class)->confirm($shipment);
    }

    public function test_in_production_the_accept_carries_the_claim_version(): void
    {
        $this->production();
        Http::fake(['*/claims/accept*' => Http::response($this->claim('accepted', 3))]);
        $shipment = $this->accraShipment(['carrier_reference' => self::CLAIM, 'carrier_version' => 2, 'status' => ShipmentStatus::Booking]);

        app(YangoCarrier::class)->confirm($shipment);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'claims/accept?claim_id='.self::CLAIM) && $r['version'] === 2);
    }

    // --- polling ---------------------------------------------------------

    public function test_the_poller_confirms_a_priced_claim_then_follows_it_to_the_door(): void
    {
        $this->production();
        Http::fake([
            '*/claims/info*' => Http::sequence()
                ->push($this->claim('ready_for_approval', 1))
                ->push($this->claim('performer_found', 2))
                ->push($this->claim('pickuped', 2))
                ->push($this->claim('delivered_finish', 2, ['pricing' => ['final_price' => '33.5000', 'currency' => 'GHS']])),
            '*/claims/accept*' => Http::response($this->claim('accepted', 2)),
            '*/claims/tracking-links*' => Http::response($this->trackingLinks()),
        ]);
        $shipment = $this->accraShipment(['carrier_reference' => self::CLAIM, 'carrier_version' => 1, 'status' => ShipmentStatus::Booking]);

        $this->poll();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'claims/accept'));
        $this->assertSame('accepted', $shipment->fresh()->carrier_status);

        $this->poll();
        $shipment->refresh();
        $this->assertSame(ShipmentStatus::AwaitingPickup, $shipment->status);
        $this->assertSame('https://yango.example/track/xyz', $shipment->tracking_url);
        $this->assertSame('shipped', $shipment->order->fresh()->status);
        Bus::assertDispatchedTimes(SendOrderNotification::class, 1);

        $this->poll();
        $this->poll();
        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertSame(3_350, $shipment->actual_cost);
        $this->assertSame('delivered', $shipment->order->fresh()->status);
        // Told once that it shipped, not again on each later status.
        Bus::assertDispatchedTimes(SendOrderNotification::class, 1);
    }

    /** Staging with a real token: the claim is priced, then left to lapse. */
    public function test_outside_production_a_priced_claim_is_never_accepted(): void
    {
        Http::fake(['*/claims/info*' => Http::response($this->claim('ready_for_approval'))]);
        $shipment = $this->accraShipment(['carrier_reference' => self::CLAIM, 'status' => ShipmentStatus::Booking]);

        $this->poll();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'claims/accept'));
        $this->assertSame(ShipmentStatus::Failed, $shipment->fresh()->status);
        $this->assertStringContainsString('only accepted in production', $shipment->fresh()->failure_reason);
    }

    public function test_a_poll_with_nothing_new_writes_no_event(): void
    {
        Http::fake(['*/claims/info*' => Http::response($this->claim('pickuped', 2))]);
        $shipment = $this->accraShipment([
            'carrier_reference' => self::CLAIM, 'status' => ShipmentStatus::InTransit,
            'carrier_status' => 'pickuped', 'tracking_url' => 'https://yango.example/track/xyz',
        ]);

        $this->poll();
        $this->poll();

        $this->assertSame(0, $shipment->events()->count());
        $this->assertNotNull($shipment->fresh()->last_polled_at);
    }

    public function test_only_shipments_that_are_due_are_polled(): void
    {
        Http::fake(['*/claims/info*' => Http::response($this->claim('pickuped', 2))]);
        $this->accraShipment(['carrier_reference' => self::CLAIM, 'status' => ShipmentStatus::InTransit, 'next_poll_at' => now()->addMinutes(5)]);
        Shipment::factory()->create(['status' => ShipmentStatus::Pending]);

        (new PollActiveShipments)->handle();

        Http::assertNothingSent();
    }

    // --- no courier ------------------------------------------------------

    public function test_no_courier_retries_once_then_hands_to_a_person(): void
    {
        $this->production();
        Http::fake([
            '*/claims/info*' => Http::response($this->claim('performer_not_found', 2)),
            '*/claims/cancel-info*' => Http::response(['cancel_state' => 'free', 'price' => '0.0000', 'currency' => 'GHS']),
            '*/claims/cancel?*' => Http::response($this->claim('cancelled', 3)),
            '*/claims/create*' => Http::response($this->claim('new')),
        ]);
        $first = $this->accraShipment(['carrier_reference' => self::CLAIM, 'carrier_version' => 2, 'status' => ShipmentStatus::Booking]);

        $this->poll();

        $first->refresh();
        $this->assertSame(ShipmentStatus::Cancelled, $first->status);
        $second = $first->order->fresh()->shipment;
        $this->assertSame(2, $second->attempt);
        $this->assertSame($first->id, $second->supersedes_id);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'claims/cancel?claim_id=') && $r['cancel_state'] === 'free');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'request_id='.$second->uuid.'-a2'));

        // The second attempt finds no courier either, and Swoove is not on.
        $this->poll();

        $this->assertSame(ShipmentStatus::Failed, $second->fresh()->status);
        $this->assertSame(2, $first->order->shipments()->count());
        Bus::assertDispatched(SendOpsAlert::class, fn ($job) => $job->key === 'ops_shipment_failed');
    }

    public function test_a_booking_that_cannot_be_cancelled_is_never_doubled(): void
    {
        Http::fake([
            '*/claims/info*' => Http::response($this->claim('performer_not_found', 2)),
            '*/claims/cancel-info*' => Http::response(['cancel_state' => 'unavailable']),
        ]);
        $shipment = $this->accraShipment(['carrier_reference' => self::CLAIM, 'status' => ShipmentStatus::Booking]);

        $this->poll();

        $this->assertSame(ShipmentStatus::Failed, $shipment->fresh()->status);
        $this->assertSame(1, $shipment->order->shipments()->count());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'claims/create'));
    }

    public function test_a_courier_search_that_runs_too_long_counts_as_no_courier(): void
    {
        Http::fake([
            '*/claims/info*' => Http::response($this->claim('performer_lookup', 2)),
            '*/claims/cancel-info*' => Http::response(['cancel_state' => 'free']),
            '*/claims/cancel?*' => Http::response($this->claim('cancelled', 3)),
            '*/claims/create*' => Http::response($this->claim('new')),
        ]);
        $shipment = $this->accraShipment([
            'carrier_reference' => self::CLAIM, 'status' => ShipmentStatus::Booking,
            'packed_at' => now()->subMinutes(20), 'created_at' => now()->subMinutes(20),
        ]);

        $this->poll();

        $this->assertSame(ShipmentStatus::Cancelled, $shipment->fresh()->status);
        $this->assertSame(2, $shipment->order->fresh()->shipment->attempt);
    }

    // --- admin guards ----------------------------------------------------

    public function test_a_booking_in_progress_cannot_be_marked_by_hand(): void
    {
        $shipment = $this->accraShipment(['carrier_reference' => self::CLAIM, 'status' => ShipmentStatus::Booking]);

        $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/mark-failed", ['reason' => 'impatient'])
            ->assertStatus(422);
    }

    public function test_an_order_with_a_stock_conflict_cannot_be_packed(): void
    {
        Http::fake();
        $shipment = $this->accraShipment();
        $shipment->order->update(['status' => 'inventory_conflict']);

        $this->pack($shipment)->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_an_intern_cannot_pack(): void
    {
        $shipment = $this->accraShipment();

        $this->actingAs(AdminUser::factory()->create(['role' => 'intern']), 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/pack")
            ->assertForbidden();
    }

    // --- local development ----------------------------------------------

    /** No token outside production: the fake runs the whole flow, offline. */
    public function test_without_a_token_the_fake_carries_an_order_to_delivered(): void
    {
        config(['services.yango.key' => null]);
        Http::fake();
        $shipment = $this->accraShipment();

        $this->pack($shipment)->assertOk();
        foreach (range(1, 8) as $_) {
            $this->poll();
        }

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertSame('delivered', $shipment->order->fresh()->status);
        $this->assertNotNull($shipment->tracking_url);
        Http::assertNothingSent();
    }

    public function test_the_fake_exercises_the_no_courier_path_on_request(): void
    {
        config(['services.yango.key' => null]);
        $shipment = $this->accraShipment();
        $shipment->order->update(['shipping_address' => [...self::OSU, 'line1' => 'NOCOURIER test']]);

        $this->pack($shipment)->assertOk();
        foreach (range(1, 8) as $_) {
            $this->poll();
        }

        $this->assertSame(ShipmentStatus::Failed, $shipment->order->fresh()->shipment->status);
        $this->assertSame(2, $shipment->order->shipments()->count());
    }

    // --- status map ------------------------------------------------------

    public function test_every_documented_claim_status_is_mapped(): void
    {
        $documented = [
            'new', 'estimating', 'estimating_failed', 'ready_for_approval', 'accepted', 'performer_lookup',
            'performer_draft', 'performer_found', 'performer_not_found', 'pickup_arrived',
            'ready_for_pickup_confirmation', 'pickuped', 'delivery_arrived', 'ready_for_delivery_confirmation',
            'delivered', 'delivered_finish', 'returning', 'return_arrived', 'ready_for_return_confirmation',
            'returned', 'returned_finish', 'failed', 'cancelled', 'cancelled_with_payment', 'cancelled_by_taxi',
            'cancelled_with_items_on_hands',
        ];

        $this->assertCount(26, $documented);
        $this->assertEqualsCanonicalizing($documented, YangoStatusMap::known());
        $this->assertSame(ShipmentStatus::Failed, YangoStatusMap::canonical('cancelled_with_items_on_hands', ShipmentStatus::InTransit));
        // Something Yango adds later leaves the shipment where it was.
        $this->assertSame(ShipmentStatus::InTransit, YangoStatusMap::canonical('teleported', ShipmentStatus::InTransit));
    }
}

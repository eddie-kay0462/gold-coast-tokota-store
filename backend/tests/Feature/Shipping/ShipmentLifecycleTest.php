<?php

namespace Tests\Feature\Shipping;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingZone;
use App\Events\OrderPaid;
use App\Jobs\SendOpsAlert;
use App\Jobs\SendOrderNotification;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use LogicException;
use Tests\TestCase;

/**
 * From payment to doorstep: the shipment opened on payment, dispatched by
 * hand, and closed — and the order following it.
 */
class ShipmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shipping.ops.email' => 'ops@example.com', 'shipping.ops.phone' => '233200000001']);
    }

    private function paidGhanaOrder(): Order
    {
        return Order::factory()->paid()->create([
            'shipping_zone' => ShippingZone::Ghana,
            'shipping_option' => ['carrier' => 'manual', 'service_code' => null, 'quoted_raw' => null, 'quoted_currency' => null],
            'shipping_address' => [
                'full_name' => 'Kofi Mensah', 'email' => 'kofi@example.com', 'phone' => '233240000000',
                'line1' => '9 Prempeh Street', 'city' => 'Kumasi', 'region' => 'Ashanti', 'country' => 'GH',
            ],
        ]);
    }

    private function staff(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'staff']);
    }

    // --- on payment -----------------------------------------------------

    public function test_payment_opens_a_pending_shipment_and_tells_ops(): void
    {
        Bus::fake([SendOpsAlert::class, SendOrderNotification::class]);
        $order = $this->paidGhanaOrder();

        OrderPaid::dispatch($order);

        $shipment = $order->fresh()->shipment;
        $this->assertNotNull($shipment);
        $this->assertSame(ShipmentStatus::Pending, $shipment->status);
        $this->assertSame('manual', $shipment->carrier);
        $this->assertSame(ShippingZone::Ghana, $shipment->zone);
        $this->assertCount(1, $shipment->events);
        Bus::assertDispatched(SendOpsAlert::class, fn ($job) => $job->key === 'ops_manual_shipment'
            && str_contains($job->body, $order->reference));
    }

    /** A replayed event must not open a second shipment or a second alert. */
    public function test_opening_a_shipment_is_idempotent(): void
    {
        Bus::fake([SendOpsAlert::class, SendOrderNotification::class]);
        $order = $this->paidGhanaOrder();

        OrderPaid::dispatch($order);
        OrderPaid::dispatch($order);

        $this->assertSame(1, Shipment::query()->count());
        Bus::assertDispatchedTimes(SendOpsAlert::class, 1);
    }

    /** An order placed before zones existed still gets one, from its address. */
    public function test_an_order_without_a_zone_is_zoned_from_its_address(): void
    {
        Bus::fake([SendOpsAlert::class, SendOrderNotification::class]);
        $order = Order::factory()->usd()->paid()->create();

        OrderPaid::dispatch($order);

        $this->assertSame(ShippingZone::International, $order->fresh()->shipment->zone);
        $this->assertSame(ShippingZone::International, $order->fresh()->shipping_zone);
    }

    // --- manual dispatch ------------------------------------------------

    public function test_staff_record_a_manual_dispatch_and_the_customer_is_told_once(): void
    {
        Bus::fake([SendOpsAlert::class, SendOrderNotification::class]);
        $shipment = Shipment::factory()->create(['order_id' => $this->paidGhanaOrder()->id]);

        $response = $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/manual-dispatch", [
                'courier_name' => 'VIP Bus Parcels',
                'tracking_number' => 'VIP-88213',
                'tracking_url' => 'https://example.com/track/VIP-88213',
                'cost_paid_ghs' => 3_000,
                'notes' => 'Collect at Kejetia station.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_transit')
            ->assertJsonPath('data.courier_name', 'VIP Bus Parcels')
            ->assertJsonPath('data.actual_cost.amount', 3_000);

        $order = $shipment->order->fresh();
        $this->assertSame('shipped', $order->status);
        $this->assertSame('VIP-88213', $order->delivery_reference);
        Bus::assertDispatchedTimes(SendOrderNotification::class, 1);
        Bus::assertDispatched(SendOrderNotification::class, fn ($job) => $job->reason === 'shipped');

        // A second submission is refused rather than re-announcing.
        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/manual-dispatch", ['courier_name' => 'Again'])
            ->assertStatus(422);
        Bus::assertDispatchedTimes(SendOrderNotification::class, 1);
    }

    public function test_marking_delivered_delivers_the_order_and_starts_the_returns_clock(): void
    {
        Bus::fake([SendOpsAlert::class, SendOrderNotification::class]);
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();
        $shipment->order->update(['status' => 'shipped']);

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/mark-delivered")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $order = $shipment->order->fresh();
        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertNotNull($shipment->fresh()->delivered_at);
    }

    public function test_marking_failed_records_why_and_tells_ops(): void
    {
        Bus::fake([SendOpsAlert::class, SendOrderNotification::class]);
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/mark-failed", ['reason' => 'Parcel lost at the station'])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'Parcel lost at the station');

        Bus::assertDispatched(SendOpsAlert::class, fn ($job) => $job->key === 'ops_shipment_failed');
    }

    /** A failed courier booking is rescued by hand, and stays on record. */
    public function test_a_failed_automated_shipment_can_be_dispatched_by_hand(): void
    {
        Bus::fake([SendOpsAlert::class, SendOrderNotification::class]);
        $failed = Shipment::factory()->status(ShipmentStatus::Failed)->create([
            'zone' => ShippingZone::Accra,
            'carrier' => 'yango',
            'carrier_reference' => 'claim-1',
        ]);

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/shipments/{$failed->uuid}/manual-dispatch", ['courier_name' => 'Okada rider'])
            ->assertOk()
            ->assertJsonPath('data.carrier', 'manual');

        $order = $failed->order->fresh();
        $this->assertSame(2, $order->shipments()->count());
        $this->assertSame('manual', $order->shipment->carrier);
        $this->assertSame($failed->id, $order->shipment->supersedes_id);
        $this->assertSame(ShipmentStatus::Failed, $failed->fresh()->status);
    }

    public function test_a_closed_shipment_cannot_be_marked_again(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::Delivered)->create();

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/mark-failed", ['reason' => 'x'])
            ->assertStatus(422);
    }

    // --- access ---------------------------------------------------------

    public function test_an_intern_can_see_a_shipment_but_not_act_on_it(): void
    {
        $intern = AdminUser::factory()->create(['role' => 'intern']);
        $shipment = Shipment::factory()->create();

        $this->actingAs($intern, 'admin')->getJson("/api/v1/admin/shipments/{$shipment->uuid}")->assertOk();
        $this->actingAs($intern, 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/manual-dispatch", ['courier_name' => 'x'])
            ->assertForbidden();
        $this->actingAs($intern, 'admin')
            ->postJson("/api/v1/admin/shipments/{$shipment->uuid}/mark-delivered")
            ->assertForbidden();
    }

    public function test_shipment_actions_are_closed_to_guests(): void
    {
        $shipment = Shipment::factory()->create();

        $this->postJson("/api/v1/admin/shipments/{$shipment->uuid}/mark-delivered")->assertUnauthorized();
    }

    // --- the order follows the shipment ---------------------------------

    public function test_an_order_with_a_shipment_cannot_be_marked_shipped_directly(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs($this->staff(), 'admin')
            ->patchJson("/api/v1/admin/orders/{$shipment->order->reference}", ['status' => 'shipped'])
            ->assertStatus(422);

        $this->assertSame('paid', $shipment->order->fresh()->status);
    }

    public function test_the_admin_order_carries_its_shipment_and_history(): void
    {
        $shipment = Shipment::factory()->create();
        $shipment->record('system', 'Opened on payment.');

        $this->actingAs($this->staff(), 'admin')
            ->getJson("/api/v1/admin/orders/{$shipment->order->reference}")
            ->assertOk()
            ->assertJsonPath('data.shipment.id', $shipment->uuid)
            ->assertJsonPath('data.shipment.events.0.message', 'Opened on payment.');
    }

    public function test_the_customer_sees_the_tracking_link(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create([
            'tracking_url' => 'https://example.com/track/1',
        ]);

        $this->getJson("/api/v1/orders/{$shipment->order->reference}")
            ->assertOk()
            ->assertJsonPath('data.shipment.tracking_url', 'https://example.com/track/1')
            ->assertJsonPath('data.shipment.status', 'in_transit');
    }

    public function test_the_attention_list_holds_failed_and_stale_shipments(): void
    {
        Shipment::factory()->status(ShipmentStatus::Failed)->create();
        Shipment::factory()->create(['created_at' => now()->subHours(72)]);
        Shipment::factory()->create();
        Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        $this->actingAs($this->staff(), 'admin')
            ->getJson('/api/v1/admin/shipments/attention')
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->actingAs($this->staff(), 'admin')
            ->getJson('/api/v1/admin/shipments?attention=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // --- history --------------------------------------------------------

    public function test_shipment_events_are_append_only(): void
    {
        $event = Shipment::factory()->create()->record('system', 'Opened.');

        $this->expectException(LogicException::class);

        $event->update(['message' => 'Rewritten.']);
    }

    public function test_shipment_events_cannot_be_deleted(): void
    {
        $event = Shipment::factory()->create()->record('system', 'Opened.');

        try {
            $event->delete();
            $this->fail('A shipment event was deleted.');
        } catch (LogicException) {
            $this->assertTrue(ShipmentEvent::query()->whereKey($event->id)->exists());
        }
    }
}

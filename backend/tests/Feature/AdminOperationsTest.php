<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WorkshopSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin Operations surface: orders, bookings, workshop capacity, metrics.
 */
class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'admin']);
    }

    private function staff(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'staff']);
    }

    // --- orders ---------------------------------------------------------

    public function test_orders_list_is_closed_to_guests(): void
    {
        $this->getJson('/api/v1/admin/orders')->assertUnauthorized();
    }

    public function test_staff_can_list_orders(): void
    {
        Order::factory()->count(3)->create();

        $response = $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/orders');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    /** Admin's Money type is {amount, currency}, not a bare integer. */
    public function test_order_money_is_emitted_as_an_amount_and_currency_pair(): void
    {
        Order::factory()->create(['currency' => 'GHS', 'total' => 62_500]);

        $response = $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/orders');

        $response->assertOk();
        $response->assertJsonPath('data.0.total.amount', 62_500);
        $response->assertJsonPath('data.0.total.currency', 'GHS');
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        Order::factory()->count(2)->create(['status' => 'paid']);
        Order::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin(), 'admin')
            ->getJson('/api/v1/admin/orders?status=paid');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_orders_can_be_searched_by_reference(): void
    {
        $order = Order::factory()->create();
        Order::factory()->count(2)->create();

        $response = $this->actingAs($this->admin(), 'admin')
            ->getJson('/api/v1/admin/orders?q='.$order->reference);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    /** Guests have no Customer row, so the shipping address has to be searched too. */
    public function test_guest_orders_are_findable_by_the_name_on_the_shipping_address(): void
    {
        Order::factory()->create([
            'customer_id' => null,
            'shipping_address' => ['full_name' => 'Ama Serwaa', 'email' => 'ama@example.com', 'country' => 'GH'],
        ]);
        Order::factory()->count(2)->create();

        $response = $this->actingAs($this->admin(), 'admin')
            ->getJson('/api/v1/admin/orders?q=Ama');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.customer_name', 'Ama Serwaa');
        $response->assertJsonPath('data.0.is_guest', true);
    }

    public function test_a_registered_customers_name_comes_from_their_record(): void
    {
        $customer = Customer::factory()->create(['name' => 'Kwabena Mensah']);
        Order::factory()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/orders');

        $response->assertJsonPath('data.0.customer_name', 'Kwabena Mensah');
        $response->assertJsonPath('data.0.is_guest', false);
    }

    public function test_an_order_is_shown_by_reference_with_its_lines(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'The Kentehene Collection']);

        $response = $this->actingAs($this->admin(), 'admin')
            ->getJson("/api/v1/admin/orders/{$order->reference}");

        $response->assertOk();
        $response->assertJsonPath('data.items.0.name', 'The Kentehene Collection');
    }

    /** Withheld from customers, but it is what an admin reconciles against. */
    public function test_admin_can_see_the_gateway_payment_reference(): void
    {
        $order = Order::factory()->create(['payment_reference' => 'ps_live_abc123']);

        $this->actingAs($this->admin(), 'admin')
            ->getJson("/api/v1/admin/orders/{$order->reference}")
            ->assertJsonPath('data.payment_reference', 'ps_live_abc123');
    }

    public function test_staff_can_move_an_order_through_fulfilment(): void
    {
        $order = Order::factory()->create(['status' => 'paid']);

        $this->actingAs($this->staff(), 'admin')
            ->patchJson("/api/v1/admin/orders/{$order->reference}", ['status' => 'shipped'])
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped');
    }

    /** README two-tier rule: refunds are named alongside pricing as Admin-only. */
    public function test_staff_cannot_issue_a_refund(): void
    {
        $order = Order::factory()->create(['status' => 'paid']);

        $response = $this->actingAs($this->staff(), 'admin')
            ->patchJson("/api/v1/admin/orders/{$order->reference}", ['status' => 'refunded']);

        $response->assertForbidden();
        // A sentence, not a raw 403 blob — the README asks for that explicitly.
        $this->assertStringContainsString('admin', strtolower($response->json('message')));
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_an_admin_can_issue_a_refund(): void
    {
        $order = Order::factory()->create(['status' => 'paid']);

        $this->actingAs($this->admin(), 'admin')
            ->patchJson("/api/v1/admin/orders/{$order->reference}", ['status' => 'refunded'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded');
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->patchJson("/api/v1/admin/orders/{$order->reference}", ['status' => 'teleported'])
            ->assertStatus(422);
    }

    // --- bookings and capacity ------------------------------------------

    public function test_staff_can_list_and_filter_bookings(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 5]);
        Booking::factory()->count(2)->create(['workshop_session_id' => $session->id, 'status' => 'pending']);
        Booking::factory()->create(['workshop_session_id' => $session->id, 'status' => 'waitlisted']);

        $response = $this->actingAs($this->staff(), 'admin')
            ->getJson('/api/v1/admin/bookings?status=waitlisted');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_a_waitlisted_booking_can_be_promoted_when_a_seat_is_free(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 2]);
        Booking::factory()->create(['workshop_session_id' => $session->id, 'status' => 'confirmed']);
        $waitlisted = Booking::factory()->create(['workshop_session_id' => $session->id, 'status' => 'waitlisted']);

        $this->actingAs($this->staff(), 'admin')
            ->patchJson("/api/v1/admin/bookings/{$waitlisted->id}", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    /** Promotion is the one transition that can oversell a session. */
    public function test_promoting_into_a_full_session_is_refused(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 1]);
        Booking::factory()->create(['workshop_session_id' => $session->id, 'status' => 'confirmed']);
        $waitlisted = Booking::factory()->create(['workshop_session_id' => $session->id, 'status' => 'waitlisted']);

        $response = $this->actingAs($this->staff(), 'admin')
            ->patchJson("/api/v1/admin/bookings/{$waitlisted->id}", ['status' => 'confirmed']);

        $response->assertStatus(409);
        $this->assertSame('waitlisted', $waitlisted->fresh()->status);
    }

    /** Cancelling releases a seat and must never be blocked by capacity. */
    public function test_cancelling_a_booking_in_a_full_session_still_works(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 1]);
        $booking = Booking::factory()->create(['workshop_session_id' => $session->id, 'status' => 'confirmed']);

        $this->actingAs($this->staff(), 'admin')
            ->patchJson("/api/v1/admin/bookings/{$booking->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(1, $session->fresh()->remaining_capacity);
    }

    // --- workshop sessions ----------------------------------------------

    public function test_staff_can_create_a_workshop_session(): void
    {
        $response = $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/workshop-sessions', [
            'scheduled_date' => today()->addWeek()->toDateString(),
            'scheduled_slot' => '10:00 - 13:00',
            'capacity' => 8,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.capacity', 8);
        $response->assertJsonPath('data.remaining_capacity', 8);
    }

    public function test_a_session_cannot_be_scheduled_in_the_past(): void
    {
        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/workshop-sessions', [
            'scheduled_date' => today()->subDay()->toDateString(),
            'scheduled_slot' => '10:00 - 13:00',
            'capacity' => 8,
        ])->assertStatus(422)->assertJsonValidationErrors('scheduled_date');
    }

    /** Otherwise someone turns up to a seat that no longer exists. */
    public function test_capacity_cannot_be_cut_below_the_seats_already_taken(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 5]);
        Booking::factory()->count(3)->create(['workshop_session_id' => $session->id, 'status' => 'confirmed']);

        $response = $this->actingAs($this->admin(), 'admin')
            ->putJson("/api/v1/admin/workshop-sessions/{$session->id}", ['capacity' => 2]);

        $response->assertStatus(422);
        $this->assertSame(5, $session->fresh()->capacity);
    }

    public function test_a_session_with_bookings_cannot_be_deleted(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 5]);
        Booking::factory()->create(['workshop_session_id' => $session->id, 'status' => 'confirmed']);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson("/api/v1/admin/workshop-sessions/{$session->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('workshop_sessions', ['id' => $session->id]);
    }

    public function test_an_empty_session_can_be_deleted(): void
    {
        $session = WorkshopSession::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson("/api/v1/admin/workshop-sessions/{$session->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workshop_sessions', ['id' => $session->id]);
    }

    // --- dashboard ------------------------------------------------------

    public function test_metrics_are_closed_to_guests(): void
    {
        $this->getJson('/api/v1/admin/dashboard/metrics')->assertUnauthorized();
    }

    public function test_metrics_report_live_counts_with_a_read_timestamp(): void
    {
        Order::factory()->count(2)->create(['status' => 'paid', 'currency' => 'GHS', 'total' => 50_000]);
        Booking::factory()->create(['status' => 'pending']);
        Booking::factory()->count(2)->create(['status' => 'waitlisted']);

        $response = $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/dashboard/metrics');

        $response->assertOk();
        $response->assertJsonPath('data.orders_today', 2);
        $response->assertJsonPath('data.pending_bookings', 1);
        $response->assertJsonPath('data.waitlist_count', 2);
        $response->assertJsonPath('data.revenue_ghs.amount', 100_000);
        $response->assertJsonPath('data.revenue_ghs.currency', 'GHS');
        $this->assertNotNull($response->json('data.generated_at'));
    }

    /** GHS and USD are different quantities and must never be summed. */
    public function test_revenue_is_split_by_currency_never_added_together(): void
    {
        Order::factory()->create(['status' => 'paid', 'currency' => 'GHS', 'total' => 60_000]);
        Order::factory()->create(['status' => 'paid', 'currency' => 'USD', 'total' => 4_800]);

        $response = $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/dashboard/metrics');

        $response->assertJsonPath('data.revenue_ghs.amount', 60_000);
        $response->assertJsonPath('data.revenue_usd.amount', 4_800);
    }

    public function test_unpaid_orders_do_not_count_as_revenue(): void
    {
        Order::factory()->create(['status' => 'pending', 'currency' => 'GHS', 'total' => 60_000]);

        $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/dashboard/metrics')
            ->assertJsonPath('data.revenue_ghs.amount', 0);
    }

    public function test_low_stock_count_matches_the_inventory_filter(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 1, 'low_stock_threshold' => 3]);
        InventoryItem::factory()->create(['quantity_available' => 50, 'low_stock_threshold' => 3]);

        $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/dashboard/metrics')
            ->assertJsonPath('data.low_stock_count', 1);

        $this->assertNotNull($item);
    }

    /**
     * There is no inbox and no returns table. A confident 0 would read as "no
     * open returns" rather than "returns do not exist".
     */
    public function test_metrics_for_systems_that_do_not_exist_are_null_not_zero(): void
    {
        $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/dashboard/metrics')
            ->assertJsonPath('data.unread_messages', null)
            ->assertJsonPath('data.open_returns', null);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ReleaseExpiredReservations;
use App\Mail\TransactionalMail;
use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\FxRate;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\WorkshopSession;
use App\Models\WorkshopType;
use App\Services\Bookings\BookingCheckout;
use App\Services\GiftCards\GiftCardService;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Paid events: a place is paid for through an Order of kind `booking`, holds
 * its seats while the customer pays, and is confirmed, with a PDF ticket,
 * when the payment lands. Seats are counted in people.
 */
class PaidBookingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_pretend_secret';

    private function paidSession(int $priceGhs = 15_000, int $capacity = 4, bool $waitlist = false): WorkshopSession
    {
        $type = WorkshopType::factory()->create(['name' => 'Sandal Sip & Paint', 'price_ghs' => $priceGhs, 'allow_waitlist' => $waitlist]);

        return WorkshopSession::factory()->create([
            'workshop_type_id' => $type->id,
            'capacity' => $capacity,
            'scheduled_date' => today()->addWeek(),
            'scheduled_slot' => '10:00 AM to 1:00 PM',
        ]);
    }

    private function book(WorkshopSession $session, int $people = 2, string $currency = 'GHS')
    {
        return $this->postJson('/api/v1/bookings', [
            'type' => 'workshop',
            'workshop_session_id' => $session->id,
            'currency' => $currency,
            'details' => [
                'first_name' => 'Ama',
                'last_name' => 'Serwaa',
                'email' => 'ama@example.com',
                'phone' => '+233241234567',
                'attendee_count' => $people,
            ],
        ]);
    }

    private function payByWebhook(Order $order): void
    {
        config(['services.paystack.secret' => self::SECRET]);
        $payload = ['event' => 'charge.success', 'data' => [
            'reference' => $order->reference,
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => 'success',
        ]];

        $this->call(
            'POST', '/api/v1/webhooks/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => hash_hmac('sha512', json_encode($payload), self::SECRET), 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        )->assertOk();
    }

    private function release(): void
    {
        (new ReleaseExpiredReservations)->handle(app(InventoryReservationService::class), app(GiftCardService::class), app(BookingCheckout::class));
    }

    public function test_a_paid_booking_opens_a_payment_and_holds_its_seats(): void
    {
        $session = $this->paidSession(capacity: 4);

        $response = $this->book($session, 2)->assertCreated();

        $this->assertNotEmpty($response->json('payment.authorization_url'));
        $booking = Booking::findOrFail($response->json('data.id'));
        $this->assertSame('awaiting_payment', $booking->status);
        $this->assertNotNull($booking->hold_expires_at);

        $order = Order::where('reference', $response->json('order_reference'))->firstOrFail();
        $this->assertSame('booking', $order->kind);
        $this->assertSame(30_000, $order->total);
        $this->assertSame(0, $order->shipping_cost);
        $this->assertSame(2, $order->items()->first()->quantity);

        $this->assertSame(2, $session->fresh()->remaining_capacity);
    }

    public function test_payment_confirms_the_booking_and_emails_the_ticket(): void
    {
        Mail::fake();
        $session = $this->paidSession();
        $response = $this->book($session, 2)->assertCreated();
        $order = Order::where('reference', $response->json('order_reference'))->firstOrFail();

        $this->payByWebhook($order);

        $booking = Booking::findOrFail($response->json('data.id'));
        $this->assertSame('confirmed', $booking->status);
        $this->assertNotNull($booking->ticket_code);
        $this->assertNull($booking->hold_expires_at);
        $this->assertSame(0, Shipment::count());

        Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail) use ($booking) {
            $files = $mail->message->attachments;

            return $mail->hasTo('ama@example.com')
                && $mail->message->key === 'booking_confirmed'
                && count($files) === 1
                && $files[0]['name'] === 'ticket-WS-'.$booking->id.'.pdf'
                && $files[0]['ticket_booking_id'] === $booking->id
                // The attachment the mailer would send is the rendered PDF.
                && str_starts_with($mail->attachments()[0]->attachWith(fn ($path) => '', fn ($data) => $data()), '%PDF');
        });
        // One email: the ticket, not an order confirmation as well.
        Mail::assertNotSent(TransactionalMail::class, fn ($m) => $m->message->key === 'order_placed');

        // The confirmation page sees the booking and its ticket.
        $this->getJson("/api/v1/orders/{$order->reference}")
            ->assertJsonPath('data.booking.status', 'confirmed')
            ->assertJsonPath('data.booking.attendee_count', 2);
    }

    public function test_the_ticket_link_is_signed(): void
    {
        $session = $this->paidSession();
        $response = $this->book($session, 1)->assertCreated();
        $this->payByWebhook(Order::where('reference', $response->json('order_reference'))->firstOrFail());
        $url = $this->getJson('/api/v1/orders/'.$response->json('order_reference'))->json('data.booking.ticket_url');

        $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(strtok($url, '?'))->assertForbidden();
    }

    public function test_an_unpaid_hold_lapses_and_frees_the_seats(): void
    {
        $session = $this->paidSession(capacity: 2);
        $response = $this->book($session, 2)->assertCreated();
        $this->assertSame(0, $session->fresh()->remaining_capacity);

        $this->travel(BookingCheckout::HOLD_MINUTES + 1)->minutes();
        $this->release();

        $this->assertSame('expired', Booking::findOrFail($response->json('data.id'))->status);
        $this->assertSame(2, $session->fresh()->remaining_capacity);
    }

    /** Paid after the hold lapsed and someone else took the seats: confirmed, and flagged. */
    public function test_a_late_payment_into_a_full_session_is_flagged(): void
    {
        $session = $this->paidSession(capacity: 2);
        $late = $this->book($session, 2)->assertCreated();
        $this->travel(BookingCheckout::HOLD_MINUTES + 1)->minutes();
        $this->release();
        Booking::factory()->workshop($session)->create(['status' => 'confirmed', 'details' => ['name' => 'X', 'email' => 'x@example.com', 'phone' => '1', 'attendee_count' => 2]]);

        $this->payByWebhook(Order::where('reference', $late->json('order_reference'))->firstOrFail());

        $booking = Booking::findOrFail($late->json('data.id'));
        $this->assertSame('confirmed', $booking->status);
        $this->assertTrue($booking->needs_attention);
    }

    public function test_a_full_session_without_a_waitlist_is_sold_out(): void
    {
        $session = $this->paidSession(capacity: 2);
        $this->book($session, 2)->assertCreated();

        $this->book($session, 1)->assertStatus(409)->assertJsonPath('message', 'This session is sold out.');

        $smaller = $this->paidSession(capacity: 3);
        $this->book($smaller, 2)->assertCreated();
        $this->book($smaller, 2)->assertStatus(409)->assertJsonPath('remaining_capacity', 1);
    }

    public function test_with_a_waitlist_a_full_session_waitlists_without_payment(): void
    {
        $session = $this->paidSession(capacity: 1, waitlist: true);
        $this->book($session, 1)->assertCreated();

        $response = $this->book($session, 1)->assertCreated();

        $this->assertSame('waitlisted', $response->json('data.status'));
        $this->assertNull($response->json('payment'));
        $this->assertSame(1, Order::count());
    }

    /** At a paid event, promotion offers the seat with a pay link; the ticket follows payment. */
    public function test_a_paid_waitlist_offer_sends_a_pay_link(): void
    {
        Mail::fake();
        $session = $this->paidSession(capacity: 1, waitlist: true);
        $first = $this->book($session, 1)->assertCreated();
        $waiting = $this->book($session, 1)->assertCreated();
        Booking::findOrFail($first->json('data.id'))->update(['status' => 'cancelled']);

        $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->patchJson('/api/v1/admin/bookings/'.$waiting->json('data.id'), ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_payment');

        $booking = Booking::findOrFail($waiting->json('data.id'));
        $this->assertNotNull($booking->order_id);
        $this->assertTrue($booking->hold_expires_at->greaterThan(now()->addHours(BookingCheckout::OFFER_HOURS - 1)));
        Mail::assertSent(TransactionalMail::class, fn ($m) => $m->message->key === 'waitlist_promoted' && ! empty($m->message->data['payUrl']));
    }

    public function test_seats_are_counted_in_people(): void
    {
        $session = WorkshopSession::factory()->create(['capacity' => 10]);
        Booking::factory()->workshop($session)->create(['status' => 'confirmed', 'details' => ['name' => 'A', 'email' => 'a@example.com', 'phone' => '1', 'attendee_count' => 4]]);
        Booking::factory()->workshop($session)->create(['status' => 'waitlisted', 'details' => ['name' => 'B', 'email' => 'b@example.com', 'phone' => '1', 'attendee_count' => 3]]);

        $this->assertSame(4, $session->fresh()->occupied_seats);
        $this->assertSame(6, $session->fresh()->remaining_capacity);
    }

    public function test_check_in_works_once_and_only_for_a_confirmed_booking(): void
    {
        $session = $this->paidSession();
        $response = $this->book($session, 2)->assertCreated();
        $booking = Booking::findOrFail($response->json('data.id'));
        $staff = AdminUser::factory()->create(['role' => 'staff']);

        $this->actingAs($staff, 'admin')->postJson("/api/v1/admin/bookings/{$booking->id}/check-in")
            ->assertUnprocessable()->assertJsonPath('message', 'This booking hasn’t been paid for.');

        $this->payByWebhook(Order::where('reference', $response->json('order_reference'))->firstOrFail());
        $code = $booking->fresh()->ticket_code;

        $this->actingAs($staff, 'admin')->getJson('/api/v1/admin/bookings/check-in/'.strtolower($code))
            ->assertOk()->assertJsonPath('data.id', $booking->id);
        $this->actingAs($staff, 'admin')->postJson("/api/v1/admin/bookings/{$booking->id}/check-in")
            ->assertOk()->assertJsonPath('data.checked_in_by', $staff->name);
        $this->actingAs($staff, 'admin')->postJson("/api/v1/admin/bookings/{$booking->id}/check-in")
            ->assertStatus(409);
        $this->actingAs($staff, 'admin')->getJson('/api/v1/admin/bookings/check-in/NOPE')->assertNotFound();
    }

    public function test_dollars_are_charged_at_the_locked_rate(): void
    {
        FxRate::factory()->create(['rate' => 0.075, 'fetched_at' => now()]);
        $session = $this->paidSession(priceGhs: 20_000);

        $response = $this->book($session, 2, 'USD')->assertCreated();

        $order = Order::where('reference', $response->json('order_reference'))->firstOrFail();
        $this->assertSame('USD', $order->currency);
        $this->assertSame(3_000, $order->total);
    }

    public function test_a_free_event_is_unchanged(): void
    {
        Mail::fake();
        $session = WorkshopSession::factory()->create(['capacity' => 10]);

        $response = $this->book($session, 2)->assertCreated();

        $this->assertSame('pending', $response->json('data.status'));
        $this->assertSame(0, Order::count());
        Mail::assertSent(TransactionalMail::class, fn ($m) => $m->message->key === 'booking_submitted');
    }
}

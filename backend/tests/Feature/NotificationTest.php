<?php

namespace Tests\Feature;

use App\Contracts\NotificationChannel;
use App\Events\OrderPaid;
use App\Jobs\SendBookingNotification;
use App\Jobs\SendOrderNotification;
use App\Listeners\SendOrderConfirmationNotification;
use App\Mail\TransactionalMail;
use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\WorkshopSession;
use App\Notifications\NotificationRecipient;
use App\Notifications\TransactionalMessages;
use App\Services\Notifications\FishAfricaSmsService;
use App\Services\Notifications\LogSmsChannel;
use App\Services\Notifications\MailChannel;
use App\Services\Notifications\NotificationChannelFactory;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Feature 8. The acceptance criterion that drives most of this file is the
 * non-fatal one: a notification failure must never take down the order or
 * booking that triggered it.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        return Order::factory()->create([
            'reference' => 'GCT-TEST-1',
            'currency' => 'GHS',
            'total' => 25_000,
            'shipping_address' => [
                'full_name' => 'Ama Serwaa',
                'email' => 'ama@example.com',
                'phone' => '0241234567',
                'line1' => '12 Oxford Street',
                'city' => 'Accra',
                'country' => 'GH',
            ],
            ...$attributes,
        ]);
    }

    // ---------------------------------------------------------------- triggers

    public function test_a_paid_order_queues_a_confirmation(): void
    {
        Bus::fake();

        (new SendOrderConfirmationNotification)->handle(new OrderPaid($this->order()));

        Bus::assertDispatched(
            SendOrderNotification::class,
            fn (SendOrderNotification $job) => $job->reason === 'paid',
        );
    }

    public function test_marking_an_order_shipped_queues_a_dispatch_notice(): void
    {
        Bus::fake();
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $order = $this->order(['status' => 'processing']);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/orders/{$order->reference}", ['status' => 'shipped'])
            ->assertOk();

        Bus::assertDispatched(
            SendOrderNotification::class,
            fn (SendOrderNotification $job) => $job->reason === 'shipped',
        );
    }

    public function test_re_saving_an_already_shipped_order_does_not_notify_again(): void
    {
        Bus::fake();
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $order = $this->order(['status' => 'shipped']);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/orders/{$order->reference}", ['status' => 'shipped'])
            ->assertOk();

        // A customer told twice that their order shipped has been given wrong
        // information, not extra service.
        Bus::assertNotDispatched(SendOrderNotification::class);
    }

    public function test_submitting_a_booking_queues_a_notification(): void
    {
        Bus::fake();
        $session = WorkshopSession::factory()->create(['capacity' => 5]);

        $this->postJson('/api/v1/bookings', [
            'type' => 'workshop',
            'workshop_session_id' => $session->id,
            'details' => [
                'name' => 'Kofi Mensah',
                'email' => 'kofi@example.com',
                'phone' => '0209876543',
                'attendee_count' => 2,
            ],
        ])->assertCreated();

        Bus::assertDispatched(
            SendBookingNotification::class,
            fn (SendBookingNotification $job) => $job->reason === 'submitted',
        );
    }

    public function test_a_diy_booking_queues_a_notification_too(): void
    {
        Bus::fake();

        $this->postJson('/api/v1/bookings', [
            'type' => 'diy_order',
            'details' => [
                'name' => 'Kofi Mensah',
                'email' => 'kofi@example.com',
                'phone' => '0209876543',
                'size' => '42',
                'foot_length' => 27.5,
                'fulfilment' => 'pickup',
            ],
        ])->assertCreated();

        Bus::assertDispatched(SendBookingNotification::class);
    }

    public function test_confirming_a_booking_queues_a_confirmation(): void
    {
        Bus::fake();
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $booking = Booking::factory()->create(['status' => 'pending']);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/bookings/{$booking->id}", ['status' => 'confirmed'])
            ->assertOk();

        Bus::assertDispatched(
            SendBookingNotification::class,
            fn (SendBookingNotification $job) => $job->reason === 'confirmed',
        );
    }

    public function test_re_confirming_a_booking_does_not_notify_again(): void
    {
        Bus::fake();
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $booking = Booking::factory()->create(['status' => 'confirmed']);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/bookings/{$booking->id}", ['status' => 'confirmed'])
            ->assertOk();

        Bus::assertNotDispatched(SendBookingNotification::class);
    }

    public function test_promoting_from_the_waitlist_queues_the_promotion_notice(): void
    {
        Bus::fake();
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $session = WorkshopSession::factory()->create(['capacity' => 5]);
        $booking = Booking::factory()->create([
            'status' => 'waitlisted',
            'workshop_session_id' => $session->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/bookings/{$booking->id}", ['status' => 'confirmed'])
            ->assertOk();

        // Promotion, not plain confirmation: the customer's news is that a
        // place opened up, which is a different message.
        Bus::assertDispatched(
            SendBookingNotification::class,
            fn (SendBookingNotification $job) => $job->reason === 'waitlist_promoted',
        );
    }

    public function test_a_refused_promotion_notifies_nobody(): void
    {
        Bus::fake();
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $session = WorkshopSession::factory()->create(['capacity' => 1]);
        Booking::factory()->create(['status' => 'confirmed', 'workshop_session_id' => $session->id]);
        $waitlisted = Booking::factory()->create([
            'status' => 'waitlisted',
            'workshop_session_id' => $session->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/bookings/{$waitlisted->id}", ['status' => 'confirmed'])
            ->assertStatus(409);

        // The session was full, so nothing changed — telling someone they got
        // a place they did not get would be the worst possible failure here.
        Bus::assertNotDispatched(SendBookingNotification::class);
    }

    // ---------------------------------------------------------------- delivery

    public function test_the_order_confirmation_email_is_addressed_and_rendered(): void
    {
        Mail::fake();
        $order = $this->order();
        $product = Product::factory()->create();
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'product_name' => 'Kentehene Slide',
            'quantity' => 2,
            'unit_price' => 12_500,
            'currency' => 'GHS',
        ]);

        (new SendOrderNotification($order, 'paid'))->handle(app(NotificationDispatcher::class));

        Mail::assertSent(
            TransactionalMail::class,
            fn (TransactionalMail $mail) => $mail->hasTo('ama@example.com')
                && $mail->message->key === 'order_placed'
                && str_contains($mail->message->subject, 'GCT-TEST-1'),
        );
    }

    public function test_a_registered_customers_own_details_win_over_the_shipping_address(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'account@example.com',
            'phone' => '0501112222',
            'name' => 'Yaa Boateng',
        ]);
        $order = $this->order(['customer_id' => $customer->id]);

        $recipient = NotificationRecipient::forOrder($order->load('customer'));

        // A delivery address can be a gift going to someone else; the account
        // is the record the customer maintains.
        $this->assertSame('account@example.com', $recipient->email);
        $this->assertSame('0501112222', $recipient->phone);
        $this->assertSame('Yaa', $recipient->firstName());
    }

    public function test_a_guest_order_falls_back_to_the_shipping_address(): void
    {
        $recipient = NotificationRecipient::forOrder($this->order());

        $this->assertSame('ama@example.com', $recipient->email);
        $this->assertSame('0241234567', $recipient->phone);
        $this->assertSame('Ama', $recipient->firstName());
    }

    // ------------------------------------------------------------- resilience

    public function test_an_sms_failure_does_not_stop_the_email(): void
    {
        Mail::fake();
        Log::spy();

        // A channel that always throws, standing in for a gateway outage.
        $exploding = new class implements NotificationChannel
        {
            public function name(): string
            {
                return 'sms';
            }

            public function send($to, $message): bool
            {
                throw new \RuntimeException('gateway down');
            }
        };

        $dispatcher = new NotificationDispatcher([
            new MailChannel,
            $exploding,
        ]);

        $order = $this->order();
        $results = $dispatcher->send(
            NotificationRecipient::forOrder($order),
            TransactionalMessages::orderConfirmation($order),
        );

        // Feature 8: notification failure is non-fatal, and the other channel
        // still delivers.
        $this->assertTrue($results['mail']);
        $this->assertFalse($results['sms']);
        Mail::assertSent(TransactionalMail::class);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_missing_phone_number_is_not_treated_as_a_failure(): void
    {
        $order = $this->order(['shipping_address' => [
            'full_name' => 'Ama Serwaa',
            'email' => 'ama@example.com',
            'line1' => '12 Oxford Street',
            'city' => 'Accra',
            'country' => 'GH',
        ]]);

        $sent = (new LogSmsChannel)->send(
            NotificationRecipient::forOrder($order),
            TransactionalMessages::orderConfirmation($order),
        );

        // An ordinary case, not an error: plenty of customers give no phone.
        $this->assertFalse($sent);
    }

    public function test_the_sms_channel_falls_back_to_logging_without_credentials(): void
    {
        config(['services.fish_africa.app_id' => null, 'services.fish_africa.app_secret' => null]);
        Http::fake();
        Log::spy();

        $this->assertFalse(FishAfricaSmsService::isConfigured());

        $order = $this->order();
        (new NotificationChannelFactory)->make()->send(
            NotificationRecipient::forOrder($order),
            TransactionalMessages::orderConfirmation($order),
        );

        // Nothing was sent to a gateway, and the message was written to the
        // log in full instead — a system that looks like it is texting
        // customers and is not should be noisy about it, the same call
        // FakeGateway makes for payments.
        Http::assertNothingSent();
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $m) => str_contains($m, 'SMS not sent'))
            ->once();
    }

    /**
     * Mail::fake() never renders the view, so every other test in this file
     * would still pass with a broken Blade template. This one actually builds
     * the HTML.
     */
    public function test_every_email_template_renders(): void
    {
        $order = $this->order();
        OrderItem::factory()->for($order)->create([
            'product_id' => Product::factory()->create()->id,
            'product_name' => 'Kentehene Slide',
            'quantity' => 2,
            'unit_price' => 12_500,
            'currency' => 'GHS',
        ]);
        $session = WorkshopSession::factory()->create(['capacity' => 5]);
        $booking = Booking::factory()->create([
            'status' => 'pending',
            'workshop_session_id' => $session->id,
        ]);
        $waitlisted = Booking::factory()->create([
            'status' => 'waitlisted',
            'workshop_session_id' => $session->id,
        ]);

        $cases = [
            TransactionalMessages::orderConfirmation($order->load('items')),
            TransactionalMessages::orderShipped($order),
            TransactionalMessages::bookingSubmitted($booking->load('workshopSession.workshopType')),
            TransactionalMessages::bookingSubmitted($waitlisted->load('workshopSession.workshopType')),
            TransactionalMessages::bookingConfirmed($booking->load('workshopSession.workshopType')),
            TransactionalMessages::waitlistPromoted($waitlisted->load('workshopSession.workshopType')),
        ];

        foreach ($cases as $message) {
            $html = (new TransactionalMail($message, NotificationRecipient::forOrder($order)))->render();

            $this->assertNotEmpty($html, "{$message->key} rendered empty.");
            // The greeting is the one thing every template shares, so it is
            // the cheapest proof the data actually reached the view.
            $this->assertStringContainsString('Ama', $html, "{$message->key} lost its greeting.");
        }
    }

    public function test_the_dispatch_email_reads_correctly_without_a_tracking_reference(): void
    {
        // Feature 5 is still a static rate table, so most orders have no
        // delivery_reference. The template must not trail an empty label.
        $order = $this->order(['delivery_reference' => null]);
        $html = (new TransactionalMail(
            TransactionalMessages::orderShipped($order),
            NotificationRecipient::forOrder($order),
        ))->render();

        $this->assertStringNotContainsString('Tracking reference', $html);
        $this->assertStringContainsString('as soon as the courier provides one', $html);

        $tracked = $this->order(['reference' => 'GCT-TEST-2', 'delivery_reference' => 'DHL-99']);
        $trackedHtml = (new TransactionalMail(
            TransactionalMessages::orderShipped($tracked),
            NotificationRecipient::forOrder($tracked),
        ))->render();

        $this->assertStringContainsString('DHL-99', $trackedHtml);
    }

    public function test_fish_africa_is_used_once_credentials_exist(): void
    {
        config([
            'services.fish_africa.app_id' => 'app-id',
            'services.fish_africa.app_secret' => 'app-secret',
            'services.fish_africa.base_url' => 'https://api.letsfish.africa',
        ]);
        Http::fake(['api.letsfish.africa/*' => Http::response(['status' => 'ok'])]);

        $this->assertTrue(FishAfricaSmsService::isConfigured());

        $order = $this->order();
        $sent = (new FishAfricaSmsService)->send(
            NotificationRecipient::forOrder($order),
            TransactionalMessages::orderConfirmation($order),
        );

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) => $request['to'] === '+233241234567'
            && str_contains($request['message'], 'GCT-TEST-1'));
    }

    /**
     * Ghanaian numbers are written locally with a leading zero; an SMS gateway
     * needs E.164. Getting this wrong means every domestic text silently goes
     * nowhere, which is the kind of failure nobody notices until a customer
     * complains.
     */
    public function test_phone_numbers_are_normalised_to_e164(): void
    {
        $this->assertSame('+233241234567', FishAfricaSmsService::normalisePhone('0241234567'));
        $this->assertSame('+233241234567', FishAfricaSmsService::normalisePhone('024 123 4567'));
        $this->assertSame('+233241234567', FishAfricaSmsService::normalisePhone('233241234567'));
        $this->assertSame('+233241234567', FishAfricaSmsService::normalisePhone('+233 24 123 4567'));
        $this->assertSame('+447700900123', FishAfricaSmsService::normalisePhone('+44 7700 900123'));
        $this->assertSame('+447700900123', FishAfricaSmsService::normalisePhone('00447700900123'));

        // Unusable rather than guessed at.
        $this->assertNull(FishAfricaSmsService::normalisePhone('12345'));
        $this->assertNull(FishAfricaSmsService::normalisePhone('not a number'));
    }

    public function test_every_message_stays_within_one_sms_segment_where_it_can(): void
    {
        $order = $this->order();
        $booking = Booking::factory()->create(['status' => 'pending']);

        foreach ([
            TransactionalMessages::orderConfirmation($order),
            TransactionalMessages::orderShipped($order),
            TransactionalMessages::bookingSubmitted($booking),
            TransactionalMessages::bookingConfirmed($booking),
            TransactionalMessages::waitlistPromoted($booking),
        ] as $message) {
            $this->assertNotNull($message->sms, "{$message->key} has no SMS body.");
            // Texts are billed per segment, so a message that quietly runs to
            // three of them is a cost decision nobody made.
            $this->assertLessThanOrEqual(
                320,
                mb_strlen($message->sms),
                "{$message->key} SMS runs past two segments.",
            );
        }
    }

    public function test_the_confirmation_quotes_the_documented_timeframes(): void
    {
        $message = TransactionalMessages::orderConfirmation($this->order());

        // §22.3 forbids changing shipping timelines and return windows. If
        // somebody edits the copy to a different number, this fails.
        $this->assertStringContainsString('48 hours', $message->sms);
        $this->assertSame(7, $message->data['returnWindowDays']);
    }
}

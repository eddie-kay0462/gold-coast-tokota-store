<?php

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Mail\TransactionalMail;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProcessedWebhookEvent;
use App\Models\Product;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Paystack — the gateway §13 of the brand document names — and its webhook.
 *
 * The webhook is one of the two places in this codebase where a bug costs real
 * money (the other is the checkout race), so the cases below are the ones that
 * cost something: a forged signature, a replayed delivery, and a payment for
 * less than the order total.
 */
class PaystackPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_pretend_secret';

    private function configureGateway(): void
    {
        config([
            'services.paystack.secret' => self::SECRET,
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.callback_base_url' => 'https://goldcoasttokota.store',
        ]);
    }

    private function pendingOrder(int $total = 120_000, string $currency = 'GHS'): Order
    {
        $product = Product::factory()->create();
        $inventory = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 5,
            'quantity_reserved' => 2,
        ]);

        $order = Order::factory()->create([
            'status' => 'pending',
            'currency' => $currency,
            'total' => $total,
            'shipping_address' => ['email' => 'ama@example.com', 'country' => 'GH'],
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'inventory_item_id' => $inventory->id,
            'quantity' => 2,
        ]);

        return $order->fresh('items');
    }

    private function signature(array $payload): string
    {
        return hash_hmac('sha512', json_encode($payload), self::SECRET);
    }

    private function chargeSuccess(Order $order, ?int $amount = null, ?string $currency = null): array
    {
        return [
            'event' => 'charge.success',
            'data' => [
                'reference' => $order->reference,
                'amount' => $amount ?? $order->total,
                'currency' => $currency ?? $order->currency,
                'status' => 'success',
            ],
        ];
    }

    // --- gateway selection ----------------------------------------------

    /** §13 and §22.12: one gateway, both currencies. */
    public function test_both_currencies_route_to_paystack(): void
    {
        $this->configureGateway();
        $factory = new PaymentGatewayFactory;

        $this->assertSame('paystack', $factory->for('GHS')->name());
        $this->assertSame('paystack', $factory->for('USD')->name());
        $this->assertInstanceOf(PaystackService::class, $factory->for('USD'));
    }

    /** Without a key the whole checkout path still works — against the fake. */
    public function test_an_unconfigured_gateway_falls_back_to_the_fake(): void
    {
        config(['services.paystack.secret' => null]);

        $gateway = (new PaymentGatewayFactory)->for('GHS');

        $this->assertSame('paystack', $gateway->name());
        $this->assertInstanceOf(FakeGateway::class, $gateway);
    }

    public function test_an_unsupported_currency_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PaymentGatewayFactory)->for('EUR');
    }

    // --- opening a session ----------------------------------------------

    /**
     * Amounts are already in minor units everywhere in this codebase, and
     * Paystack wants minor units. A factor of a hundred here is a real charge
     * of the wrong size, so it is asserted rather than assumed.
     */
    public function test_a_session_sends_the_total_in_minor_units_unscaled(): void
    {
        $this->configureGateway();
        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['reference' => 'GCT-TEST', 'authorization_url' => 'https://checkout.paystack.com/abc'],
        ])]);

        $order = $this->pendingOrder(total: 120_000);
        $session = (new PaystackService)->createSession($order);

        Http::assertSent(function ($request) use ($order) {
            return $request['amount'] === 120_000
                && $request['currency'] === 'GHS'
                && $request['reference'] === $order->reference
                && $request['email'] === 'ama@example.com'
                && str_ends_with($request['callback_url'], "/order-confirmation/{$order->reference}");
        });

        $this->assertSame('https://checkout.paystack.com/abc', $session->authorizationUrl);
        $this->assertNull($session->clientSecret);
    }

    public function test_a_refusal_from_paystack_is_not_swallowed(): void
    {
        $this->configureGateway();
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

        $this->expectException(\RuntimeException::class);

        (new PaystackService)->createSession($this->pendingOrder());
    }

    // --- the webhook ----------------------------------------------------

    public function test_a_signed_charge_success_marks_the_order_paid(): void
    {
        $this->configureGateway();
        Event::fake([OrderPaid::class]);

        $order = $this->pendingOrder();
        $payload = $this->chargeSuccess($order);

        $this->call(
            'POST', '/api/v1/webhooks/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->signature($payload), 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        )->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('paystack', $order->fresh()->payment_gateway);
        Event::assertDispatched(OrderPaid::class);
    }

    /**
     * The whole chain, unfaked: a signed webhook arrives and the customer ends
     * up with a confirmation email. Every other notification test fakes the
     * bus or the event, so this is the only one that would catch the listener
     * being unregistered or the job never reaching the dispatcher.
     */
    public function test_a_signed_payment_actually_sends_the_customer_a_confirmation(): void
    {
        $this->configureGateway();
        Mail::fake();

        $order = $this->pendingOrder();
        $payload = $this->chargeSuccess($order);

        $this->call(
            'POST', '/api/v1/webhooks/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->signature($payload), 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        )->assertOk();

        Mail::assertSent(
            TransactionalMail::class,
            fn (TransactionalMail $mail) => $mail->hasTo('ama@example.com')
                && $mail->message->key === 'order_placed',
        );
    }

    /**
     * The only thing separating a real payment notification from someone
     * marking their own order paid.
     */
    public function test_an_unsigned_webhook_is_refused(): void
    {
        $this->configureGateway();
        $order = $this->pendingOrder();

        $this->postJson('/api/v1/webhooks/paystack', $this->chargeSuccess($order))
            ->assertStatus(401);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_forged_signature_is_refused(): void
    {
        $this->configureGateway();
        $order = $this->pendingOrder();
        $payload = $this->chargeSuccess($order);

        $this->call(
            'POST', '/api/v1/webhooks/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => hash_hmac('sha512', json_encode($payload), 'wrong-key'), 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        )->assertStatus(401);

        $this->assertSame('pending', $order->fresh()->status);
    }

    /**
     * Paystack retries anything it does not get a 200 for. A replay that ran
     * the handler twice would finalise the same reservation twice — one sale,
     * two decrements.
     */
    public function test_a_replayed_webhook_creates_no_second_payment_and_no_double_decrement(): void
    {
        $this->configureGateway();

        $order = $this->pendingOrder();
        $payload = $this->chargeSuccess($order);
        $inventory = $order->items->first()->inventoryItem;
        $availableBefore = $inventory->quantity_available;

        $deliver = fn () => $this->call(
            'POST', '/api/v1/webhooks/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->signature($payload), 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        );

        $deliver()->assertOk();
        $deliver()->assertOk();

        $this->assertSame(1, ProcessedWebhookEvent::query()->count());
        $this->assertSame('paid', $order->fresh()->status);
        // Two units sold, decremented once.
        $this->assertSame($availableBefore - 2, $inventory->fresh()->quantity_available);
        $this->assertSame(0, $inventory->fresh()->quantity_reserved);
    }

    /** A partial payment is not a completed sale. */
    public function test_a_payment_for_less_than_the_total_does_not_mark_the_order_paid(): void
    {
        $this->configureGateway();
        Event::fake([OrderPaid::class]);

        $order = $this->pendingOrder(total: 120_000);
        $payload = $this->chargeSuccess($order, amount: 100);

        $this->call(
            'POST', '/api/v1/webhooks/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->signature($payload), 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        )->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        Event::assertNotDispatched(OrderPaid::class);
    }

    /** Neither is a payment in a currency the order was not priced in. */
    public function test_a_payment_in_the_wrong_currency_is_rejected(): void
    {
        $this->configureGateway();

        $order = $this->pendingOrder(total: 120_000, currency: 'GHS');
        $payload = $this->chargeSuccess($order, currency: 'USD');

        $this->call(
            'POST', '/api/v1/webhooks/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->signature($payload), 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        )->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
    }

    /** An event we do not act on is still acknowledged, or Paystack retries it forever. */
    public function test_an_uninteresting_event_is_acknowledged(): void
    {
        $this->configureGateway();
        $payload = ['event' => 'transfer.success', 'data' => ['reference' => 'unrelated-ref']];

        $this->call(
            'POST', '/api/v1/webhooks/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->signature($payload), 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        )->assertOk();
    }

    public function test_a_payment_for_an_unknown_order_is_acknowledged_not_errored(): void
    {
        $this->configureGateway();
        $payload = [
            'event' => 'charge.success',
            'data' => ['reference' => 'GCT-NOTAREALORDER', 'amount' => 1000, 'currency' => 'GHS'],
        ];

        $this->call(
            'POST', '/api/v1/webhooks/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->signature($payload), 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        )->assertOk();
    }
}

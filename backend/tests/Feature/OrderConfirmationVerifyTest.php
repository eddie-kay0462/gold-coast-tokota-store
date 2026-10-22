<?php

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The confirmation page's poll asks Paystack directly while an order reads
 * pending, so a payment confirms without waiting for the webhook.
 */
class OrderConfirmationVerifyTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_pretend_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret' => self::SECRET,
            'services.paystack.base_url' => 'https://api.paystack.co',
        ]);
        Http::preventStrayRequests();
    }

    private function pendingOrder(array $attributes = []): Order
    {
        $product = Product::factory()->create();
        $inventory = InventoryItem::factory()->create(['product_id' => $product->id, 'quantity_available' => 5, 'quantity_reserved' => 0]);
        $order = Order::factory()->create([
            'status' => 'pending', 'currency' => 'GHS', 'total' => 60_000,
            'payment_gateway' => 'paystack', 'reservation_expires_at' => now()->addMinutes(15),
            ...$attributes,
        ]);
        $order->update(['payment_reference' => $attributes['payment_reference'] ?? $order->reference]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'inventory_item_id' => $inventory->id, 'quantity' => 1]);
        app(InventoryReservationService::class)->reserve($inventory, 1, now()->addMinutes(15), $order);

        return $order->fresh();
    }

    private function verifyAnswers(string $status, int $amount = 60_000, string $currency = 'GHS'): void
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true, 'message' => 'Verification successful',
            'data' => ['status' => $status, 'amount' => $amount, 'currency' => $currency],
        ])]);
    }

    public function test_a_paid_order_confirms_on_the_poll_without_waiting_for_the_webhook(): void
    {
        $this->verifyAnswers('success');
        $order = $this->pendingOrder();

        $this->getJson("/api/v1/orders/{$order->reference}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame('paid', $order->fresh()->status);
        // The ordinary paid path ran: the held pair has left the shelf.
        $this->assertSame(4, InventoryItem::query()->sole()->quantity_available);
    }

    public function test_paystack_is_asked_at_most_every_few_seconds_per_order(): void
    {
        $this->verifyAnswers('abandoned');
        $order = $this->pendingOrder();

        $this->getJson("/api/v1/orders/{$order->reference}")->assertJsonPath('data.status', 'pending');
        $this->getJson("/api/v1/orders/{$order->reference}")->assertJsonPath('data.status', 'pending');
        Http::assertSentCount(1);

        $this->travel(9)->seconds();
        $this->getJson("/api/v1/orders/{$order->reference}");
        Http::assertSentCount(2);
    }

    public function test_the_webhook_after_a_verified_payment_changes_nothing(): void
    {
        Event::fake([OrderPaid::class]);
        $this->verifyAnswers('success');
        $order = $this->pendingOrder();

        $this->getJson("/api/v1/orders/{$order->reference}")->assertJsonPath('data.status', 'paid');

        $payload = ['event' => 'charge.success', 'data' => ['reference' => $order->reference, 'amount' => 60_000, 'currency' => 'GHS', 'status' => 'success']];
        $body = json_encode($payload);
        $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::SECRET),
        ], $body)->assertOk();

        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_a_resent_links_reference_is_checked_first(): void
    {
        $order = $this->pendingOrder();
        $order->update(['payment_reference' => "{$order->reference}-2"]);
        Http::fake([
            "api.paystack.co/transaction/verify/{$order->reference}-2" => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 60_000, 'currency' => 'GHS']]),
            '*' => Http::response(['status' => true, 'data' => ['status' => 'abandoned', 'amount' => 60_000, 'currency' => 'GHS']]),
        ]);

        $this->getJson("/api/v1/orders/{$order->reference}")->assertJsonPath('data.status', 'paid');
        $this->assertSame("{$order->reference}-2", $order->fresh()->payment_reference);
    }

    public function test_an_unpaid_or_short_paid_order_stays_pending(): void
    {
        $this->verifyAnswers('success', amount: 10_000);
        $order = $this->pendingOrder();

        $this->getJson("/api/v1/orders/{$order->reference}")->assertJsonPath('data.status', 'pending');
    }

    public function test_orders_not_on_paystack_are_never_checked(): void
    {
        $order = $this->pendingOrder(['payment_gateway' => 'fake']);

        $this->getJson("/api/v1/orders/{$order->reference}")->assertOk()->assertJsonPath('data.status', 'pending');
        Http::assertNothingSent();
    }
}

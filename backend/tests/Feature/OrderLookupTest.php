<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /orders/{reference}` — what the order-confirmation page polls.
 */
class OrderLookupTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        return Order::create(array_replace([
            'reference' => 'GCT-ABCDEFGHJKLM',
            'currency' => 'GHS',
            'subtotal' => 60_000,
            'shipping_cost' => 2_500,
            'tax' => 0,
            'total' => 62_500,
            'status' => 'pending',
            'delivery_provider' => 'yango',
            'shipping_address' => ['full_name' => 'Ama Serwaa', 'country' => 'GH'],
        ], $overrides));
    }

    public function test_an_order_is_readable_by_its_reference(): void
    {
        $order = $this->order();

        $response = $this->getJson("/api/v1/orders/{$order->reference}");

        $response->assertOk();
        $response->assertJsonPath('data.reference', $order->reference);
        $response->assertJsonPath('data.total', 62_500);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.shipping_address.full_name', 'Ama Serwaa');
    }

    /**
     * Guest checkout means this endpoint cannot sit behind auth, so the lookup
     * key must not be enumerable. An order carries a name, an email and a home
     * address — a sequential id would hand the whole table to anyone counting.
     */
    public function test_an_order_is_not_readable_by_its_numeric_id(): void
    {
        $order = $this->order();

        $this->getJson("/api/v1/orders/{$order->id}")->assertNotFound();
    }

    public function test_an_unknown_reference_is_a_404(): void
    {
        $this->getJson('/api/v1/orders/GCT-ZZZZZZZZZZZZ')->assertNotFound();
    }

    public function test_line_items_come_back_with_their_snapshots(): void
    {
        $order = $this->order();
        $product = Product::factory()->create(['images' => ['/design/product-kentehene.png']]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'The Kentehene Collection',
            'variant_label' => '42 | Brown',
            'quantity' => 2,
            'unit_price' => 30_000,
            'currency' => 'GHS',
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->reference}");

        $response->assertOk();
        $response->assertJsonPath('data.items.0.name', 'The Kentehene Collection');
        $response->assertJsonPath('data.items.0.variant_label', '42 | Brown');
        $response->assertJsonPath('data.items.0.quantity', 2);
        $response->assertJsonPath('data.items.0.slug', $product->slug);
        $response->assertJsonPath('data.items.0.image', '/design/product-kentehene.png');
    }

    /** product_id is nullOnDelete, so a receipt has to outlive its product. */
    public function test_a_line_item_survives_its_product_being_deleted(): void
    {
        $order = $this->order();
        $product = Product::factory()->create();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'The Kentehene Collection',
            'quantity' => 1,
            'unit_price' => 60_000,
            'currency' => 'GHS',
        ]);

        $product->delete();

        $response = $this->getJson("/api/v1/orders/{$order->reference}");

        $response->assertOk();
        $response->assertJsonPath('data.items.0.name', 'The Kentehene Collection');
        $response->assertJsonPath('data.items.0.slug', null);
    }

    /** The confirmation page polls until the webhook settles the status. */
    public function test_the_status_reflects_a_webhook_landing_after_the_redirect(): void
    {
        $order = $this->order();

        $this->getJson("/api/v1/orders/{$order->reference}")
            ->assertJsonPath('data.status', 'pending');

        $order->update(['status' => 'paid']);

        $this->getJson("/api/v1/orders/{$order->reference}")
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_usd_orders_report_the_rate_they_were_charged_at(): void
    {
        $order = $this->order(['currency' => 'USD', 'fx_rate_applied' => 0.08]);

        $this->getJson("/api/v1/orders/{$order->reference}")
            ->assertOk()
            ->assertJsonPath('data.fx_rate_applied', 0.08);
    }
}

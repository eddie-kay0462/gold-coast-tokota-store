<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Returns and exchanges, against §9 and §21 of the brand document.
 *
 * Each test names the clause it is holding the code to, because every rule
 * here is transcribed policy rather than a design decision — §22.2 and §22.3
 * forbid inventing or altering any of them.
 */
class ReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'staff']);
    }

    private function admin(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'admin']);
    }

    /** @param  array<string, mixed>  $productAttributes */
    private function deliveredOrder(array $attributes = [], array $productAttributes = []): Order
    {
        $order = Order::factory()->create([
            'status' => 'delivered',
            'delivered_at' => now()->subDay(),
            'currency' => 'GHS',
            'total' => 120_000,
            ...$attributes,
        ]);

        $product = Product::factory()->create($productAttributes);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
        ]);

        return $order->fresh('items');
    }

    // --- the window -----------------------------------------------------

    /** §9: "Returns are accepted within 7 days of receiving the order." */
    public function test_a_return_inside_the_seven_day_window_is_eligible(): void
    {
        $order = $this->deliveredOrder(['delivered_at' => now()->subDays(6)]);

        $response = $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'defective',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_eligible', true);
        $response->assertJsonPath('data.ineligible_reason', null);
    }

    public function test_a_return_after_seven_days_is_outside_the_window(): void
    {
        $order = $this->deliveredOrder(['delivered_at' => now()->subDays(8)]);

        $response = $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'defective',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_eligible', false);
        $this->assertStringContainsString('7-day return window closed', $response->json('data.ineligible_reason'));
    }

    /**
     * The clock runs from receipt, not from despatch — so an order still in
     * transit has no window to be inside or outside of.
     */
    public function test_an_undelivered_order_has_not_started_its_window(): void
    {
        $order = $this->deliveredOrder(['status' => 'shipped', 'delivered_at' => null]);

        $response = $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'defective',
        ]);

        $response->assertJsonPath('data.is_eligible', false);
        $response->assertJsonPath('data.window_closes_at', null);
    }

    /** Marking an order delivered is what starts the clock. */
    public function test_marking_an_order_delivered_stamps_the_receipt_date(): void
    {
        $order = Order::factory()->create(['status' => 'shipped']);

        $this->actingAs($this->staff(), 'admin')
            ->patchJson("/api/v1/admin/orders/{$order->reference}", ['status' => 'delivered'])
            ->assertOk();

        $this->assertNotNull($order->fresh()->delivered_at);
    }

    /** A correction back and forth must not quietly extend the window. */
    public function test_the_receipt_date_is_not_overwritten_by_a_second_delivery(): void
    {
        $order = Order::factory()->create(['status' => 'delivered', 'delivered_at' => now()->subDays(5)]);
        $original = $order->delivered_at;

        $this->actingAs($this->staff(), 'admin')
            ->patchJson("/api/v1/admin/orders/{$order->reference}", ['status' => 'delivered']);

        $this->assertTrue($original->equalTo($order->fresh()->delivered_at));
    }

    // --- non-returnable categories --------------------------------------

    /** §9: custom-made sandals and personalised products cannot be returned. */
    public function test_a_custom_product_is_never_returnable(): void
    {
        $order = $this->deliveredOrder([], ['name' => 'Bespoke Ahenema', 'is_returnable' => false]);

        $response = $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'defective',
        ]);

        $response->assertJsonPath('data.is_eligible', false);
        $this->assertStringContainsString('Bespoke Ahenema', $response->json('data.ineligible_reason'));
    }

    /** §9: "Clearance or sale items, unless defective." */
    public function test_a_sale_item_can_come_back_defective_but_not_for_a_size_swap(): void
    {
        $order = $this->deliveredOrder([], ['base_price_ghs' => 40_000, 'compare_at_ghs' => 60_000]);

        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'defective',
        ])->assertJsonPath('data.is_eligible', true);

        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'size_exchange',
        ])->assertJsonPath('data.ineligible_reason', 'Sale and clearance items can only be returned if they are defective.');
    }

    // --- money ----------------------------------------------------------

    /**
     * §9: "Shipping charges are non-refundable unless the error was caused by
     * Gold Coast Tokota." All three accepted return reasons are its error.
     */
    public function test_a_fault_return_refunds_the_whole_order_including_shipping(): void
    {
        $order = $this->deliveredOrder(['total' => 120_000]);

        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'damaged_in_transit',
        ])->assertJsonPath('data.refund_amount.amount', 120_000);
    }

    /** An exchange swaps the pair; nothing is refunded. */
    public function test_a_size_exchange_refunds_nothing(): void
    {
        $order = $this->deliveredOrder();

        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'size_exchange',
        ])->assertJsonPath('data.refund_amount', null);
    }

    /** §9: "Approved refunds are processed within 7-14 business days." */
    public function test_the_published_refund_window_travels_with_the_request(): void
    {
        $order = $this->deliveredOrder();

        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'defective',
        ])->assertJsonPath('data.refund_processing_days', [7, 14]);
    }

    // --- who may do what ------------------------------------------------

    public function test_staff_can_record_a_return_but_not_resolve_one(): void
    {
        $order = $this->deliveredOrder();
        $return = ReturnRequest::factory()->create(['order_id' => $order->id]);

        $this->actingAs($this->staff(), 'admin')
            ->getJson('/api/v1/admin/returns')->assertOk();

        $this->actingAs($this->staff(), 'admin')
            ->patchJson("/api/v1/admin/returns/{$return->id}", ['status' => 'approved'])
            ->assertForbidden();
    }

    public function test_an_admin_resolving_a_return_as_refunded_refunds_the_order(): void
    {
        $order = $this->deliveredOrder();
        $return = ReturnRequest::factory()->create(['order_id' => $order->id]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->patchJson("/api/v1/admin/returns/{$return->id}", ['status' => 'refunded']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'refunded');
        $this->assertNotNull($response->json('data.resolved_at'));
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_a_resolved_return_is_not_reopened(): void
    {
        $order = $this->deliveredOrder();
        $return = ReturnRequest::factory()->create(['order_id' => $order->id, 'status' => 'rejected', 'resolved_at' => now()]);

        $this->actingAs($this->admin(), 'admin')
            ->patchJson("/api/v1/admin/returns/{$return->id}", ['status' => 'approved'])
            ->assertStatus(422);
    }

    /**
     * §9's "products damaged through misuse" is a judgement made on seeing the
     * pair — no rule can reach it from the order alone, so staff can override.
     */
    public function test_an_admin_can_overrule_the_automatic_assessment(): void
    {
        $order = $this->deliveredOrder();
        $return = ReturnRequest::factory()->create(['order_id' => $order->id, 'is_eligible' => true]);

        $this->actingAs($this->admin(), 'admin')->patchJson("/api/v1/admin/returns/{$return->id}", [
            'status' => 'rejected',
            'is_eligible' => false,
            'ineligible_reason' => 'Sole shows damage from misuse.',
        ])->assertOk()->assertJsonPath('data.ineligible_reason', 'Sole shows damage from misuse.');
    }

    public function test_returns_are_closed_to_guests(): void
    {
        $this->getJson('/api/v1/admin/returns')->assertUnauthorized();
    }

    /** §9 gives WhatsApp as the contact route; there is no customer endpoint. */
    public function test_there_is_no_public_returns_endpoint(): void
    {
        $this->postJson('/api/v1/returns', ['reason' => 'defective'])->assertNotFound();
    }

    public function test_an_unrecognised_reason_is_refused(): void
    {
        $order = $this->deliveredOrder();

        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/returns', [
            'order_reference' => $order->reference,
            'reason' => 'changed_my_mind',
        ])->assertStatus(422)->assertJsonValidationErrors('reason');
    }
}

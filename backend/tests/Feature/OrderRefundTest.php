<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\GiftCard;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Refunds sent from the order drawer, through Paystack (OrderRefunds). */
class OrderRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret' => 'sk_test_x', 'services.paystack.base_url' => 'https://api.paystack.co']);
    }

    private function admin(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'admin']);
    }

    private function paidOrder(array $attributes = []): Order
    {
        return Order::factory()->create([
            'status' => 'delivered', 'currency' => 'GHS', 'total' => 60_000, 'shipping_cost' => 3_500,
            'payment_gateway' => 'paystack', 'payment_reference' => 'ps_ref_1', ...$attributes,
        ]);
    }

    public function test_a_full_refund_goes_to_paystack_and_marks_the_order_refunded(): void
    {
        Http::fake(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['status' => 'pending', 'amount' => 60_000]])]);
        $order = $this->paidOrder();

        $this->actingAs($this->admin(), 'admin')
            ->postJson("/api/v1/admin/orders/{$order->reference}/refund", ['amount' => 60_000])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.refundable_amount.amount', 0);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/refund'
            && $request['transaction'] === 'ps_ref_1' && $request['amount'] === 60_000);
    }

    public function test_a_partial_refund_keeps_the_order_open_and_cannot_be_exceeded(): void
    {
        Http::fake(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['status' => 'pending']])]);
        $order = $this->paidOrder();

        $this->actingAs($this->admin(), 'admin')
            ->postJson("/api/v1/admin/orders/{$order->reference}/refund", ['amount' => 56_500])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.refundable_amount.amount', 3_500);

        $this->actingAs($this->admin(), 'admin')
            ->postJson("/api/v1/admin/orders/{$order->reference}/refund", ['amount' => 5_000])
            ->assertStatus(422);
    }

    public function test_a_paystack_refusal_records_nothing(): void
    {
        Http::fake(['api.paystack.co/refund' => Http::response(['status' => false, 'message' => 'Transaction has been fully reversed'], 400)]);
        $order = $this->paidOrder();

        $this->actingAs($this->admin(), 'admin')
            ->postJson("/api/v1/admin/orders/{$order->reference}/refund", ['amount' => 60_000])
            ->assertStatus(422)
            ->assertJsonFragment(['Paystack didn’t take the refund: Transaction has been fully reversed']);

        $this->assertSame(0, $order->fresh()->refunded_amount);
    }

    public function test_the_gift_card_part_goes_back_on_the_card(): void
    {
        Http::fake(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['status' => 'pending']])]);
        $card = GiftCard::query()->create([
            'code' => 'GCT-AAAA-BBBB-CCCC', 'source' => 'purchase', 'status' => 'active',
            'initial_balance' => 20_000, 'balance' => 0,
        ]);
        $order = $this->paidOrder(['gift_card_id' => $card->id, 'gift_card_amount' => 20_000, 'gift_card_amount_ghs' => 20_000]);

        $this->actingAs($this->admin(), 'admin')
            ->postJson("/api/v1/admin/orders/{$order->reference}/refund", ['amount' => 40_000, 'return_gift_card' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded');

        $this->assertSame(20_000, $card->fresh()->balance);
    }

    public function test_staff_cannot_refund(): void
    {
        $order = $this->paidOrder();

        $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->postJson("/api/v1/admin/orders/{$order->reference}/refund", ['amount' => 100])
            ->assertForbidden();
        Http::assertNothingSent();
    }
}

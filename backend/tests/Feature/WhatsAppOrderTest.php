<?php

namespace Tests\Feature;

use App\Jobs\PruneWhatsAppOrderDrafts;
use App\Jobs\SendOpsAlert;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\SiteSetting;
use App\Models\StockHold;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppOrderDraft;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Order on WhatsApp: the storefront draft, the inbox order builder, payment by link. */
class WhatsAppOrderTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';

    private const PAYSTACK_SECRET = 'sk_test_pretend_secret';

    private const CUSTOMER = '233245551234';

    private const ADDRESS = [
        'full_name' => 'Ama Serwaa',
        'email' => 'ama@example.com',
        'phone' => '+233245551234',
        'line1' => '12 Haatso Road',
        'city' => 'Accra',
        'region' => 'Greater Accra',
        'country' => 'GH',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.app_secret' => self::APP_SECRET,
            'whatsapp.phone_number_id' => 'PNID',
            'whatsapp.access_token' => null,
            'services.paystack.secret' => null,
        ]);
        $this->app->forgetInstance(WhatsAppClient::class);
        Http::preventStrayRequests();
    }

    // --- helpers ------------------------------------------------------------

    private function stocked(int $quantity = 5, int $price = 45_000): InventoryItem
    {
        $product = Product::factory()->create(['name' => 'Kentehene', 'base_price_ghs' => $price, 'is_active' => true]);

        return InventoryItem::factory()->create([
            'product_id' => $product->id,
            'variant_attributes' => ['size' => '42'],
            'quantity_available' => $quantity,
            'quantity_reserved' => 0,
        ]);
    }

    private function inbound(string $text, ?int $at = null): void
    {
        $body = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
                'metadata' => ['phone_number_id' => 'PNID'],
                'contacts' => [['profile' => ['name' => 'Ama'], 'wa_id' => self::CUSTOMER]],
                'messages' => [[
                    'from' => self::CUSTOMER, 'id' => 'wamid.'.uniqid(), 'timestamp' => (string) ($at ?? now()->timestamp),
                    'type' => 'text', 'text' => ['body' => $text],
                ]],
            ]]]]],
        ]);

        $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::APP_SECRET),
        ], $body)->assertOk();
    }

    private function thread(): WhatsAppConversation
    {
        return WhatsAppConversation::query()->sole();
    }

    private function staff(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'staff']);
    }

    private function orderPayload(InventoryItem $item, array $overrides = []): array
    {
        return array_replace([
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 2]],
            'currency' => 'GHS',
            'shipping_address' => self::ADDRESS,
        ], $overrides);
    }

    private function placeOrder(InventoryItem $item, ?AdminUser $by = null, array $overrides = [])
    {
        return $this->actingAs($by ?? $this->staff(), 'admin')
            ->postJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/orders", $this->orderPayload($item, $overrides));
    }

    // --- the storefront draft ------------------------------------------

    public function test_the_storefront_saves_a_cart_under_a_short_code_and_holds_nothing(): void
    {
        $item = $this->stocked();

        $reference = $this->postJson('/api/v1/whatsapp-orders', [
            'items' => [['slug' => $item->product->slug, 'size' => '42', 'quantity' => 1]],
            'currency' => 'GHS',
        ])->assertCreated()->json('data.reference');

        $this->assertMatchesRegularExpression('/^WA-[A-HJ-NP-Z2-9]{6}$/', $reference);
        // assertEquals: Postgres jsonb stores keys in its own order.
        $this->assertEquals([['inventory_item_id' => $item->id, 'quantity' => 1]], WhatsAppOrderDraft::query()->sole()->items);
        $this->assertSame(0, $item->fresh()->quantity_reserved);
        $this->assertSame(0, StockHold::query()->count());
    }

    public function test_a_draft_with_an_unknown_size_is_refused(): void
    {
        $item = $this->stocked();

        $this->postJson('/api/v1/whatsapp-orders', [
            'items' => [['slug' => $item->product->slug, 'size' => '50', 'quantity' => 1]],
            'currency' => 'GHS',
        ])->assertUnprocessable();
    }

    public function test_the_customers_message_with_the_code_links_their_chat_to_the_cart(): void
    {
        $item = $this->stocked(quantity: 1);
        $draft = WhatsAppOrderDraft::create([
            'reference' => 'WA-7KQ2MP', 'items' => [['inventory_item_id' => $item->id, 'quantity' => 2]],
            'currency' => 'GHS', 'expires_at' => now()->addDays(7),
        ]);

        // Typed in lower case: codes are matched case-insensitively.
        $this->inbound("Hi Gold Coast Tokota, I'd like to order:\n1 × Kentehene\nOrder code: wa-7kq2mp");

        $this->assertSame($this->thread()->id, $draft->fresh()->conversation_id);
        $this->assertSame('order', $this->thread()->topic);

        $this->actingAs($this->staff(), 'admin')
            ->getJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/order-builder")
            ->assertOk()
            ->assertJsonPath('data.draft.reference', 'WA-7KQ2MP')
            ->assertJsonPath('data.draft.lines.0.product_name', 'Kentehene')
            ->assertJsonPath('data.draft.lines.0.unit_price', ['amount' => 45_000, 'currency' => 'GHS'])
            // Two wanted, one left: the builder says so before anything is held.
            ->assertJsonPath('data.draft.lines.0.problem', 'Only 1 available.')
            ->assertJsonPath('data.prefill.phone', '+233245551234')
            ->assertJsonPath('data.hold_hours', 24)
            ->assertJsonPath('data.window_open', true);
    }

    public function test_an_expired_or_already_used_code_links_nothing(): void
    {
        WhatsAppOrderDraft::create(['reference' => 'WA-AAAAAA', 'items' => [], 'currency' => 'GHS', 'expires_at' => now()->subDay()]);

        $this->inbound('Order code: WA-AAAAAA');

        $this->assertNull(WhatsAppOrderDraft::query()->sole()->conversation_id);
    }

    public function test_the_builder_prefills_from_the_customers_account_and_last_order(): void
    {
        $customer = Customer::factory()->create(['name' => 'Ama Serwaa', 'email' => 'ama@example.com', 'phone' => '0245551234']);
        Order::factory()->create(['customer_id' => $customer->id, 'shipping_address' => [...self::ADDRESS, 'line1' => '4 Osu Avenue']]);
        $this->inbound('Hello');

        $this->actingAs($this->staff(), 'admin')
            ->getJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/order-builder")
            ->assertOk()
            ->assertJsonPath('data.prefill.full_name', 'Ama Serwaa')
            ->assertJsonPath('data.prefill.email', 'ama@example.com')
            ->assertJsonPath('data.prefill.line1', '4 Osu Avenue');
    }

    // --- placing the order ------------------------------------------------

    public function test_staff_build_an_order_that_holds_stock_for_the_configured_hours_and_sends_a_pay_button(): void
    {
        SiteSetting::current()->update(['whatsapp_hold_hours' => 36]);
        $item = $this->stocked();
        $this->inbound('Two pairs of Kentehene in 42 please');
        WhatsAppOrderDraft::create(['reference' => 'WA-BBBBBB', 'items' => [], 'currency' => 'GHS', 'expires_at' => now()->addDay(), 'conversation_id' => $this->thread()->id]);
        $staff = $this->staff();

        $response = $this->placeOrder($item, $staff, ['draft_reference' => 'WA-BBBBBB'])
            ->assertCreated()
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.warning', null)
            ->assertJsonPath('data.message.kind', 'interactive');

        $order = Order::query()->sole();
        $this->assertSame('whatsapp', $order->channel);
        $this->assertSame($staff->id, $order->created_by_admin_id);
        $this->assertSame($this->thread()->id, $order->whatsapp_conversation_id);
        $this->assertSame(90_000, $order->subtotal);
        $this->assertSame($order->id, $this->thread()->linked_order_id);
        $this->assertSame($order->id, WhatsAppOrderDraft::query()->sole()->converted_order_id);

        // Held for 36 hours, as the setting says, and logged as a WhatsApp hold.
        $hold = StockHold::query()->sole();
        $this->assertSame('whatsapp', $hold->channel);
        $this->assertEqualsWithDelta(now()->addHours(36)->timestamp, $hold->expires_at->timestamp, 5);
        $this->assertSame(2, $item->fresh()->quantity_reserved);
        $this->assertSame('whatsapp', InventoryMovement::query()->where('reason', 'hold')->sole()->channel);

        // The message: the order, its total, and a Pay button with the link.
        $message = WhatsAppMessage::query()->where('kind', 'interactive')->sole();
        $this->assertStringContainsString("Here is your order {$order->reference}:", $message->body);
        $this->assertStringContainsString('• 2 × Kentehene (42)', $message->body);
        $this->assertSame($response->json('data.payment_url'), $message->meta['cta']['url']);
        $this->assertStringStartsWith('Pay GH₵', $message->meta['cta']['button']);
        $this->assertSame('sent', $message->status);
    }

    public function test_paying_the_link_runs_the_ordinary_paid_order_path(): void
    {
        $item = $this->stocked();
        $this->inbound('Hello');
        $this->placeOrder($item)->assertCreated();
        $order = Order::query()->sole();

        $this->get("/fake-gateway/{$order->payment_reference}")->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(3, $item->fresh()->quantity_available);
        $this->assertSame(0, $item->fresh()->quantity_reserved);
        $this->assertSame(1, Shipment::query()->where('order_id', $order->id)->count());
        $this->assertSame('whatsapp', InventoryMovement::query()->where('reason', 'sale')->sole()->channel);
    }

    public function test_a_line_with_too_little_stock_is_refused_and_nothing_is_held(): void
    {
        $item = $this->stocked(quantity: 1);
        $this->inbound('Hello');

        $this->placeOrder($item)->assertStatus(409)->assertJsonPath('available', 1);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, $item->fresh()->quantity_reserved);
    }

    public function test_an_email_is_required_because_paystack_needs_one(): void
    {
        $item = $this->stocked();
        $this->inbound('Hello');
        $address = self::ADDRESS;
        unset($address['email']);

        $this->placeOrder($item, overrides: ['shipping_address' => $address])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipping_address.email');
    }

    public function test_outside_the_window_the_approved_payment_link_template_is_used(): void
    {
        $item = $this->stocked();
        $this->inbound('Hello', now()->subDays(2)->timestamp);
        WhatsAppTemplate::create([
            'name' => 'payment_link', 'language' => 'en', 'category' => 'utility', 'status' => 'approved',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, your order {{2}} comes to {{3}}. Pay here: {{4}}']],
        ]);

        $this->placeOrder($item)->assertCreated()->assertJsonPath('data.message.kind', 'template');

        $message = WhatsAppMessage::query()->where('kind', 'template')->sole();
        $this->assertStringStartsWith('Hi Ama, your order '.Order::query()->sole()->reference.' comes to GH₵', $message->body);
    }

    public function test_outside_the_window_with_no_template_the_order_stands_and_the_link_is_handed_back(): void
    {
        $item = $this->stocked();
        $this->inbound('Hello', now()->subDays(2)->timestamp);

        $this->placeOrder($item)
            ->assertCreated()
            ->assertJsonPath('data.message', null)
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJson(fn ($json) => $json->where('data.warning', fn ($w) => str_contains($w, 'payment_link'))->etc());

        $this->assertNotEmpty(Order::query()->sole()->payment_reference);
    }

    public function test_an_intern_cannot_build_an_order(): void
    {
        $item = $this->stocked();
        $this->inbound('Hello');
        $intern = AdminUser::factory()->create(['role' => 'intern', 'access_expires_at' => now()->addMonth()]);

        $this->placeOrder($item, $intern)->assertForbidden();
        $this->actingAs($intern, 'admin')->getJson("/api/v1/admin/inbox/threads/{$this->thread()->id}/order-builder")->assertForbidden();
    }

    // --- after placing --------------------------------------------------------

    public function test_cancelling_gives_the_stock_back_at_once(): void
    {
        $item = $this->stocked();
        $this->inbound('Hello');
        $this->placeOrder($item)->assertCreated();
        $order = Order::query()->sole();

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/inbox/orders/{$order->reference}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, $item->fresh()->quantity_reserved);
        $this->assertSame('released', StockHold::query()->sole()->release_reason);

        // And a second time: there is nothing left to cancel.
        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/inbox/orders/{$order->reference}/cancel")
            ->assertUnprocessable()->assertJsonPath('reason', 'not_pending');
    }

    public function test_a_web_order_cannot_be_cancelled_from_the_inbox(): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'channel' => 'web']);

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/inbox/orders/{$order->reference}/cancel")
            ->assertUnprocessable()->assertJsonPath('reason', 'not_whatsapp');
    }

    public function test_a_resent_link_is_a_fresh_paystack_transaction_and_either_link_can_pay(): void
    {
        config(['services.paystack.secret' => self::PAYSTACK_SECRET, 'services.paystack.callback_base_url' => 'https://goldcoasttokota.store']);
        Http::fake(['api.paystack.co/transaction/initialize' => fn ($request) => Http::response([
            'status' => true,
            'data' => ['reference' => $request['reference'], 'authorization_url' => "https://checkout.paystack.com/{$request['reference']}"],
        ])]);
        Queue::fake([SendOpsAlert::class]);
        config(['shipping.ops.email' => 'ops@goldcoasttokota.store']);
        $item = $this->stocked();
        $this->inbound('Hello');
        $this->placeOrder($item)->assertCreated();
        $order = Order::query()->sole();
        $this->assertSame($order->reference, $order->payment_reference);

        $this->actingAs($this->staff(), 'admin')
            ->postJson("/api/v1/admin/inbox/orders/{$order->reference}/resend-link")
            ->assertOk()
            ->assertJsonPath('data.payment_url', "https://checkout.paystack.com/{$order->reference}-2");
        $this->assertSame("{$order->reference}-2", $order->fresh()->payment_reference);

        // The customer pays on the new link: the webhook finds the order by it.
        $this->paystackWebhook("{$order->reference}-2", $order->total);
        $this->assertSame('paid', $order->fresh()->status);

        // Then also on the old one. Both charges are real: someone must refund one.
        $this->paystackWebhook($order->reference, $order->total);
        Queue::assertPushed(SendOpsAlert::class, fn ($job) => str_contains(json_encode($job), 'Refund needed'));
    }

    public function test_a_payment_for_a_cancelled_order_raises_a_refund_alert(): void
    {
        config(['services.paystack.secret' => self::PAYSTACK_SECRET, 'shipping.ops.email' => 'ops@goldcoasttokota.store']);
        Queue::fake([SendOpsAlert::class]);
        $order = Order::factory()->create(['status' => 'cancelled', 'channel' => 'whatsapp', 'payment_reference' => null]);

        $this->paystackWebhook($order->reference, $order->total);

        $this->assertSame('cancelled', $order->fresh()->status);
        Queue::assertPushed(SendOpsAlert::class);
    }

    public function test_unused_drafts_are_pruned_after_their_week_and_converted_ones_kept(): void
    {
        $order = Order::factory()->create();
        WhatsAppOrderDraft::create(['reference' => 'WA-OLD222', 'items' => [], 'currency' => 'GHS', 'expires_at' => now()->subMinute()]);
        WhatsAppOrderDraft::create(['reference' => 'WA-USED22', 'items' => [], 'currency' => 'GHS', 'expires_at' => now()->subMinute(), 'converted_order_id' => $order->id]);
        WhatsAppOrderDraft::create(['reference' => 'WA-NEW222', 'items' => [], 'currency' => 'GHS', 'expires_at' => now()->addDay()]);

        (new PruneWhatsAppOrderDrafts)->handle();

        $this->assertEqualsCanonicalizing(['WA-USED22', 'WA-NEW222'], WhatsAppOrderDraft::query()->pluck('reference')->all());
    }

    public function test_the_hold_setting_is_admin_editable_within_bounds(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')->putJson('/api/v1/admin/site-settings', ['whatsapp_hold_hours' => 48])->assertOk();
        $this->assertSame(48, SiteSetting::current()->whatsapp_hold_hours);

        $this->actingAs($admin, 'admin')->putJson('/api/v1/admin/site-settings', ['whatsapp_hold_hours' => 200])
            ->assertUnprocessable()->assertJsonValidationErrors('whatsapp_hold_hours');
        $this->actingAs($this->staff(), 'admin')->putJson('/api/v1/admin/site-settings', ['whatsapp_hold_hours' => 2])->assertForbidden();
    }

    private function paystackWebhook(string $reference, int $amount): void
    {
        $payload = ['event' => 'charge.success', 'data' => ['reference' => $reference, 'amount' => $amount, 'currency' => 'GHS', 'status' => 'success']];
        $body = json_encode($payload);

        $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::PAYSTACK_SECRET),
        ], $body)->assertOk();
    }
}

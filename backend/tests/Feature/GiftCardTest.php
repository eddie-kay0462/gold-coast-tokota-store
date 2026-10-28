<?php

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Jobs\ReleaseExpiredReservations;
use App\Mail\TransactionalMail;
use App\Models\AdminUser;
use App\Models\FxRate;
use App\Models\GiftCard;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\SiteSetting;
use App\Services\GiftCards\GiftCardService;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Gift cards and store credit: one balance-code system.
 *
 * What matters most is money: a balance is never spent twice, never lost to an
 * abandoned checkout, and never silently waived when a payment lands late.
 */
class GiftCardTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_pretend_secret';

    private const ADDRESS = [
        'full_name' => 'Ama Serwaa',
        'email' => 'ama@example.com',
        'phone' => '233200000000',
        'line1' => '12 Haatso Road',
        'city' => 'Accra',
        'region' => 'Greater Accra',
        'country' => 'GH',
    ];

    private function onSale(array $amounts = [20_000, 50_000], ?int $expiryMonths = null): void
    {
        SiteSetting::current()->update([
            'gift_cards_enabled' => true,
            'gift_card_amounts' => $amounts,
            'gift_card_expiry_months' => $expiryMonths,
        ]);
    }

    private function card(int $balance = 50_000, array $attributes = []): GiftCard
    {
        $card = app(GiftCardService::class)->issue($balance, GiftCard::SOURCE_STORE_CREDIT, ['recipient_name' => 'Kofi']);
        $card->update($attributes);

        return $card;
    }

    private function stocked(int $priceGhs = 60_000): InventoryItem
    {
        return InventoryItem::factory()->create([
            'product_id' => Product::factory()->create(['base_price_ghs' => $priceGhs])->id,
            'variant_attributes' => ['size' => '42'],
            'quantity_available' => 5,
            'quantity_reserved' => 0,
        ]);
    }

    private function checkout(InventoryItem $item, ?string $code, string $currency = 'GHS')
    {
        return $this->postJson('/api/v1/checkout/session', array_filter([
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
            'currency' => $currency,
            'shipping_address' => self::ADDRESS,
            'gift_card_code' => $code,
        ]));
    }

    private function purchasePayload(array $overrides = []): array
    {
        return array_replace([
            'amount' => 50_000,
            'currency' => 'GHS',
            'purchaser' => ['first_name' => 'Ama', 'last_name' => 'Serwaa', 'email' => 'ama@example.com'],
            'recipient_name' => 'Kofi Mensah',
            'recipient_email' => 'kofi@example.com',
            'sender_name' => 'Ama',
            'message' => 'Happy birthday!',
        ], $overrides);
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

    // --- policy ---------------------------------------------------------

    public function test_gift_cards_are_off_until_the_client_puts_them_on_sale(): void
    {
        $this->getJson('/api/v1/gift-cards/settings')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.amounts', [])
            ->assertJsonPath('data.expiry_months', null)
            ->assertJsonPath('data.redemption_open', false);

        $this->postJson('/api/v1/gift-cards/purchase', $this->purchasePayload())->assertStatus(422);
    }

    public function test_store_credit_opens_the_checkout_field_even_while_cards_are_not_on_sale(): void
    {
        $this->card();

        $this->getJson('/api/v1/gift-cards/settings')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.redemption_open', true);
    }

    public function test_only_an_amount_the_client_set_can_be_bought(): void
    {
        $this->onSale([20_000]);

        $this->postJson('/api/v1/gift-cards/purchase', $this->purchasePayload(['amount' => 1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_expiry_follows_the_setting_at_the_time_of_issue(): void
    {
        $this->onSale(expiryMonths: 12);
        $card = $this->card();

        $this->assertTrue($card->expires_at->isSameDay(now()->addMonths(12)));

        SiteSetting::current()->update(['gift_card_expiry_months' => null]);
        $this->assertNull($this->card()->expires_at);
        $this->assertNotNull($card->fresh()->expires_at);
    }

    // --- buying ---------------------------------------------------------

    public function test_buying_a_card_opens_a_payment_for_an_order_with_no_delivery(): void
    {
        $this->onSale();

        $response = $this->postJson('/api/v1/gift-cards/purchase', $this->purchasePayload());

        $response->assertCreated()
            ->assertJsonPath('data.kind', 'gift_card')
            ->assertJsonPath('data.total', 50_000)
            ->assertJsonPath('data.shipping_cost', 0)
            ->assertJsonPath('data.items.0.name', 'Gift card')
            ->assertJsonPath('data.items.0.gift_card.recipient_name', 'Kofi Mensah');
        $this->assertNotEmpty($response->json('payment.authorization_url'));
        $this->assertSame(0, GiftCard::count());
    }

    public function test_a_card_bought_in_dollars_still_holds_its_cedi_amount(): void
    {
        $this->onSale();
        FxRate::factory()->create(['rate' => 0.08, 'fetched_at' => now()]);

        $response = $this->postJson('/api/v1/gift-cards/purchase', $this->purchasePayload(['currency' => 'USD']));

        $response->assertCreated()->assertJsonPath('data.total', 4_000);

        $this->payByWebhook(Order::firstOrFail());
        $this->assertSame(50_000, GiftCard::firstOrFail()->balance);
    }

    public function test_payment_issues_the_card_and_emails_it_without_shipping_anything(): void
    {
        $this->onSale();
        Mail::fake();
        $this->postJson('/api/v1/gift-cards/purchase', $this->purchasePayload())->assertCreated();
        $order = Order::firstOrFail();

        $this->payByWebhook($order);

        $card = GiftCard::firstOrFail();
        $this->assertSame(GiftCard::SOURCE_PURCHASE, $card->source);
        $this->assertSame(50_000, $card->balance);
        $this->assertSame($order->id, $card->purchase_order_id);
        $this->assertSame('kofi@example.com', $card->recipient_email);
        $this->assertSame($card->id, $order->items()->first()->gift_card['gift_card_id']);
        $this->assertSame(0, Shipment::count());

        Mail::assertSent(TransactionalMail::class, fn ($m) => $m->hasTo('kofi@example.com') && $m->message->key === 'gift_card_issued');
        Mail::assertSent(TransactionalMail::class, fn ($m) => $m->hasTo('ama@example.com') && $m->message->key === 'gift_card_purchased');
        Mail::assertNotSent(TransactionalMail::class, fn ($m) => $m->message->key === 'order_placed');
    }

    public function test_a_replayed_payment_issues_one_card(): void
    {
        $this->onSale();
        $this->postJson('/api/v1/gift-cards/purchase', $this->purchasePayload())->assertCreated();
        $order = Order::firstOrFail();

        $this->payByWebhook($order);
        $this->payByWebhook($order);
        event(new OrderPaid($order->fresh()));

        $this->assertSame(1, GiftCard::count());
    }

    // --- spending -------------------------------------------------------

    public function test_a_card_takes_its_balance_off_the_order_total(): void
    {
        $card = $this->card(20_000);
        $item = $this->stocked(60_000);

        $response = $this->checkout($item, strtolower($card->code));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.gift_card_amount', 20_000)
            ->assertJsonPath('data.total', $response->json('data.subtotal') + $response->json('data.shipping_cost') - 20_000);

        $this->assertSame(0, $card->fresh()->balance);
        $this->assertSame(['issue', 'redeem'], $card->transactions()->pluck('type')->all());
    }

    public function test_a_card_that_covers_everything_pays_the_order_on_the_spot(): void
    {
        $card = $this->card(1_000_000);
        $item = $this->stocked(60_000);

        $response = $this->checkout($item, $card->code);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('payment.gateway', 'gift_card');
        $this->assertStringEndsWith('/order-confirmation/'.$response->json('data.reference'), $response->json('payment.authorization_url'));

        // OrderPaid ran: the held pair has left the shelf.
        $this->assertSame(4, $item->fresh()->quantity_available);
        $this->assertSame(0, $item->fresh()->quantity_reserved);
        $this->assertSame(1_000_000 - $response->json('data.subtotal') - $response->json('data.shipping_cost'), $card->fresh()->balance);
    }

    public function test_dollar_orders_convert_the_cedi_amount_at_the_locked_rate(): void
    {
        FxRate::factory()->create(['rate' => 0.08, 'fetched_at' => now()]);
        $card = $this->card(10_000);
        $item = $this->stocked(60_000);

        $response = $this->checkout($item, $card->code, 'USD');

        $response->assertCreated()->assertJsonPath('data.gift_card_amount', 800);
        $this->assertSame(0, $card->fresh()->balance);
    }

    public function test_unusable_codes_are_refused_and_nothing_is_held(): void
    {
        $item = $this->stocked();
        $expired = $this->card(10_000, ['expires_at' => now()->subDay()]);
        $disabled = $this->card(10_000, ['status' => 'disabled']);
        $empty = $this->card(10_000, ['balance' => 0]);

        foreach (['GCT-NOPE-NOPE-NOPE', $expired->code, $disabled->code, $empty->code] as $code) {
            $this->checkout($item, $code)->assertStatus(422)->assertJsonValidationErrors('gift_card_code');
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, $item->fresh()->quantity_reserved);
    }

    public function test_one_balance_cannot_be_spent_twice(): void
    {
        $card = $this->card(50_000);

        $this->checkout($this->stocked(60_000), $card->code)->assertCreated()->assertJsonPath('data.gift_card_amount', 50_000);
        $this->checkout($this->stocked(60_000), $card->code)->assertStatus(422);
    }

    public function test_an_unpaid_order_gives_the_balance_back_when_its_hold_lapses(): void
    {
        $card = $this->card(20_000);
        $this->checkout($this->stocked(), $card->code)->assertCreated();

        $this->travel(16)->minutes();
        (new ReleaseExpiredReservations)->handle(app(InventoryReservationService::class), app(GiftCardService::class));

        $this->assertSame(20_000, $card->fresh()->balance);
        $this->assertNotNull(Order::firstOrFail()->gift_card_released_at);

        // Running again gives nothing back twice.
        app(GiftCardService::class)->releaseLapsed();
        $this->assertSame(20_000, $card->fresh()->balance);
    }

    public function test_a_payment_after_the_release_takes_the_balance_again(): void
    {
        $card = $this->card(20_000);
        $this->checkout($this->stocked(), $card->code)->assertCreated();
        $order = Order::firstOrFail();

        $this->travel(16)->minutes();
        app(GiftCardService::class)->releaseLapsed();
        $this->assertSame(20_000, $card->fresh()->balance);

        $this->payByWebhook($order);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(0, $card->fresh()->balance);
        $this->assertNull($order->fresh()->gift_card_released_at);
        $this->assertSame(['issue', 'redeem', 'release', 'reclaim'], $card->transactions()->pluck('type')->all());
    }

    public function test_cancelling_an_unpaid_order_in_admin_gives_the_balance_back(): void
    {
        $card = $this->card(20_000);
        $this->checkout($this->stocked(), $card->code)->assertCreated();
        $order = Order::firstOrFail();

        $this->actingAs(AdminUser::factory()->create(['role' => 'admin']), 'admin')
            ->patchJson("/api/v1/admin/orders/{$order->reference}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(20_000, $card->fresh()->balance);
    }

    public function test_the_balance_check_shows_what_is_left_and_never_the_code(): void
    {
        $card = $this->card(12_345);

        $this->postJson('/api/v1/gift-cards/balance', ['code' => $card->code])
            ->assertOk()
            ->assertJsonPath('data.balance', 12_345)
            ->assertJsonPath('data.last_four', substr($card->code, -4))
            ->assertJsonMissingPath('data.code');

        $this->postJson('/api/v1/gift-cards/balance', ['code' => 'GCT-XXXX-XXXX-XXXX'])->assertStatus(422);
    }

    // --- admin ----------------------------------------------------------

    public function test_staff_can_look_cards_up_but_only_an_admin_can_issue_store_credit(): void
    {
        $payload = ['amount' => 30_000, 'recipient_name' => 'Kofi', 'recipient_email' => 'kofi@example.com', 'note' => 'Size sold out after payment', 'send_email' => true];

        $this->actingAs(AdminUser::factory()->create(['role' => 'intern']), 'admin')
            ->getJson('/api/v1/admin/gift-cards')->assertOk();
        $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->postJson('/api/v1/admin/gift-cards', $payload)->assertForbidden();

        Mail::fake();
        $response = $this->actingAs(AdminUser::factory()->create(['role' => 'admin']), 'admin')
            ->postJson('/api/v1/admin/gift-cards', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.source', 'store_credit')
            ->assertJsonPath('data.balance.amount', 30_000);
        Mail::assertSent(TransactionalMail::class, fn ($m) => $m->hasTo('kofi@example.com') && $m->message->key === 'gift_card_issued');
    }

    public function test_an_admin_correction_is_recorded_with_its_reason(): void
    {
        $card = $this->card(30_000);
        $admin = AdminUser::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/gift-cards/{$card->id}", ['balance' => 10_000, 'note' => 'Part refunded in cash'])
            ->assertOk()
            ->assertJsonPath('data.balance.amount', 10_000)
            ->assertJsonPath('data.transactions.1.type', 'adjust')
            ->assertJsonPath('data.transactions.1.amount.amount', -20_000)
            ->assertJsonPath('data.transactions.1.note', 'Part refunded in cash');
    }

    public function test_cards_cannot_go_on_sale_with_no_amounts(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->putJson('/api/v1/admin/gift-cards/settings', ['enabled' => true, 'amounts' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amounts');

        $this->actingAs($admin, 'admin')
            ->putJson('/api/v1/admin/gift-cards/settings', ['enabled' => true, 'amounts' => [50_000, 20_000], 'expiry_months' => null, 'terms' => 'Our terms.'])
            ->assertOk()
            ->assertJsonPath('data.amounts', [20_000, 50_000]);

        $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->putJson('/api/v1/admin/gift-cards/settings', ['enabled' => false, 'amounts' => []])
            ->assertForbidden();
    }
}

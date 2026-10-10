<?php

namespace Tests\Feature;

use App\Models\FxRate;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS_GH = [
        'full_name' => 'Ama Serwaa',
        'email' => 'ama@example.com',
        'phone' => '233200000000',
        'line1' => '12 Haatso Road',
        'city' => 'Accra',
        'region' => 'Greater Accra',
        'country' => 'GH',
    ];

    private const ADDRESS_INTL = [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '447700000000',
        'line1' => '5 Bridge Street',
        'city' => 'London',
        'country' => 'GB',
    ];

    private function stockedProduct(int $quantity = 5, int $priceGhs = 60_000): InventoryItem
    {
        $product = Product::factory()->create(['base_price_ghs' => $priceGhs]);

        return InventoryItem::factory()->create([
            'product_id' => $product->id,
            'variant_attributes' => ['size' => '42'],
            'quantity_available' => $quantity,
            'quantity_reserved' => 0,
        ]);
    }

    private function payload(InventoryItem $item, array $overrides = []): array
    {
        return array_replace([
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
            'currency' => 'GHS',
            'delivery_method' => 'standard',
            'shipping_address' => self::ADDRESS_GH,
        ], $overrides);
    }

    // --- happy path -----------------------------------------------------

    public function test_it_creates_a_pending_order_with_a_payment_session(): void
    {
        $item = $this->stockedProduct();

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($item));

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.currency', 'GHS');
        $response->assertJsonPath('data.items.0.quantity', 1);
        $this->assertNotEmpty($response->json('payment.reference'));
    }

    /** The receipt must survive the product being renamed or deleted later. */
    public function test_order_items_snapshot_the_product_name_and_variant(): void
    {
        $item = $this->stockedProduct();
        $name = $item->product->name;

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($item));

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.name', $name);
        $response->assertJsonPath('data.items.0.variant_label', '42');

        $item->product->update(['name' => 'Renamed Entirely']);
        $this->assertSame($name, Order::first()->items->first()->product_name);
    }

    public function test_prices_come_from_the_database_not_the_request(): void
    {
        $item = $this->stockedProduct(priceGhs: 60_000);

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($item, [
            'items' => [[
                'inventory_item_id' => $item->id,
                'quantity' => 1,
                // A client trying to set its own price.
                'unit_price' => 1,
                'base_price_ghs' => 1,
            ]],
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.unit_price', 60_000);
        $response->assertJsonPath('data.subtotal', 60_000);
    }

    // --- money ----------------------------------------------------------

    public function test_ghs_orders_route_to_paystack_and_lock_no_fx_rate(): void
    {
        $item = $this->stockedProduct();

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($item));

        $response->assertCreated();
        $response->assertJsonPath('data.payment_gateway', 'paystack');
        $response->assertJsonPath('data.fx_rate_applied', null);
    }

    /**
     * §13 of the brand document names one payment gateway. This test used to
     * assert USD routed to Stripe — the README's original split — and the
     * document does not mention Stripe anywhere: Visa, Mastercard and Verve
     * all sit under Paystack, and settlement is in cedis either way.
     */
    public function test_usd_orders_route_to_paystack_and_snapshot_the_rate(): void
    {
        FxRate::factory()->create(['rate' => 0.08]);
        $item = $this->stockedProduct(priceGhs: 60_000);

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($item, ['currency' => 'USD']));

        $response->assertCreated();
        $response->assertJsonPath('data.payment_gateway', 'paystack');
        // And so it redirects rather than handing back a client secret — the
        // storefront's currency branch at the payment step is now a branch
        // with nothing on the other side of it.
        $this->assertNotNull($response->json('payment.authorization_url'));
        $this->assertNull($response->json('payment.client_secret'));
        $response->assertJsonPath('data.fx_rate_applied', 0.08);
        // 60000 goods + 2500 domestic standard shipping, converted once.
        $response->assertJsonPath('data.subtotal', 4_800);
        $response->assertJsonPath('data.shipping_cost', 200);
        $response->assertJsonPath('data.total', 5_000);
    }

    /**
     * A later rate move must not change what an existing order says it charged.
     */
    public function test_a_locked_rate_survives_a_later_rate_change(): void
    {
        FxRate::factory()->create(['rate' => 0.08]);
        $item = $this->stockedProduct();

        $this->postJson('/api/v1/checkout/session', $this->payload($item, ['currency' => 'USD']))
            ->assertCreated();

        $totalAtCheckout = Order::first()->total;
        FxRate::factory()->create(['rate' => 0.05]);

        $this->assertSame($totalAtCheckout, Order::first()->fresh()->total);
    }

    public function test_usd_checkout_is_refused_when_no_rate_has_ever_been_fetched(): void
    {
        $item = $this->stockedProduct();

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($item, ['currency' => 'USD']));

        $response->assertStatus(503);
        $this->assertSame(0, Order::query()->count());
    }

    // --- delivery -------------------------------------------------------

    public function test_ghana_addresses_route_to_yango_and_never_to_dhl(): void
    {
        $item = $this->stockedProduct();

        $this->postJson('/api/v1/checkout/session', $this->payload($item))
            ->assertCreated()
            ->assertJsonPath('data.delivery_provider', 'yango');
    }

    public function test_international_addresses_route_to_dhl(): void
    {
        $item = $this->stockedProduct();

        $this->postJson('/api/v1/checkout/session', $this->payload($item, [
            'shipping_address' => self::ADDRESS_INTL,
        ]))->assertCreated()->assertJsonPath('data.delivery_provider', 'dhl');
    }

    public function test_domestic_standard_shipping_is_free_above_the_advertised_threshold(): void
    {
        // The product page promises free delivery on Ghana orders over GHS 1,500.
        $item = $this->stockedProduct(priceGhs: 160_000);

        $this->postJson('/api/v1/checkout/session', $this->payload($item))
            ->assertCreated()
            ->assertJsonPath('data.shipping_cost', 0);
    }

    public function test_a_missing_country_is_rejected_before_payment(): void
    {
        $item = $this->stockedProduct();
        $address = self::ADDRESS_GH;
        unset($address['country']);

        $this->postJson('/api/v1/checkout/session', $this->payload($item, ['shipping_address' => $address]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipping_address.country');
    }

    // --- inventory ------------------------------------------------------

    public function test_checkout_reserves_stock_without_decrementing_it(): void
    {
        $item = $this->stockedProduct(quantity: 5);

        $this->postJson('/api/v1/checkout/session', $this->payload($item, [
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 2]],
        ]))->assertCreated();

        $item->refresh();
        // Nothing is decremented until the payment webhook confirms (Feature 3).
        $this->assertSame(5, $item->quantity_available);
        $this->assertSame(2, $item->quantity_reserved);
        $this->assertNotNull($item->reservation_expires_at);
    }

    public function test_ordering_more_than_is_sellable_is_refused_with_409(): void
    {
        $item = $this->stockedProduct(quantity: 2);

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($item, [
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 3]],
        ]));

        $response->assertStatus(409);
        $response->assertJsonPath('available', 2);
        $this->assertSame(0, Order::query()->count());
    }

    /**
     * README Feature 4: a failed session must leave no Order behind and must
     * release anything it had already reserved. Two lines, the second short —
     * the first line's reservation has to roll back with the transaction.
     */
    public function test_a_failed_line_rolls_back_the_whole_order_and_its_reservations(): void
    {
        $plenty = $this->stockedProduct(quantity: 10);
        $scarce = $this->stockedProduct(quantity: 1);

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($plenty, [
            'items' => [
                ['inventory_item_id' => $plenty->id, 'quantity' => 1],
                ['inventory_item_id' => $scarce->id, 'quantity' => 5],
            ],
        ]));

        $response->assertStatus(409);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, $plenty->fresh()->quantity_reserved);
        $this->assertSame(0, $scarce->fresh()->quantity_reserved);
    }

    /**
     * THE acceptance criterion for Feature 3/4: two checkouts for the last unit
     * produce exactly one order, and the loser is refused before any charge.
     *
     * Sequential rather than genuinely parallel — SQLite/Postgres test
     * transactions can't be driven concurrently from one process — but it
     * exercises the same path: the second reserve() reads the row after the
     * first has committed its hold, and `sellable_quantity` is what it checks.
     */
    public function test_two_checkouts_for_the_last_unit_produce_exactly_one_order(): void
    {
        $item = $this->stockedProduct(quantity: 1);

        $first = $this->postJson('/api/v1/checkout/session', $this->payload($item));
        $second = $this->postJson('/api/v1/checkout/session', $this->payload($item));

        $first->assertCreated();
        $second->assertStatus(409);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, $item->fresh()->quantity_reserved);
        $this->assertSame(1, $item->fresh()->quantity_available);
    }

    public function test_an_inactive_product_cannot_be_checked_out(): void
    {
        $item = $this->stockedProduct();
        $item->product->update(['is_active' => false]);

        $this->postJson('/api/v1/checkout/session', $this->payload($item))
            ->assertStatus(409);

        $this->assertSame(0, Order::query()->count());
    }

    // --- references -----------------------------------------------------

    // --- storefront cart lines (slug + size) ------------------------------

    public function test_a_line_can_name_its_stock_by_slug_and_size(): void
    {
        $item = $this->stockedProduct();
        $slug = $item->product->slug;

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($item, [
            'items' => [['slug' => $slug, 'size' => '42', 'quantity' => 2]],
        ]));

        $response->assertCreated();
        $this->assertSame(2, $item->fresh()->quantity_reserved);
    }

    public function test_a_size_the_style_is_not_made_in_is_a_validation_error(): void
    {
        $item = $this->stockedProduct();

        $this->postJson('/api/v1/checkout/session', $this->payload($item, [
            'items' => [['slug' => $item->product->slug, 'size' => '47', 'quantity' => 1]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.size');

        $this->postJson('/api/v1/checkout/session', $this->payload($item, [
            'items' => [['slug' => 'no-such-style', 'size' => '42', 'quantity' => 1]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.size');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_line_needs_either_an_id_or_a_slug(): void
    {
        $item = $this->stockedProduct();

        $this->postJson('/api/v1/checkout/session', $this->payload($item, [
            'items' => [['quantity' => 1]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['items.0.inventory_item_id', 'items.0.slug']);
    }

    public function test_a_sold_out_line_is_identified_by_its_position_in_the_cart(): void
    {
        $plenty = $this->stockedProduct(quantity: 5);
        $scarce = $this->stockedProduct(quantity: 1);

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($plenty, [
            'items' => [
                ['slug' => $plenty->product->slug, 'size' => '42', 'quantity' => 1],
                ['slug' => $scarce->product->slug, 'size' => '42', 'quantity' => 2],
            ],
        ]));

        $response->assertStatus(409);
        $response->assertJsonPath('line', 1);
    }

    // --- colour variants (issue 39) ---------------------------------------

    /** @return array{0: Product, 1: InventoryItem, 2: InventoryItem} */
    private function twoColourProduct(): array
    {
        $product = Product::factory()->create(['base_price_ghs' => 50_000]);
        $tan = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'variant_attributes' => ['size' => '42', 'colour' => 'Tan'],
            'quantity_available' => 3,
            'quantity_reserved' => 0,
        ]);
        $black = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'variant_attributes' => ['size' => '42', 'colour' => 'Black'],
            'quantity_available' => 3,
            'quantity_reserved' => 0,
        ]);

        return [$product, $tan, $black];
    }

    public function test_the_chosen_colour_is_the_one_reserved_and_named_on_the_order(): void
    {
        [$product, $tan, $black] = $this->twoColourProduct();

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($tan, [
            'items' => [['slug' => $product->slug, 'size' => '42', 'colour' => 'black', 'quantity' => 1]],
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.variant_label', '42 | Black');
        $this->assertSame(1, $black->fresh()->quantity_reserved);
        $this->assertSame(0, $tan->fresh()->quantity_reserved);
    }

    public function test_a_colour_is_required_when_the_size_comes_in_several(): void
    {
        [$product, $tan] = $this->twoColourProduct();

        $this->postJson('/api/v1/checkout/session', $this->payload($tan, [
            'items' => [['slug' => $product->slug, 'size' => '42', 'quantity' => 1]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.colour');
    }

    public function test_a_colour_the_style_is_not_made_in_is_refused(): void
    {
        [$product, $tan] = $this->twoColourProduct();

        $this->postJson('/api/v1/checkout/session', $this->payload($tan, [
            'items' => [['slug' => $product->slug, 'size' => '42', 'colour' => 'Purple', 'quantity' => 1]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.colour');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_colour_sent_for_a_style_with_no_colour_axis_is_ignored(): void
    {
        $item = $this->stockedProduct();

        $this->postJson('/api/v1/checkout/session', $this->payload($item, [
            'items' => [['slug' => $item->product->slug, 'size' => '42', 'colour' => 'Tan', 'quantity' => 1]],
        ]))->assertCreated();
    }

    // --- the gateway without a key --------------------------------------

    public function test_production_without_a_paystack_key_refuses_checkout(): void
    {
        config(['services.paystack.secret' => null]);
        $this->app->detectEnvironment(fn () => 'production');
        $item = $this->stockedProduct();

        $this->postJson('/api/v1/checkout/session', $this->payload($item))->assertStatus(503);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, $item->fresh()->quantity_reserved);
    }

    public function test_the_fake_gateway_page_marks_the_order_paid_and_returns_to_the_storefront(): void
    {
        config(['services.paystack.secret' => null, 'services.paystack.callback_base_url' => 'http://shop.test']);
        $item = $this->stockedProduct();

        $created = $this->postJson('/api/v1/checkout/session', $this->payload($item))->assertCreated();
        $reference = $created->json('data.reference');
        $path = parse_url($created->json('payment.authorization_url'), PHP_URL_PATH);

        $this->get($path)->assertRedirect("http://shop.test/order-confirmation/{$reference}");

        $this->assertSame('paid', Order::query()->where('reference', $reference)->value('status'));
    }

    public function test_cancelling_on_the_fake_gateway_leaves_the_order_pending(): void
    {
        config(['services.paystack.secret' => null]);
        $item = $this->stockedProduct();

        $created = $this->postJson('/api/v1/checkout/session', $this->payload($item))->assertCreated();
        $path = parse_url($created->json('payment.authorization_url'), PHP_URL_PATH);

        $this->get("{$path}?outcome=cancel")->assertRedirect();

        $this->assertSame('pending', Order::query()->where('reference', $created->json('data.reference'))->value('status'));
    }

    public function test_every_order_gets_an_unguessable_reference(): void
    {
        $item = $this->stockedProduct(quantity: 10);

        $this->postJson('/api/v1/checkout/session', $this->payload($item))->assertCreated();
        $this->postJson('/api/v1/checkout/session', $this->payload($item))->assertCreated();

        $references = Order::query()->pluck('reference');

        $this->assertCount(2, $references->unique());
        foreach ($references as $reference) {
            $this->assertMatchesRegularExpression('/^GCT-[A-HJ-NP-Z2-9]{12}$/', $reference);
        }
    }

    /** The gateway's own reference is internal and must not be published. */
    public function test_the_gateway_reference_is_not_exposed_on_the_order(): void
    {
        $item = $this->stockedProduct();

        $response = $this->postJson('/api/v1/checkout/session', $this->payload($item));

        $response->assertCreated();
        $this->assertArrayNotHasKey('payment_reference', $response->json('data'));
    }

    // --- the gateway call -------------------------------------------------

    private function configurePaystack(): void
    {
        config([
            'services.paystack.secret' => 'sk_test_pretend_secret',
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.callback_base_url' => 'https://goldcoasttokota.store',
        ]);
    }

    /**
     * Paystack can take tens of seconds to answer. The holds commit first, so
     * no stock row stays locked while it does.
     */
    public function test_paystack_is_called_after_the_holds_commit(): void
    {
        $this->configurePaystack();
        $item = $this->stockedProduct();
        // RefreshDatabase wraps the test in a transaction of its own.
        $baseline = DB::transactionLevel();
        $levelDuringCall = null;

        Http::fake(['api.paystack.co/*' => function () use (&$levelDuringCall) {
            $levelDuringCall = DB::transactionLevel();

            return Http::response(['status' => true, 'data' => [
                'reference' => 'ref', 'authorization_url' => 'https://checkout.paystack.com/abc',
            ]]);
        }]);

        $this->postJson('/api/v1/checkout/session', $this->payload($item))->assertCreated();

        $this->assertSame($baseline, $levelDuringCall);
        $this->assertSame(1, $item->fresh()->quantity_reserved);
        $this->assertNotNull(Order::query()->sole()->reservation_expires_at);
    }

    /** A refused session leaves no order and no hold, as a rolled-back one did. */
    public function test_a_paystack_refusal_releases_the_holds_and_removes_the_order(): void
    {
        $this->configurePaystack();
        $item = $this->stockedProduct();
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

        $this->postJson('/api/v1/checkout/session', $this->payload($item))->assertServerError();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, DB::table('order_items')->count());
        $this->assertSame(0, $item->fresh()->quantity_reserved);
    }
}

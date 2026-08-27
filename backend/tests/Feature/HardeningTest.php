<?php

namespace Tests\Feature;

use App\Models\FxRate;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\Currency\FxRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Stage 8: the behaviours that only matter when something has gone wrong —
 * a dead FX provider, an abusive client.
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // getCachedRate() memoises for five minutes; each test sets its own.
        Cache::flush();
    }

    private function staleRate(): FxRate
    {
        return FxRate::factory()->create([
            'rate' => 0.08,
            'fetched_at' => now()->subHours(FxRateService::STALE_AFTER_HOURS + 1),
        ]);
    }

    private function freshRate(): FxRate
    {
        return FxRate::factory()->create(['rate' => 0.08, 'fetched_at' => now()]);
    }

    // --- FX staleness ---------------------------------------------------

    public function test_a_fresh_rate_prices_products_in_usd(): void
    {
        $this->freshRate();
        $product = Product::factory()->create(['base_price_ghs' => 60_000]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.price_usd', 4_800);
    }

    /**
     * The storefront falls back to cedis when price_usd is null. Showing a
     * week-old dollar figure that checkout would then refuse is the worse
     * failure — the customer only finds out at the payment step.
     */
    public function test_a_stale_rate_drops_the_usd_price_rather_than_showing_it(): void
    {
        $this->staleRate();
        $product = Product::factory()->create(['base_price_ghs' => 60_000]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.price_usd', null);
        $response->assertJsonPath('data.compare_at_usd', null);
        // The cedi price is untouched — a dead FX provider must not break
        // product reads at all.
        $response->assertJsonPath('data.base_price_ghs', 60_000);
    }

    /** A rate that has not moved in a week is a loss on every unit sold. */
    public function test_usd_checkout_is_refused_on_a_stale_rate(): void
    {
        $this->staleRate();
        $product = Product::factory()->create(['base_price_ghs' => 60_000]);
        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'variant_attributes' => ['size' => '42'],
            'quantity_available' => 5,
        ]);

        $response = $this->postJson('/api/v1/checkout/session', [
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
            'currency' => 'USD',
            'delivery_method' => 'standard',
            'shipping_address' => [
                'full_name' => 'Ama Serwaa', 'email' => 'ama@example.com',
                'phone' => '233200000000', 'line1' => '12 Haatso Road',
                'city' => 'Accra', 'country' => 'GH',
            ],
        ]);

        $response->assertStatus(503);
        $this->assertSame(0, Order::query()->count());
    }

    /** GHS orders never touch the FX rate, so a dead provider cannot block them. */
    public function test_ghs_checkout_still_works_on_a_stale_rate(): void
    {
        $this->staleRate();
        $product = Product::factory()->create(['base_price_ghs' => 60_000]);
        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'variant_attributes' => ['size' => '42'],
            'quantity_available' => 5,
        ]);

        $this->postJson('/api/v1/checkout/session', [
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
            'currency' => 'GHS',
            'delivery_method' => 'standard',
            'shipping_address' => [
                'full_name' => 'Ama Serwaa', 'email' => 'ama@example.com',
                'phone' => '233200000000', 'line1' => '12 Haatso Road',
                'city' => 'Accra', 'country' => 'GH',
            ],
        ])->assertCreated();
    }

    /** The rate endpoint still serves it, flagged — that is what it is for. */
    public function test_the_fx_endpoint_serves_a_stale_rate_but_says_it_is_stale(): void
    {
        $this->staleRate();

        $this->getJson('/api/v1/fx-rate')
            ->assertOk()
            ->assertJsonPath('data.is_stale', true);
    }

    public function test_a_fresh_rate_is_not_flagged_stale(): void
    {
        $this->freshRate();

        $this->getJson('/api/v1/fx-rate')
            ->assertOk()
            ->assertJsonPath('data.is_stale', false);
    }

    public function test_getusablerate_is_null_only_when_missing_or_stale(): void
    {
        $service = app(FxRateService::class);

        $this->assertNull($service->getUsableRate());

        $this->staleRate();
        Cache::flush();
        $this->assertNull($service->getUsableRate());

        $this->freshRate();
        Cache::flush();
        $this->assertNotNull($service->getUsableRate());
    }

    // --- rate limiting --------------------------------------------------

    /**
     * A floor under every API route, so one added later is never completely
     * unthrottled. Routes with tighter needs set their own.
     */
    public function test_the_api_has_a_baseline_rate_limit(): void
    {
        $response = $this->getJson('/api/v1/site-settings');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit', '120');
    }

    public function test_the_inventory_polling_interval_stays_well_inside_the_limit(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->create(['product_id' => $product->id, 'quantity_available' => 3]);

        // The storefront polls every 15-30s per open product page. Even a
        // customer with several tabs open must not be throttled.
        for ($i = 0; $i < 12; $i++) {
            $this->getJson("/api/v1/products/{$product->slug}/stock")->assertOk();
        }
    }
}

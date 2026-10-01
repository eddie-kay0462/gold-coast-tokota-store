<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two read endpoints on top of Feature 3's inventory model: the
 * storefront's polling endpoint and the admin Inventory table.
 */
class InventoryEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function productWithStock(array $sizes): Product
    {
        $product = Product::factory()->create();

        foreach ($sizes as $size => $quantities) {
            InventoryItem::factory()->create([
                'product_id' => $product->id,
                'variant_attributes' => ['size' => (string) $size],
                'quantity_available' => $quantities[0],
                'quantity_reserved' => $quantities[1] ?? 0,
                'low_stock_threshold' => $quantities[2] ?? 0,
            ]);
        }

        return $product;
    }

    // --- GET /products/{slug}/stock -------------------------------------

    public function test_stock_endpoint_returns_the_shape_the_polling_composable_reads(): void
    {
        $product = $this->productWithStock(['41' => [4], '42' => [6]]);

        $response = $this->getJson("/api/v1/products/{$product->slug}/stock");

        $response->assertOk();
        $response->assertJsonPath('data.slug', $product->slug);
        $response->assertJsonPath('data.quantity_available', 10);
        $response->assertJsonPath('data.in_stock', true);
    }

    /** The panel strikes through individual sizes, so the aggregate alone isn't enough. */
    public function test_stock_endpoint_returns_per_size_availability(): void
    {
        $product = $this->productWithStock(['41' => [3], '42' => [0]]);

        $response = $this->getJson("/api/v1/products/{$product->slug}/stock");

        $response->assertOk();
        $response->assertJsonPath('data.size_availability.41', 3);
        $response->assertJsonPath('data.size_availability.42', 0);
    }

    /**
     * A unit held by someone else's in-progress payment is not purchasable.
     * Reporting raw quantity_available here would offer stock that checkout
     * then refuses — the polling display and the reservation logic must agree.
     */
    public function test_stock_endpoint_reports_sellable_stock_not_raw_availability(): void
    {
        $product = $this->productWithStock(['42' => [10, 4]]);

        $response = $this->getJson("/api/v1/products/{$product->slug}/stock");

        $response->assertOk();
        $response->assertJsonPath('data.quantity_available', 6);
    }

    public function test_stock_endpoint_reports_out_of_stock_when_everything_is_reserved(): void
    {
        $product = $this->productWithStock(['42' => [3, 3]]);

        $response = $this->getJson("/api/v1/products/{$product->slug}/stock");

        $response->assertOk();
        $response->assertJsonPath('data.quantity_available', 0);
        $response->assertJsonPath('data.in_stock', false);
        $response->assertJsonPath('data.merchandising_badge', 'out_of_stock');
    }

    public function test_stock_endpoint_404s_for_an_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/v1/products/{$product->slug}/stock")->assertNotFound();
    }

    // --- GET /admin/inventory -------------------------------------------

    public function test_admin_inventory_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/inventory')->assertUnauthorized();
    }

    /**
     * A guest hitting an API route gets 401, not a redirect to a `login` route
     * this app never defines. That lookup used to throw, so an expired admin
     * session surfaced as a 500 on any request without an Accept header —
     * which the admin SPA always sends, so nobody had seen it.
     */
    public function test_api_auth_failures_are_401_even_without_a_json_accept_header(): void
    {
        $response = $this->get('/api/v1/admin/inventory', ['Accept' => 'text/html']);

        $response->assertUnauthorized();
        $response->assertJsonPath('message', 'Unauthenticated.');
    }

    /** Restocking is operations, not pricing — Staff must not be blocked. */
    public function test_staff_can_read_the_inventory_list(): void
    {
        $staff = AdminUser::factory()->create(['role' => 'staff']);
        $this->productWithStock(['42' => [5]]);

        $response = $this->actingAs($staff, 'admin')->getJson('/api/v1/admin/inventory');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_inventory_rows_name_the_product_they_belong_to(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $product = $this->productWithStock(['42' => [5]]);

        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/inventory');

        $response->assertOk();
        $response->assertJsonPath('data.0.product_name', $product->name);
        $response->assertJsonPath('data.0.sku', $product->sku);
        $response->assertJsonPath('data.0.variant_attributes.size', '42');
    }

    public function test_low_stock_filter_returns_only_rows_at_or_below_their_threshold(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        // 2 <= 3 is low; 20 > 3 is not.
        $this->productWithStock(['41' => [2, 0, 3], '42' => [20, 0, 3]]);

        $response = $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/inventory?low_stock=true');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.quantity_available', 2);
    }

    /**
     * The low-stock filter asks "what do we need to make more of", which is a
     * question about physical stock — a reserved unit is still on the shelf.
     */
    public function test_low_stock_filter_ignores_reservations(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        // Sellable is 0, but 20 physical units are not a restocking problem.
        $this->productWithStock(['42' => [20, 20, 3]]);

        $response = $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/inventory?low_stock=true');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_inventory_exposes_sellable_quantity_alongside_the_raw_counts(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $this->productWithStock(['42' => [10, 4]]);

        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/inventory');

        $response->assertOk();
        $response->assertJsonPath('data.0.quantity_available', 10);
        $response->assertJsonPath('data.0.quantity_reserved', 4);
        $response->assertJsonPath('data.0.sellable_quantity', 6);
    }

    // --- PATCH /admin/inventory/{id} ------------------------------------

    /** The catalogue is seeded without quantities — this is how stock gets in. */
    public function test_staff_can_set_the_stock_count(): void
    {
        $staff = AdminUser::factory()->create(['role' => 'staff']);
        $item = InventoryItem::factory()->create(['quantity_available' => 0, 'low_stock_threshold' => 2]);

        $response = $this->actingAs($staff, 'admin')
            ->patchJson("/api/v1/admin/inventory/{$item->id}", ['quantity_available' => 12]);

        $response->assertOk();
        $response->assertJsonPath('data.quantity_available', 12);
        $response->assertJsonPath('data.low_stock_threshold', 2);
        $response->assertJsonPath('data.product_name', $item->product->name);
        $this->assertSame(12, $item->fresh()->quantity_available);
    }

    public function test_an_intern_can_see_stock_but_not_change_it(): void
    {
        $intern = AdminUser::factory()->create(['role' => 'intern']);
        $item = InventoryItem::factory()->create(['quantity_available' => 3]);

        $this->actingAs($intern, 'admin')
            ->patchJson("/api/v1/admin/inventory/{$item->id}", ['quantity_available' => 99])
            ->assertForbidden();

        $this->assertSame(3, $item->fresh()->quantity_available);
    }

    public function test_the_count_cannot_drop_below_what_checkouts_are_holding(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $item = InventoryItem::factory()->create(['quantity_available' => 10, 'quantity_reserved' => 4]);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/inventory/{$item->id}", ['quantity_available' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_available');

        $this->assertSame(10, $item->fresh()->quantity_available);
    }

    public function test_reserved_stock_is_not_writable_from_admin(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $item = InventoryItem::factory()->create(['quantity_available' => 10, 'quantity_reserved' => 4]);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/inventory/{$item->id}", ['quantity_reserved' => 0, 'low_stock_threshold' => 1])
            ->assertOk();

        $this->assertSame(4, $item->fresh()->quantity_reserved);
        $this->assertSame(1, $item->fresh()->low_stock_threshold);
    }

    public function test_a_negative_count_is_refused(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $item = InventoryItem::factory()->create(['quantity_available' => 10]);

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/v1/admin/inventory/{$item->id}", ['quantity_available' => -1])
            ->assertUnprocessable();
    }
}

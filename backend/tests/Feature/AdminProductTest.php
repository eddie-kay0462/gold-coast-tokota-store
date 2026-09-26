<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Collection;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_product(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/products', [
            'name' => 'Artisan Sandal',
            'slug' => 'artisan-sandal',
            'base_price_ghs' => 15_000,
            'sku' => 'ART-001',
            'category_id' => $category->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'artisan-sandal');
        // Defaults must be reflected in the response, not just persisted —
        // Product::create() doesn't re-read DB column defaults into memory.
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.is_featured', false);
        $response->assertJsonPath('data.images', []);
        $this->assertDatabaseHas('products', ['slug' => 'artisan-sandal']);
    }

    public function test_admin_can_assign_a_collection_and_back_in_stock_badge(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $collection = Collection::factory()->create();

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/products', [
            'name' => 'Artisan Sandal',
            'slug' => 'artisan-sandal',
            'base_price_ghs' => 15_000,
            'sku' => 'ART-001',
            'collection_id' => $collection->id,
            'merchandising_badge' => 'back_in_stock',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.collection_id', $collection->id);
        $this->assertDatabaseHas('products', ['slug' => 'artisan-sandal', 'merchandising_badge' => 'back_in_stock']);
    }

    public function test_create_product_rejects_an_invalid_merchandising_badge(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/products', [
            'name' => 'Artisan Sandal',
            'slug' => 'artisan-sandal',
            'base_price_ghs' => 15_000,
            'sku' => 'ART-001',
            'merchandising_badge' => 'out_of_stock',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('merchandising_badge');
    }

    public function test_create_product_requires_a_unique_slug(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        Product::factory()->create(['slug' => 'taken-slug']);

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/products', [
            'name' => 'Another Sandal',
            'slug' => 'taken-slug',
            'base_price_ghs' => 15_000,
            'sku' => 'ART-002',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('slug');
    }

    public function test_admin_can_update_a_product(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create(['base_price_ghs' => 10_000]);

        $response = $this->actingAs($admin, 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}", ['base_price_ghs' => 12_000]);

        $response->assertOk();
        // Admin-shaped money: { amount, currency }, not a bare integer —
        // the admin app's Money type makes the pair inseparable.
        $response->assertJsonPath('data.base_price_ghs.amount', 12_000);
        $response->assertJsonPath('data.base_price_ghs.currency', 'GHS');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'base_price_ghs' => 12_000]);
    }

    public function test_admin_can_delete_a_product(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();

        $response = $this->actingAs($admin, 'admin')->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_staff_cannot_create_a_product(): void
    {
        $staff = AdminUser::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff, 'admin')->postJson('/api/v1/admin/products', [
            'name' => 'Staff Attempt',
            'slug' => 'staff-attempt',
            'base_price_ghs' => 1_000,
            'sku' => 'STAFF-001',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('products', ['slug' => 'staff-attempt']);
    }

    public function test_staff_cannot_update_a_product(): void
    {
        $staff = AdminUser::factory()->create(['role' => 'staff']);
        $product = Product::factory()->create(['base_price_ghs' => 10_000]);

        $response = $this->actingAs($staff, 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}", ['base_price_ghs' => 99_000]);

        $response->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'base_price_ghs' => 10_000]);
    }

    public function test_staff_cannot_delete_a_product(): void
    {
        $staff = AdminUser::factory()->create(['role' => 'staff']);
        $product = Product::factory()->create();

        $response = $this->actingAs($staff, 'admin')->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_admin_listing_includes_inactive_products(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        Product::factory()->create(['name' => 'Live Sandal', 'is_active' => true]);
        Product::factory()->create(['name' => 'Draft Sandal', 'is_active' => false]);

        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/products');

        $response->assertOk();
        // The whole point of a separate admin listing: the public endpoint is
        // scoped active(), so a draft would be invisible on the screen whose
        // job is to manage it.
        $response->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(
            ['Live Sandal', 'Draft Sandal'],
            array_column($response->json('data'), 'name'),
        );
    }

    public function test_admin_listing_can_filter_by_active_state(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        Product::factory()->create(['name' => 'Live Sandal', 'is_active' => true]);
        Product::factory()->create(['name' => 'Draft Sandal', 'is_active' => false]);

        $active = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/products?active=1');
        $active->assertOk()->assertJsonCount(1, 'data');
        $active->assertJsonPath('data.0.name', 'Live Sandal');

        $inactive = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/products?active=0');
        $inactive->assertOk()->assertJsonCount(1, 'data');
        $inactive->assertJsonPath('data.0.name', 'Draft Sandal');
    }

    public function test_admin_listing_rolls_up_stock_across_variants(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        InventoryItem::factory()->for($product)->create([
            'quantity_available' => 40,
            'quantity_reserved' => 3,
            'low_stock_threshold' => 5,
        ]);
        // One healthy variant and one starved one. The starved size is what
        // makes the product low-stock; summed totals (42 available vs a
        // combined threshold of 10) would say otherwise and hide the restock.
        InventoryItem::factory()->for($product)->create([
            'quantity_available' => 2,
            'quantity_reserved' => 1,
            'low_stock_threshold' => 5,
        ]);

        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/products');

        $response->assertOk();
        $response->assertJsonPath('data.0.total_available', 42);
        $response->assertJsonPath('data.0.total_reserved', 4);
        $response->assertJsonPath('data.0.low_stock', true);
    }

    public function test_admin_can_search_the_catalogue_by_name_or_sku(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        Product::factory()->create(['name' => 'Kentehene Slide', 'sku' => 'GCT-100']);
        Product::factory()->create(['name' => 'Odeneho Ahenema', 'sku' => 'GCT-200']);

        // Case-insensitive, and matching on either field.
        $byName = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/products?q=kentehene');
        $byName->assertOk()->assertJsonCount(1, 'data');
        $byName->assertJsonPath('data.0.sku', 'GCT-100');

        $bySku = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/products?q=GCT-200');
        $bySku->assertOk()->assertJsonCount(1, 'data');
        $bySku->assertJsonPath('data.0.name', 'Odeneho Ahenema');
    }

    public function test_admin_can_view_one_product(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create(['name' => 'Ahenema']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price_ghs' => 15_000,
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin, 'admin')->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $product->id);
        $response->assertJsonPath('data.category_name', 'Ahenema');
        $response->assertJsonPath('data.base_price_ghs.amount', 15_000);
        $response->assertJsonPath('data.base_price_ghs.currency', 'GHS');
    }

    public function test_the_admin_product_payload_never_carries_a_stored_usd_price(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();

        $response = $this->actingAs($admin, 'admin')->getJson("/api/v1/admin/products/{$product->id}");

        // README Feature 2: USD is always derived, never stored. The admin
        // screens compute it at render time from the cached rate, so a dollar
        // figure here would be a second source of truth for a number that must
        // not have one.
        $response->assertOk();
        $response->assertJsonMissingPath('data.price_usd');
        $response->assertJsonMissingPath('data.base_price_usd');
    }

    public function test_staff_can_view_products_but_intern_sees_them_too(): void
    {
        $product = Product::factory()->create();

        // products.view is held by every tier down to Intern — reading the
        // catalogue is not the same as repricing it (AdminCapability).
        foreach (['staff', 'intern'] as $role) {
            $user = AdminUser::factory()->create(['role' => $role]);

            $this->actingAs($user, 'admin')->getJson('/api/v1/admin/products')->assertOk();
            $this->actingAs($user, 'admin')->getJson("/api/v1/admin/products/{$product->id}")->assertOk();
        }
    }

    public function test_guest_cannot_list_products_through_the_admin_api(): void
    {
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();
    }

    public function test_guest_cannot_create_a_product(): void
    {
        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'Guest Attempt',
            'slug' => 'guest-attempt',
            'base_price_ghs' => 1_000,
            'sku' => 'GUEST-001',
        ]);

        $response->assertUnauthorized();
    }
}

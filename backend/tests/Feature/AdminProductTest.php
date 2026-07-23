<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Collection;
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
        $response->assertJsonPath('data.collection.id', $collection->id);
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
        $response->assertJsonPath('data.base_price_ghs', 12_000);
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

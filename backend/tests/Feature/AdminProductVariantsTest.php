<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sizes and colourways set in admin, and the stock rows that follow them. */
class AdminProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'admin']);
    }

    /** @return list<string> */
    private function grid(Product $product): array
    {
        return InventoryItem::query()->where('product_id', $product->id)->get()
            ->map(fn ($row) => ($row->variant_attributes['colour'] ?? '-').' '.$row->variant_attributes['size'])
            ->sort()->values()->all();
    }

    public function test_a_product_created_with_sizes_and_colours_gets_a_row_for_each_pair(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/products', [
            'name' => 'Nana', 'slug' => 'nana', 'sku' => 'NANA-1', 'base_price_ghs' => 55_000,
            'product_type' => 'slippers',
            'sizes' => ['40', '41'],
            'colours' => [['name' => 'Tan', 'hex' => '#8b5a2b'], ['name' => 'Black', 'hex' => null]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sizes', ['40', '41'])
            ->assertJsonPath('data.colours.0', ['name' => 'Tan', 'hex' => '#8B5A2B'])
            ->assertJsonPath('data.product_type', 'slippers');

        $product = Product::first();
        $this->assertSame(['Black 40', 'Black 41', 'Tan 40', 'Tan 41'], $this->grid($product));
        $this->assertSame(0, (int) InventoryItem::query()->sum('quantity_available'));
    }

    public function test_sizes_can_be_added_and_an_empty_one_removed_later(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->create(['product_id' => $product->id, 'variant_attributes' => ['size' => '40'], 'quantity_available' => 3]);
        InventoryItem::factory()->create(['product_id' => $product->id, 'variant_attributes' => ['size' => '41'], 'quantity_available' => 0]);

        $this->actingAs($this->admin(), 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}/variants", ['sizes' => ['40', '42'], 'colours' => []])
            ->assertOk()
            ->assertJsonPath('data.sizes', ['40', '42']);

        $this->assertSame(['- 40', '- 42'], $this->grid($product));
        // The kept size keeps its count.
        $this->assertSame(3, InventoryItem::query()->where('variant_attributes->size', '40')->value('quantity_available'));
    }

    public function test_removing_a_size_with_stock_asks_first(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->create(['product_id' => $product->id, 'variant_attributes' => ['size' => '40'], 'quantity_available' => 3]);

        $this->actingAs($this->admin(), 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}/variants", ['sizes' => ['41'], 'colours' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('remove_stocked');
        $this->assertSame(['- 40'], $this->grid($product));

        $this->actingAs($this->admin(), 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}/variants", ['sizes' => ['41'], 'colours' => [], 'remove_stocked' => true])
            ->assertOk();
        $this->assertSame(['- 41'], $this->grid($product));
    }

    public function test_a_size_held_for_an_order_in_progress_is_never_removed(): void
    {
        $product = Product::factory()->create();
        $item = InventoryItem::factory()->create(['product_id' => $product->id, 'variant_attributes' => ['size' => '40'], 'quantity_available' => 3, 'quantity_reserved' => 0]);
        app(InventoryReservationService::class)->reserve($item, 1, now()->addMinutes(15), Order::factory()->create(['status' => 'pending']));

        $this->actingAs($this->admin(), 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}/variants", ['sizes' => ['41'], 'colours' => [], 'remove_stocked' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sizes');
        $this->assertSame(['- 40'], $this->grid($product));
    }

    public function test_adding_the_first_colour_replaces_the_colourless_rows(): void
    {
        $product = Product::factory()->create(['colors' => []]);
        InventoryItem::factory()->create(['product_id' => $product->id, 'variant_attributes' => ['size' => '40'], 'quantity_available' => 0]);

        $this->actingAs($this->admin(), 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}/variants", ['sizes' => ['40'], 'colours' => [['name' => 'Red', 'hex' => '#C6212B']]])
            ->assertOk();

        $this->assertSame(['Red 40'], $this->grid($product));
        $this->assertSame([['name' => 'Red', 'hex' => '#C6212B']], $product->fresh()->colors);
    }

    public function test_staff_cannot_change_sizes(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}/variants", ['sizes' => ['40'], 'colours' => []])
            ->assertForbidden();
    }
}

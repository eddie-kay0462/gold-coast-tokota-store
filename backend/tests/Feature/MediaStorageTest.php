<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['app.storefront_url' => 'https://shop.test']);
    }

    public function test_every_kind_of_stored_reference_becomes_a_loadable_url(): void
    {
        $this->assertSame('https://cdn.test/a.webp', MediaUrl::absolute('https://cdn.test/a.webp'));
        $this->assertSame('https://shop.test/products/domfo/1-tan.webp', MediaUrl::absolute('/products/domfo/1-tan.webp'));
        $this->assertStringEndsWith('/storage/media/2026/10/x.webp', MediaUrl::absolute('media/2026/10/x.webp'));
        $this->assertNull(MediaUrl::absolute(null));
    }

    public function test_a_product_photo_key_left_over_from_s3_resolves_to_the_storefront(): void
    {
        // Rows rewritten to `products/…` on 2 Oct still load from the
        // storefront, where the photos are committed.
        $this->assertSame('https://shop.test/products/domfo/1-tan.webp', MediaUrl::absolute('products/domfo/1-tan.webp'));

        Product::factory()->create([
            'slug' => 'domfo',
            'images' => ['products/domfo/1-tan.webp', '/products/domfo/2-green.webp'],
            'colour_images' => ['Tan' => ['products/domfo/1-tan.webp']],
        ]);

        $this->getJson('/api/v1/products/domfo')
            ->assertOk()
            ->assertJsonPath('data.images.0', 'https://shop.test/products/domfo/1-tan.webp')
            ->assertJsonPath('data.images.1', 'https://shop.test/products/domfo/2-green.webp')
            ->assertJsonPath('data.colour_images.Tan.0', 'https://shop.test/products/domfo/1-tan.webp');
    }
}

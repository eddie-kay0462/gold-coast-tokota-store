<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\MediaStorage;
use App\Support\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['filesystems.media_disk' => 'public', 'app.storefront_url' => 'https://shop.test']);

        // A stand-in for frontend/public/products: two styles, three photos.
        $this->source = storage_path('framework/testing/photo-source-'.uniqid());
        File::ensureDirectoryExists("{$this->source}/domfo");
        File::ensureDirectoryExists("{$this->source}/nshira");
        File::put("{$this->source}/domfo/1-tan.webp", 'tan-bytes');
        File::put("{$this->source}/domfo/2-green.webp", 'green-bytes');
        File::put("{$this->source}/nshira/1-brown.webp", 'brown-bytes');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->source);

        parent::tearDown();
    }

    private function domfo(): Product
    {
        return Product::factory()->create([
            'slug' => 'domfo',
            'images' => ['/products/domfo/1-tan.webp', '/products/domfo/2-green.webp'],
            'colour_images' => ['Tan' => ['/products/domfo/1-tan.webp'], 'Green' => ['/products/domfo/2-green.webp']],
        ]);
    }

    // --- URLs -------------------------------------------------------------

    public function test_every_kind_of_stored_reference_becomes_a_loadable_url(): void
    {
        $this->assertSame('https://cdn.test/a.webp', MediaUrl::absolute('https://cdn.test/a.webp'));
        $this->assertSame('https://shop.test/products/domfo/1-tan.webp', MediaUrl::absolute('/products/domfo/1-tan.webp'));
        $this->assertStringEndsWith('/storage/products/domfo/1-tan.webp', MediaUrl::absolute('products/domfo/1-tan.webp'));
        $this->assertNull(MediaUrl::absolute(null));
    }

    public function test_a_private_file_on_the_local_disk_falls_back_to_its_plain_url(): void
    {
        // The local `public` disk cannot sign URLs; development still works.
        $this->assertStringEndsWith('/storage/booking-references/x.jpg', MediaStorage::privateUrl('booking-references/x.jpg'));
    }

    // --- the import command ----------------------------------------------

    public function test_it_uploads_the_photos_then_points_products_at_them(): void
    {
        $product = $this->domfo();

        $this->artisan('media:import-product-photos', ['--source' => $this->source])->assertSuccessful();

        Storage::disk('public')->assertExists(['products/domfo/1-tan.webp', 'products/domfo/2-green.webp', 'products/nshira/1-brown.webp']);
        $this->assertSame('tan-bytes', Storage::disk('public')->get('products/domfo/1-tan.webp'));

        $product->refresh();
        $this->assertSame(['products/domfo/1-tan.webp', 'products/domfo/2-green.webp'], $product->images);
        $this->assertSame(['products/domfo/2-green.webp'], $product->colour_images['Green']);

        // And the storefront now gets image-storage URLs.
        $this->getJson('/api/v1/products/domfo')
            ->assertOk()
            ->assertJsonPath('data.images.0', Storage::disk('public')->url('products/domfo/1-tan.webp'));
    }

    public function test_a_photo_with_no_uploaded_file_keeps_its_old_path(): void
    {
        $product = Product::factory()->create([
            'images' => ['/products/domfo/1-tan.webp', '/products/missing/1-red.webp'],
        ]);

        $this->artisan('media:import-product-photos', ['--source' => $this->source])->assertSuccessful();

        $this->assertSame(['products/domfo/1-tan.webp', '/products/missing/1-red.webp'], $product->refresh()->images);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $product = $this->domfo();

        $this->artisan('media:import-product-photos', ['--source' => $this->source, '--dry-run' => true])->assertSuccessful();

        Storage::disk('public')->assertMissing('products/domfo/1-tan.webp');
        $this->assertSame('/products/domfo/1-tan.webp', $product->refresh()->images[0]);
    }

    public function test_running_it_twice_uploads_nothing_new(): void
    {
        $this->domfo();
        $this->artisan('media:import-product-photos', ['--source' => $this->source])->assertSuccessful();

        $this->artisan('media:import-product-photos', ['--source' => $this->source])
            ->expectsOutputToContain('0 uploaded, 3 already there')
            ->assertSuccessful();
    }

    public function test_without_a_source_it_only_repoints_at_photos_already_in_storage(): void
    {
        // Production: no frontend/ in the API container, photos already in S3.
        Storage::disk('public')->put('products/domfo/1-tan.webp', 'tan-bytes');
        $product = $this->domfo();

        $this->artisan('media:import-product-photos', ['--source' => '/nonexistent/path'])->assertSuccessful();

        $this->assertSame(['products/domfo/1-tan.webp', '/products/domfo/2-green.webp'], $product->refresh()->images);
    }
}

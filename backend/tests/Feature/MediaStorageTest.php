<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Product;
use App\Support\MediaStorage;
use App\Support\MediaUrl;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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

    public function test_diy_photos_go_to_the_private_disk_and_media_to_the_public_one(): void
    {
        Storage::fake('r2');
        Storage::fake('r2-private');
        config(['filesystems.media_disk' => 'r2', 'filesystems.private_media_disk' => 'r2-private']);

        $path = $this->postJson('/api/v1/booking-uploads', [
            'file' => UploadedFile::fake()->image('idea.jpg', 400, 300),
        ])->assertCreated()->json('data.path');

        Storage::disk('r2-private')->assertExists($path);
        Storage::disk('r2')->assertMissing($path);
        $this->assertSame('r2', MediaStorage::diskName());
    }

    public function test_a_private_photo_on_r2_gets_a_signed_link_that_expires(): void
    {
        // Signing happens locally, so no network or real bucket is needed.
        config([
            'filesystems.private_media_disk' => 'r2-private',
            'filesystems.disks.r2-private.key' => 'test-key',
            'filesystems.disks.r2-private.secret' => 'test-secret',
            'filesystems.disks.r2-private.bucket' => 'tokota-private',
            'filesystems.disks.r2-private.endpoint' => 'https://acct.r2.cloudflarestorage.com',
        ]);

        $url = MediaStorage::privateUrl('booking-references/2026/10/x.jpg');

        $this->assertStringStartsWith('https://acct.r2.cloudflarestorage.com/tokota-private/booking-references/2026/10/x.jpg?', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
        $this->assertStringContainsString('X-Amz-Expires=900', $url);
    }

    public function test_a_private_photo_on_the_local_disk_falls_back_to_its_plain_url(): void
    {
        $this->assertStringEndsWith('/storage/booking-references/x.jpg', MediaStorage::privateUrl('booking-references/x.jpg'));
    }

    public function test_the_r2_disks_use_the_s3_driver_with_r2_settings(): void
    {
        foreach (['r2', 'r2-private'] as $disk) {
            $this->assertSame('s3', config("filesystems.disks.{$disk}.driver"));
            $this->assertSame('auto', config("filesystems.disks.{$disk}.region"));
            $this->assertTrue(config("filesystems.disks.{$disk}.throw"));
        }

        // The private bucket must never be given a public address.
        $this->assertNull(config('filesystems.disks.r2-private.url'));
    }

    public function test_the_seeder_refuses_a_guessable_admin_password_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            (new DatabaseSeeder)->setContainer($this->app)->run();
            $this->fail('Seeding production without SEED_ADMIN_PASSWORD should throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SEED_ADMIN_PASSWORD', $e->getMessage());
        }

        $this->assertFalse(AdminUser::query()->exists());
    }

    public function test_the_seeder_uses_the_given_admin_password_in_production(): void
    {
        $this->app['env'] = 'production';
        putenv('SEED_ADMIN_PASSWORD=a-long-real-password');

        try {
            (new DatabaseSeeder)->setContainer($this->app)->run();
        } finally {
            putenv('SEED_ADMIN_PASSWORD');
        }

        $admin = AdminUser::query()->where('email', 'admin@goldcoasttokota.store')->firstOrFail();
        $this->assertTrue(Hash::check('a-long-real-password', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
    }
}

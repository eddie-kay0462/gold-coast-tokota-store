<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Support\Catalogue\CataloguePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A photo uploaded from the admin is named and stored exactly as
 * `catalogue:import-media` stores the client's photos.
 */
class AdminProductPhotoTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = AdminUser::factory()->create(['role' => 'admin']);
        $this->product = Product::factory()->create([
            'slug' => 'kente',
            'name' => 'Kente Ahenema',
            'images' => [],
            'colors' => [['name' => 'Gold', 'hex' => '#C8960C'], ['name' => 'Royal Blue', 'hex' => '#1E4C8F']],
            'colour_images' => [],
        ]);
    }

    public function test_an_upload_follows_the_catalogue_naming_and_format(): void
    {
        $response = $this->upload(['colour' => 'Royal Blue'], $this->photo(3024, 4032));

        $response->assertOk();
        $key = $this->product->fresh()->images[0];

        $this->assertMatchesRegularExpression('#^catalogue/kente/1-royal-blue-[0-9a-f]{8}\.webp$#', $key);
        $this->assertTrue(CataloguePhoto::belongsTo($key, 'kente'));

        $stored = Storage::disk('public')->get($key);
        $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer($stored));
        $this->assertSame([CataloguePhoto::FRAME_WIDTH, CataloguePhoto::FRAME_HEIGHT], array_slice(getimagesizefromstring($stored), 0, 2));

        $this->assertSame(['Royal Blue' => [$key]], $this->product->fresh()->colour_images);
        $response->assertJsonPath('data.colour_photos.1.keys.0', $key);

        $asset = MediaAsset::query()->where('path', $key)->firstOrFail();
        $this->assertSame('Kente Ahenema in Royal Blue', $asset->alt_text);
        $this->assertSame($this->admin->id, $asset->uploaded_by_admin_id);
        $this->assertSame('IMG_0001.jpg', $asset->filename);
    }

    public function test_photos_are_numbered_in_the_order_they_are_added(): void
    {
        $this->upload(['colour' => 'Gold'], $this->photo(1200, 1600, [200, 150, 0]))->assertOk();
        $this->upload(['colour' => 'Royal Blue'], $this->photo(1200, 1600, [30, 70, 140]))->assertOk();
        $this->upload(['colour' => 'Gold'], $this->photo(1600, 1200, [210, 160, 10]))->assertOk();

        $product = $this->product->fresh();

        $this->assertSame(['1-gold', '2-royal-blue', '3-gold'], array_map(
            fn ($key) => preg_replace('#^catalogue/kente/(.*)-[0-9a-f]{8}\.webp$#', '$1', $key),
            $product->images,
        ));
        $this->assertCount(2, $product->colour_images['Gold']);
    }

    public function test_the_colour_must_be_one_of_the_products(): void
    {
        $this->upload([], $this->photo(1200, 1600))->assertUnprocessable()->assertJsonValidationErrors('colour');
        $this->upload(['colour' => 'Purple'], $this->photo(1200, 1600))->assertUnprocessable()->assertJsonValidationErrors('colour');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_product_without_colourways_takes_photos_without_one(): void
    {
        $this->product->forceFill(['colors' => []])->save();

        $this->upload([], $this->photo(1200, 1600))->assertOk();

        $this->assertMatchesRegularExpression('#^catalogue/kente/1-[0-9a-f]{8}\.webp$#', $this->product->fresh()->images[0]);
    }

    public function test_small_or_non_image_files_are_refused(): void
    {
        $this->upload(['colour' => 'Gold'], $this->photo(300, 300))->assertJsonValidationErrors('file');
        $this->upload(['colour' => 'Gold'], UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))->assertJsonValidationErrors('file');
    }

    public function test_staff_cannot_upload(): void
    {
        $staff = AdminUser::factory()->create(['role' => 'staff']);

        $this->actingAs($staff, 'admin')
            ->post("/api/v1/admin/products/{$this->product->id}/photos", ['colour' => 'Gold', 'file' => $this->photo(1200, 1600)], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_removing_a_photo_takes_it_off_the_product_and_its_colourway(): void
    {
        $this->upload(['colour' => 'Gold'], $this->photo(1200, 1600))->assertOk();
        $key = $this->product->fresh()->images[0];

        $this->actingAs($this->admin, 'admin')
            ->deleteJson("/api/v1/admin/products/{$this->product->id}/photos", ['key' => $key])
            ->assertOk();

        $product = $this->product->fresh();
        $this->assertSame([], $product->images);
        $this->assertSame(['Gold' => []], $product->colour_images);
        // Still in the library, so it can be found again.
        Storage::disk('public')->assertExists($key);
    }

    public function test_the_product_endpoint_reorders_and_removes_but_never_adds_paths(): void
    {
        $this->upload(['colour' => 'Gold'], $this->photo(1200, 1600, [200, 150, 0]))->assertOk();
        $this->upload(['colour' => 'Royal Blue'], $this->photo(1200, 1600, [30, 70, 140]))->assertOk();
        [$gold, $blue] = $this->product->fresh()->images;
        $url = "/api/v1/admin/products/{$this->product->id}";

        $this->actingAs($this->admin, 'admin')->putJson($url, ['images' => [$blue, 'media/2026/10/x.jpg']])
            ->assertJsonValidationErrors('images.1');

        $this->actingAs($this->admin, 'admin')->putJson($url, ['images' => [$blue, $gold]])->assertOk();
        $this->assertSame([$blue, $gold], $this->product->fresh()->images);

        $this->actingAs($this->admin, 'admin')->putJson($url, ['images' => [$blue]])->assertOk();
        $this->assertSame(['Gold' => [], 'Royal Blue' => [$blue]], $this->product->fresh()->colour_images);

        // And a new product starts with none; photos come after it exists.
        $this->actingAs($this->admin, 'admin')->postJson('/api/v1/admin/products', [
            'name' => 'Nana', 'slug' => 'nana', 'base_price_ghs' => 55_000, 'sku' => 'NANA-1',
            'images' => ['https://elsewhere.example/x.jpg'],
        ])->assertJsonValidationErrors('images');
    }

    private function upload(array $fields, UploadedFile $file)
    {
        return $this->actingAs($this->admin, 'admin')->post(
            "/api/v1/admin/products/{$this->product->id}/photos",
            [...$fields, 'file' => $file],
            ['Accept' => 'application/json'],
        );
    }

    /** @param array<int, int> $rgb */
    private function photo(int $width, int $height, array $rgb = [120, 80, 40]): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        $path = tempnam(sys_get_temp_dir(), 'photo');
        imagejpeg($image, $path, 90);

        return new UploadedFile($path, 'IMG_0001.jpg', 'image/jpeg', null, true);
    }
}

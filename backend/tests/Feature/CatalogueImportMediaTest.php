<?php

namespace Tests\Feature;

use App\Console\Commands\ImportCatalogueMedia;
use App\Models\InventoryItem;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Support\Catalogue\ProductUpserter;
use App\Support\MediaStorage;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogueImportMediaTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $manifest;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->dir = sys_get_temp_dir().'/catalogue-import-'.uniqid();
        $this->manifest = $this->dir.'/products.json';
        File::makeDirectory($this->dir.'/Slippers/Domfo', recursive: true);
        File::makeDirectory($this->dir.'/Ahenema/Kente', recursive: true);

        $this->frame('Slippers/Domfo/IMG_1.framed.png');
        $this->frame('Slippers/Domfo/IMG_2.framed.png', [40, 40, 40]);
        $this->frame('Slippers/Domfo/IMG_3.plain.png', [90, 60, 30]);
        $this->frame('Ahenema/Kente/A.framed.png', [200, 150, 0]);

        $this->seed(ProductSeeder::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_it_uploads_photos_and_points_the_product_at_them(): void
    {
        $domfo = Product::query()->where('slug', 'domfo')->firstOrFail();
        $price = $domfo->base_price_ghs;
        $stock = InventoryItem::query()->where('product_id', $domfo->id)->sum('quantity_available');

        $this->writeManifest(['domfo' => $this->domfoSource()]);

        $this->artisan('catalogue:import-media', ['dir' => $this->dir, '--manifest' => $this->manifest])
            ->assertSuccessful();

        $domfo->refresh();

        $this->assertCount(3, $domfo->images);

        foreach ($domfo->images as $key) {
            $this->assertMatchesRegularExpression('#^catalogue/domfo/\d-[a-z-]+-[0-9a-f]{8}\.webp$#', $key);
            Storage::disk('public')->assertExists($key);
            $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer(Storage::disk('public')->get($key)));
        }

        $this->assertSame(['Tan', 'Black'], array_keys($domfo->colour_images));
        $this->assertCount(2, $domfo->colour_images['Tan']);
        $this->assertSame(3, MediaAsset::query()->where('path', 'like', 'catalogue/domfo/%')->count());

        // Only the photos changed.
        $this->assertSame($price, $domfo->base_price_ghs);
        $this->assertEquals($stock, InventoryItem::query()->where('product_id', $domfo->id)->sum('quantity_available'));

        // And they resolve to the media disk, not the storefront.
        $this->getJson('/api/v1/products/domfo')
            ->assertOk()
            ->assertJsonPath('data.images.0', MediaStorage::url($domfo->images[0]))
            ->assertJsonPath('data.colour_images.Black.0', MediaStorage::url($domfo->colour_images['Black'][0]));
    }

    public function test_a_keep_photo_publishes_the_plain_version(): void
    {
        $source = $this->domfoSource();
        $source['photos'][1]['background'] = 'keep';
        $source['photos'][1]['file'] = 'IMG_3.jpg';
        $this->writeManifest(['domfo' => $source]);

        $this->artisan('catalogue:import-media', ['dir' => $this->dir, '--manifest' => $this->manifest])
            ->assertSuccessful();

        $key = Product::query()->where('slug', 'domfo')->value('images')[1] ?? null;
        $this->assertNotNull($key);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($key));
        $this->assertSame([ImportCatalogueMedia::FRAME_WIDTH, ImportCatalogueMedia::FRAME_HEIGHT], [$width, $height]);
    }

    public function test_running_it_again_uploads_nothing_new(): void
    {
        $this->writeManifest(['domfo' => $this->domfoSource()]);
        $args = ['dir' => $this->dir, '--manifest' => $this->manifest];

        $this->artisan('catalogue:import-media', $args)->assertSuccessful();
        $first = Product::query()->where('slug', 'domfo')->value('images');

        $this->artisan('catalogue:import-media', $args)
            ->expectsOutputToContain('0 photos uploaded (3 already there)')
            ->assertSuccessful();

        $this->assertSame($first, Product::query()->where('slug', 'domfo')->value('images'));
        $this->assertCount(3, Storage::disk('public')->allFiles('catalogue/domfo'));
        $this->assertSame(3, MediaAsset::query()->count());
    }

    public function test_a_new_style_is_created_with_its_inventory(): void
    {
        $this->writeManifest([], [[
            'slug' => 'kente',
            'name' => 'Kente Ahenema',
            'base_price_ghs' => 45000,
            'images' => [],
            'color' => 'Gold',
            'colors' => [['name' => 'Gold', 'hex' => '#C8960C']],
            'product_type' => 'sandals',
            'departments' => ['womens'],
            'sizes' => ['38', '39'],
            'materials' => ['Leather'],
            'category' => 'ahenema',
            'source' => ['dir' => 'Ahenema/Kente', 'photos' => [['file' => 'A.jpg', 'colour' => 'Gold']]],
        ]]);

        $this->artisan('catalogue:import-media', ['dir' => $this->dir, '--manifest' => $this->manifest])
            ->assertSuccessful();

        $kente = Product::query()->with('category')->where('slug', 'kente')->firstOrFail();

        $this->assertSame('ahenema', $kente->category->slug);
        $this->assertSame(45000, $kente->base_price_ghs);
        $this->assertCount(1, $kente->images);
        $this->assertSame(2, InventoryItem::query()->where('product_id', $kente->id)->count());
    }

    public function test_an_existing_style_takes_the_fixtures_category_and_new_colours(): void
    {
        $domfo = Product::query()->where('slug', 'domfo')->firstOrFail();
        $domfo->forceFill(['base_price_ghs' => 12345])->save();
        $colours = array_column($domfo->colors, 'name');
        $stock = InventoryItem::query()->where('product_id', $domfo->id)->sum('quantity_available');
        $sizes = InventoryItem::query()->where('product_id', $domfo->id)->get()->pluck('variant_attributes.size')->unique()->count();

        $this->writeManifest(['domfo' => $this->domfoSource()]);
        $manifest = json_decode(File::get($this->manifest), true);

        foreach ($manifest as &$entry) {
            if ($entry['slug'] === 'domfo') {
                $entry['category'] = 'ahenema';
                $entry['product_type'] = 'ahenema';
                $entry['colors'][] = ['name' => 'Grey', 'hex' => '#6C685E'];
                $entry['source']['photos'][] = ['file' => 'IMG_2.HEIC', 'colour' => 'Grey'];
            }
        }

        File::put($this->manifest, json_encode($manifest));
        $args = ['dir' => $this->dir, '--manifest' => $this->manifest];

        $this->artisan('catalogue:import-media', [...$args, '--dry-run' => true])
            ->expectsOutputToContain('domfo: category slippers → ahenema; type slippers → ahenema; adds colour Grey')
            ->assertSuccessful();

        $this->assertSame('slippers', $domfo->fresh()->category->slug);

        $this->artisan('catalogue:import-media', $args)->assertSuccessful();

        $domfo = $domfo->fresh();
        $this->assertSame('ahenema', $domfo->category->slug);
        $this->assertSame('ahenema', $domfo->product_type);
        $this->assertSame([...$colours, 'Grey'], array_column($domfo->colors, 'name'));
        $this->assertArrayHasKey('Grey', $domfo->colour_images);
        $this->assertSame(12345, $domfo->base_price_ghs);

        $grey = InventoryItem::query()->where('product_id', $domfo->id)->where('variant_attributes->colour', 'Grey');
        $this->assertSame($sizes, $grey->count());
        $this->assertEquals($stock + $sizes * ProductUpserter::DEV_STOCK_PER_SIZE,
            InventoryItem::query()->where('product_id', $domfo->id)->sum('quantity_available'));

        // A second run has nothing left to change.
        $this->artisan('catalogue:import-media', [...$args, '--dry-run' => true])
            ->doesntExpectOutputToContain('domfo: ')
            ->assertSuccessful();

        // And a restore takes the category back with the photos.
        $backup = collect(File::files(storage_path('app/catalogue-import')))->sortByDesc(fn ($f) => $f->getMTime())->first();
        $this->artisan('catalogue:import-media', ['--restore' => $backup->getPathname()])->assertSuccessful();

        $this->assertSame('slippers', $domfo->fresh()->category->slug);
        $this->assertSame($colours, array_column($domfo->fresh()->colors, 'name'));

        File::delete($backup->getPathname());
    }

    public function test_a_problem_anywhere_stops_the_run_before_any_write(): void
    {
        $before = Product::query()->where('slug', 'domfo')->value('images');

        $cases = [
            'missing cut-out' => fn ($s) => [...$s, 'photos' => [...$s['photos'], ['file' => 'IMG_9.jpg', 'colour' => 'Tan']]],
            'unknown colour' => fn ($s) => [...$s, 'photos' => [['file' => 'IMG_1.jpg', 'colour' => 'Purple']]],
            'missing plain version' => fn ($s) => [...$s, 'photos' => [['file' => 'IMG_1.jpg', 'colour' => 'Tan', 'background' => 'keep']]],
        ];

        foreach ($cases as $case => $mutate) {
            $this->writeManifest(['domfo' => $mutate($this->domfoSource())]);

            $this->artisan('catalogue:import-media', ['dir' => $this->dir, '--manifest' => $this->manifest])
                ->expectsOutputToContain('Nothing was uploaded or written.')
                ->assertFailed();

            $this->assertSame([], Storage::disk('public')->allFiles('catalogue'), $case);
            $this->assertSame($before, Product::query()->where('slug', 'domfo')->value('images'), $case);
        }
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $before = Product::query()->where('slug', 'domfo')->value('images');
        $this->writeManifest(['domfo' => $this->domfoSource()]);

        $this->artisan('catalogue:import-media', ['dir' => $this->dir, '--manifest' => $this->manifest, '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($before, Product::query()->where('slug', 'domfo')->value('images'));
        $this->assertSame(0, MediaAsset::query()->count());
    }

    public function test_restore_puts_the_previous_photos_back(): void
    {
        $before = Product::query()->where('slug', 'domfo')->firstOrFail();
        $this->writeManifest(['domfo' => $this->domfoSource()]);

        $this->artisan('catalogue:import-media', ['dir' => $this->dir, '--manifest' => $this->manifest])->assertSuccessful();

        $backup = collect(File::files(storage_path('app/catalogue-import')))->sortByDesc(fn ($f) => $f->getMTime())->first();

        $this->artisan('catalogue:import-media', ['--restore' => $backup->getPathname()])->assertSuccessful();

        $after = Product::query()->where('slug', 'domfo')->firstOrFail();
        $this->assertSame($before->images, $after->images);
        $this->assertEquals($before->colour_images, $after->colour_images);

        File::delete($backup->getPathname());
    }

    /**
     * The import checks this too, but only against a local copy of the Drive
     * folders; this catches a bad edit to the fixture in CI.
     */
    public function test_the_real_fixtures_sources_are_consistent(): void
    {
        $entries = json_decode(File::get(database_path('data/products.json')), true);
        $slugs = array_column($entries, 'slug');

        $this->assertSame(count($slugs), count(array_unique($slugs)), 'duplicate slugs');

        foreach ($entries as $entry) {
            $colours = array_column($entry['colors'], 'name');

            $this->assertNotEmpty($entry['source']['photos'] ?? [], "{$entry['slug']} has no source photos");
            $this->assertGreaterThan(0, $entry['base_price_ghs'], $entry['slug']);
            $this->assertNotEmpty($entry['sizes'], $entry['slug']);

            foreach ($entry['source']['photos'] as $photo) {
                $this->assertContains($photo['colour'], $colours, "{$entry['slug']}/{$photo['file']}");
            }

            // Every colour on sale has a photo, so the gallery can follow it.
            $this->assertEqualsCanonicalizing($colours, array_values(array_unique(array_column($entry['source']['photos'], 'colour'))), $entry['slug']);
        }

        // Photo-less styles wait for the import rather than being seeded bare.
        $this->assertSame(
            count(array_filter($entries, fn ($e) => ! empty($e['images']))),
            Product::query()->count(),
        );
    }

    public function test_reseeding_keeps_imported_photos(): void
    {
        $this->writeManifest(['domfo' => $this->domfoSource()]);
        $this->artisan('catalogue:import-media', ['dir' => $this->dir, '--manifest' => $this->manifest])->assertSuccessful();

        $imported = Product::query()->where('slug', 'domfo')->value('images');

        $this->seed(ProductSeeder::class);

        $this->assertSame($imported, Product::query()->where('slug', 'domfo')->value('images'));
        // Products still on storefront photos are refreshed from the fixture as before.
        $this->assertStringStartsWith('/products/', Product::query()->where('slug', 'osram')->value('images')[0]);
    }

    /** @return array<string, mixed> */
    private function domfoSource(): array
    {
        return ['dir' => 'Slippers/Domfo', 'photos' => [
            ['file' => 'IMG_1.jpg', 'colour' => 'Tan'],
            ['file' => 'IMG_2.HEIC', 'colour' => 'Black'],
            ['file' => 'IMG_1.jpg', 'colour' => 'Tan', 'background' => 'remove'],
        ]];
    }

    /**
     * The real fixture with `source` blocks added, plus any extra entries.
     *
     * @param  array<string, array<string, mixed>>  $sources
     * @param  array<int, array<string, mixed>>  $extra
     */
    private function writeManifest(array $sources, array $extra = []): void
    {
        $entries = json_decode(File::get(database_path('data/products.json')), true);

        foreach ($entries as &$entry) {
            unset($entry['source']);

            if (isset($sources[$entry['slug']])) {
                $entry['source'] = $sources[$entry['slug']];
            }
        }

        File::put($this->manifest, json_encode([...$entries, ...$extra]));
    }

    /** @param array<int, int> $rgb */
    private function frame(string $relative, array $rgb = [245, 244, 244]): void
    {
        $image = imagecreatetruecolor(ImportCatalogueMedia::FRAME_WIDTH, ImportCatalogueMedia::FRAME_HEIGHT);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        imagepng($image, $this->dir.'/'.$relative);
    }
}

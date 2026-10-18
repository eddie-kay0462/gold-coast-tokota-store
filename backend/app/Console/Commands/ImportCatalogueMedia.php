<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Catalogue\CataloguePhoto;
use App\Support\Catalogue\ProductUpserter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Publishes the client's product photographs to the media disk (R2 in
 * production) and points each product at them.
 *
 * Input is the tree `scripts/catalogue/cutout.py` writes from the client's
 * Drive folders: for every photo, `{stem}.framed.png` (background removed,
 * placed on the storefront grey in the 9:10 card frame) and `{stem}.plain.png`
 * (the original, cropped to the same frame). Which photos belong to which
 * product, in what order and colour, comes from the `source` block of each
 * entry in `database/data/products.json`; a photo marked
 * `"background": "keep"` publishes its plain version.
 *
 * Existing products get new `images` and `colour_images`, and are brought in
 * line with the fixture's category, product type and colours (a colour is only
 * added, never removed; see ProductUpserter::catchUp). Price, name and stock
 * are the admin's. A style the database doesn't have yet is created from its
 * fixture entry first. Every photo is checked before anything is
 * uploaded or written, and the previous photos are saved to a backup file
 * that `--restore` puts back. See scripts/catalogue/README.md.
 */
class ImportCatalogueMedia extends Command
{
    protected $signature = 'catalogue:import-media
        {dir? : The tree cutout.py wrote (e.g. ~/tokota-cutout)}
        {--only= : Comma-separated slugs to import; default is every entry with a source}
        {--dry-run : Check and list what would happen; upload and write nothing}
        {--quality=82 : WebP quality}
        {--manifest= : Products fixture (default: database/data/products.json)}
        {--restore= : Put back the photos saved in this backup file, then stop}';

    protected $description = 'Upload the client\'s framed product photos to the media disk and point products at them';

    /** cutout.py's frame: the storefront's 9:10 product card. */
    public const FRAME_WIDTH = CataloguePhoto::FRAME_WIDTH;

    public const FRAME_HEIGHT = CataloguePhoto::FRAME_HEIGHT;

    public function handle(ProductUpserter $upserter): int
    {
        if ($restore = $this->option('restore')) {
            return $this->restore($this->expandHome($restore));
        }

        $dir = $this->argument('dir');

        if (! $dir) {
            $this->error('Give the directory cutout.py wrote, e.g. php artisan catalogue:import-media ~/tokota-cutout');

            return self::FAILURE;
        }

        $dir = rtrim($this->expandHome($dir), '/');

        if (! is_dir($dir)) {
            $this->error("No such directory: {$dir}");

            return self::FAILURE;
        }

        try {
            $plan = $this->plan($this->entries(), $dir, $upserter);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('Nothing was uploaded or written.');

            return self::FAILURE;
        }

        if ($plan === []) {
            $this->warn('No products.json entry has a `source` block (or none matched --only).');

            return self::SUCCESS;
        }

        $this->report($plan);

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing uploaded or written.');

            return self::SUCCESS;
        }

        $backup = $this->backup($plan);
        $this->line("Previous photos saved to {$backup} (undo with --restore={$backup})");

        $uploaded = 0;

        foreach ($plan as $item) {
            foreach ($item['photos'] as $photo) {
                $uploaded += (int) CataloguePhoto::store(
                    $photo['key'],
                    $photo['bytes'],
                    $photo['file'],
                    CataloguePhoto::altText($item['entry'], $photo['colour']),
                );
            }

            // Uploads first, row second: a product is only ever pointed at
            // photos that are already there.
            DB::transaction(function () use ($item, $upserter) {
                $product = Product::query()->where('slug', $item['entry']['slug'])->first();

                if ($product) {
                    $upserter->catchUp($product, $item['entry']);
                } else {
                    $product = $upserter->upsert($item['entry']);
                }

                $product->forceFill([
                    'images' => array_column($item['photos'], 'key'),
                    'colour_images' => $this->colourImages($item['photos']),
                ])->save();
            });
        }

        $this->info(sprintf('Done: %d products, %d photos uploaded (%d already there).',
            count($plan),
            $uploaded,
            array_sum(array_map(fn ($item) => count($item['photos']), $plan)) - $uploaded,
        ));

        return self::SUCCESS;
    }

    /**
     * Checks every photo and builds its WebP before anything is uploaded, so
     * one missing file or misspelt colour stops the whole run cleanly.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array{entry: array<string, mixed>, exists: bool, changes: array<int, string>, photos: array<int, array<string, mixed>>}>
     */
    private function plan(array $entries, string $dir, ProductUpserter $upserter): array
    {
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $known = array_column($entries, 'slug');

        foreach (array_diff($only, $known) as $missing) {
            throw new RuntimeException("--only names a slug that isn't in products.json: {$missing}");
        }

        $plan = [];

        foreach ($entries as $entry) {
            if (empty($entry['source']) || ($only && ! in_array($entry['slug'], $only, true))) {
                continue;
            }

            $slug = $entry['slug'];
            $source = $entry['source'];
            $colours = array_column($entry['colors'] ?? [], 'name');
            $photos = [];

            if (empty($source['dir']) || empty($source['photos'])) {
                throw new RuntimeException("{$slug}: `source` needs a `dir` and at least one photo.");
            }

            foreach (array_values($source['photos']) as $index => $photo) {
                $file = $photo['file'] ?? null;
                $colour = $photo['colour'] ?? null;
                $background = $photo['background'] ?? 'remove';

                if (! $file) {
                    throw new RuntimeException("{$slug}: photo ".($index + 1).' has no `file`.');
                }

                if ($colour !== null && ! in_array($colour, $colours, true)) {
                    throw new RuntimeException("{$slug}/{$file}: colour \"{$colour}\" isn't one of the product's colors (".implode(', ', $colours).').');
                }

                if (! in_array($background, ['remove', 'keep'], true)) {
                    throw new RuntimeException("{$slug}/{$file}: `background` must be \"remove\" or \"keep\".");
                }

                $variant = $background === 'remove' ? 'framed' : 'plain';
                $path = $dir.'/'.trim($source['dir'], '/').'/'.pathinfo($file, PATHINFO_FILENAME).".{$variant}.png";

                if (! is_file($path)) {
                    throw new RuntimeException("{$slug}/{$file}: {$path} is missing. Run scripts/catalogue/cutout.py first.");
                }

                $png = file_get_contents($path);
                $bytes = $this->webp($png, $path);

                $photos[] = [
                    'file' => $file,
                    'colour' => $colour,
                    'background' => $background,
                    'key' => CataloguePhoto::key($slug, $index + 1, $colour, sha1($png.'|q'.$this->option('quality'))),
                    'bytes' => $bytes,
                ];
            }

            $existing = Product::query()->with('category')->where('slug', $slug)->first();

            $plan[] = [
                'entry' => $entry,
                'exists' => $existing !== null,
                'changes' => $existing ? $upserter->catchUp($existing, $entry, write: false) : [],
                'photos' => $photos,
            ];
        }

        return $plan;
    }

    private function webp(string $png, string $path): string
    {
        $image = @imagecreatefromstring($png);

        if ($image === false) {
            throw new RuntimeException("{$path} isn't a readable image.");
        }

        try {
            return CataloguePhoto::webp($image, (int) $this->option('quality'));
        } catch (RuntimeException $e) {
            throw new RuntimeException("{$path}: {$e->getMessage()} Frame it with cutout.py.");
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $photos
     * @return array<string, array<int, string>>
     */
    private function colourImages(array $photos): array
    {
        $byColour = [];

        foreach ($photos as $photo) {
            if ($photo['colour'] !== null) {
                $byColour[$photo['colour']][] = $photo['key'];
            }
        }

        return $byColour;
    }

    /** @param array<int, array<string, mixed>> $plan */
    private function report(array $plan): void
    {
        $rows = [];

        foreach ($plan as $item) {
            foreach ($item['photos'] as $i => $photo) {
                $rows[] = [
                    $i === 0 ? $item['entry']['slug'].($item['exists'] ? '' : ' (new)') : '',
                    $photo['file'],
                    $photo['colour'] ?? '—',
                    $photo['background'],
                    round(strlen($photo['bytes']) / 1024).' KB',
                    $photo['key'],
                ];
            }
        }

        $this->table(['Product', 'Photo', 'Colour', 'Background', 'WebP', 'Key'], $rows);

        foreach ($plan as $item) {
            if ($item['changes'] !== []) {
                $this->line("{$item['entry']['slug']}: ".implode('; ', $item['changes']));
            }
        }
    }

    /** @param array<int, array<string, mixed>> $plan */
    private function backup(array $plan): string
    {
        $before = Product::query()
            ->whereIn('slug', array_map(fn ($item) => $item['entry']['slug'], $plan))
            ->get(['slug', 'images', 'colour_images', 'category_id', 'product_type', 'colors'])
            ->mapWithKeys(fn (Product $p) => [$p->slug => [
                'images' => $p->images ?? [],
                'colour_images' => $p->colour_images ?? [],
                'category_id' => $p->category_id,
                'product_type' => $p->product_type,
                'colors' => $p->colors ?? [],
            ]])
            ->all();

        $path = storage_path('app/catalogue-import/'.now()->format('Ymd-His').'-before.json');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function restore(string $path): int
    {
        if (! is_file($path)) {
            $this->error("No such backup: {$path}");

            return self::FAILURE;
        }

        $before = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($before) {
            foreach ($before as $slug => $saved) {
                // Backups from before catchUp() hold only the photos.
                Product::query()->where('slug', $slug)->first()?->forceFill(
                    array_intersect_key($saved, array_flip(['images', 'colour_images', 'category_id', 'product_type', 'colors'])),
                )->save();
            }
        });

        // Products the import created aren't in the backup and stay, as do
        // inventory rows for colours it added (unsold, they're harmless); the
        // uploaded files stay on the disk too, so a restore can be undone by
        // running the import again.
        $this->info('Restored photos for '.count($before).' products.');

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function entries(): array
    {
        $path = $this->option('manifest') ? $this->expandHome($this->option('manifest')) : database_path('data/products.json');

        if (! is_file($path)) {
            throw new RuntimeException("Missing products fixture: {$path}");
        }

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /** `--dir=~/x` reaches PHP unexpanded: the shell only expands a leading ~. */
    private function expandHome(string $path): string
    {
        return str_starts_with($path, '~/') ? rtrim((string) getenv('HOME'), '/').substr($path, 1) : $path;
    }
}

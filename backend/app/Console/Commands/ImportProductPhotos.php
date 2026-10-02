<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\MediaStorage;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Copies the product photographs into image storage and points the catalogue
 * at them.
 *
 *   php artisan media:import-product-photos            # into MEDIA_DISK
 *   php artisan media:import-product-photos --dry-run  # report only
 *
 * Source: frontend/public/products/<slug>/<file>, where the client's photos
 * were first committed. Destination key: products/<slug>/<file> on the image
 * disk — local storage in development, the S3 bucket in production.
 *
 * Two phases, deliberately in this order: upload everything, and only if
 * every upload succeeded rewrite `products.images` / `colour_images` from
 * `/products/…` (served by the storefront) to `products/…` (served from image
 * storage). A failed or partial upload therefore leaves the shop exactly as
 * it was rather than pointing products at files that aren't there.
 *
 * Idempotent: files already present with the same size are skipped, and rows
 * already rewritten are left alone, so it is safe to run on every deploy.
 *
 * Production runs it on boot (Dockerfile). The API container has no
 * frontend/ directory, so there the upload phase is skipped and only the
 * repointing runs — against the bucket, which the photos were uploaded to
 * once from a developer machine (`MEDIA_DISK=s3 php artisan
 * media:import-product-photos`). Repointing checks each key exists in the
 * bucket first, so it is harmless before that upload has happened.
 */
class ImportProductPhotos extends Command
{
    protected $signature = 'media:import-product-photos
        {--source= : Directory holding <slug>/<file> photos (default: ../frontend/public/products)}
        {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Copy product photos into image storage (MEDIA_DISK) and point products at them';

    public function handle(): int
    {
        $source = rtrim((string) ($this->option('source') ?: base_path('../frontend/public/products')), '/');
        $dryRun = (bool) $this->option('dry-run');
        $disk = MediaStorage::disk();

        $uploaded = $skipped = 0;

        if (! is_dir($source)) {
            $this->warn("No source photos at {$source} — skipping upload, repointing only.");
        } else {
            $this->info(sprintf('Importing from %s into the "%s" disk%s.', $source, MediaStorage::diskName(), $dryRun ? ' (dry run)' : ''));
        }

        foreach (is_dir($source) ? Finder::create()->files()->in($source)->sortByName() : [] as $file) {
            $key = 'products/'.str_replace('\\', '/', $file->getRelativePathname());

            try {
                if ($disk->exists($key) && $disk->size($key) === $file->getSize()) {
                    $skipped++;

                    continue;
                }

                if (! $dryRun) {
                    $disk->put($key, $file->getContents(), [
                        // A day, not a year: a reshoot may reuse a filename.
                        'CacheControl' => 'public, max-age=86400',
                    ]);
                }

                $uploaded++;
                $this->line("  ↑ {$key}");
            } catch (Throwable $e) {
                $this->error("Upload failed for {$key}: {$e->getMessage()}");
                $this->error('Nothing was repointed — products still use their current photos. Fix the error and run again.');

                return self::FAILURE;
            }
        }

        $this->info("{$uploaded} uploaded, {$skipped} already there.");

        $repointed = $this->repointProducts($dryRun);
        $this->info("{$repointed} product(s) ".($dryRun ? 'would be' : 'now').' pointed at image storage.');

        return self::SUCCESS;
    }

    /**
     * `/products/x.webp` → `products/x.webp`, but only where that key now
     * exists in image storage — a path with no file behind it is left as it
     * was, rather than swapped for a broken one.
     */
    private function repointProducts(bool $dryRun): int
    {
        $disk = MediaStorage::disk();
        $changed = 0;

        $toKey = function (string $reference) use ($disk): string {
            if (! str_starts_with($reference, '/products/')) {
                return $reference;
            }

            $key = ltrim($reference, '/');

            return $disk->exists($key) ? $key : $reference;
        };

        foreach (Product::query()->get() as $product) {
            $images = array_map($toKey, $product->images ?? []);
            $colourImages = array_map(
                fn (array $paths) => array_map($toKey, $paths),
                $product->colour_images ?? [],
            );

            if ($images === ($product->images ?? []) && $colourImages === ($product->colour_images ?? [])) {
                continue;
            }

            $changed++;

            if (! $dryRun) {
                $product->forceFill(['images' => $images, 'colour_images' => $colourImages])->save();
            }
        }

        return $changed;
    }
}

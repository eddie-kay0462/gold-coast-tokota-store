<?php

namespace App\Support\Catalogue;

use App\Models\MediaAsset;
use App\Models\Product;
use App\Support\MediaStorage;
use GdImage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * How a product photo is named, shaped and stored on the media disk (R2 in
 * production). One place, so the `catalogue:import-media` command and the
 * admin's product photo upload can't drift apart:
 *
 *   catalogue/{slug}/{n}-{colour}-{hash}.webp
 *
 * - `n` is the photo's position on the product when it was added (1-based).
 * - `colour` is the colourway, slugged; left out for a photo with no colour.
 * - `hash` is 8 hex characters of the content, so a changed photo gets a new
 *   key (CDN caches never serve the old one) and the same photo twice is one
 *   object.
 *
 * Every photo is a 1500×1667 WebP: the storefront's 9:10 product card.
 *
 * Never under `products/`: MediaUrl reads that prefix as a storefront path,
 * not a media-disk key.
 */
final class CataloguePhoto
{
    public const FRAME_WIDTH = 1500;

    public const FRAME_HEIGHT = 1667;

    public const QUALITY = 82;

    public const PREFIX = 'catalogue/';

    public static function key(string $slug, int $position, ?string $colour, string $hash): string
    {
        $name = implode('-', array_filter([$position, $colour ? Str::slug($colour) : null, substr($hash, 0, 8)]));

        return self::PREFIX."{$slug}/{$name}.webp";
    }

    /** True for a key this class would have made for the given product. */
    public static function belongsTo(string $key, string $slug): bool
    {
        return (bool) preg_match('#^'.preg_quote(self::PREFIX.$slug, '#').'/\d+(-[a-z0-9-]+)?-[0-9a-f]{8}\.webp$#', $key);
    }

    /**
     * Any photo (JPEG, PNG, WebP, AVIF), turned upright and cover-cropped to
     * the card frame. The same crop `scripts/catalogue/cutout.py` makes for
     * its `.plain.png`, so an uploaded photo matches an imported one.
     */
    public static function frame(string $bytes): GdImage
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new RuntimeException('Not a readable image.');
        }

        $image = self::upright($image, $bytes);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = max(self::FRAME_WIDTH / $width, self::FRAME_HEIGHT / $height);
        $cropWidth = (int) round(self::FRAME_WIDTH / $scale);
        $cropHeight = (int) round(self::FRAME_HEIGHT / $scale);

        $frame = imagecreatetruecolor(self::FRAME_WIDTH, self::FRAME_HEIGHT);
        imagecopyresampled(
            $frame, $image, 0, 0,
            (int) (($width - $cropWidth) / 2), (int) (($height - $cropHeight) / 2),
            self::FRAME_WIDTH, self::FRAME_HEIGHT, $cropWidth, $cropHeight,
        );

        return $frame;
    }

    public static function webp(GdImage $image, int $quality = self::QUALITY): string
    {
        if (imagesx($image) !== self::FRAME_WIDTH || imagesy($image) !== self::FRAME_HEIGHT) {
            throw new RuntimeException(sprintf('Photo is %d×%d; product photos are %d×%d.',
                imagesx($image), imagesy($image), self::FRAME_WIDTH, self::FRAME_HEIGHT));
        }

        ob_start();
        imagewebp($image, null, $quality);

        return (string) ob_get_clean();
    }

    /**
     * Puts the WebP on the media disk (unless it's already there) and records
     * it in the media library. Returns whether it was uploaded.
     */
    public static function store(string $key, string $webp, string $filename, ?string $altText, ?int $uploadedBy = null): bool
    {
        $disk = MediaStorage::disk();
        $uploaded = false;

        if (! $disk->exists($key)) {
            $disk->put($key, $webp, ['ContentType' => 'image/webp']);
            $uploaded = true;
        }

        MediaAsset::query()->firstOrCreate(['path' => $key], [
            'filename' => $filename,
            'mime_type' => 'image/webp',
            'size_bytes' => strlen($webp),
            'width' => self::FRAME_WIDTH,
            'height' => self::FRAME_HEIGHT,
            'alt_text' => $altText,
            'uploaded_by_admin_id' => $uploadedBy,
        ]);

        return $uploaded;
    }

    /** "Domfo Collection in Tan", the media library's alt text for a photo. */
    public static function altText(Product|array $product, ?string $colour): string
    {
        $name = $product instanceof Product ? $product->name : $product['name'];

        return trim($name.($colour ? ' in '.$colour : ''));
    }

    /** Phone photos are stored sideways with an EXIF note; GD ignores it. */
    private static function upright(GdImage $image, string $bytes): GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($bytes, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));

        return match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}

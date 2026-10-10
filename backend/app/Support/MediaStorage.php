<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The one place that knows where uploaded images live.
 *
 * Two disks (config/filesystems.php): `media_disk` for the admin media
 * library, served publicly, and `private_media_disk` for customers' DIY
 * reference photos. Both are the local `public` disk in development and
 * tests; production puts them in two Cloudflare R2 buckets, `r2` and
 * `r2-private`, because an R2 bucket is public or private as a whole.
 *
 * Product photos are not here: they are committed to the storefront under
 * frontend/public/products (see MediaUrl).
 */
final class MediaStorage
{
    public static function diskName(): string
    {
        return (string) config('filesystems.media_disk', 'public');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /** A permanent URL for a file on the public media disk. */
    public static function url(string $path): string
    {
        return self::disk()->url($path);
    }

    public static function privateDiskName(): string
    {
        return (string) config('filesystems.private_media_disk', 'public');
    }

    public static function privateDisk(): Filesystem
    {
        return Storage::disk(self::privateDiskName());
    }

    /**
     * A URL for a file on the private disk. On R2, a signed link that
     * expires, so a copied URL stops working rather than exposing a
     * customer's photo for good. The local `public` disk cannot sign, so
     * there it is the plain URL — that disk is only used in development.
     */
    public static function privateUrl(string $path, int $minutes = 15): string
    {
        $disk = self::privateDisk();

        if (config('filesystems.disks.'.self::privateDiskName().'.driver') === 's3') {
            try {
                return $disk->temporaryUrl($path, now()->addMinutes($minutes));
            } catch (Throwable) {
                // Fall through to the plain URL rather than failing the page.
            }
        }

        return $disk->url($path);
    }
}

<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The one place that knows where images live.
 *
 * `MEDIA_DISK` picks the disk: `public` (the API server's own
 * storage/app/public, served at /storage — the default, and what local
 * development and the test suite use) or `s3` (production). Render's disk is
 * wiped on every deploy, so production must use `s3` — see FOR_THE_TEAM.md D3.
 *
 * In the bucket, `products/` and `media/` are publicly readable through the
 * bucket policy; everything else — including the DIY reference photos under
 * `booking-references/` — is private, and only `privateUrl()` can show it.
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

    /** A permanent URL — for public images (`products/`, `media/`). */
    public static function url(string $path): string
    {
        return self::disk()->url($path);
    }

    /**
     * A URL for a private file. On S3, a signed link that expires; on the
     * local `public` disk, which cannot sign, the plain URL (that disk is
     * public by construction, and only ever used in development).
     */
    public static function privateUrl(string $path, int $minutes = 15): string
    {
        $disk = self::disk();

        if (config('filesystems.disks.'.self::diskName().'.driver') === 's3') {
            try {
                return $disk->temporaryUrl($path, now()->addMinutes($minutes));
            } catch (Throwable) {
                // Fall through to the plain URL rather than failing the page.
            }
        }

        return $disk->url($path);
    }
}

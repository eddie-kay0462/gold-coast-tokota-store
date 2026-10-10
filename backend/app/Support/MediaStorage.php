<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * The one place that knows where uploaded images live.
 *
 * Today that is the `public` disk: this server's storage/app/public, served
 * at /storage. ⚠️ Render's disk is wiped on every deploy, so admin media
 * uploads and DIY reference photos do not survive a release — see
 * FOR_THE_TEAM.md D3. Moving them somewhere durable means changing this
 * class, not its callers.
 *
 * Product photos are not here: they are committed to the storefront under
 * frontend/public/products (see MediaUrl).
 */
final class MediaStorage
{
    public static function diskName(): string
    {
        return 'public';
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function url(string $path): string
    {
        return self::disk()->url($path);
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Turns a stored image reference into a URL any of the three apps can load.
 *
 * Product photos are stored three ways today, and only one of them is
 * self-describing:
 *
 *  - `https://…` — already absolute (a media-library upload's URL). Kept.
 *  - `/products/domfo/1-tan.webp` — root-relative, meaning "on the
 *    storefront", where the photos are committed. Fine on the storefront;
 *    in the admin (another origin) the same path loads the admin's own HTML.
 *    Resolved against `app.storefront_url`.
 *  - `media/2026/10/x.webp` — a bare storage path. Resolved through the
 *    `public` disk, which is also where a bucket URL will come from once
 *    images move to object storage (FOR_THE_TEAM.md D3) — at which point
 *    only this class needs to know.
 */
final class MediaUrl
{
    public static function absolute(?string $reference): ?string
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $reference)) {
            return $reference;
        }

        if (str_starts_with($reference, '/')) {
            return rtrim((string) config('app.storefront_url'), '/').$reference;
        }

        return Storage::disk('public')->url($reference);
    }
}

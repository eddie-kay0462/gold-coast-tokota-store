<?php

namespace App\Support;

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
 *  - `products/domfo/1-tan.webp`, `media/2026/10/x.webp` — a key on the
 *    image disk (MediaStorage: local storage in development, the S3 bucket
 *    in production). This is how product photos are stored once
 *    `media:import-product-photos` has run.
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

        return MediaStorage::url($reference);
    }
}

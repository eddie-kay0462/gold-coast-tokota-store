<?php

namespace Database\Seeders;

use App\Support\Catalogue\ProductUpserter;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the brand's real catalogue: 25 slipper styles, 2 shoes and Ohene, the
 * one Ahenema the 30 Sep set included (the other 12 Ahenema styles come in
 * through `catalogue:import-media`; see below).
 *
 * `database/data/products.json` is transcribed from what the client supplied
 * on 30 Sep 2026 — one folder per style, holding its photographs and a sheet
 * giving price, size range, materials and gender. Names, prices, sizes,
 * materials and departments are the client's; the photographs are theirs too,
 * resized to WebP under `frontend/public/products/`.
 *
 * Prices are in cedis (stored ×100, as pesewas). Colour names are basic
 * colours read off each photograph, which the client agreed to.
 *
 * What the sheets do NOT give is deliberately left empty rather than invented:
 * there is no description, no was-price, no cost breakdown and no collection
 * (the client's word "collection" means a style — one Product — not the
 * merchandising grouping `collections` models). See FOR_THE_TEAM.md.
 *
 * These replace the six demo products the storefront was mocked up with.
 *
 * A product whose photos have since moved to R2 (`catalogue:import-media`)
 * keeps them: the fixture's `/products/…` paths are only the starting point.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/products.json');

        if (! is_file($path)) {
            throw new RuntimeException("Missing product seed fixture: {$path}");
        }

        $products = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $upserter = app(ProductUpserter::class);

        // Writing, inventory rows, and keeping photos that already moved to
        // the media disk all live in ProductUpserter, which
        // `catalogue:import-media` shares.
        foreach ($products as $entry) {
            // Styles with no committed photos (the Ahenema range) exist only
            // once `catalogue:import-media` creates them with their photos;
            // seeding them would put photo-less products on the shop.
            if (empty($entry['images'])) {
                continue;
            }

            $upserter->upsert($entry);
        }
    }
}

<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The shop's filter options, from the catalogue: the product types and the
 * colours that active products actually have, each with how many products
 * it would show.
 *
 * Before this the sidebar listed a fixed set (Accessories & Gift Cards,
 * Merchandise, Orange, Pink, Olive…) that matched nothing, missed colours
 * the catalogue does have (Purple), and drew swatches in its own shades
 * rather than the ones the products are entered with.
 *
 * - **Colours** are the names in each product's `colors` (with the swatch hex
 *   entered for it) plus its primary `color`. A product counts once per
 *   colour. The hex is the one most products use for that name.
 * - The `value` is what `?color=` takes: ProductFilter matches it against
 *   the same two fields, case-insensitively.
 *
 * Cached; dropped whenever a product changes.
 */
final class CatalogueFacets
{
    public const CACHE_KEY = 'storefront.catalogue-facets';

    /** A swatch for a colour name with no hex on any product. */
    private const NO_HEX = '#D9D9D9';

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{types: list<array>, colors: list<array>} */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            $products = Product::query()->active()->get(['id', 'product_type', 'color', 'colors']);

            return ['types' => $this->types($products), 'colors' => $this->colors($products)];
        });
    }

    private function types(Collection $products): array
    {
        return $products->pluck('product_type')->filter()->countBy()
            ->map(fn (int $count, string $type) => ['value' => $type, 'label' => ProductTypes::label($type), 'count' => $count])
            ->sortBy([['count', 'desc'], fn ($a, $b) => ProductTypes::rank($a['value']) <=> ProductTypes::rank($b['value'])])
            ->values()
            ->all();
    }

    private function colors(Collection $products): array
    {
        $counts = [];
        $hexes = [];
        $labels = [];

        foreach ($products as $product) {
            $seen = [];
            $entries = collect($product->colors ?? [])
                ->map(fn ($c) => ['name' => is_array($c) ? ($c['name'] ?? null) : $c, 'hex' => is_array($c) ? ($c['hex'] ?? null) : null])
                ->push(['name' => $product->color, 'hex' => null]);

            foreach ($entries as $entry) {
                $name = trim((string) $entry['name']);
                if ($name === '') {
                    continue;
                }
                $key = mb_strtolower($name);
                $labels[$key] ??= $name;
                if ($entry['hex'] && preg_match('/^#[0-9a-f]{6}$/i', $entry['hex'])) {
                    $hex = strtoupper($entry['hex']);
                    $hexes[$key][$hex] = ($hexes[$key][$hex] ?? 0) + 1;
                }
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }
        }

        return collect($counts)
            ->map(function (int $count, string $key) use ($hexes, $labels) {
                $byUse = $hexes[$key] ?? [];
                arsort($byUse);

                return ['value' => $key, 'label' => $labels[$key], 'hex' => array_key_first($byUse) ?? self::NO_HEX, 'count' => $count];
            })
            ->sortBy([['count', 'desc'], ['label', 'asc']])
            ->values()
            ->all();
    }
}

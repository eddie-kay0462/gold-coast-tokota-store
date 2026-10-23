<?php

namespace App\Support;

use App\Models\MegaMenuPromo;
use App\Models\Product;
use App\Services\Catalogue\ProductFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The storefront's mega menus (header row 3), built from the catalogue.
 *
 * Each menu is a scope on the catalogue, written as the same query string
 * `/shop` takes, and every link in it is that scope narrowed one step further.
 * So a link can only appear if it leads to products:
 *
 * - **Styles:** the product types with active products in the scope. In a
 *   scope that is already one type (Ahenema), the column is just "All".
 * - **Shop for:** the departments present (Men's, Women's, Kids).
 * - **Featured:** New Arrivals, Best Sellers and On Sale (when the scope has
 *   anything on sale), never repeating the menu itself, plus Customised
 *   Sandals, which is a booking. There is no "Top Rated": there are no ratings
 *   (ProductFilter::sort).
 *
 * The two image tiles are chosen in the admin (MegaMenuPromo). An empty slot
 * shows the scope's newest product photo, linking to that product.
 *
 * The result is cached, and dropped whenever a tile or a product changes.
 */
final class MegaMenus
{
    public const CACHE_KEY = 'storefront.mega-menus';

    public const SLOTS = 2;

    /**
     * Header row 3, in order. `to` is where the header item itself goes; the
     * scope is its query string. "Sandals" is the whole range: everything the
     * brand makes is a sandal, and no product is typed `sandals`.
     */
    public const MENUS = [
        'best-sellers' => ['label' => 'Best Sellers', 'all' => 'All Best Sellers', 'to' => '/shop?sort=best-selling'],
        'sandals' => ['label' => 'Sandals', 'all' => 'All Sandals', 'to' => '/shop'],
        'ahenema' => ['label' => 'Ahenema', 'all' => 'All Ahenema', 'to' => '/shop?type=ahenema'],
        'sale' => ['label' => 'Sale', 'all' => 'Everything on Sale', 'to' => '/shop?sale=true'],
    ];

    private const DEPARTMENT_LABELS = ['mens' => 'Men’s', 'womens' => 'Women’s', 'kids' => 'Kids'];

    public function __construct(private readonly ProductFilter $filter) {}

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return list<array{key: string, label: string, to: string, columns: list<array>, promos: list<array>}> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            $chosen = MegaMenuPromo::query()->with('mediaAsset')->get()->groupBy('menu');

            return collect(self::MENUS)
                ->map(fn (array $menu, string $key) => $this->build($key, $menu, $chosen->get($key, collect())))
                ->values()
                ->all();
        });
    }

    /** Every link the menu offers, for the admin's link suggestions. */
    public function links(string $key): array
    {
        $menu = collect($this->all())->firstWhere('key', $key);

        return collect($menu['columns'] ?? [])->flatMap(fn ($column) => $column['links'])->unique('to')->values()->all();
    }

    private function build(string $key, array $menu, Collection $chosen): array
    {
        $scope = self::params($menu['to']);
        $products = $this->scoped($scope)->get(['id', 'slug', 'name', 'product_type', 'departments', 'images', 'compare_at_ghs', 'base_price_ghs', 'created_at']);

        return [
            'key' => $key,
            'label' => $menu['label'],
            'to' => $menu['to'],
            'columns' => array_values(array_filter([
                $this->styles($menu, $products),
                $this->departments($menu, $products),
                $this->featured($key, $menu, $products),
            ])),
            'promos' => $this->promos($chosen, $this->fallbacks($scope)),
        ];
    }

    /** The scope's active products, newest first. */
    private function scoped(array $scope): Builder
    {
        $query = Product::query()->active();
        // Sort is not a scope: "Best Sellers" contains every product.
        $this->filter->apply($query, Request::create('/', 'GET', collect($scope)->except('sort')->all()));

        return $query->reorder()->latest()->orderByDesc('id');
    }

    private function styles(array $menu, Collection $products): array
    {
        $types = $products->pluck('product_type')->filter()->countBy();
        $ordered = $types->keys()->sortBy(fn ($type) => [ProductTypes::rank($type), $type]);

        $links = [['label' => $menu['all'], 'to' => $menu['to']]];
        if ($types->count() > 1) {
            foreach ($ordered as $type) {
                $links[] = ['label' => ProductTypes::label($type), 'to' => self::with($menu['to'], ['type' => $type])];
            }
        }

        return ['heading' => 'Styles', 'links' => $links];
    }

    private function departments(array $menu, Collection $products): ?array
    {
        $present = $products->flatMap(fn (Product $p) => $p->departments ?? [])->unique();
        $links = collect(self::DEPARTMENT_LABELS)
            ->filter(fn ($label, $slug) => $present->contains($slug))
            ->map(fn ($label, $slug) => ['label' => $label, 'to' => self::with($menu['to'], ['category' => $slug])])
            ->values()
            ->all();

        return count($links) > 1 ? ['heading' => 'Shop for', 'links' => $links] : null;
    }

    private function featured(string $key, array $menu, Collection $products): array
    {
        $onSale = $products->contains(fn (Product $p) => $p->compare_at_ghs !== null && $p->compare_at_ghs > $p->base_price_ghs);

        $links = [['label' => 'New Arrivals', 'to' => self::with($menu['to'], ['sort' => 'newest'])]];
        if ($key !== 'best-sellers') {
            $links[] = ['label' => 'Best Sellers', 'to' => self::with($menu['to'], ['sort' => 'best-selling'])];
        }
        if ($key !== 'sale' && $onSale) {
            $links[] = ['label' => 'On Sale', 'to' => self::with($menu['to'], ['sale' => 'true'])];
        }
        // Custom work is a booking, not a catalogue filter.
        $links[] = ['label' => 'Customised Sandals', 'to' => '/booking'];

        return ['heading' => 'Featured', 'links' => $links];
    }

    /**
     * Products for empty tiles, in the menu's own order: best-selling for Best
     * Sellers, newest otherwise. Where the menu spans several styles, the
     * first picks are of different styles, so two empty tiles don't show two
     * of the same thing.
     */
    private function fallbacks(array $scope): Collection
    {
        $query = Product::query()->active();
        $this->filter->apply($query, Request::create('/', 'GET', $scope + ['sort' => 'newest']));
        $ranked = $query->limit(24)->get()->filter(fn (Product $p) => ! empty($p->images))->values();

        $seen = [];
        [$first, $rest] = $ranked->partition(function (Product $p) use (&$seen) {
            if (in_array($p->product_type, $seen, true)) {
                return false;
            }
            $seen[] = $p->product_type;

            return true;
        });

        return $first->concat($rest)->values();
    }

    private function promos(Collection $chosen, Collection $fallbacks): array
    {
        $bySlot = $chosen->keyBy('position');
        $tiles = [];

        for ($slot = 1; $slot <= self::SLOTS; $slot++) {
            $promo = $bySlot->get($slot);
            if ($promo?->mediaAsset) {
                $tiles[] = [
                    'label' => $promo->caption,
                    'image' => $promo->mediaAsset->url,
                    'alt' => $promo->mediaAsset->alt_text ?: $promo->caption,
                    'to' => $promo->link,
                    'chosen' => true,
                ];

                continue;
            }

            $product = $fallbacks->shift();
            if ($product) {
                $tiles[] = [
                    'label' => $product->name,
                    'image' => MediaUrl::absolute($product->images[0]),
                    'alt' => $product->name,
                    'to' => '/shop/'.$product->slug,
                    'chosen' => false,
                ];
            }
        }

        return $tiles;
    }

    /** @return array<string, string> */
    public static function params(string $to): array
    {
        parse_str((string) parse_url($to, PHP_URL_QUERY), $params);

        return $params;
    }

    /** The route with these query params set over its own. */
    public static function with(string $to, array $params): string
    {
        $path = (string) parse_url($to, PHP_URL_PATH);
        $query = http_build_query(array_merge(self::params($to), $params));

        return $query === '' ? $path : "{$path}?{$query}";
    }
}

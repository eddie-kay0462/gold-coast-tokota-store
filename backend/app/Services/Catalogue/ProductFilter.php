<?php

namespace App\Services\Catalogue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Server-side catalogue filtering for `GET /products` (README Feature 2).
 *
 * **This exists because `/shop` was filtering client-side over one page of 12.**
 * The storefront's own comment said "the API is expected to filter
 * server-side"; until it did, every facet silently operated on the first
 * twelve products and a shopper filtering a larger catalogue got wrong
 * results with no error to notice.
 *
 * The query contract is the one `frontend/pages/shop/index.vue` already sends,
 * unchanged — `?type=&color=&size=&width=&category=&q=&sale=&sort=` — because
 * the page was written against it and a different one would mean editing both
 * sides to no benefit. Values are comma-separated. **Within one facet the
 * match is OR, across facets it is AND**, which is what the sidebar's
 * checkboxes mean: two colours widen the result, a colour plus a size narrows
 * it.
 *
 * ### Portability
 *
 * Production is Postgres and the test suite is SQLite (known issue 29), so
 * every clause here has to compile on both. Two constructs are used, and both
 * were checked against each driver rather than assumed:
 *   - `whereJsonContains` — Laravel compiles it to `@>` on Postgres and a
 *     `json_each` subquery on SQLite. Safe for arrays of scalars.
 *   - `LOWER(CAST(col AS text)) LIKE ?` — a no-op cast on SQLite, a jsonb-to-text
 *     cast on Postgres. Used where a *substring* of a JSON blob is wanted,
 *     which `whereJsonContains` cannot express.
 *
 * `ILIKE` is deliberately absent: it exists on only one of the two, and a
 * search that works in only one environment is worse than a longer clause.
 */
class ProductFilter
{
    /** Sorts the storefront can ask for and this class can honour. */
    public const SORTS = ['newest', 'best-selling'];

    public function apply(Builder $query, Request $request): Builder
    {
        $this->search($query, $request);
        $this->facets($query, $request);
        $this->taxonomy($query, $request);
        $this->sort($query, $request);

        return $query;
    }

    /**
     * Free text, over the same fields the storefront's own predicate searches:
     * name, colour, product type and tags. Kept deliberately in step with it —
     * the page falls back to filtering the design catalogue locally when the
     * API is unreachable, and a search that returns different things depending
     * on whether the API answered would be a confusing bug to chase.
     */
    private function search(Builder $query, Request $request): void
    {
        if (! $request->filled('q')) {
            return;
        }

        $term = '%'.mb_strtolower(trim((string) $request->string('q'))).'%';

        $query->where(function (Builder $scoped) use ($term) {
            $scoped
                ->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(color) LIKE ?', [$term])
                ->orWhereRaw('LOWER(product_type) LIKE ?', [$term])
                // Tags are a JSON array of strings and the match is a
                // substring, so json containment is the wrong tool.
                ->orWhereRaw('LOWER(CAST(tags AS text)) LIKE ?', [$term]);
        });
    }

    private function facets(Builder $query, Request $request): void
    {
        // Product type is a plain column, so this one is just an IN.
        if ($types = $this->list($request, 'type')) {
            $query->whereIn('product_type', $types);
        }

        // Colour is a substring match against the colourway names, mirroring
        // the storefront exactly: a facet value of `tan` is expected to match
        // a colourway called "Tan Leather". `colors` is an array of objects,
        // so containment cannot express it. The `color` column carries the
        // primary colourway and is searched alongside.
        if ($colors = $this->list($request, 'color')) {
            $query->where(function (Builder $scoped) use ($colors) {
                foreach ($colors as $color) {
                    $needle = '%'.mb_strtolower($color).'%';
                    $scoped
                        ->orWhereRaw('LOWER(color) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(colors AS text)) LIKE ?', [$needle]);
                }
            });
        }

        // Width is an array of scalars — containment is exactly right.
        if ($widths = $this->list($request, 'width')) {
            $query->where(function (Builder $scoped) use ($widths) {
                foreach ($widths as $width) {
                    $scoped->orWhereJsonContains('widths', $width);
                }
            });
        }

        // Size lives on the variants, not the product: `sizes` in the API
        // payload is derived from each inventory item's variant_attributes.
        // Matching on the variant's existence rather than its stock is
        // deliberate and matches the storefront — a size that is made but
        // currently sold out still renders (struck through), so filtering it
        // out here would make the two disagree.
        if ($sizes = $this->list($request, 'size')) {
            $path = $this->jsonPath('variant_attributes', 'size');

            $query->whereHas(
                'inventoryItems',
                fn (Builder $items) => $items->whereIn(DB::raw($path), $sizes),
            );
        }
    }

    private function taxonomy(Builder $query, Request $request): void
    {
        // `?category=` is the *department* (mens/womens/kids/merchandise), not
        // the catalogue category — the storefront's own comment is emphatic
        // about the distinction, because the sidebar's "Category" group
        // filters product type instead and sharing one key would filter every
        // product out. `?category_id=` below is the real category.
        if ($departments = $this->list($request, 'category')) {
            $query->where(function (Builder $scoped) use ($departments) {
                foreach ($departments as $department) {
                    $scoped->orWhereJsonContains('departments', $department);
                }
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('collection_id')) {
            $query->where('collection_id', $request->integer('collection_id'));
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        // On sale means there is a was-price and it is genuinely higher. A
        // compare_at equal to the price is not a discount, and showing it as
        // one would be the sort of claim §22 does not permit inventing.
        if ($request->boolean('sale')) {
            $query->whereNotNull('compare_at_ghs')->whereColumn('compare_at_ghs', '>', 'base_price_ghs');
        }
    }

    /**
     * `newest` and `best-selling` are honoured. **`top-rated` is not, and
     * cannot be**: there is no ratings data anywhere in this system. Product
     * reviews are unplanned scope that the storefront nonetheless has a fully
     * built UI for (open decision 24), and inventing a `rating` column to
     * satisfy a sort would be inventing the whole reviews feature by the back
     * door. An unrecognised sort falls through to the default rather than
     * erroring — a shopper who clicks "Top Rated" should get products, not a
     * 422 — but it does not silently pretend to have sorted.
     */
    private function sort(Builder $query, Request $request): void
    {
        match ($request->string('sort')->toString()) {
            'newest' => $query->latest(),
            'best-selling' => $query
                ->orderByDesc($this->unitsSoldSubquery())
                ->orderByDesc('created_at'),
            // Featured first, then newest — the storefront labels the unsorted
            // view "Featured", so this is what that word has to mean.
            default => $query->orderByDesc('is_featured')->orderByDesc('created_at'),
        };
    }

    /**
     * Units sold per product, as a correlated subquery.
     *
     * Two details that a simpler version gets wrong:
     *
     *   1. **It sums `quantity`, it does not count rows.** One order line for
     *      nine pairs is nine sales, not one — counting lines would rank a
     *      product bought once in bulk below one bought singly twice.
     *   2. **`COALESCE(..., 0)`, and the expression is ordered on directly
     *      rather than through a select alias.** A product that has never sold
     *      aggregates to NULL, and Postgres sorts NULLs *first* under
     *      `DESC` — so without the coalesce the best-sellers list would open
     *      with everything that has never sold. Postgres also refuses an
     *      output alias inside an ORDER BY expression, which is why this is a
     *      subquery rather than `withSum` plus a wrapped alias.
     *
     * Only orders where money is settled and kept count. A refunded or
     * cancelled order is not a sale, or a product everybody sent back would
     * lead the best-sellers.
     */
    private function unitsSoldSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('order_items')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0)')
            ->whereColumn('order_items.product_id', 'products.id')
            ->whereIn('order_items.order_id', DB::table('orders')
                ->select('id')
                ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered']));
    }

    /**
     * A comma-separated facet, as the storefront serialises it into the URL.
     * Empty entries are dropped so a trailing comma cannot produce a clause
     * that matches nothing.
     *
     * @return list<string>
     */
    private function list(Request $request, string $key): array
    {
        if (! $request->filled($key)) {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', (string) $request->string($key))),
            fn (string $value) => $value !== '',
        ));
    }

    /**
     * A JSON field reference that reads the same on Postgres and SQLite —
     * the same helper Admin\OrderController needs for the same reason.
     */
    private function jsonPath(string $column, string $key): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "{$column}->>'{$key}'"
            : "json_extract({$column}, '$.{$key}')";
    }
}

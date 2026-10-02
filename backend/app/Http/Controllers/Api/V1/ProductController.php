<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Catalogue\ProductFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * The public catalogue.
     *
     * Filtering, search and sorting all live in ProductFilter, which is
     * written against the query string `/shop` already sends. Before it, this
     * method understood only `category_id` and `featured` and the storefront
     * filtered what it got client-side — over a single page of 12, so every
     * facet was quietly wrong as soon as the catalogue outgrew one page.
     */
    public function index(Request $request, ProductFilter $filter): AnonymousResourceCollection
    {
        $query = Product::query()
            ->active()
            ->with(['category', 'collection', 'inventoryItems']);

        $products = $filter->apply($query, $request)
            // 12 matches the storefront grid. Capped rather than free so a
            // crawler cannot ask for the whole catalogue in one request.
            ->paginate(min($request->integer('per_page', 12), 48))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::query()
            ->active()
            ->with(['category', 'collection', 'inventoryItems'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new ProductResource($product);
    }

    /**
     * `GET /products/recommendations?for=domfo,obaapa&limit=4`
     *
     * What the product page shows under "Recommended Products" (`for` = the
     * product being viewed) and what the cart drawer suggests (`for` = what is
     * already in the cart). The `for` products are never recommended back.
     *
     * Ranked, best first: shares a department with any `for` product (men's
     * buyers see men's styles), same category, has stock, featured. Name
     * breaks ties so a server render and a client refetch agree on the order.
     * Out-of-stock styles only fill slots nothing better can — dropping them
     * outright would leave the row empty in a production where stock has
     * not been entered yet.
     *
     * Ranked in PHP over the active catalogue: it is 28 styles, and a
     * weighted score is far clearer here than as SQL.
     */
    public function recommendations(Request $request): AnonymousResourceCollection
    {
        $limit = max(1, min($request->integer('limit', 4), 12));
        $for = collect(explode(',', (string) $request->query('for', '')))
            ->map(fn (string $slug) => trim($slug))
            ->filter()
            ->unique()
            ->take(20);

        $active = Product::query()
            ->active()
            ->with(['category', 'collection', 'inventoryItems'])
            ->get();

        $seeds = $active->whereIn('slug', $for);
        $departments = $seeds->flatMap(fn (Product $product) => $product->departments ?? [])->unique();
        $categories = $seeds->pluck('category_id')->filter()->unique();

        $ranked = $active
            ->reject(fn (Product $product) => $for->contains($product->slug))
            ->sortBy([
                fn (Product $a, Product $b) => $this->rank($b, $departments, $categories) <=> $this->rank($a, $departments, $categories),
                fn (Product $a, Product $b) => strcmp($a->name, $b->name),
            ])
            ->take($limit)
            ->values();

        return ProductResource::collection($ranked);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $departments
     * @param  \Illuminate\Support\Collection<int, int>  $categories
     */
    private function rank(Product $product, $departments, $categories): int
    {
        return (array_intersect($product->departments ?? [], $departments->all()) ? 8 : 0)
            + ($categories->contains($product->category_id) ? 4 : 0)
            + ($product->in_stock ? 2 : 0)
            + ($product->is_featured ? 1 : 0);
    }
}

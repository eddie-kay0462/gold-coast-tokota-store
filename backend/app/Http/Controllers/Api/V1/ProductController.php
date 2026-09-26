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
}

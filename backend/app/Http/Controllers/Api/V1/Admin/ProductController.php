<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The admin catalogue (Feature 9).
 *
 * `index`/`show` are deliberately **not** scoped `active()`, unlike the public
 * ProductController: a draft or a withdrawn product is precisely what an admin
 * needs to see, and scoping them out would make the catalogue screen unable to
 * show the thing you just unpublished.
 *
 * Every method returns AdminProductResource rather than the storefront's
 * ProductResource. The admin app types prices as `Money` (`{ amount,
 * currency }`), so a write that echoed back a bare integer would land as a
 * malformed object on the client — the shapes have to agree across all five
 * endpoints, not just the reads.
 */
class ProductController extends Controller
{
    /** Relations every response loads — stock rollups and the category name
     *  are part of the resource, so omitting them would silently drop fields. */
    private const WITH = ['category', 'collection', 'inventoryItems'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(self::WITH)
            ->when(
                $request->filled('category_id'),
                fn ($query) => $query->where('category_id', $request->integer('category_id')),
            )
            // Tri-state on purpose: absent means "everything", which is what
            // the screen's All tab wants. `->boolean()` alone cannot express
            // that, since it reads a missing key as false and would hide every
            // active product.
            ->when(
                $request->has('active'),
                fn ($query) => $query->where('is_active', $request->boolean('active')),
            )
            ->when(
                $request->filled('q'),
                fn ($query) => $query->where(
                    fn ($inner) => $inner
                        ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($request->string('q')).'%'])
                        ->orWhereRaw('LOWER(sku) LIKE ?', ['%'.strtolower($request->string('q')).'%']),
                ),
            )
            ->orderBy('name')
            // A far larger page than the storefront's 12: the admin table does
            // its own searching, sorting and paging client-side over the set it
            // is given (see useResource in admin/), so a small page would make
            // those controls quietly operate on a fraction of the catalogue.
            ->paginate(min($request->integer('per_page', 100), 200))
            ->withQueryString();

        return AdminProductResource::collection($products);
    }

    public function show(Product $product): AdminProductResource
    {
        return new AdminProductResource($product->load(self::WITH));
    }

    public function store(StoreProductRequest $request): AdminProductResource
    {
        $product = Product::create($request->validated());

        return new AdminProductResource($product->load(self::WITH));
    }

    public function update(UpdateProductRequest $request, Product $product): AdminProductResource
    {
        $product->update($request->validated());

        return new AdminProductResource($product->load(self::WITH));
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}

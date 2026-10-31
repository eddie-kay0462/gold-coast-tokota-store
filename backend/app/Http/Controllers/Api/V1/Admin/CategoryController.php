<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CollectionResource;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Catalogue taxonomy for the admin product forms.
 *
 * Categories are the top-level split (Sandals, Ahenema); collections are the
 * merchandising grouping within one (Obrempong, Sikapa, Slides). Both are
 * returned together because the product form needs both dropdowns and asking
 * for them separately is two round trips for one screen.
 */
class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'categories' => CategoryResource::collection($this->withUsualType(
                    Category::query()->withCount('products')->orderBy('name')->get()
                )),
                'collections' => CollectionResource::collection(Collection::query()->orderBy('name')->get()),
            ],
        ]);
    }

    /**
     * Each category's most common product type, taken from its products rather
     * than a fixed map, so a new category picks one up once it's stocked. Ties
     * go to the alphabetically first type.
     *
     * @param  EloquentCollection<int, Category>  $categories
     * @return EloquentCollection<int, Category>
     */
    private function withUsualType(EloquentCollection $categories): EloquentCollection
    {
        $usual = Product::query()
            ->whereNotNull('category_id')->whereNotNull('product_type')->where('product_type', '!=', '')
            ->selectRaw('category_id, product_type, COUNT(*) as uses')
            ->groupBy('category_id', 'product_type')
            ->orderByDesc('uses')->orderBy('product_type')
            ->get()
            ->groupBy('category_id')
            ->map(fn ($rows) => $rows->first()->product_type);

        return $categories->each(fn (Category $c) => $c->setAttribute('usual_product_type', $usual->get($c->id)));
    }

    public function store(Request $request): CategoryResource
    {
        $data = $this->validated($request);

        return new CategoryResource(Category::create($data)->loadCount('products'));
    }

    /** A new slug changes the category's shop links, so the admin warns. */
    public function update(Request $request, Category $category): CategoryResource
    {
        $category->update($this->validated($request, $category));

        return new CategoryResource($category->loadCount('products'));
    }

    /**
     * Only an empty category goes. Deleting one with products would leave
     * them in no category without anyone deciding where they belong.
     */
    public function destroy(Category $category): JsonResponse|Response
    {
        $count = $category->products()->count();

        if ($count > 0) {
            return response()->json([
                'message' => "{$category->name} still has {$count} product".($count === 1 ? '' : 's').'. Move '.($count === 1 ? 'it' : 'them').' to another category first.',
            ], 422);
        }

        $category->delete();

        return response()->noContent();
    }

    /** @return array{name: string, slug: string} */
    private function validated(Request $request, ?Category $category = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category?->id)],
        ], [
            'slug.unique' => 'Another category already uses that web address.',
        ]);
    }
}

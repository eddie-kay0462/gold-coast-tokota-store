<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CollectionResource;
use App\Models\Category;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;

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
                'categories' => CategoryResource::collection(Category::query()->orderBy('name')->get()),
                'collections' => CollectionResource::collection(Collection::query()->orderBy('name')->get()),
            ],
        ]);
    }
}

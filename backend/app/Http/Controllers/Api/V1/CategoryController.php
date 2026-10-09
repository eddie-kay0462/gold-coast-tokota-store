<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Only categories a shopper can actually browse into. A category with
        // no active product is a link to an empty page — and seeding never
        // deletes, so a database seeded before the real catalogue landed still
        // carries the demo-era Sandals/Ahenema rows. Admin taxonomy
        // (`/admin/categories`) stays unscoped.
        $categories = Category::query()
            ->whereHas('products', fn ($query) => $query->active())
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }
}

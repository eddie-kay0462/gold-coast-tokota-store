<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function store(StoreProductRequest $request): ProductResource
    {
        $product = Product::create($request->validated());

        return new ProductResource($product->load(['category', 'collection', 'inventoryItems']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->load(['category', 'collection', 'inventoryItems']));
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}

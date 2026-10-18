<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductPhotoRequest;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use App\Support\Catalogue\CataloguePhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Adding and removing a product's photos from the admin.
 *
 * Uploads are named, shaped and stored exactly as `catalogue:import-media`
 * does it (CataloguePhoto): a 1500×1667 WebP at
 * `catalogue/{slug}/{n}-{colour}-{hash}.webp` on the media disk, with a media
 * library row. That is why `images` on the product endpoints only reorders or
 * removes: this is the one way a photo gets in.
 */
class ProductPhotoController extends Controller
{
    public function store(StoreProductPhotoRequest $request, Product $product): AdminProductResource
    {
        $file = $request->file('file');
        $colour = $request->validated('colour');

        try {
            $webp = CataloguePhoto::webp(CataloguePhoto::frame($file->getContent()));
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['file' => 'That photo couldn’t be read. Try a JPG or PNG.']);
        }

        $product = DB::transaction(function () use ($product, $colour, $webp, $file, $request) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $images = $product->images ?? [];
            $key = CataloguePhoto::key($product->slug, count($images) + 1, $colour, sha1($webp));

            CataloguePhoto::store(
                $key,
                $webp,
                $file->getClientOriginalName(),
                CataloguePhoto::altText($product, $colour),
                $request->user('admin')->id,
            );

            if (! in_array($key, $images, true)) {
                $colourImages = $product->colour_images ?? [];

                if ($colour !== null) {
                    $colourImages[$colour][] = $key;
                }

                $product->forceFill([
                    'images' => [...$images, $key],
                    'colour_images' => $colourImages,
                ])->save();
            }

            return $product;
        });

        return new AdminProductResource($product->load(ProductController::WITH));
    }

    /**
     * Takes a photo off the product. The file stays on the disk and in the
     * media library; removing the wrong one is undone by uploading it again.
     */
    public function destroy(Request $request, Product $product): AdminProductResource
    {
        $key = $request->validate([
            'key' => ['required', 'string', Rule::in($product->images ?? [])],
        ])['key'];

        $product->forceFill([
            'images' => array_values(array_diff($product->images ?? [], [$key])),
            'colour_images' => collect($product->colour_images ?? [])
                ->map(fn (array $keys) => array_values(array_diff($keys, [$key])))
                ->all(),
        ])->save();

        return new AdminProductResource($product->load(ProductController::WITH));
    }
}

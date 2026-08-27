<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaAssetRequest;
use App\Http\Resources\Admin\MediaAssetResource;
use App\Models\MediaAsset;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * The media library — Admin and Staff.
 *
 * This is what makes `products.images` fillable at all. Before it, a product
 * photo could only be added by editing seed data.
 */
class MediaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return MediaAssetResource::collection(
            MediaAsset::query()->with('uploadedBy')->latest()->paginate(50)
        );
    }

    public function store(StoreMediaAssetRequest $request): MediaAssetResource
    {
        $file = $request->file('file');

        // Laravel generates the stored name, so a hostile original filename
        // ("../../.env", "shell.php") can never reach the filesystem. The
        // original is kept only as a display label.
        $path = $file->store('media/'.now()->format('Y/m'), 'public');

        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

        $asset = MediaAsset::create([
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            // From the decoded file, not the client's Content-Type header.
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $request->validated('alt_text'),
            'uploaded_by_admin_id' => $request->user('admin')->id,
        ]);

        return new MediaAssetResource($asset->load('uploadedBy'));
    }

    public function destroy(MediaAsset $mediaAsset): Response
    {
        // File first, row second: a missing row pointing at a real file is an
        // orphan nobody can find, which is worse than a row pointing at a
        // missing file — that one at least shows up as a broken image.
        Storage::disk('public')->delete($mediaAsset->path);
        $mediaAsset->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlogPostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // The storefront home page asks for `?limit=5` for its Stories strip;
        // /blog itself takes the default page size. Clamped so the parameter
        // can't be used to pull the whole table in one request.
        $perPage = $request->filled('limit')
            ? max(1, min($request->integer('limit'), 24))
            : 9;

        $posts = BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return BlogPostResource::collection($posts);
    }

    public function show(string $slug): BlogPostResource
    {
        $post = BlogPost::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return new BlogPostResource($post);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogPostRequest;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Blog CMS (README Feature 9) — Admin and Staff.
 *
 * Unlike the public BlogPostController this lists drafts and scheduled posts
 * too: an editor has to be able to find the thing they have not published yet.
 */
class BlogPostController extends Controller
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = BlogPost::query()
            ->when($request->filled('status'), function ($query) use ($request) {
                match ($request->string('status')->toString()) {
                    'published' => $query->published(),
                    'draft' => $query->where('is_published', false),
                    'scheduled' => $query->where('is_published', true)->where('published_at', '>', now()),
                    default => null,
                };
            })
            ->latest('published_at')
            ->paginate(25)
            ->withQueryString();

        return BlogPostResource::collection($posts);
    }

    public function show(BlogPost $blogPost): BlogPostResource
    {
        return new BlogPostResource($blogPost);
    }

    public function store(StoreBlogPostRequest $request): BlogPostResource
    {
        $post = BlogPost::create($this->prepare($request->validated()));

        return new BlogPostResource($post);
    }

    public function update(StoreBlogPostRequest $request, BlogPost $blogPost): BlogPostResource
    {
        $blogPost->update($this->prepare($request->validated(), $blogPost));

        return new BlogPostResource($blogPost->fresh());
    }

    public function destroy(BlogPost $blogPost): Response
    {
        $blogPost->delete();

        return response()->noContent();
    }

    /** @param  array<string, mixed>  $data */
    private function prepare(array $data, ?BlogPost $existing = null): array
    {
        // Sanitised on the way in, so what is stored is already safe to render.
        // The editor may strip scripts in the browser, but the browser is not
        // the trust boundary — anything that can POST here can post anything.
        if (array_key_exists('body', $data)) {
            $data['body'] = $this->sanitizer->clean($data['body']);
        }

        // Derived from the title when an editor doesn't supply one, but never
        // silently re-derived on update: a published post's slug is a URL
        // somebody may already have linked to.
        if (empty($data['slug']) && $existing === null) {
            $data['slug'] = Str::slug($data['title']);
        }

        return array_filter(
            $data,
            fn ($value, $key) => ! ($key === 'slug' && $value === null),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}

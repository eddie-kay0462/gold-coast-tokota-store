<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogPostRequest;
use App\Http\Resources\Admin\AdminBlogPostResource;
use App\Models\BlogPost;
use App\Services\Content\HtmlSanitizer;
use App\Support\AdminCapability;
use Illuminate\Http\JsonResponse;
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

        return AdminBlogPostResource::collection($posts);
    }

    public function show(BlogPost $blogPost): AdminBlogPostResource
    {
        return new AdminBlogPostResource($blogPost);
    }

    public function store(StoreBlogPostRequest $request): AdminBlogPostResource|JsonResponse
    {
        if ($refusal = $this->refusePublishing($request, $request->validated())) {
            return $refusal;
        }

        $post = BlogPost::create($this->prepare($request->validated()));

        return new AdminBlogPostResource($post);
    }

    public function update(StoreBlogPostRequest $request, BlogPost $blogPost): AdminBlogPostResource|JsonResponse
    {
        if ($refusal = $this->refusePublishing($request, $request->validated(), $blogPost)) {
            return $refusal;
        }

        $blogPost->update($this->prepare($request->validated(), $blogPost));

        return new AdminBlogPostResource($blogPost->fresh());
    }

    public function destroy(BlogPost $blogPost): Response
    {
        $blogPost->delete();

        return response()->noContent();
    }

    /** @param  array<string, mixed>  $data */
    /**
     * Publishing and unpublishing take `content.publish`, which Staff don't
     * have: they write drafts and an Admin puts them live. The route only
     * asks for `content.write`, since saving a draft is the common case.
     */
    private function refusePublishing(Request $request, array $data, ?BlogPost $existing = null): ?JsonResponse
    {
        if (! array_key_exists('is_published', $data)) {
            return null;
        }

        $changes = (bool) $data['is_published'] !== (bool) ($existing?->is_published ?? false);
        $admin = $request->user('admin');

        if (! $changes || $admin->hasCapability('content.publish')) {
            return null;
        }

        $message = AdminCapability::denialMessage('content.publish', $admin->role);

        // The same shape as the capability middleware's refusal.
        return response()->json(['data' => null, 'meta' => null, 'message' => $message, 'errors' => ['message' => $message]], 403);
    }

    private function prepare(array $data, ?BlogPost $existing = null): array
    {
        // A post goes live when it is published *and* its date has come (the
        // `published` scope), so publishing without a date means now. One
        // already dated keeps its date, so a republish doesn't reorder it.
        if (($data['is_published'] ?? false) && empty($data['published_at']) && $existing?->published_at === null) {
            $data['published_at'] = now();
        }

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

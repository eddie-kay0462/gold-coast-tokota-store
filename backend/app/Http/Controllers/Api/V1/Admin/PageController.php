<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CMS pages — the About page and its siblings (README Feature 9, Admin and
 * Staff).
 *
 * Edit only: no store, no destroy. Page slugs are referenced by storefront
 * routes, so creating one from admin produces a page nothing links to, and
 * deleting one breaks a live route. New pages are a code change on purpose.
 */
class PageController extends Controller
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function index(): AnonymousResourceCollection
    {
        // Drafts included — this is the editor's own list.
        return PageResource::collection(Page::query()->orderBy('slug')->get());
    }

    public function show(Page $page): PageResource
    {
        return new PageResource($page);
    }

    public function update(UpdatePageRequest $request, Page $page): PageResource
    {
        $data = $request->validated();

        if (array_key_exists('body', $data)) {
            $data['body'] = $this->sanitizer->clean($data['body']);
        }

        // Who last touched it, for the CMS audit line. Taken from the session,
        // never the body.
        $data['updated_by_admin_id'] = $request->user('admin')->id;

        $page->update($data);

        return new PageResource($page->fresh());
    }
}

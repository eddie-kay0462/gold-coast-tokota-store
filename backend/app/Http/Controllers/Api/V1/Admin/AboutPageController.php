<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAboutPageRequest;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Support\AboutPage;
use Illuminate\Http\JsonResponse;

/**
 * Content › Pages › About: the whole About page as structured content
 * (App\Support\AboutPage). Read with content.view, saved with content.write.
 */
class AboutPageController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->respond($this->page());
    }

    public function update(UpdateAboutPageRequest $request): JsonResponse
    {
        $page = $this->page();
        // Only the schema's keys are kept (merged() drops anything else).
        $content = AboutPage::merged($request->validated('content'));
        $page->update([
            'content' => $content,
            // The opening statement is also the page's CMS body, so anything
            // still reading `body` agrees with it.
            'body' => '<p>'.e($content['statement']['text']).'</p>',
            'updated_by_admin_id' => $request->user('admin')->id,
        ]);

        return $this->respond($page->fresh());
    }

    private function page(): Page
    {
        return Page::query()->firstOrCreate(['slug' => 'about'], ['title' => 'About Gold Coast Tokota', 'is_draft' => false]);
    }

    private function respond(Page $page): JsonResponse
    {
        $content = AboutPage::forStorefront($page);
        // The chosen images, so the editor can show their file names.
        $ids = collect(['hero', 'people', 'ahenema'])->map(fn ($s) => $content[$s]['imageId'])->filter();
        $assets = MediaAsset::query()->whereIn('id', $ids)->get(['id', 'filename', 'alt_text'])->keyBy('id');

        return response()->json(['data' => [
            'content' => $content,
            'images' => $assets->map(fn ($a) => ['id' => $a->id, 'filename' => $a->filename, 'alt_text' => $a->alt_text])->values(),
            'updated_at' => $page->updated_at,
            'updated_by' => $page->updatedByAdmin?->name,
        ]]);
    }
}

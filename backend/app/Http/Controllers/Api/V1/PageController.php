<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug): PageResource
    {
        return new PageResource(Page::query()->where('slug', $slug)->firstOrFail());
    }
}

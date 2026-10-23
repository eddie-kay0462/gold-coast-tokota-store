<?php

namespace App\Http\Resources;

use App\Support\AboutPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            // The About page's sections, merged over their defaults, with
            // image URLs resolved. Null for every other page.
            'content' => $this->slug === 'about' ? AboutPage::forStorefront($this->resource) : null,
            // Drives the storefront's "draft — awaiting review" banner.
            'is_draft' => (bool) $this->is_draft,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'cover_image' => $this->cover_image,
            'cover_image_alt' => $this->cover_image_alt,
            // The admin's Excerpt. `subtitle` is the name the storefront's
            // story card and article deck read, and its description falls
            // back to it; both carry the same text.
            'excerpt' => $this->excerpt,
            'subtitle' => $this->excerpt,
            'meta_description' => $this->meta_description,
            'author' => $this->author,
            'published_at' => $this->published_at,
        ];
    }
}

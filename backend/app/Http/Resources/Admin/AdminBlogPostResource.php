<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Blog posts as the admin CMS needs them — deliberately not the storefront's
 * BlogPostResource, which leaves out drafts' state entirely.
 *
 * The admin list and editor read `is_published`, `updated_at`, `author_name`
 * and the three editorial fields; with the storefront shape every one arrived
 * undefined and `/blog` crashed rendering the author's initials. Strings the
 * editor binds to inputs come back as '' rather than null so a text field
 * never receives null.
 */
class AdminBlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt ?? '',
            'body' => $this->body,
            'cover_image' => $this->cover_image,
            'cover_image_alt' => $this->cover_image_alt ?? '',
            'meta_description' => $this->meta_description ?? '',
            // The column is `author`; the admin type calls it authorName.
            'author_name' => $this->author ?? '',
            'is_published' => (bool) $this->is_published,
            'published_at' => $this->published_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

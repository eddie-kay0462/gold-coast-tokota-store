<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $postId = $this->route('blogPost')?->id;

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('blog_posts', 'slug')->ignore($postId),
            ],
            'body' => [$creating ? 'required' : 'sometimes', 'string'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'author' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'is_published' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'A slug can only contain lowercase letters, numbers and hyphens.',
        ];
    }
}

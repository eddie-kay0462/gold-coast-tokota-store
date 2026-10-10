<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The About page and its siblings. Pages are edited, never created or deleted
 * from admin: their slugs are referenced by storefront routes, so an editor
 * inventing or removing one would break a link rather than publish a page.
 */
class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'is_draft' => ['boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The body `components/forms/FeedbackForm.vue` posts: `{ name, email, message }`.
 * `rating` is accepted but not currently sent — the admin table has a column
 * for it, so the API takes one if the form ever grows one.
 */
class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            // Capped rather than unbounded: this endpoint is unauthenticated,
            // and an open text field with no ceiling is a way to fill a disk.
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
